<?php

declare(strict_types=1);

namespace Drupal\soarm_core;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Creates the default taxonomy terms used by the robot knowledge content type.
 */
final class DefaultTermsInstaller {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Creates the default terms for every vocabulary, skipping ones that exist.
   */
  public function install(): void {
    $this->createTerms('task_category', [
      '家庭内作業',
      '製造・組立',
      '物流・搬送',
      '研究・教育',
      'データ収集・学習',
    ]);

    $this->createTerms('difficulty', [
      '初級',
      '中級',
      '上級',
    ]);

    $this->createTerms('robot_model', [
      'SO-ARM100' => 'SO-ARM100 は教育・軽量用途向けのモデルです。',
      'SO-ARM101' => 'SO-ARM101 は高トルク・産業用途向けのモデルです。',
    ]);

    $this->createTerms('tech_tags', [
      'ROS2',
      'MoveIt',
      'LeRobot',
      'Jetson',
      '模倣学習',
      '強化学習',
      'Diffusion Policy',
      'ACT',
      'VLA',
      'ピック&プレース',
      '柔軟物把持',
      '力制御',
      'Isaac Sim',
    ]);

    $this->createTerms('difficulty_factor', [
      '柔軟物把持',
      '未知形状対応',
      '非構造環境計画',
      '安全性',
      '位置決め精度±1mm',
      '力制御',
      '再現性',
      '剛性・タクト',
      '可搬重量制限',
      '多品種把持',
      '重なり分離',
      '協調制御',
      'Sim2Real',
      'キャリブレーション',
      '個体差',
      '再現性担保',
      '量確保',
      '品質ばらつき',
      'フォーマット統一（LeRobot）',
      '評価基準',
    ]);
  }

  /**
   * Creates the terms of a vocabulary, in order, if they do not exist yet.
   *
   * @param string $vocabulary
   *   The vocabulary machine name.
   * @param array<int|string, string> $terms
   *   Either a list of term names, or an associative array of
   *   name => description.
   */
  private function createTerms(string $vocabulary, array $terms): void {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');

    $weight = 0;
    foreach ($terms as $key => $value) {
      $name = is_int($key) ? $value : $key;
      $description = is_int($key) ? '' : $value;

      $existing = $storage->loadByProperties([
        'vid' => $vocabulary,
        'name' => $name,
      ]);

      if (!$existing) {
        $storage->create([
          'vid' => $vocabulary,
          'name' => $name,
          'description' => $description,
          'weight' => $weight,
        ])->save();
      }

      $weight++;
    }
  }

}
