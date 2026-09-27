<?php

declare(strict_types=1);

namespace Drupal\soarm_dashboard;

/**
 * Builds the node ID lists shown on the SO-ARM dashboard.
 *
 * Implementations are access-agnostic: they only look at publication
 * status, never at the current user's permissions. Callers that render the
 * results to a visitor must still check node access themselves.
 */
interface DashboardQueryInterface {

  /**
   * Finds the most recently created published knowledge posts.
   *
   * @param int $limit
   *   The maximum number of node IDs to return.
   *
   * @return int[]
   *   Node IDs, newest `created` first (ties broken by node ID, highest
   *   first).
   */
  public function latest(int $limit): array;

  /**
   * Finds published knowledge posts that are not resolved.
   *
   * A post counts as unresolved unless its outcome is "success". A post
   * whose "resolved" field was never set counts as unresolved too.
   *
   * @param int $limit
   *   The maximum number of node IDs to return.
   *
   * @return int[]
   *   Node IDs, newest `created` first (ties broken by node ID, highest
   *   first).
   */
  public function unresolved(int $limit): array;

  /**
   * Finds the most-voted published knowledge posts.
   *
   * @param int $limit
   *   The maximum number of nodes to return.
   *
   * @return array
   *   An array mapping node ID (int) to total vote count (int), ordered by
   *   vote count descending.
   */
  public function popular(int $limit): array;

}
