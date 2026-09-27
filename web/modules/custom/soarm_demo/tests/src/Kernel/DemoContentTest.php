<?php

declare(strict_types=1);

namespace Drupal\Tests\soarm_demo\Kernel;

use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Tests\soarm_core\Kernel\SoarmCoreKernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Medium;

/**
 * Tests the demo posts created on install and removed on uninstall.
 *
 * Kernel tests do not run hook_install()/hook_uninstall(), so the work lives
 * in the soarm_demo.content service that those hooks call.
 */
#[Group('soarm_demo')]
#[Medium]
final class DemoContentTest extends SoarmCoreKernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['soarm_lerobot', 'soarm_demo'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['user']);
    $this->installSchema('user', ['users_data']);
    // Uid 1, so validating references to the demo author's files passes.
    $this->setCurrentUser($this->createUser());
    $this->container->get('soarm_core.default_terms')->install();
  }

  /**
   * Loads all robot knowledge posts.
   *
   * @return \Drupal\node\NodeInterface[]
   *   The posts.
   */
  private function posts(): array {
    return $this->container->get('entity_type.manager')->getStorage('node')->loadByProperties(['type' => 'robot_knowledge']);
  }

  /**
   * Returns the labels referenced by a field of a post.
   *
   * @return string[]
   *   The labels.
   */
  private function labels(NodeInterface $post, string $field): array {
    return array_map(static fn ($term) => $term->label(), $post->get($field)->referencedEntities());
  }

  /**
   * Tests that install() creates at least ten published posts.
   */
  public function testCreatesAtLeastTenPublishedPosts(): void {
    $this->container->get('soarm_demo.content')->install();

    $posts = $this->posts();
    $this->assertGreaterThanOrEqual(10, count($posts));
    foreach ($posts as $post) {
      $this->assertTrue($post->isPublished(), $post->label());
    }
  }

  /**
   * Tests that every task category has at least two demo posts.
   */
  public function testEveryCategoryHasAtLeastTwoPosts(): void {
    $this->container->get('soarm_demo.content')->install();

    $per_category = array_count_values(array_merge(...array_values(array_map(
      fn (NodeInterface $post) => $this->labels($post, 'field_task_category'),
      $this->posts(),
    ))));

    foreach (['家庭内作業', '製造・組立', '物流・搬送', '研究・教育', 'データ収集・学習'] as $category) {
      $this->assertGreaterThanOrEqual(2, $per_category[$category] ?? 0, $category);
    }
  }

  /**
   * Tests that the demo posts cover both robots and every outcome/difficulty.
   */
  public function testPostsCoverBothRobotsAllOutcomesAndAllDifficulties(): void {
    $this->container->get('soarm_demo.content')->install();

    $models = $outcomes = $difficulties = [];
    $both = $unresolved = 0;
    foreach ($this->posts() as $post) {
      $post_models = $this->labels($post, 'field_robot_model');
      $models = array_merge($models, $post_models);
      $both += (int) (count($post_models) === 2);
      $outcomes[] = $post->get('field_outcome')->value;
      $difficulties = array_merge($difficulties, $this->labels($post, 'field_difficulty'));
      $unresolved += (int) ($post->get('field_outcome')->value !== 'success' && !$post->get('field_resolved')->value);
    }

    $this->assertEqualsCanonicalizing(['SO-ARM100', 'SO-ARM101'], array_unique($models));
    $this->assertGreaterThanOrEqual(1, $both, 'At least one post supports both robots.');
    $this->assertEqualsCanonicalizing(['success', 'partial', 'failure'], array_unique($outcomes));
    $this->assertEqualsCanonicalizing(['初級', '中級', '上級'], array_unique($difficulties));
    $this->assertGreaterThanOrEqual(2, $unresolved, 'The dashboard needs unresolved issues to show.');
  }

  /**
   * Tests that every demo post validates and has its required fields filled.
   */
  public function testEveryPostIsCompleteAndValid(): void {
    $this->container->get('soarm_demo.content')->install();

    foreach ($this->posts() as $post) {
      $violations = [];
      foreach ($post->validate() as $violation) {
        $violations[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
      }
      $this->assertSame([], $violations, $post->label());
      $this->assertSame('soarm_markdown', $post->get('field_procedure')->format, $post->label());
      $this->assertNotEmpty(trim((string) $post->get('field_procedure')->value), $post->label());
      $this->assertNotEmpty($this->labels($post, 'field_tech_tags'), $post->label());
      $this->assertNotEmpty($this->labels($post, 'field_difficulty_factors'), $post->label());
      $this->assertNotEmpty($post->get('field_environment')->value, $post->label());
    }
  }

  /**
   * Tests that at least one demo post carries valid LeRobot metadata.
   */
  public function testAtLeastOnePostCarriesValidLeRobotMetadata(): void {
    $this->container->get('soarm_demo.content')->install();

    $infos = array_filter(array_map(
      fn (NodeInterface $post) => $this->container->get('soarm_lerobot.episode_info')->forNode($post),
      $this->posts(),
    ));

    $this->assertNotEmpty($infos);
    $this->assertSame(['grasp', 'move', 'release'], array_column(reset($infos)['phases'], 'name'));
  }

  /**
   * Tests that the demo posts share one blocked demo author with a profile.
   */
  public function testPostsBelongToDemoAuthorWithProfile(): void {
    $this->container->get('soarm_demo.content')->install();

    $authors = [];
    foreach ($this->posts() as $post) {
      $authors[$post->getOwnerId()] = $post->getOwner();
    }

    $this->assertCount(1, $authors);
    $author = reset($authors);
    $this->assertSame('soarm_demo', $author->getAccountName());
    $this->assertTrue($author->isBlocked(), 'Nobody can log in as the demo author.');
    $this->assertNotEmpty($author->get('field_affiliation')->value);
    $this->assertNotEmpty($author->get('field_expertise')->value);
  }

  /**
   * Tests that calling install() a second time does not duplicate posts.
   */
  public function testInstallIsIdempotent(): void {
    $content = $this->container->get('soarm_demo.content');
    $content->install();
    $count = count($this->posts());

    $content->install();

    $this->assertCount($count, $this->posts());
  }

  /**
   * Tests that remove() deletes the demo content and leaves other content.
   */
  public function testRemoveDeletesDemoContentAndNothingElse(): void {
    $content = $this->container->get('soarm_demo.content');
    $content->install();
    $mine = Node::create(['type' => 'robot_knowledge', 'title' => 'mine', 'field_outcome' => 'success']);
    $mine->save();

    $content->remove();

    $this->assertSame(['mine'], array_values(array_map(static fn ($post) => $post->label(), $this->posts())));
    $this->assertSame([], $this->container->get('entity_type.manager')->getStorage('user')->loadByProperties(['name' => 'soarm_demo']));
    $this->assertSame([], $this->container->get('entity_type.manager')->getStorage('file')->loadMultiple());
  }

}
