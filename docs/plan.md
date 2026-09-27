# 開発計画

`docs/spec/` の仕様に対する残タスクと、着手の順序をまとめます。
進め方（TDD・Tidy First・テストサイズ・コミット規約）は `CLAUDE.md` に従います。

最終更新: 2026-09-27

## 現状（仕様の実装ステップとの対応）

| 実装ステップ | 状態 | 根拠 |
|---|---|---|
| Docker Compose 起動 | 済 | `docker-compose.yml`、E2E `test_06` |
| JSON:API / Search API / Media / File 有効化 | 一部 | Media は使わない（下の「仕様からの逸脱」） |
| コンテンツタイプとフィールド | 済 | soarm_core `robot_knowledge` |
| タクソノミー | 済 | soarm_core の語彙 5 つ |
| ストレージ（S3 またはローカル） | 済（ローカル） | S3 は残スコープ |
| 検索（全文 + ファセット） | 一部 | 全文と絞り込みは `/knowledge`。件数付きのファセットが無い（残スコープ） |
| 一覧のフィルタ | 済 | `/knowledge`、E2E `test_01` |
| 詳細（動画・軌道 DL・手順） | 済 | `file_video` / `file_default` / Markdown、E2E `test_02` |
| コメント・投票 | 済 | soarm_vote、E2E `test_04` |
| ダッシュボード | 済 | soarm_dashboard `/dashboard` |
| プロフィール（履歴・専門・所属） | 一部 | 専門・所属はある。投稿履歴が無い（残スコープ） |
| JSON:API と LeRobot | 済 | soarm_api / soarm_lerobot、E2E `test_05` |

完了条件の 7 項目は E2E（`tests/e2e/`、40 関数）で受け入れ済みです。

テスト（2026-09-27）: Unit 18 / Kernel 72 メソッド、Functional 0。
全テストにサイズの属性が付いています（`--list-tests` で Small 23 件 / Medium 102 件）。
E2E（Large）は 50 件で、比率は Small 13% / Medium 58% / Large 29%。目安の
70〜80% / 15〜20% / 5〜10%（CLAUDE.md「テスト分類」）から外れていて、ピラミッドになっていません。
CI は GitHub Actions（`.github/workflows/ci.yml`）で、Small・Medium・phpcs を回します。

## テスト基盤と CI

ゴール: main への push と PR のたびに、Small と Medium のテストが GitHub Actions で走り、
サイズの無いテストが入ったら CI が落ちる。Large（E2E・GUI）は CI に入れない。

この節は上から順に進めます（2026-09-27 承認）。

- [x] `test`: 既存テストに `#[Small]` / `#[Medium]` を付ける（Unit = Small、Kernel = Medium）。
      モジュールごとに 1 コミット（core / dashboard / lerobot / vote。demo は済）。
      例外: soarm_lerobot の `TrajectoryFormatDetectorTest` は Unit にあるが一時ファイルを書くので Medium
- [x] `ci`: GitHub Actions のワークフローを足す（PR #1）
  - `Dockerfile` の `app` ステージを 1 回 build し（buildx + GHA キャッシュ）、artifact で
    各ジョブに渡す。Kernel は SQLite なので DB コンテナも compose も要らない
  - サイズの検査: `phpunit --list-tests --exclude-group small,medium` が 0 件でなければ落とす
    （PHPUnit に `#[Large]` は付けない）
  - Small: `phpunit --group small`。phpcs（`Drupal,DrupalPractice`）も同じジョブ
  - Medium: `phpunit --group medium`。モジュールごとに matrix で並列にする
    （soarm_core の Kernel だけで 4 分以上かかる）
- [ ] `test(dashboard)`: `unresolved()` と `popular()` が他のコンテンツタイプを除くことを
      確かめるテスト 2 件（`f7c4289` と同じ手）
- [ ] `build(docker)`: `pcntl` を入れ、CI で `--enforce-time-limit` を効かせる。
      先に JUnit ログでテストごとの時間を測り、Medium の既定 10 秒に収まるか確かめる
- [ ] Functional（`BrowserTestBase`）の基盤を確かめ、CI に compose を使うジョブを足す。
      最初の Functional テストが要る機能（プロフィールの投稿履歴かブックマーク）と一緒にやる
- [ ] Small の条件を CI で機械的に確かめる: Small のステップを `docker run --network none --read-only`
      で流し、ネットワークとファイルの書き込みを使う Small を落とす（今は印の有無しか見ていない）
- [ ] 改めて整頓: Kernel に偏ったテストを Small へ押し下げる。モジュールごとに別の依頼。
      `TrajectoryFormatDetectorTest` も対象（`detect()` がファイルパスを受け取るので、今は一時ファイルが要る）

## 残スコープ

順序は未承認です。着手する前にユーザーに確かめます。

- [ ] ファセット（件数付き）: `facets` は導入済み・未有効。`/knowledge` の絞り込みに件数を出す
- [ ] ブックマーク（flag）+ プロフィールの投稿履歴
- [ ] Project（プロジェクト管理）
- [ ] OAuth2（simple_oauth）
- [ ] 多言語（日本語／英語）
- [ ] サブテーマ + WCAG 2.1 AA（ここで Playwright を導入。Large なので CI には入れない）
- [ ] React / Vue のフロント統合（最大。OAuth2 の後）
- [ ] S3 互換ストレージ（s3fs + minio）
- [ ] ドキュメント（OpenAPI・ユーザマニュアル・技術仕様書）

## 仕様からの逸脱（作らないと決めたもの）

2026-09-27 に決定。

- Media モジュールは使わない。動画・軌道・YAML・画像は file / image フィールドで持つ
  （完了条件の登録・表示・ダウンロードは E2E で満たしている）
- 画像の WebP 変換はしない（要件「パフォーマンス」）
- コンテンツ設計の Use Case / Technical Note は、`robot_knowledge` 1 つでまかなう
  （Project は残スコープ）
- HuggingFace / ROS2 連携は、今の HF リポジトリのリンクフィールドと、コード内の拡張ポイントの
  コメントまでにする

## 運用

- [ ] 稼働 DB の `bad-…` 投稿 4 件（nid 289, 290, 339, 340）を削除するか決める（承認が要る操作）

## 決定事項

- 2026-09-27: CI は Small と Medium だけを回す。Large（E2E・GUI）は CI に入れない
- 2026-09-27: 「テスト基盤と CI」を最初にやる
