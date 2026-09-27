<?php

declare(strict_types=1);

namespace Drupal\soarm_vote\Hook;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\soarm_vote\VoteManagerInterface;

/**
 * Hook implementations for the soarm_vote module.
 */
final class SoarmVoteHooks {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly VoteManagerInterface $voteManager,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
  ) {}

  /**
   * Implements hook_ENTITY_TYPE_predelete() for node entities.
   *
   * Deletes all soarm_vote entities that reference the node being deleted.
   */
  #[Hook('node_predelete')]
  public function nodePredelete(NodeInterface $node): void {
    $storage = $this->entityTypeManager->getStorage('soarm_vote');

    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('node', $node->id())
      ->execute();

    if ($ids) {
      $storage->delete($storage->loadMultiple($ids));
      $this->cacheTagsInvalidator->invalidateTags(['soarm_vote:node:' . $node->id()]);
    }
  }

  /**
   * Implements hook_entity_extra_field_info().
   */
  #[Hook('entity_extra_field_info')]
  public function entityExtraFieldInfo(): array {
    return [
      'node' => [
        'robot_knowledge' => [
          'display' => [
            'soarm_votes' => [
              'label' => new TranslatableMarkup('SO-ARM votes'),
              'description' => new TranslatableMarkup('Vote counts (useful / improvement idea / replicated).'),
              'weight' => 100,
              'visible' => TRUE,
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Implements hook_ENTITY_TYPE_view() for node entities.
   */
  #[Hook('node_view')]
  public function nodeView(array &$build, NodeInterface $node, EntityViewDisplayInterface $display, $view_mode): void {
    if ($node->bundle() !== 'robot_knowledge') {
      return;
    }

    $component = $display->getComponent('soarm_votes');
    if (!$component) {
      return;
    }

    $build['soarm_votes'] = [
      '#theme' => 'soarm_vote_summary',
      '#nid' => $node->id(),
      '#counts' => $this->voteManager->counts($node),
      '#weight' => $component['weight'] ?? 100,
      '#cache' => [
        'tags' => ['soarm_vote:node:' . $node->id()],
      ],
    ];
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'soarm_vote_summary' => [
        'variables' => [
          'nid' => NULL,
          'counts' => [],
        ],
      ],
    ];
  }

}
