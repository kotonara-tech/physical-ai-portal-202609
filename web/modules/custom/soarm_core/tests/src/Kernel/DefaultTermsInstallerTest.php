<?php

declare(strict_types=1);

namespace Drupal\Tests\soarm_core\Kernel;

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the default taxonomy terms created on install.
 *
 * Kernel tests do not run hook_install(), so the work lives in the
 * soarm_core.default_terms service, which hook_install() calls.
 */
#[Group('soarm_core')]
final class DefaultTermsInstallerTest extends SoarmCoreKernelTestBase {

  /**
   * Returns term names of a vocabulary ordered by weight.
   */
  private function termNames(string $vid): array {
    $storage = $this->container->get('entity_type.manager')->getStorage('taxonomy_term');
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('vid', $vid)->sort('weight')->sort('tid')->execute();
    return array_values(array_map(static fn ($term) => $term->label(), $storage->loadMultiple($ids)));
  }

  /**
   * Tests that the five task category terms are created in weight order.
   */
  public function testCreatesTheFiveCategoriesInOrder(): void {
    $this->container->get('soarm_core.default_terms')->install();

    $this->assertSame(
      ['家庭内作業', '製造・組立', '物流・搬送', '研究・教育', 'データ収集・学習'],
      $this->termNames('task_category'),
    );
  }

  /**
   * Tests that the difficulty and robot model terms are created.
   */
  public function testCreatesDifficultyAndRobotModels(): void {
    $this->container->get('soarm_core.default_terms')->install();

    $this->assertSame(['初級', '中級', '上級'], $this->termNames('difficulty'));
    $this->assertSame(['SO-ARM100', 'SO-ARM101'], $this->termNames('robot_model'));
  }

  /**
   * Tests that the robot model terms describe their characteristics.
   */
  public function testRobotModelTermsDescribeTheirCharacteristics(): void {
    $this->container->get('soarm_core.default_terms')->install();
    $storage = $this->container->get('entity_type.manager')->getStorage('taxonomy_term');

    $descriptions = [];
    foreach ($storage->loadByProperties(['vid' => 'robot_model']) as $term) {
      $descriptions[$term->label()] = (string) $term->getDescription();
    }
    $this->assertStringContainsString('教育', $descriptions['SO-ARM100']);
    $this->assertStringContainsString('高トルク', $descriptions['SO-ARM101']);
  }

  /**
   * Tests that the tech tags terms from the spec are created.
   */
  public function testCreatesTechTagsFromTheSpec(): void {
    $this->container->get('soarm_core.default_terms')->install();

    $names = $this->termNames('tech_tags');
    foreach ([
      'ROS2', 'MoveIt', 'LeRobot', 'Jetson', '模倣学習', '強化学習',
      'Diffusion Policy', 'ACT', 'VLA', 'ピック&プレース', '柔軟物把持',
      '力制御', 'Isaac Sim',
    ] as $expected) {
      $this->assertContains($expected, $names);
    }
  }

  /**
   * Tests that the difficulty factor terms from the requirements are created.
   */
  public function testCreatesDifficultyFactorsFromTheRequirements(): void {
    $this->container->get('soarm_core.default_terms')->install();

    $names = $this->termNames('difficulty_factor');
    foreach (['柔軟物把持', '未知形状対応', '位置決め精度±1mm', '力制御', '可搬重量制限', 'Sim2Real', 'キャリブレーション', 'フォーマット統一（LeRobot）'] as $expected) {
      $this->assertContains($expected, $names);
    }
  }

  /**
   * Tests that installing the default terms twice does not duplicate them.
   */
  public function testInstallIsIdempotent(): void {
    $installer = $this->container->get('soarm_core.default_terms');
    $installer->install();
    $installer->install();

    $this->assertCount(5, $this->termNames('task_category'));
    $this->assertCount(3, $this->termNames('difficulty'));
  }

}
