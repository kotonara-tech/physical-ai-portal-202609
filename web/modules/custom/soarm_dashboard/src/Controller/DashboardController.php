<?php

declare(strict_types=1);

namespace Drupal\soarm_dashboard\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\soarm_dashboard\DashboardQueryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns the SO-ARM dashboard page.
 */
final class DashboardController extends ControllerBase {

  /**
   * The `robot_knowledge` content type machine name.
   */
  private const BUNDLE = 'robot_knowledge';

  /**
   * How many items each section shows.
   */
  private const ITEMS_PER_SECTION = 10;

  /**
   * Cache tags accumulated while building the current response.
   *
   * @var string[]
   */
  private array $cacheTags = [];

  public function __construct(
    private readonly DashboardQueryInterface $dashboardQuery,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('soarm_dashboard.query'),
    );
  }

  /**
   * Builds the dashboard page render array.
   */
  public function page(): array {
    $this->cacheTags = [];

    return [
      '#theme' => 'soarm_dashboard',
      '#popular' => $this->buildPopular(),
      '#latest' => $this->buildLatest(),
      '#unresolved' => $this->buildUnresolved(),
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => $this->cacheTags,
      ],
    ];
  }

  /**
   * Builds the "popular" section.
   */
  private function buildPopular(): array {
    $votes = $this->dashboardQuery->popular(self::ITEMS_PER_SECTION);
    $this->addCacheTags(['soarm_vote_list', 'node_list:' . self::BUNDLE]);

    $items = [];
    foreach ($this->loadViewable(array_keys($votes)) as $node) {
      $nid = (int) $node->id();
      $items[] = [
        'link' => $this->linkTo($node),
        'extra' => (string) $votes[$nid],
      ];
      $this->addCacheTags(['soarm_vote:node:' . $nid]);
    }

    return [
      'items' => $items,
      'empty' => $this->t('No votes yet.'),
    ];
  }

  /**
   * Builds the "latest" section.
   */
  private function buildLatest(): array {
    $nids = $this->dashboardQuery->latest(self::ITEMS_PER_SECTION);
    $this->addCacheTags(['node_list:' . self::BUNDLE]);

    $items = [];
    foreach ($this->loadViewable($nids) as $node) {
      $items[] = ['link' => $this->linkTo($node)];
    }

    return [
      'items' => $items,
      'empty' => $this->t('No posts yet.'),
    ];
  }

  /**
   * Builds the "unresolved" section.
   */
  private function buildUnresolved(): array {
    $nids = $this->dashboardQuery->unresolved(self::ITEMS_PER_SECTION);
    $this->addCacheTags(['node_list:' . self::BUNDLE]);

    $items = [];
    foreach ($this->loadViewable($nids) as $node) {
      $items[] = [
        'link' => $this->linkTo($node),
        'extra' => $this->outcomeLabel($node),
      ];
    }

    return [
      'items' => $items,
      'empty' => $this->t('No unresolved issues.'),
    ];
  }

  /**
   * Loads nodes by ID, preserving order, keeping only viewable ones.
   *
   * Also merges each kept node's own cache tags into the response, so an
   * edit to a listed post (e.g. its title) invalidates the page.
   *
   * @param int[] $nids
   *   Node IDs to load.
   *
   * @return \Drupal\node\NodeInterface[]
   *   The viewable nodes, in the same order as $nids.
   */
  private function loadViewable(array $nids): array {
    if ($nids === []) {
      return [];
    }

    $storage = $this->entityTypeManager()->getStorage('node');
    $nodes = $storage->loadMultiple($nids);

    $result = [];
    foreach ($nids as $nid) {
      $node = $nodes[$nid] ?? NULL;
      if ($node instanceof NodeInterface && $node->access('view')) {
        $this->addCacheTags($node->getCacheTags());
        $result[] = $node;
      }
    }

    return $result;
  }

  /**
   * Builds a link render array to a node's canonical page.
   */
  private function linkTo(NodeInterface $node): array {
    return [
      '#type' => 'link',
      '#title' => $node->label(),
      '#url' => $node->toUrl(),
    ];
  }

  /**
   * Returns the human-readable label for a node's outcome value.
   */
  private function outcomeLabel(NodeInterface $node): string {
    $field = $node->get('field_outcome');
    $value = $field->value;

    if ($field->isEmpty() || $value === NULL) {
      return '';
    }

    $allowed = $field->getFieldDefinition()->getSetting('allowed_values') ?? [];

    return $allowed[$value] ?? (string) $value;
  }

  /**
   * Merges cache tags into the tags accumulated for the current response.
   *
   * @param string[] $tags
   *   Cache tags to add.
   */
  private function addCacheTags(array $tags): void {
    $this->cacheTags = Cache::mergeTags($this->cacheTags, $tags);
  }

}
