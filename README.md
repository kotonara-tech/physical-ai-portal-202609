# SO-ARM Knowledge Portal

SO-ARM-100 / 101 の実験データ・操作デモ・モデル・失敗事例を投稿し、検索・閲覧・ダウンロード
できる知見共有ポータルです（Drupal 11 + Nginx + PHP-FPM + MariaDB）。

## 起動

必要なもの: Docker（Compose v2）

```sh
docker compose up -d
```

初回はイメージの build と Drupal のインストールが自動で走るため、数分かかります。
`docker compose ps` で `drupal` が `healthy` になったら <http://localhost:8080> を開きます。

管理者としてログインする URL を作る:

```sh
docker exec -u www-data workspace-drupal-1 drush uli --uid=1
```

| 変えたいもの | 環境変数（`.env` に書く） | 既定値 |
|---|---|---|
| 公開ポート | `HTTP_PORT` | `8080` |
| サイトの URL | `SITE_URL` | `http://localhost:8080` |
| 管理者パスワード | `ADMIN_PASS` | `admin`（ローカル専用。公開環境では必ず変える） |
| 受け付けるホスト名 | `TRUSTED_HOSTS` | `localhost` のみ |

## 構成

```text
nginx (web) --FastCGI--> drupal (PHP-FPM) --SQL--> mysql (MariaDB)
```

| パス | 役割 |
|---|---|
| `Dockerfile` | multi-stage。`app` = PHP-FPM + Composer 依存、`web` = Nginx + 静的ファイル |
| `composer.json` / `composer.lock` | Drupal core と contrib モジュールのバージョン固定 |
| `docker/drupal/entrypoint.sh` | 初回起動時のサイトインストールと `soarm_*` モジュール有効化 |
| `docker/drupal/settings.php` | 環境変数から DB などを読む Drupal 設定 |
| `web/modules/custom/` | カスタムモジュール（コンテナに bind mount。編集はすぐ反映） |
| `web/themes/custom/` | カスタムテーマ |
| `config/sync/` | `drush config:export` の出力先 |
| `tests/e2e/` | 完了条件の受け入れテスト（pytest、起動中のサイトに HTTP で当てる） |

contrib モジュールを足すとき（`composer.json` / `composer.lock` が更新されるので、コミットして再 build）:

```sh
docker run --rm -u "$(id -u):$(id -g)" -e COMPOSER_HOME=/tmp/composer -v "$PWD":/app -w /app workspace-drupal composer require drupal/<module> --no-install
docker compose up -d --build
```

## テスト

```sh
# 受け入れテスト（ホスト側）
python3 -m pip install -r tests/requirements.txt
python3 -m pytest -q

# モジュールの PHPUnit（コンテナ内）
docker exec -u www-data -w /opt/drupal workspace-drupal-1 \
  vendor/bin/phpunit -c phpunit.xml web/modules/custom

# コーディング規約
docker exec -w /opt/drupal workspace-drupal-1 \
  vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/custom
```

## 作り直す

データベースとアップロード済みファイルを **すべて消して** 最初からやり直す:

```sh
docker compose down -v
docker compose up -d --build
```

## GitHub CLI のセットアップ（任意）

```sh
./setup.sh --with-tools  # サイト起動 + GitHub CLI
```
