<?php

declare(strict_types=1);

namespace Drupal\soarm_demo;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Creates and removes the demo robot_knowledge posts.
 *
 * Every demo entity (the author, the posts, the metadata files) is keyed by
 * a fixed UUID, so install() can be called more than once without creating
 * duplicates, and remove() can delete exactly what it created without
 * touching content added by a real user.
 */
final class DemoContent {

  /**
   * The UUID of the demo author account.
   */
  private const AUTHOR_UUID = 'fcebf766-fc76-42ec-8412-f6737a1e70eb';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileSystemInterface $fileSystem,
    private readonly FileUsageInterface $fileUsage,
  ) {}

  /**
   * Creates the demo author, posts, and metadata files, if not present yet.
   */
  public function install(): void {
    $author = $this->ensureAuthor();

    foreach ($this->postDefinitions() as $definition) {
      $this->ensurePost($definition, $author);
    }
  }

  /**
   * Deletes the demo posts, author, and metadata files created by install().
   *
   * Content created by real users (even a post of the same content type) is
   * left untouched, because everything here is looked up by its fixed UUID.
   */
  public function remove(): void {
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $fileStorage = $this->entityTypeManager->getStorage('file');
    $userStorage = $this->entityTypeManager->getStorage('user');

    foreach ($this->postDefinitions() as $definition) {
      $posts = $nodeStorage->loadByProperties(['uuid' => $definition['uuid']]);
      if ($posts !== []) {
        $nodeStorage->delete($posts);
      }

      if (isset($definition['metadata_yaml'])) {
        $files = $fileStorage->loadByProperties(['uuid' => $definition['metadata_yaml']['uuid']]);
        foreach ($files as $file) {
          $this->fileUsage->delete($file, 'soarm_demo');
          $file->delete();
        }
      }
    }

    $authors = $userStorage->loadByProperties(['uuid' => self::AUTHOR_UUID]);
    if ($authors !== []) {
      $userStorage->delete($authors);
    }
  }

  /**
   * Creates the blocked demo author, if it does not exist yet.
   */
  private function ensureAuthor(): UserInterface {
    $storage = $this->entityTypeManager->getStorage('user');
    $existing = $storage->loadByProperties(['uuid' => self::AUTHOR_UUID]);
    if ($existing !== []) {
      return reset($existing);
    }

    $author = User::create([
      'uuid' => self::AUTHOR_UUID,
      'name' => 'soarm_demo',
      'mail' => 'soarm-demo@example.com',
      // Blocked: nobody should be able to log in as the demo author.
      'status' => 0,
      'field_affiliation' => 'SO-ARM Portal デモアカウント',
      'field_expertise' => 'ロボット教育・デモコンテンツ作成',
    ]);
    $author->save();

    return $author;
  }

  /**
   * Creates one demo post (and its metadata file, if any), if not present.
   *
   * @param array $definition
   *   One entry from self::postDefinitions().
   * @param \Drupal\user\UserInterface $author
   *   The demo author, owner of every demo post and metadata file.
   */
  private function ensurePost(array $definition, UserInterface $author): void {
    $storage = $this->entityTypeManager->getStorage('node');
    if ($storage->loadByProperties(['uuid' => $definition['uuid']]) !== []) {
      return;
    }

    $values = [
      'type' => 'robot_knowledge',
      'uuid' => $definition['uuid'],
      'title' => $definition['title'],
      'uid' => $author->id(),
      'status' => 1,
      'field_task_category' => ['target_id' => $this->termId('task_category', $definition['category'])],
      'field_outcome' => $definition['outcome'],
      'field_resolved' => $definition['resolved'],
      'field_difficulty' => ['target_id' => $this->termId('difficulty', $definition['difficulty'])],
      'field_robot_model' => $this->targetIds('robot_model', $definition['robot_models']),
      'field_tech_tags' => $this->targetIds('tech_tags', $definition['tech_tags']),
      'field_difficulty_factors' => $this->targetIds('difficulty_factor', $definition['difficulty_factors']),
      'field_environment' => $definition['environment'],
      'field_procedure' => [
        'value' => $definition['procedure'],
        'format' => 'soarm_markdown',
      ],
    ];

    if (isset($definition['hf_repo'])) {
      $values['field_hf_repo'] = $definition['hf_repo'];
    }

    if (isset($definition['metadata_yaml'])) {
      $metadata = $definition['metadata_yaml'];
      $file = $this->ensureFile($metadata['uuid'], $metadata['filename'], $metadata['contents'], (int) $author->id());
      $values['field_metadata_yaml'] = ['target_id' => $file->id()];
    }

    $node = $storage->create($values);
    $node->save();

    if (isset($file)) {
      $this->fileUsage->add($file, 'soarm_demo', 'node', (string) $node->id());
    }
  }

  /**
   * Creates a permanent demo file with fixed content, if not present yet.
   */
  private function ensureFile(string $uuid, string $filename, string $contents, int $uid): FileInterface {
    $storage = $this->entityTypeManager->getStorage('file');
    $existing = $storage->loadByProperties(['uuid' => $uuid]);
    if ($existing !== []) {
      return reset($existing);
    }

    $directory = 'public://soarm-demo';
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    $uri = $directory . '/' . $filename;
    $this->fileSystem->saveData($contents, $uri, FileSystemInterface::EXISTS_REPLACE);

    $file = File::create([
      'uuid' => $uuid,
      'uri' => $uri,
      'filename' => $filename,
      'uid' => $uid,
      'status' => FileInterface::STATUS_PERMANENT,
    ]);
    $file->save();

    return $file;
  }

  /**
   * Converts taxonomy term names into an entity reference values array.
   *
   * @param string $vocabulary
   *   The vocabulary machine name.
   * @param string[] $names
   *   The term names to look up, in order.
   *
   * @return array<int, array{target_id: int}>
   *   Field item values, ready to assign to an entity reference field.
   */
  private function targetIds(string $vocabulary, array $names): array {
    return array_map(
      fn (string $name) => ['target_id' => $this->termId($vocabulary, $name)],
      $names,
    );
  }

  /**
   * Looks up a taxonomy term's ID by vocabulary and name.
   *
   * The default terms are expected to already exist (installed by
   * soarm_core.default_terms), so a missing term is a configuration problem
   * rather than something to silently work around.
   */
  private function termId(string $vocabulary, string $name): int {
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
      'vid' => $vocabulary,
      'name' => $name,
    ]);
    if ($terms === []) {
      throw new \RuntimeException(sprintf('Missing taxonomy term "%s" in vocabulary "%s".', $name, $vocabulary));
    }

    return (int) reset($terms)->id();
  }

  /**
   * The demo posts, keyed by nothing in particular but a fixed UUID each.
   *
   * @return array[]
   *   Each entry has: uuid, title, category, outcome, resolved, difficulty,
   *   robot_models, tech_tags, difficulty_factors, environment, procedure,
   *   and optionally hf_repo and metadata_yaml (uuid, filename, contents).
   */
  private function postDefinitions(): array {
    return [
      [
        'uuid' => 'e4edd350-b790-4e24-892e-05b5a0126f60',
        'title' => '洗濯物たたみの基本ピック&プレース検証',
        'category' => '家庭内作業',
        'outcome' => 'success',
        'resolved' => TRUE,
        'difficulty' => '初級',
        'robot_models' => ['SO-ARM100'],
        'tech_tags' => ['ピック&プレース', 'ROS2'],
        'difficulty_factors' => ['柔軟物把持'],
        'environment' => '在宅の一般的なリビングを想定し、白色LED照明下でTシャツとタオルを台の上に置いて撮影。背景は無地のマット。',
        'procedure' => "## 手順\n\n1. カメラでたたむ対象の衣類を認識する。\n2. 衣類の端をつまみ、テーブル上の基準位置まで運ぶ（ピック&プレース）。\n3. 二本目のアームで対辺をつまみ、たたみ動作を行う。\n4. たたんだ衣類を指定のスタック位置に置く。\n\n## 結果\n\n12回中11回成功。Tシャツのしわが大きい場合にグリップがずれることがあった。\n",
        'hf_repo' => 'example-org/so100-laundry-fold',
        'metadata_yaml' => [
          'uuid' => '226ec198-48a4-4d39-a687-64d761a02bca',
          'filename' => 'post01-metadata.yaml',
          'contents' => "robot_type: so-arm100\nfps: 30\ntask: 洗濯物たたみのピック&プレース\nphases:\n  - name: grasp\n    start_frame: 0\n    end_frame: 45\n  - name: move\n    start_frame: 46\n    end_frame: 120\n  - name: release\n    start_frame: 121\n    end_frame: 150\n",
        ],
      ],
      [
        'uuid' => 'e8f591f0-32c9-4509-a12f-32f29b00baae',
        'title' => '重なった食器のピッキングと分離',
        'category' => '家庭内作業',
        'outcome' => 'partial',
        'resolved' => FALSE,
        'difficulty' => '中級',
        'robot_models' => ['SO-ARM101'],
        'tech_tags' => ['柔軟物把持', '力制御'],
        'difficulty_factors' => ['重なり分離', '力制御'],
        'environment' => 'キッチンのシンク横を想定し、皿を2〜3枚重ねた状態でカメラ前に配置。反射のある陶器皿を使用。',
        'procedure' => "## 手順\n\n1. 深度カメラで重なった皿の輪郭を検出する。\n2. 一番上の皿の縁を検出し、力制御しながら持ち上げる。\n3. 持ち上げた皿を隣のトレイへ移動する。\n4. 残りの皿についても同様に繰り返す。\n\n## 結果\n\n反射の強い皿でエッジ検出が不安定になり、3回に1回は皿同士がずれて分離に失敗した。\n",
      ],
      [
        'uuid' => '1756518f-4b09-4db3-9ed8-40abf340b0f0',
        'title' => 'おもちゃ片付けタスクでの未知形状把持',
        'category' => '家庭内作業',
        'outcome' => 'failure',
        'resolved' => FALSE,
        'difficulty' => '上級',
        'robot_models' => ['SO-ARM100'],
        'tech_tags' => ['模倣学習', 'ACT'],
        'difficulty_factors' => ['未知形状対応', '非構造環境計画'],
        'environment' => '子供部屋を想定し、形状やサイズがバラバラなブロック・ぬいぐるみ・車のおもちゃを床に散乱させた状態。',
        'procedure' => "## 手順\n\n1. RGB-Dカメラでシーン全体をスキャンし、対象物候補を検出する。\n2. 模倣学習で事前学習したポリシーで把持姿勢を推定する。\n3. 推定した姿勢でアプローチし、グリップを閉じる。\n4. 収納ボックスまで運搬し、リリースする。\n\n## 結果\n\n未知形状のぬいぐるみで把持姿勢の推定精度が低く、10回中6回落下した。非構造環境での動作計画にも時間がかかった。\n",
      ],
      [
        'uuid' => 'c71e3d8a-7a33-4cb3-aec9-846a2ed33bca',
        'title' => '小型部品の組立ライン投入テスト',
        'category' => '製造・組立',
        'outcome' => 'success',
        'resolved' => TRUE,
        'difficulty' => '中級',
        'robot_models' => ['SO-ARM101'],
        'tech_tags' => ['MoveIt', 'ROS2'],
        'difficulty_factors' => ['位置決め精度±1mm', '剛性・タクト'],
        'environment' => '簡易組立ラインを模したベンチ上で、ネジとブラケットを供給トレイに配置。工場を想定した明るい照明。',
        'procedure' => "## 手順\n\n1. MoveItで生成した経路でアームを供給トレイ上へ移動する。\n2. 部品を把持し、位置決め治具に合わせて挿入する。\n3. 挿入後にトルクセンサーで固定状態を確認する。\n4. 次の部品供給位置へ戻る。\n\n## 結果\n\n20回中19回成功。位置決め精度は±1mm以内に収まった。\n",
      ],
      [
        'uuid' => 'beed1342-746b-4962-8ae7-263135f1ed2f',
        'title' => '多品種部品のビンピッキング検証（SO-ARM100/101比較）',
        'category' => '製造・組立',
        'outcome' => 'partial',
        'resolved' => TRUE,
        'difficulty' => '上級',
        'robot_models' => ['SO-ARM100', 'SO-ARM101'],
        'tech_tags' => ['Isaac Sim', 'VLA'],
        'difficulty_factors' => ['多品種把持', 'Sim2Real'],
        'environment' => '部品混載のビン（部品箱）を用意し、複数種類のボルト・ワッシャー・小型ブラケットを無造作に投入した状態。',
        'procedure' => "## 手順\n\n1. Isaac Simで事前検証したVLAモデルでビン内の部品配置を推定する。\n2. SO-ARM100とSO-ARM101それぞれで同一ビンからのピッキングを試行する。\n3. 推定した把持点で部品を取り出し、種類別トレイへ仕分ける。\n4. ビン内の部品がなくなるまで繰り返す。\n\n## 結果\n\nSO-ARM101はトルクに余裕がありビン奥の部品も取り出せたが、SO-ARM100は可搬重量の制約で大型ブラケットを落下させることがあった。\n",
        'hf_repo' => 'example-org/so-arm-bin-picking-both',
      ],
      [
        'uuid' => '32be2768-ea0b-44cf-8742-1ea894740605',
        'title' => '倉庫内ピック&プレースの搬送検証',
        'category' => '物流・搬送',
        'outcome' => 'success',
        'resolved' => TRUE,
        'difficulty' => '初級',
        'robot_models' => ['SO-ARM100'],
        'tech_tags' => ['ピック&プレース', 'Jetson'],
        'difficulty_factors' => ['可搬重量制限'],
        'environment' => '小型倉庫の棚を模したラックにダンボール箱を並べ、Jetson搭載のカメラで棚全体を撮影。',
        'procedure' => "## 手順\n\n1. カメラで搬送対象の箱の位置を検出する。\n2. 箱の側面を把持し、コンベア上まで搬送する。\n3. コンベアに置いたら次の箱へ移動する。\n\n## 結果\n\n15回中15回成功。可搬重量の範囲内であれば安定して動作した。\n",
      ],
      [
        'uuid' => '6b7d70b9-caa6-44ab-a6d1-b4016558a1d1',
        'title' => '梱包前の位置決め搬送タスク',
        'category' => '物流・搬送',
        'outcome' => 'failure',
        'resolved' => FALSE,
        'difficulty' => '中級',
        'robot_models' => ['SO-ARM101'],
        'tech_tags' => ['力制御', 'MoveIt'],
        'difficulty_factors' => ['位置決め精度±1mm', '協調制御'],
        'environment' => '梱包ラインを想定し、部品を規定の向きで梱包箱の所定位置に置く検証。複数アームでの協調搬送を含む。',
        'procedure' => "## 手順\n\n1. 部品をピックし、梱包箱手前まで搬送する。\n2. もう一方のアームと協調しながら箱を開いた状態で保持する。\n3. 規定の向きになるよう位置決めしながら箱内に配置する。\n\n## 結果\n\n協調動作のタイミングがずれ、10回中4回で部品の向きがずれたまま配置された。位置決め精度の課題が残る。\n",
        'metadata_yaml' => [
          'uuid' => '47638f8b-f4d5-4269-b686-446d85bfabb4',
          'filename' => 'post07-metadata.yaml',
          'contents' => "robot_type: so-arm101\nfps: 25\ntask: 梱包前の位置決め搬送\nphases:\n  - name: grasp\n    start_frame: 0\n    end_frame: 30\n  - name: move\n    start_frame: 31\n    end_frame: 90\n  - name: release\n    start_frame: 91\n    end_frame: 110\n",
        ],
      ],
      [
        'uuid' => '482180b3-8453-427c-8f5f-8eeca2510b48',
        'title' => '強化学習によるアーム制御の教育デモ',
        'category' => '研究・教育',
        'outcome' => 'success',
        'resolved' => TRUE,
        'difficulty' => '初級',
        'robot_models' => ['SO-ARM100'],
        'tech_tags' => ['強化学習', 'ROS2'],
        'difficulty_factors' => ['再現性'],
        'environment' => '大学の実習室を想定し、単純なリーチングタスク用のターゲットマーカーを机上に設置。',
        'procedure' => "## 手順\n\n1. シンプルな到達タスクの報酬関数を設定する。\n2. 強化学習ポリシーを学習用シミュレーションで訓練する。\n3. 学習済みポリシーを実機のSO-ARM100に転送して動作確認する。\n\n## 結果\n\n学生向けデモとして安定して動作し、8回中8回ターゲットに到達できた。\n",
      ],
      [
        'uuid' => '4f531a09-3af0-4e11-aa6a-02deb7e14280',
        'title' => '模倣学習アルゴリズムの比較実験',
        'category' => '研究・教育',
        'outcome' => 'partial',
        'resolved' => TRUE,
        'difficulty' => '上級',
        'robot_models' => ['SO-ARM101'],
        'tech_tags' => ['模倣学習', 'Diffusion Policy', 'ACT'],
        'difficulty_factors' => ['評価基準', '個体差'],
        'environment' => '大学の研究室内で統一した照明条件下、同一タスクを複数の模倣学習手法で実行し比較。',
        'procedure' => "## 手順\n\n1. 同一のデモンストレーションデータセットからACTとDiffusion Policyをそれぞれ学習する。\n2. 同じ評価タスクで両モデルを実機評価する。\n3. 成功率・再現性・推論速度を比較する。\n\n## 結果\n\nDiffusion Policyの方が成功率は高かったが、個体差による再現性のばらつきが大きく評価基準の統一が課題となった。\n",
      ],
      [
        'uuid' => '072d43ee-7df9-4603-8149-ead080b2f2c7',
        'title' => 'テレオペによる模倣学習データ収集',
        'category' => 'データ収集・学習',
        'outcome' => 'success',
        'resolved' => TRUE,
        'difficulty' => '中級',
        'robot_models' => ['SO-ARM100'],
        'tech_tags' => ['LeRobot', '模倣学習'],
        'difficulty_factors' => ['フォーマット統一（LeRobot）', '量確保'],
        'environment' => 'テレオペ用コントローラとカメラを設置した収録ブースで、ピック&プレースの実演データを収集。',
        'procedure' => "## 手順\n\n1. テレオペコントローラでSO-ARM100を操作し、タスクを実演する。\n2. 実演中の関節角度・カメラ映像・タイムスタンプをLeRobot形式で記録する。\n3. 収集したエピソードをフォーマット統一のうえHuggingFaceリポジトリへアップロードする。\n\n## 結果\n\n1時間で30エピソードを収集でき、LeRobot形式に統一したことでそのまま学習パイプラインに投入できた。\n",
        'hf_repo' => 'example-org/so100-teleop-demo',
      ],
      [
        'uuid' => 'b29a214b-7050-4d3b-9782-aa2e03a2b1a6',
        'title' => 'キャリブレーション誤差によるデータ品質低下',
        'category' => 'データ収集・学習',
        'outcome' => 'failure',
        'resolved' => FALSE,
        'difficulty' => '上級',
        'robot_models' => ['SO-ARM101'],
        'tech_tags' => ['LeRobot', 'Jetson'],
        'difficulty_factors' => ['キャリブレーション', '品質ばらつき'],
        'environment' => '長時間の連続収録セッションで、キャリブレーション未実施のままSO-ARM101を使用したケース。',
        'procedure' => "## 手順\n\n1. 収録開始前のキャリブレーションを省略してテレオペ収録を開始する。\n2. 数時間にわたりデータを収集し続ける。\n3. 収集後にログを確認し、関節角度のドリフトを検証する。\n\n## 結果\n\nキャリブレーションのずれが蓄積し、後半のエピソードで軌道データの品質が大きくばらついた。次回はセッション途中の再キャリブレーションが必要。\n",
      ],
      [
        'uuid' => '7bfbe0ec-2f30-4969-a4bd-cbfa5a952d4b',
        'title' => 'VLA向け軌道データの再現性検証',
        'category' => 'データ収集・学習',
        'outcome' => 'partial',
        'resolved' => FALSE,
        'difficulty' => '中級',
        'robot_models' => ['SO-ARM100'],
        'tech_tags' => ['VLA', 'LeRobot'],
        'difficulty_factors' => ['再現性担保', 'フォーマット統一（LeRobot）'],
        'environment' => '同一タスク・同一環境設定で複数回収録を行い、軌道データのばらつきを比較する検証ブース。',
        'procedure' => "## 手順\n\n1. 同じタスクシナリオでSO-ARM100の実演を5セッション収録する。\n2. 各セッションのLeRobot形式データをフォーマット統一のうえ比較する。\n3. VLAモデル学習に使えるだけの再現性があるかを評価する。\n\n## 結果\n\nセッション間で軌道の再現性担保が不十分な回があり、VLA学習用データとして一部エピソードを除外する必要があった。\n",
      ],
    ];
  }

}
