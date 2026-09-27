<?php

declare(strict_types=1);

namespace Drupal\soarm_dashboard;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\soarm_vote\VoteManagerInterface;

/**
 * Builds the node ID lists shown on the SO-ARM dashboard.
 */
final class DashboardQuery implements DashboardQueryInterface {

  /**
   * The `robot_knowledge` content type machine name.
   */
  private const BUNDLE = 'robot_knowledge';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly VoteManagerInterface $voteManager,
  ) {}

  /**
   * Returns the node entity storage.
   */
  private function nodeStorage(): EntityStorageInterface {
    return $this->entityTypeManager->getStorage('node');
  }

  /**
   * {@inheritdoc}
   */
  public function latest(int $limit): array {
    $ids = $this->nodeStorage()->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', self::BUNDLE)
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->sort('nid', 'DESC')
      ->range(0, $limit)
      ->execute();

    return array_values(array_map('intval', $ids));
  }

  /**
   * {@inheritdoc}
   */
  public function unresolved(int $limit): array {
    $query = $this->nodeStorage()->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', self::BUNDLE)
      ->condition('status', 1)
      ->condition('field_outcome', 'success', '<>');

    // A post with no "resolved" value at all (the field was never set) has
    // no row in the field table, so a plain condition would silently drop
    // it. Match "never set" OR "set to something other than TRUE".
    $not_resolved = $query->orConditionGroup()
      ->notExists('field_resolved')
      ->condition('field_resolved', 1, '<>');
    $query->condition($not_resolved);

    $ids = $query
      ->sort('created', 'DESC')
      ->sort('nid', 'DESC')
      ->range(0, $limit)
      ->execute();

    return array_values(array_map('intval', $ids));
  }

  /**
   * {@inheritdoc}
   */
  public function popular(int $limit): array {
    if ($limit <= 0) {
      return [];
    }

    $batch = $limit;
    $previous_count = 0;

    while (TRUE) {
      $candidates = $this->voteManager->mostVoted($batch);
      $filtered = $this->filterPublished($candidates);

      if (count($filtered) >= $limit || count($candidates) === $previous_count) {
        return array_slice($filtered, 0, $limit, TRUE);
      }

      $previous_count = count($candidates);
      $batch *= 2;
    }
  }

  /**
   * Keeps only published `robot_knowledge` nodes, preserving order.
   *
   * @param array $counts
   *   An array mapping node ID to vote count, as returned by
   *   \Drupal\soarm_vote\VoteManagerInterface::mostVoted().
   *
   * @return array
   *   The same mapping, with unpublished nodes and nodes of other content
   *   types removed.
   */
  private function filterPublished(array $counts): array {
    if ($counts === []) {
      return [];
    }

    $nodes = $this->nodeStorage()->loadMultiple(array_keys($counts));

    $filtered = [];
    foreach ($counts as $nid => $total) {
      $node = $nodes[$nid] ?? NULL;
      if ($node instanceof NodeInterface && $node->bundle() === self::BUNDLE && $node->isPublished()) {
        $filtered[$nid] = $total;
      }
    }

    return $filtered;
  }

}
