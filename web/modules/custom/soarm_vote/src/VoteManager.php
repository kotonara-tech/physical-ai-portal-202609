<?php

declare(strict_types=1);

namespace Drupal\soarm_vote;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Manages soarm_vote entities on behalf of votable nodes.
 */
final class VoteManager implements VoteManagerInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
  ) {}

  /**
   * Returns the soarm_vote entity storage.
   */
  private function storage(): EntityStorageInterface {
    return $this->entityTypeManager->getStorage('soarm_vote');
  }

  /**
   * Validates the vote type and account before casting or withdrawing.
   *
   * @throws \InvalidArgumentException
   */
  private function assertVotable(AccountInterface $account, string $type): void {
    if (!in_array($type, self::TYPES, TRUE)) {
      throw new \InvalidArgumentException(sprintf('Unknown vote type "%s".', $type));
    }
    if ($account->isAnonymous()) {
      throw new \InvalidArgumentException('Anonymous users cannot vote.');
    }
  }

  /**
   * Finds the ID of an existing vote by this account, of this type.
   */
  private function existingVoteId(NodeInterface $node, AccountInterface $account, string $type): ?int {
    $ids = $this->storage()->getQuery()
      ->accessCheck(FALSE)
      ->condition('node', $node->id())
      ->condition('uid', $account->id())
      ->condition('type', $type)
      ->execute();

    return $ids ? (int) reset($ids) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function cast(NodeInterface $node, AccountInterface $account, string $type): bool {
    $this->assertVotable($account, $type);

    if ($this->existingVoteId($node, $account, $type) !== NULL) {
      return FALSE;
    }

    $this->storage()->create([
      'node' => $node->id(),
      'uid' => $account->id(),
      'type' => $type,
    ])->save();

    $this->cacheTagsInvalidator->invalidateTags(['soarm_vote:node:' . $node->id()]);

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function withdraw(NodeInterface $node, AccountInterface $account, string $type): bool {
    $this->assertVotable($account, $type);

    $id = $this->existingVoteId($node, $account, $type);
    if ($id === NULL) {
      return FALSE;
    }

    $vote = $this->storage()->load($id);
    if ($vote !== NULL) {
      $vote->delete();
    }

    $this->cacheTagsInvalidator->invalidateTags(['soarm_vote:node:' . $node->id()]);

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function counts(NodeInterface $node): array {
    $counts = array_fill_keys(self::TYPES, 0);

    // Count in the database: a popular post can have thousands of votes.
    $rows = $this->storage()->getAggregateQuery()
      ->accessCheck(FALSE)
      ->condition('node', $node->id())
      ->groupBy('type')
      ->aggregate('id', 'COUNT')
      ->execute();

    foreach ($rows as $row) {
      if (isset($counts[$row['type']])) {
        $counts[$row['type']] = (int) $row['id_count'];
      }
    }

    return $counts;
  }

  /**
   * {@inheritdoc}
   */
  public function typesCastBy(NodeInterface $node, AccountInterface $account): array {
    $ids = $this->storage()->getQuery()
      ->accessCheck(FALSE)
      ->condition('node', $node->id())
      ->condition('uid', $account->id())
      ->execute();

    $types = [];
    foreach ($this->storage()->loadMultiple($ids) as $vote) {
      $types[] = $vote->get('type')->value;
    }

    return $types;
  }

  /**
   * {@inheritdoc}
   */
  public function mostVoted(int $limit): array {
    $rows = $this->storage()->getAggregateQuery()
      ->accessCheck(FALSE)
      ->groupBy('node')
      ->aggregate('id', 'COUNT')
      ->sortAggregate('id', 'COUNT', 'DESC')
      ->sort('node', 'DESC')
      ->range(0, $limit)
      ->execute();

    $result = [];
    foreach ($rows as $row) {
      $result[(int) $row['node']] = (int) $row['id_count'];
    }

    return $result;
  }

}
