<?php

declare(strict_types=1);

namespace Drupal\soarm_vote;

use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Casts, withdraws and counts votes on nodes.
 */
interface VoteManagerInterface {

  /**
   * The vote types a user may cast on a node.
   */
  public const TYPES = ['useful', 'improvement', 'replication'];

  /**
   * Casts a vote of the given type by an account on a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node being voted on.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account casting the vote. Must not be anonymous.
   * @param string $type
   *   One of self::TYPES.
   *
   * @return bool
   *   TRUE if a vote was added, FALSE if the account already cast that type
   *   of vote on that node.
   *
   * @throws \InvalidArgumentException
   *   If $type is not one of self::TYPES, or if $account is anonymous.
   */
  public function cast(NodeInterface $node, AccountInterface $account, string $type): bool;

  /**
   * Withdraws a previously cast vote.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node the vote was cast on.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account that cast the vote. Must not be anonymous.
   * @param string $type
   *   One of self::TYPES.
   *
   * @return bool
   *   TRUE if a vote was removed, FALSE if there was nothing to withdraw.
   *
   * @throws \InvalidArgumentException
   *   If $type is not one of self::TYPES, or if $account is anonymous.
   */
  public function withdraw(NodeInterface $node, AccountInterface $account, string $type): bool;

  /**
   * Counts the votes cast on a node, per type.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to count votes for.
   *
   * @return array
   *   An array keyed by all of self::TYPES (in that order) with integer
   *   vote counts as values.
   */
  public function counts(NodeInterface $node): array;

  /**
   * Lists the vote types an account has cast on a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to check.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to check.
   *
   * @return string[]
   *   The list of types (a subset of self::TYPES) the account has cast.
   */
  public function typesCastBy(NodeInterface $node, AccountInterface $account): array;

  /**
   * Finds the nodes with the most votes, in descending order.
   *
   * @param int $limit
   *   The maximum number of nodes to return.
   *
   * @return array
   *   An array mapping node ID (int) to total vote count (int), ordered by
   *   total vote count descending. Nodes without any votes are excluded.
   */
  public function mostVoted(int $limit): array;

}
