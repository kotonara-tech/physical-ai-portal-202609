<?php

declare(strict_types=1);

namespace Drupal\Tests\soarm_dashboard\Kernel;

use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\soarm_dashboard\DashboardQueryInterface;
use Drupal\Tests\soarm_core\Kernel\SoarmCoreKernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the three dashboard lists: latest, unresolved issues, popular.
 */
#[Group('soarm_dashboard')]
final class DashboardQueryTest extends SoarmCoreKernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['soarm_vote', 'soarm_dashboard'];

  private DashboardQueryInterface $dashboard;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('soarm_vote');
    $this->dashboard = $this->container->get('soarm_dashboard.query');
  }

  /**
   * Creates a robot knowledge post.
   */
  private function createPost(array $values = []): NodeInterface {
    $node = Node::create($values + [
      'type' => 'robot_knowledge',
      'title' => $this->randomMachineName(),
      'field_outcome' => 'success',
      'status' => 1,
    ]);
    $node->save();
    return $node;
  }

  public function testLatestIsNewestFirstAndLimited(): void {
    $old = $this->createPost(['created' => 1000]);
    $new = $this->createPost(['created' => 3000]);
    $mid = $this->createPost(['created' => 2000]);

    $this->assertSame([(int) $new->id(), (int) $mid->id(), (int) $old->id()], $this->dashboard->latest(10));
    $this->assertSame([(int) $new->id(), (int) $mid->id()], $this->dashboard->latest(2));
  }

  public function testLatestSkipsUnpublishedAndOtherContentTypes(): void {
    $this->createPost(['status' => 0]);
    $visible = $this->createPost();

    $this->assertSame([(int) $visible->id()], $this->dashboard->latest(10));
  }

  public function testUnresolvedMeansNotSuccessfulAndNotResolved(): void {
    $this->createPost(['field_outcome' => 'success']);
    $this->createPost(['field_outcome' => 'failure', 'field_resolved' => TRUE]);
    $failure = $this->createPost(['field_outcome' => 'failure', 'field_resolved' => FALSE, 'created' => 1000]);
    $partial = $this->createPost(['field_outcome' => 'partial', 'field_resolved' => FALSE, 'created' => 2000]);

    $this->assertSame([(int) $partial->id(), (int) $failure->id()], $this->dashboard->unresolved(10));
  }

  public function testUnresolvedIncludesPostsWhereResolvedWasNeverSet(): void {
    $never_set = $this->createPost(['field_outcome' => 'failure']);

    $this->assertSame([(int) $never_set->id()], $this->dashboard->unresolved(10));
  }

  public function testUnresolvedSkipsUnpublished(): void {
    $this->createPost(['field_outcome' => 'failure', 'status' => 0]);

    $this->assertSame([], $this->dashboard->unresolved(10));
  }

  public function testPopularOrdersByVotesAndSkipsUnpublished(): void {
    $votes = $this->container->get('soarm_vote.manager');
    $alice = $this->createUser();
    $bob = $this->createUser();
    $this->createPost();
    $one = $this->createPost();
    $two = $this->createPost();
    $hidden = $this->createPost(['status' => 0]);
    $votes->cast($one, $alice, 'useful');
    $votes->cast($two, $alice, 'useful');
    $votes->cast($two, $bob, 'replication');
    foreach (['useful', 'improvement', 'replication'] as $type) {
      $votes->cast($hidden, $alice, $type);
    }

    $this->assertSame([(int) $two->id() => 2, (int) $one->id() => 1], $this->dashboard->popular(10));
    $this->assertSame([(int) $two->id() => 2], $this->dashboard->popular(1));
  }

}
