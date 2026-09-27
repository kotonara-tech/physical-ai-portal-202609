# Coding Agent（Claude Code）への指示

## プロジェクト概要

Physical AI共創プログラムの知見共有ポータルを構築する。SO\-ARM\-100/101のユースケース、技術知見、実装事例を集約し、研究者・開発者・教育機関が協働できる場を提供する。

## 要件定義

### ユースケース

1. 家庭内作業
- 作業例：食洗機投入、洗濯物たたみ、ペット給餌、片付け、テーブル拭き、注水、冷蔵庫から取り出し、折り紙、料理補助、植物の世話、子供のお箸矯正
- 技術課題：柔軟物把持、未知形状対応、非構造環境計画、安全性
4. 製造・組立
- 作業例：小型部品ピック&amp;プレース、ネジ締め・供給、コネクタ挿入、ラベル貼付、治具位置決め、キッティング、簡易検査
- 技術課題：位置決め精度±1mm、力制御、再現性、剛性・タクト
7. 物流・搬送
- 作業例：ビンピッキング、棚移動、仕分け分配、スキャン補助、AMR積替え、軽量梱包補助、整理・自動仕分け
- 技術課題：可搬重量制限、多品種把持、重なり分離、協調制御
10. 研究・教育
- 作業例：模倣学習収集、強化学習ベンチ、ROS2/MoveIt学習、制御理論実験、ハッカソン・展示、危険化学実験の遠隔
- 技術課題：Sim2Real、キャリブレーション、個体差、再現性担保
13. データ収集・学習（VLA/ポリシー）
- 作業例：テレオペ収集、把持軌道記録、失敗アノテーション、並列記録、HuggingFace公開
- 技術課題：量確保、品質ばらつき、フォーマット統一（LeRobot）、評価基準

難易度が高いタスク（留意）：錠剤1粒取り出し、コンセント挿入、ペットボトル蓋開け、ボタン留め・ファスナー、SO\-101がSO\-101組立、ルービックキューブ、ゲーム操作。

### ポータルサイトの機能要件

- コンテンツ管理：事例登録/検索、課題と解決共有、動画・画像・コード、タグ分類
- ユーザ機能：登録、プロジェクト管理、コメント、ブックマーク
- データベース：事例メタ、技術スタック、センサ/キャリブレーション、モデル/データセットリンク
- 連携：Drupal JSON:API、ROS2、HuggingFace統合
- ターゲットユーザ：研究者、開発者、教育機関、ハッカソン参加者

## 技術スタック

### 必須要件

- CLAUDE\.md準拠、Drupal10\+、JSON:API/REST、React/Vue、WCAG2\.1AA、PHP8\.1\+、Composer、Custom Module、MySQL/PostgreSQL、適切なエンティティ設計、RBAC、OAuth2/JWT、CSRF/XSS対策

### 推奨連携

- ROS2（rosbridge\_suite）、NVIDIA Isaac Sim、LeRobot/HuggingFace、GitとCI/CD

## 実装指針

### Drupalベストプラクティス

- コンテンツ設計：Use Case/Technical Note/Project、Taxonomy、Entity Reference
- モジュール構成：modules/custom と modules/contrib、機能単位分割
- 標準・品質：Drupal Coding Standards、PHPCS、PHPUnit
- パフォーマンス：Render/Dynamic Cache、ビュー最適化、WebP
- 多言語：日本語/英語、Interface/Content Translation

### 開発フロー

- ローカル環境（DDEV/Lando）→Content Type/Field設計→Custom Module→JSON:API→フロント統合→テスト（機能/性能）→ドキュメント

## 成果物

- Drupalサイト一式、API仕様（OpenAPI）、セットアップ手順、ユーザマニュアル、技術仕様書

## 参考情報

- Drupal JSON:APIで制御可
- NVIDIA Isaac Simでシミュレーション
- SO\-ARM\-100/101は「掴む→移動→離す」が得意（可搬数百g・6DoF）

