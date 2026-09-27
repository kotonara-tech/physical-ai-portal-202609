<?php

declare(strict_types=1);

namespace Drupal\Tests\soarm_vote\Kernel;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\soarm_vote\VoteManagerInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests casting, withdrawing and counting votes.
 */
#[Group('soarm_vote')]
final class VoteManagerTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'text', 'filter', 'node', 'soarm_vote'];

  /**
   * The vote manager under test.
   */
  private VoteManagerInterface $votes;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('soarm_vote');
    $this->installSchema('node', ['node_access']);
    NodeType::create(['type' => 'robot_knowledge', 'name' => 'Robot knowledge'])->save();
    $this->votes = $this->container->get('soarm_vote.manager');
  }

  /**
   * Creates a robot knowledge post.
   */
  private function createPost(string $title = 'post'): NodeInterface {
    $node = Node::create(['type' => 'robot_knowledge', 'title' => $title]);
    $node->save();
    return $node;
  }

  /**
   * Tests that the vote types are the three from the spec.
   */
  public function testTypesAreTheThreeFromTheSpec(): void {
    $this->assertSame(['useful', 'improvement', 'replication'], VoteManagerInterface::TYPES);
  }

  /**
   * Tests that counts start at zero for every vote type.
   */
  public function testCountsStartAtZeroForEveryType(): void {
    $this->assertSame(
      ['useful' => 0, 'improvement' => 0, 'replication' => 0],
      $this->votes->counts($this->createPost()),
    );
  }

  /**
   * Tests that casting a vote counts only that type.
   */
  #[DataProvider('typeProvider')]
  public function testCastCountsOnlyThatType(string $type): void {
    $node = $this->createPost();

    $this->assertTrue($this->votes->cast($node, $this->createUser(), $type));

    $expected = ['useful' => 0, 'improvement' => 0, 'replication' => 0];
    $expected[$type] = 1;
    $this->assertSame($expected, $this->votes->counts($node));
  }

  /**
   * Data provider for testCastCountsOnlyThatType().
   */
  public static function typeProvider(): array {
    return [['useful'], ['improvement'], ['replication']];
  }

  /**
   * Tests that the same user casting the same type twice counts once.
   */
  public function testSameUserSameTypeCountsOnce(): void {
    $node = $this->createPost();
    $user = $this->createUser();

    $this->assertTrue($this->votes->cast($node, $user, 'useful'));
    $this->assertFalse($this->votes->cast($node, $user, 'useful'), 'A repeated vote reports that nothing changed.');

    $this->assertSame(1, $this->votes->counts($node)['useful']);
  }

  /**
   * Tests that one user may cast different vote types on the same post.
   */
  public function testOneUserMayCastDifferentTypes(): void {
    $node = $this->createPost();
    $user = $this->createUser();

    $this->votes->cast($node, $user, 'useful');
    $this->votes->cast($node, $user, 'replication');

    $this->assertSame(['useful' => 1, 'improvement' => 0, 'replication' => 1], $this->votes->counts($node));
    $this->assertEqualsCanonicalizing(['useful', 'replication'], $this->votes->typesCastBy($node, $user));
  }

  /**
   * Tests that votes from different users add up.
   */
  public function testDifferentUsersAddUp(): void {
    $node = $this->createPost();

    $this->votes->cast($node, $this->createUser(), 'useful');
    $this->votes->cast($node, $this->createUser(), 'useful');

    $this->assertSame(2, $this->votes->counts($node)['useful']);
  }

  /**
   * Tests that votes are counted per node, not shared across posts.
   */
  public function testVotesAreCountedPerNode(): void {
    $first = $this->createPost('first');
    $second = $this->createPost('second');

    $this->votes->cast($first, $this->createUser(), 'useful');

    $this->assertSame(0, $this->votes->counts($second)['useful']);
  }

  /**
   * Tests that withdraw() removes only the given vote type.
   */
  public function testWithdrawRemovesOnlyThatVote(): void {
    $node = $this->createPost();
    $user = $this->createUser();
    $this->votes->cast($node, $user, 'useful');
    $this->votes->cast($node, $user, 'improvement');

    $this->assertTrue($this->votes->withdraw($node, $user, 'useful'));
    $this->assertFalse($this->votes->withdraw($node, $user, 'useful'), 'Nothing left to withdraw.');

    $this->assertSame(['useful' => 0, 'improvement' => 1, 'replication' => 0], $this->votes->counts($node));
  }

  /**
   * Tests that casting an unknown vote type is rejected.
   */
  public function testUnknownTypeIsRejected(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->votes->cast($this->createPost(), $this->createUser(), 'spam');
  }

  /**
   * Tests that anonymous users cannot vote.
   */
  public function testAnonymousCannotVote(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->votes->cast($this->createPost(), new AnonymousUserSession(), 'useful');
  }

  /**
   * Tests that deleting a node deletes its votes.
   */
  public function testDeletingNodeDeletesItsVotes(): void {
    $node = $this->createPost();
    $this->votes->cast($node, $this->createUser(), 'useful');

    $node->delete();

    $storage = $this->container->get('entity_type.manager')->getStorage('soarm_vote');
    $this->assertSame(0, (int) $storage->getQuery()->accessCheck(FALSE)->count()->execute());
  }

  /**
   * Tests that mostVoted() orders by total votes and skips unvoted posts.
   */
  public function testMostVotedOrdersByTotalVotesAndSkipsUnvoted(): void {
    $quiet = $this->createPost('quiet');
    $some = $this->createPost('some');
    $lots = $this->createPost('lots');
    $alice = $this->createUser();
    $bob = $this->createUser();
    $this->votes->cast($some, $alice, 'useful');
    $this->votes->cast($lots, $alice, 'useful');
    $this->votes->cast($lots, $bob, 'useful');
    $this->votes->cast($lots, $bob, 'replication');

    $this->assertSame([(int) $lots->id() => 3, (int) $some->id() => 1], $this->votes->mostVoted(10));
    $this->assertSame([(int) $lots->id() => 3], $this->votes->mostVoted(1));
  }

}
