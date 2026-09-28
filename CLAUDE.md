# CLAUDE.md

このリポジトリで Claude Code が作業するときのガイドです。

このリポジトリは、SO-ARM-100 / 101 の実験データ・操作デモ・モデル・失敗事例を
投稿し、検索・閲覧・ダウンロードできる知見共有ポータルです
（Drupal 11 + Nginx + PHP-FPM + MariaDB、Docker Compose）。
仕様は `docs/spec/` にあります（`requirements.md` = 要件、
`implementation-steps.md` = 実装ステップと完了条件）。

このファイルは、Drupal のコントリビュータが CodeSandbox の Drupal ワークショップ用に
書いた CLAUDE.md を土台にしています（原文: `docs/claude-md-original.md`）。
Drupal のベストプラクティスを書いた節は原文のまま残し、環境の説明だけをこの repo に
合わせ、ポータル開発用の節を末尾の「ポータル開発」に足しました。原文から残した節は
次のように読み替えてください。

- 「学生」「ワークショップ」→ この repo で開発する人。
- 「CodeSandbox Preview」「Preview のポート」→ `http://localhost:8080`
  （`.env` の `SITE_URL` / `HTTP_PORT`）。
- 「`mysql/data`」→ named volume `db_data`。消す操作は `docker compose down -v`。

説明は具体的にしてください。変更は小さく保ち、Drush で確認し、ファイルパス、
Drupal 管理画面のパス、次に開く URL を示してください。

## 基本方針

ユーザー自身の Drupal アイデアを、このローカルサンドボックスで少しずつ形に
することを助けてください。

学生に特定の制作物を押し付けないでください。アイデアが大きい場合は、
Drupal で動かせる最小の一歩に分けて、その一歩を作るか説明してください。

ユーザーが「何を作ればよいか」と聞いた場合を除き、作るものをこちらから
決めつけないでください。

役に立つことが多い Drupal の部品:

- カスタムモジュールを作る。
- URL に対応するページを作る。
- ブロックを作る。
- 簡単なフォームを作る。
- サービスを作る。
- Twig テンプレートやテーマを少し変更する。
- コンテンツタイプを作る、または説明する。
- Drush でモジュールを有効化し、キャッシュをクリアする。

これらは課題ではなく、道具です。ユーザーのアイデアに一番合うものを選んで
ください。小さな依頼を大きな設計作業に広げないでください。まずは 1 つの
動く変更を完成させて確認することを優先してください。

## 環境の概要

Drupal はホスト上で直接動いているのではなく、Docker Compose のコンテナ内で
動いています。compose ファイルは repo 直下の `docker-compose.yml` で、
プロジェクト名は `name: workspace` に固定しています。

```text
nginx (web) --FastCGI--> drupal (PHP-FPM) --SQL--> mysql (MariaDB)
```

重要なコンテナ:

```text
workspace-nginx-1    Nginx（静的ファイルの配信と PHP-FPM への中継）
workspace-drupal-1   Drupal + PHP-FPM + Drush
workspace-mysql-1    MariaDB
```

`docker-compose.yml` 上のサービス名:

```text
nginx
drupal
mysql
minio    任意。docker compose --profile s3 up -d の時だけ起動する S3 互換ストレージ
```

サイトは nginx 経由でホスト側のポート `8080` に公開されています
（`.env` の `HTTP_PORT` で変更可）。drupal（9000）と mysql（3306）はホストに
公開していません。

```text
nginx container port 80 -> host port 8080
```

URL:

```text
http://localhost:8080
```

Drush が生成する URL は `DRUSH_OPTIONS_URI`（= `.env` の `SITE_URL`、既定は
`http://localhost:8080`）で始まるので、そのままブラウザで開けます。

## 確認済みのバージョン

実行中のコンテナで確認したバージョン（2026-09-27）:

```text
Drupal       11.4.7
PHP          8.5.10
Drush        13.8.0.0
Composer     2.10.3
MariaDB      11.4.13
Nginx        1.30.5
PHPUnit      11.5.56
PHPCS        3.13.6
PHPStan      2.2.14
```

Drupal のインストールプロファイル:

```text
standard
```

サイト名:

```text
SO-ARM Knowledge Portal
```

デフォルトテーマ:

```text
olivero
```

管理画面テーマ:

```text
claro
```

管理者ユーザー:

```text
username: admin
uid: 1
```

パスワードはローカル用の既定値で、`.env` の `ADMIN_PASS` で変えます
（公開する環境では必ず変える）。値はここに書きません。

ただし、パスワード入力を案内するよりも、ワンタイムログイン URL を作るほうを
優先してください。

```sh
docker exec -u www-data workspace-drupal-1 drush uli --uid=1
```

生成された URL は `http://localhost:8080/...`（`SITE_URL`）で始まるので、
そのまま開けます。

## コマンド実行のルール

ホスト側で PHP、Composer、PHPUnit、PHPCS、Drush を直接実行しないでください。
ホスト環境には PHP や Composer が PATH 上にありません。

Drupal 関連のコマンドは `workspace-drupal-1` コンテナの中で実行します。

コンテナの既定ユーザーは root なので、**drush は必ず `-u www-data` を付けて
実行してください。** root で `drush cr` などを流すと、`sites/default/files` の下
（Twig のキャッシュなど）に root 所有のファイルができ、PHP-FPM（www-data）が
書き込めなくなってサイトが壊れます。コンテナの www-data はホストのユーザー ID に
合わせてある（build 引数 `HOST_UID` / `HOST_GID`）ので、`-u www-data` で作った
ファイルはホスト側でもそのまま編集できます。

よく使うコマンド:

```sh
docker compose ps
docker logs --tail 80 workspace-drupal-1
docker logs --tail 80 workspace-nginx-1
docker logs --tail 80 workspace-mysql-1
docker exec -u www-data workspace-drupal-1 drush status
docker exec -u www-data workspace-drupal-1 drush cr
docker exec -u www-data workspace-drupal-1 drush uli --uid=1
docker exec -u www-data workspace-drupal-1 drush watchdog:show --count=20
docker exec -u www-data workspace-drupal-1 drush core:requirements
docker exec -u www-data workspace-drupal-1 drush updatedb:status
docker exec -u www-data workspace-drupal-1 drush pm:list
docker exec -u www-data workspace-drupal-1 drush pm:list --type=module --status=enabled
docker exec -u www-data workspace-drupal-1 drush pm:list --type=theme
docker exec -u www-data workspace-drupal-1 drush config:status
```

Composer は `/opt/drupal`（コンテナの既定の作業ディレクトリ）から実行します。
入っているパッケージを見るだけなら:

```sh
docker exec -w /opt/drupal workspace-drupal-1 composer show --direct
```

contrib module を足すときは、稼働中のコンテナで `composer require` しないで
ください。vendor と contrib はイメージの中にあり（bind mount していない）、
コンテナを作り直すと消えます。ホスト側の `composer.json` / `composer.lock` を
更新し、コミットして再 build します（README と同じ手順）。

```sh
docker run --rm -u "$(id -u):$(id -g)" -e COMPOSER_HOME=/tmp/composer -v "$PWD":/app -w /app workspace-drupal composer require drupal/<module> --no-install
docker compose up -d --build
```

コマンドが `command not found` やエラーを返した場合は、まず
`docker exec -u www-data workspace-drupal-1 drush list` などで正しいコマンド名やオプションを
確認し、直してから実行し直してください。生の stack trace をそのまま貼り付けず、
何が起きたのかを一言で伝えてください。

## 重要なパス

ホスト側のワークスペース:

```text
この repo のルート（docker-compose.yml がある場所）
```

コンテナ内の Drupal プロジェクトルート（コンテナの既定の作業ディレクトリでもある）:

```text
/opt/drupal
```

コンテナ内の Drupal web root:

```text
/opt/drupal/web
```

Nginx の document root（nginx コンテナ内。drupal コンテナの `/var/www/html` も
ここへの symlink）:

```text
/opt/drupal/web
```

非公開ファイル（`private://`）と hash_salt:

```text
/opt/drupal/private
```

## bind mount

このリポジトリでは Drupal プロジェクト全体ではなく、自分で書くコードと設定だけが
ホスト側に bind mount されています。

ホストから drupal コンテナへの mount:

```text
./web/modules/custom  -> /opt/drupal/web/modules/custom
./web/themes/custom   -> /opt/drupal/web/themes/custom
./config              -> /opt/drupal/config
```

nginx コンテナにも `web/modules/custom` と `web/themes/custom` を読み取り専用で
mount しています（CSS / JS / 画像を nginx が直接配信するため）。

named volume（コンテナを作り直しても残る。`docker compose down -v` で消える）:

```text
db_data          -> /var/lib/mysql                       (mysql)
drupal_files     -> /opt/drupal/web/sites/default/files  (drupal。nginx は読み取り専用)
drupal_private   -> /opt/drupal/private                  (drupal)
minio_data       -> /data                                (minio)
```

MariaDB の初期データ: `./mysql/dump` → `/docker-entrypoint-initdb.d`（読み取り専用）。
`.sql` / `.sql.gz` を置くと、DB が空の初回起動時に流し込まれます。

イメージに COPY されていて mount されていないもの。編集しても、
`docker compose up -d --build` で再 build するまでコンテナには反映されません。

```text
docker/drupal/settings.php     -> /opt/drupal/web/sites/default/settings.php
phpunit.xml                    -> /opt/drupal/phpunit.xml
composer.json / composer.lock  -> /opt/drupal/（vendor・core・contrib は build 時にここから入る）
docker/drupal/entrypoint.sh    初回起動時のサイトインストールと soarm_* の有効化
docker/php/zz-soarm.ini        PHP の設定
docker/nginx/default.conf      Nginx の設定
```

Drupal core、vendor、contrib はコンテナイメージ内にあり、ホスト側の通常ファイル
としては見えません。core や vendor は編集しないでください。

## コードを置く場所

カスタムモジュール:

```text
web/modules/custom/<module_name>
```

カスタムテーマ:

```text
web/themes/custom/<theme_name>
```

自分で作るコードを直接置かない場所:

```text
web/core
vendor
web/modules/contrib
web/themes/contrib
```

ユーザーが環境設定を明示的に依頼した場合を除き、
`docker/drupal/settings.php`（コンテナの `web/sites/default/settings.php` の元）は
編集しないでください。

`.env`、API キー、データベースダンプ、その他の秘密情報を表示したり説明に
貼り付けたりしないでください。

## 確認済みの Drupal 状態

有効化されている core module（2026-09-27 実測）:

```text
announcements_feed  automated_cron  basic_auth  big_pipe  block  block_content
breakpoint  ckeditor5  comment  config  contextual  datetime  dblog
dynamic_page_cache  editor  field  field_ui  file  filter  help  image  jsonapi
layout_builder  layout_discovery  link  menu_link_content  menu_ui  mysql
navigation  node  options  page_cache  path  path_alias  serialization  system
taxonomy  text  update  user  views  views_ui
```

有効化されているテーマ:

```text
olivero
claro
```

contrib module（`composer.json` で導入済み）:

```text
有効:    search_api  search_api_db
未有効:  facets  flag  s3fs  simple_oauth（+ 依存の consumers）
```

未有効のものは残スコープ（末尾の「ポータル開発」を参照）のために入れてあります。
PHP ライブラリ `league/commonmark` も入っていて、soarm_core の Markdown フィルタが
使います。

カスタム module は `soarm_*` の 7 つで、すべて有効です（2026-09-27 実測。
一覧は末尾の「カスタムモジュール地図」）。`soarm_demo` が有効なので、稼働中のサイトには
デモ投稿が 12 件あります。

`Article` や `Basic Page` のようなデフォルトのコンテンツタイプが存在すると
決めつけないでください。特定の bundle に依存する作業をする前に確認してください。
2026-09-27 の実測では article / page は無く、コンテンツタイプは soarm_core が作る
`robot_knowledge`（ロボット知見投稿）だけです。

```sh
docker exec -u www-data workspace-drupal-1 drush config:get node.type.article
docker exec -u www-data workspace-drupal-1 drush config:get node.type.page
```

これらのコマンドが失敗した場合は、管理画面でコンテンツタイプを作るか、
必要な設定を明示的に作ってください。

## 設定管理の注意

`drush status` で確認した現在の config sync directory:

```text
../config/sync
```

これは `/opt/drupal/config/sync` で、repo の `config/sync/` が bind mount されて
います（`docker/drupal/settings.php` の `config_sync_directory`）。
`drush config:export` の出力は、そのまま repo の `config/sync/` に出てきます。
2026-09-27 時点では、`config/sync/` に export 済みの設定はありません
（`.gitkeep` と `.htaccess` だけ）。

設定を import/export する前に確認してください。

```sh
docker exec -u www-data workspace-drupal-1 drush status
docker exec -u www-data workspace-drupal-1 drush config:status
```

初心者向けの作業では、設定管理そのものがテーマでない限り、管理画面での変更や
小さなカスタムモジュールを優先してください。

## コード変更時の基本手順

1. 現在の状態を確認する。
2. Drupal らしい小さな変更を作る。
3. キャッシュをクリアする。
4. Drush と、必要ならブラウザや HTTP で確認する。
5. 何を変更したかを具体的に説明する。

よく使う確認の流れ:

```sh
docker exec -u www-data workspace-drupal-1 drush status
docker exec -u www-data workspace-drupal-1 drush cr
docker exec -u www-data workspace-drupal-1 drush watchdog:show --count=20
```

新しいカスタムモジュールを有効化する場合:

```sh
docker exec -u www-data workspace-drupal-1 drush pm:enable <module_name> -y
docker exec -u www-data workspace-drupal-1 drush cr
docker exec -u www-data workspace-drupal-1 drush pm:list --type=module --status=enabled
```

database update を追加または変更した場合:

```sh
docker exec -u www-data workspace-drupal-1 drush updatedb:status
docker exec -u www-data workspace-drupal-1 drush updatedb -y
docker exec -u www-data workspace-drupal-1 drush cr
```

## サイトが壊れたときの直し方

初心者のワークショップでは、サイトが真っ白になったり、エラー画面が出たりする
ことがあります。これは普通のことです。学生を責めず、落ち着いて直してください。

よくある症状:

```text
画面が真っ白になる
「The website encountered an unexpected error.」と表示される
HTTP 500 エラーが返る
Preview を開いても何も表示されない
```

まず原因を確認します。

```sh
docker exec -u www-data workspace-drupal-1 drush watchdog:show --count=20
docker logs --tail 80 workspace-drupal-1
docker exec -u www-data workspace-drupal-1 drush status
```

原因はたいてい、直前に作った custom module や theme の小さな間違いです。例:
`.info.yml` の書き間違い、PHP の syntax error、class 名や namespace の不一致。
エラーメッセージに出ている file 名と行番号が手がかりになります。

直し方の基本:

1. 直前に変更した file を開いて直す。custom の file はホスト側に bind mount
   されているので、直接編集できます。
2. cache をクリアする。

```sh
docker exec -u www-data workspace-drupal-1 drush cr
```

module が原因で、直すより一度止めたい場合:

```sh
docker exec -u www-data workspace-drupal-1 drush pm:uninstall <module_name> -y
docker exec -u www-data workspace-drupal-1 drush cr
```

PHP の致命的なエラーで `drush` コマンド自体が動かないことがあります。このときは
Drupal が起動できていないので、`drush pm:uninstall` も失敗します。原因の file を
直すか、その module のフォルダを `web/modules/custom/` から一時的に外へ移動
(またはリネーム)してから、cache をクリアしてください。

```sh
docker exec -u www-data workspace-drupal-1 drush cr
```

サイトが戻ったら、学生に「何が起きて」「なぜそうなり」「どう直したか」を短く
説明してください。同じ間違いに次は自分で気づけるようにするのが目的です。

データベースや `mysql/data` を消してサイト全体をリセットするのは最後の手段です。
学生がはっきり望まない限り実行しないでください。

## Drupal コーディング方針

Drupal 11 の書き方に合わせてください。

基本ルール:

- machine name は英小文字と underscore を使う。
- PHP クラスは `src/` 以下に置く。
- モジュールのクラス namespace は `Drupal\<module_name>\...` にする。
- services、controllers、forms、plugins では、できるだけ dependency injection を
  使う。
- PHP で HTML 文字列を組み立てるより、render array を使う。
- 出力は安全に扱う。通常の Twig 出力は Twig の autoescape に任せる。
- route と permission を明示する。
- 出力が route、user、permission、config、entity data に依存する場合は cache
  metadata を考える。
- PHP クラス、YAML、route、service、plugin、Twig template、library、config を
  変更したらキャッシュをクリアする。

生成済みキャッシュファイルは編集しないでください。

ただし、学生はこれらの言葉を知らない可能性があります。説明するときは、
必要になったタイミングで短く定義してください。

## カスタムモジュールの基本形

`event_demo` という基本的なカスタムモジュールを作る場合:

```text
web/modules/custom/event_demo/event_demo.info.yml
```

例:

```yaml
name: Event Demo
type: module
description: Workshop demo module.
package: Custom
core_version_requirement: ^11
```

有効化:

```sh
docker exec -u www-data workspace-drupal-1 drush pm:enable event_demo -y
docker exec -u www-data workspace-drupal-1 drush cr
```

## route と controller の基本形

簡単なページを作る場合:

```text
web/modules/custom/event_demo/event_demo.routing.yml
web/modules/custom/event_demo/src/Controller/EventDemoController.php
```

route file の例:

```yaml
event_demo.hello:
  path: '/event-demo'
  defaults:
    _controller: '\Drupal\event_demo\Controller\EventDemoController::hello'
    _title: 'Event Demo'
  requirements:
    _permission: 'access content'
```

controller は、直接 `echo` するのではなく render array を返してください。

controller の例:

```php
<?php

namespace Drupal\event_demo\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns pages for the Event Demo module.
 */
final class EventDemoController extends ControllerBase {

  /**
   * Builds the demo page.
   */
  public function hello(): array {
    return [
      '#markup' => $this->t('Hello Drupal.'),
    ];
  }

}
```

route や controller を追加したら:

```sh
docker exec -u www-data workspace-drupal-1 drush cr
```

その後、CodeSandbox Preview のホストで次のパスを開きます。

```text
/event-demo
```

## block plugin の基本形

`event_demo` に block plugin を作る場合:

```text
web/modules/custom/event_demo/src/Plugin/Block/EventDemoBlock.php
```

namespace:

```php
namespace Drupal\event_demo\Plugin\Block;
```

新しく block plugin を作るときは、既存コードが annotation を使っていない限り、
Drupal 11 の PHP attribute を使ってください。

block の例:

```php
<?php

namespace Drupal\event_demo\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides an event demo block.
 */
#[Block(
  id: "event_demo_block",
  admin_label: new TranslatableMarkup("Event demo block"),
  category: new TranslatableMarkup("Custom")
)]
final class EventDemoBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [
      '#markup' => $this->t('Today is @date.', [
        '@date' => date('Y-m-d'),
      ]),
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

}
```

実際の機能では、`date()` のような PHP 関数を直接呼ぶより `datetime.time` などの
service を注入するほうがよいです。ただし、最初の小さなデモでは理解しやすさを
優先してかまいません。

block を作成した後:

```sh
docker exec -u www-data workspace-drupal-1 drush cr
```

管理画面で block を配置する場所:

```text
/admin/structure/block
```

## form の基本形

簡単な form は次のような場所に class を置きます。

```text
web/modules/custom/<module_name>/src/Form/<Name>Form.php
```

単純な form には `FormBase`、設定用 form には `ConfigFormBase` を使います。

form 用のページが必要なら route を追加します。

```text
<module_name>.routing.yml
```

`_permission` または `_access` を明示してください。

form route の例:

```yaml
event_demo.form:
  path: '/event-demo/form'
  defaults:
    _form: '\Drupal\event_demo\Form\EventDemoForm'
    _title: 'Event Demo Form'
  requirements:
    _permission: 'access content'
```

簡単な form の例:

```php
<?php

namespace Drupal\event_demo\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a small workshop demo form.
 */
final class EventDemoForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'event_demo_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->messenger()->addStatus($this->t('Hello @name.', [
      '@name' => $form_state->getValue('name'),
    ]));
  }

}
```

form を追加または変更したら:

```sh
docker exec -u www-data workspace-drupal-1 drush cr
```

## コンテンツタイプの基本

コンテンツタイプは Drupal の重要な考え方です。初心者には、設定ファイルを直接
作るよりも、まず管理画面で作る方法を案内するほうが分かりやすいことが多いです。

管理画面のパス:

```text
/admin/structure/types
/admin/structure/types/add
```

コンテンツタイプ作成後、フィールドを管理する場所:

```text
/admin/structure/types/manage/<content_type_machine_name>/fields
```

machine name は英語の小文字と underscore を使います。

例:

```text
workshop_session
speaker_profile
event_news
```

コードが特定のコンテンツタイプに依存する場合は、先に存在を確認してください。

```sh
docker exec -u www-data workspace-drupal-1 drush config:get node.type.<machine_name>
```

## theme の基本形

カスタムテーマ:

```text
web/themes/custom/<theme_name>
```

小さなワークショップ用テーマなら、まずは単純にします。

```text
<theme_name>.info.yml
<theme_name>.libraries.yml
css/
js/
templates/
```

テーマを有効化してデフォルトにする:

```sh
docker exec -u www-data workspace-drupal-1 drush theme:enable <theme_name>
docker exec -u www-data workspace-drupal-1 drush config:set system.theme default <theme_name> -y
docker exec -u www-data workspace-drupal-1 drush cr
```

ユーザーが明示的に希望しない限り、管理画面テーマは Claro のままにしてください。

## Drush Code Generator

Drush には Drupal Code Generator 4.2.0 が入っています。

generator 一覧:

```sh
docker exec -u www-data workspace-drupal-1 drush generate --dry-run
```

便利な generator:

```text
module
controller
plugin:block
form:simple
form:config
service:custom
service:event-subscriber
theme
single-directory-component
test:unit
test:kernel
```

初心者向けの小さな作業では、必要な少数のファイルを直接作るほうが分かりやすい
場合があります。boilerplate が多いときや、ユーザーが generator を使いたいと
言ったときに使ってください。

## テストと品質確認ツール

`/opt/drupal/vendor/bin` にあるツール:

```text
phpunit
phpcs
phpcbf
phpstan
jsonlint
yaml-lint
```

インストール済みの PHPCS standard:

```text
Drupal
DrupalPractice
```

カスタムコードに PHPCS を実行する例:

```sh
docker exec -w /opt/drupal workspace-drupal-1 vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/custom/<module_name>
```

自動整形のために PHPCBF を実行するのは、ユーザーが望む場合だけにしてください。

```sh
docker exec -w /opt/drupal workspace-drupal-1 vendor/bin/phpcbf --standard=Drupal,DrupalPractice web/modules/custom/<module_name>
```

PHPUnit の設定は repo 直下の `phpunit.xml` です（イメージの
`/opt/drupal/phpunit.xml` に COPY される）。テストは末尾の「開発ルール」に
従ってください。

PHPStan は入っていますが、このプロジェクト専用の PHPStan 設定は見つかっていません。
高度な作業でない限り、PHPStan は任意と考えてください。

## HTTP 確認

ホスト側から nginx 経由で確認します。

```sh
curl -I http://localhost:8080
```

正常な場合に期待できるレスポンス:

```text
HTTP/1.1 200 OK
Server: nginx/1.30.5
X-Generator: Drupal 11 (https://www.drupal.org)
```

drupal コンテナは PHP-FPM だけで HTTP を話さないので、コンテナ内から
`curl http://localhost` しても繋がりません。

## データベース情報

Drupal は MariaDB に次の設定で接続しています。

```text
host: mysql
port: 3306
database / username / password: docker-compose.yml の既定値、または .env の
                                 DB_NAME / DB_USER / DB_PASSWORD
```

これらは `docker-compose.yml` から環境変数で drupal コンテナに渡り、
`docker/drupal/settings.php` が読みます。認証情報の値を説明やファイルに
そのまま貼り付けないでください。

データベース確認には、できるだけ Drush を使ってください。

```sh
docker exec -u www-data workspace-drupal-1 drush sql:query "show tables"
docker exec -it -u www-data workspace-drupal-1 drush sql:cli
```

DB のファイル（named volume `db_data`）を直接触らないでください。

## 既知の警告

次の警告は確認済み（2026-09-27）です。作業を止める理由にはなりません。

1. Drupal の status report に `HTML5 validation: Enabled` の警告が出ます。
   `settings.php` に `enable_html5_validation` が無く、Drupal 12 で既定値が
   FALSE に変わるという予告です。Drupal 12 に上げる時に対応します。

2. Drupal core update status が新しいパッチ版を知らせることがあります。
   ユーザーが依頼しない限り、機能開発の途中で core update をしないでください
   （`composer.lock` と再 build が必要な作業です）。

3. MariaDB が `io_uring` の警告（EPERM）を出し、`libaio` に fallback します。
   `memory.pressure not writable` も出ます。制限のあるコンテナ環境ではよくあります。

4. watchdog に `client error` の警告（JSON:API の 422 Unprocessable Entity、
   401 No authentication credentials provided）と `Deleted user: contrib...` が
   並びます。E2E テスト（`tests/e2e/`）がわざと不正な投稿や未認証アクセスを試し、
   作ったテスト用ユーザーを消した跡です。

5. watchdog に `The "soarm_markdown" plugin does not exist` や `/dashboard` の
   page not found が残っていることがあります。bind mount が空になった時の跡です
   （末尾の「落とし穴」を参照）。今も出ているかは、日時を見て判断してください。

## セキュリティと安全性

これは学習用サンドボックスであり、本番環境ではありません。

それでも次のルールを守ってください。

- `.env` を表示しない。
- API キーを表示しない。
- secret を commit しない。
- ユーザーに依頼されない限り database credentials を変更しない。
- ユーザーがサイトリセットを明示的に望まない限り `mysql/data` を削除しない。
- 破壊的な Docker や Git コマンドは、明確な許可なしに実行しない。
- Drupal core や Composer vendor ファイルを編集しない。

## 完了前の確認チェックリスト

Drupal のコード作業が完了したと言う前に、関連するものを確認してください。

```sh
docker exec -u www-data workspace-drupal-1 drush status
docker exec -u www-data workspace-drupal-1 drush cr
docker exec -u www-data workspace-drupal-1 drush updatedb:status
docker exec -u www-data workspace-drupal-1 drush watchdog:show --count=20
```

カスタムモジュールの場合:

```sh
docker exec -u www-data workspace-drupal-1 drush pm:list --type=module --status=enabled
```

route/page の場合:

```sh
docker exec -u www-data workspace-drupal-1 drush route --path=/<path>
```

`/event-demo` のように、実際の route path を指定してください。

最後の回答には次を含めてください。

- 何を変更したか。
- どう確認したか。
- 次に学生が開くべき CodeSandbox Preview の port、Drupal path、または管理画面の
  path。各学生の sandbox URL は異なるため、固定の sandbox host は書かないで
  ください。

---

# ポータル開発（この repo 用に足した節）

ここから下は原文に無い、ポータル開発用の節です。テストの扱いが上の節と
食い違う場合は、ここの「開発ルール」を優先します。

ユーザーが日本語で書いた場合は、日本語で回答してください。

## プロジェクト概要と完了条件

- 何を作るか: SO-ARM-100 / 101 の知見共有ポータル（`README.md` の冒頭）。
- 要件: `docs/spec/requirements.md`（ユースケース、機能要件、技術スタック）。
- 実装ステップと完了条件: `docs/spec/implementation-steps.md`。末尾の「完了条件」が
  受け入れ基準で、`tests/e2e/` の受け入れテストはこれに対応します。
- 仕様書には Drupal 10 と書かれていますが、この repo は Drupal 11 で作っています。

## カスタムモジュール地図

```text
soarm_core       コンテンツモデルの土台。コンテンツタイプ robot_knowledge、語彙、
                 Markdown フィルタ soarm_markdown（league/commonmark）、ロール
soarm_lerobot    LeRobot 互換データ（HDF5 / Parquet + YAML）の検証と API 出力
                 （/api/soarm/lerobot/{node}）
soarm_search     全文検索と絞り込み一覧 /knowledge（search_api + search_api_db）
soarm_vote       投票（有用性／改善提案／追試）。/api/soarm/vote/{node}
soarm_dashboard  ダッシュボード /dashboard（人気・最新・未解決課題）
soarm_api        JSON:API での読み書き（Basic 認証・ファイルアップロード）
soarm_demo       デモ投稿。アンインストールするとデモ投稿も消える
```

contrib は search_api / search_api_db が有効、facets・flag・s3fs・simple_oauth は
導入済みで未有効です（上の「確認済みの Drupal 状態」）。

## 開発ルール

### 計画と TODO の書き方

- 計画・TODO リスト・報告の項目に、独自の番号や記号（`P0`、`P1-2`、`Phase 1`、`T3` など）を
  振らない。項目は内容を表す名前で呼び、順序は並び順で表す。
- 仕様書などの一次資料が振った番号を引くときは、その資料の見出しや文言を添える。
- 開発計画は `docs/plan.md`。

### TDD（Red → Green → Refactor）

実装コードは Kent Beck / t_wada の TDD で書きます。「急いでいる」「簡単だから」は
例外になりません。

1. 作る振る舞いを TODO リスト（チェックボックス）にし、1 項目ずつ回す。先に整頓する
   ものも、振る舞いとは別の行で同じリストに書く（例: `- [ ] [tidy] ガード節: TrajectoryFormatDetector::detect`）。
2. **Red**: 失敗するテストを 1 つ書き、**実行して**、期待どおりの理由（アサーションの
   失敗）で落ちることを出力で確かめる。クラス未定義や構文エラーで落ちているのは
   Red ではない（まだ何も確かめていない）。
3. **Green**: そのテストを通す最小のコードを書く。Green の間はテストを編集しない。
   テストが間違っていると思ったら、Green を止めて理由を報告する。
   緑になったら、テストと実装を 1 つの `feat` / `fix` としてコミットする。
4. **Refactor**（あとに整頓）: Green をコミットしてから、テストを通したまま、重複を除き、
   名前を直し、DI にする。アサーションは変えない。下の「Tidy First」に従い、
   構造の変更は振る舞いの変更と別のコミットにする。

調査、ドキュメント、設定ファイルの編集は TDD の対象外です。ただし「これは
テストできない」と自分で判断して実装コードを書き始めるのは違反です。

コードの書き方の基準は、上の「Drupal コーディング方針」と各「基本形」の節です。

### Tidy First（構造の変更と振る舞いの変更を分ける）

Kent Beck『Tidy First?』に従います。用語は日本語版に合わせます。

- **構造の変更**: 振る舞いを変えない変更（整頓・リファクタリング）。type は `tidy` / `refactor`。
- **振る舞いの変更**: 機能の追加・変更・バグ修正。type は `feat` / `fix`。

1. 構造の変更と振る舞いの変更を同じコミットに混ぜない。差分に両方が混ざったと
   気づいたら、その時点で止めて分ける（捨てて整頓からやり直してもよい）。
2. 振る舞いの変更は Green になった時点でコミットし、それから構造の変更に移る。
   コミットしていない振る舞いの変更の上で整頓を始めない。
3. コミットするのは全テストが緑の時だけ。Red だけのコミットはしない。構造の変更の
   前後でテストを流す。流す範囲は変更の中身で決める（Kernel はモジュール全体だと
   1 回数分かかるため）。
   - コメント・docblock・空白だけの変更: テストは流さない。変えたファイルごとに、
     コメントと空白を除いた PHP のトークン列が前後で同じことを下のコマンドで確かめる。
     1 トークンでも違えば、次の 2 つのどちらかで扱う。
   - テストファイルだけの変更: 変えたテストファイルを 1 つずつ流す
     （`phpunit -c phpunit.xml web/modules/custom/<module>/tests/src/<Kernel|Unit>/<Name>Test.php`）。
   - src に触る変更: 対象モジュールのテストを流す。
   - どの場合も、push の前に、変えたモジュールのテストを 1 回ずつ流す。

   ```sh
   # 出力が無ければ一致。<path> は repo からの相対パス。コミット前の作業ツリーと HEAD を比べる
   T='foreach (token_get_all(stream_get_contents(STDIN)) as $t) { if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], TRUE)) { continue; } echo is_array($t) ? token_name($t[0]) . " " . $t[1] : $t, "\n"; }'
   diff <(git show HEAD:<path> | docker exec -i workspace-drupal-1 php -r "$T") <(docker exec -i workspace-drupal-1 php -r "$T" < <path>)
   ```
4. 整頓のタイミングは毎回選ぶ（題名の「?」）。
   - **先に整頓**（First）: 次の振る舞いの変更が楽になるか理解が進み、何をどう整頓するか
     分かっている時。次の Red を書く前に行う。
   - **あとに整頓**（After）: TDD の Refactor ステップ。量は直前の振る舞いの変更に
     見合う分まで。
   - **改めて整頓**（Later）: すぐには見返りの無い大きな整頓。TODO リストか報告に書き、
     別の依頼にする。
   - **整頓しない**（Never）: そのコードをもう変えない時。
5. 小さく保つ。1 コミットに整頓は 1 種類。整頓は数分〜1 時間の作業で、振る舞いを
   変えないまま 1 時間を超えたら、必要な分を見失っている。
6. 整頓でテストが赤になり、すぐ直せなければ、その整頓をやめて直前の緑の状態に戻す。
7. 先回りの抽象化・分離を整頓と称してやらない。結合は、実際に起きる変更についてだけ
   問題にする。
8. 元に戻しにくい変更は、振る舞いを変えなくても整頓として扱わない。route の path、
   config・field の machine name、config schema、JSON:API のリソース名・属性、
   `hook_update_N`、DB スキーマ。単独のコミットにして、レビューを厚くする。

Drupal での注意:

- デッドコード: PHP から参照が無く見えても、`*.yml`（services・routing）、`config/`、
  `*.twig`、attribute（`#[Hook]` `#[Block]` など）、手続き型フックの命名から呼ばれる
  ことがある。grep で確かめてから、少しずつ消す。
- 冗長なコメントの削除は、本文中の行コメントだけ。docblock は Drupal のコーディング
  規約で必須なので消さない。
- DI への置き換え、interface・`services.yml`・alias に触る変更は `refactor`。Kernel テストと
  `drush cr` で確かめる（「Drupal 11 の追加の注意」の alias）。
- render array への置き換えで出力（エスケープ・markup・cache metadata）が変わるなら、
  振る舞いの変更。Red から始める。

TDD との関係: 整頓とリファクタリングは新しい振る舞いを書かないので Red は要りません
（「テストなしに本体コードを書かない」には反しません）。条件は、前後で既存テストが
緑であることです。テストの無いコードを整頓する時は、先に今の振る舞いを確かめる
テストを足します（`test`）。

出典:

- Kent Beck『Tidy First?』（O'Reilly, 2023）/ 日本語版『Tidy First? ―個人で実践する経験主義的ソフトウェア設計』: https://www.oreilly.co.jp/books/9784814400911/
- Kent Beck「Structure and Behavior PRs」: https://newsletter.kentbeck.com/p/structure-and-behavior-prs
- Kent Beck「First, After, Later, Never」: https://newsletter.kentbeck.com/p/first-after-later-never
- Kent Beck の AI 用 CLAUDE.md（BPlusTree3）: https://github.com/KentBeck/BPlusTree3/blob/main/rust/docs/CLAUDE.md
- 和田卓人訳「Canon TDD」: https://t-wada.hatenablog.jp/entry/canon-tdd-by-kent-beck

### テスト分類（テストサイズ）

Google のテストサイズに合わせます。

- **テストサイズとテストスコープは別の軸です。**
  - サイズ = 実行時に使ってよい資源。
    - Small: 1 プロセスの中だけ。DB・ファイル・ネットワーク・sleep は使わない。
      外部はテストダブルにする。
    - Medium: 1 台のマシンの中なら、DB・ファイル・localhost 通信・コンテナを使ってよい。
    - Large: 制限なし。共有環境や他のマシンも使える。
  - スコープ = どれだけ広い範囲のコードを確かめるか（単体／結合／E2E）。
- ピラミッドは**サイズで**作ります。Small を増やし、Large はできるだけ減らします。
  目安は Small 70〜80% / Medium 15〜20% / Large 5〜10%（数値より形を守る）。
- **Large かどうかは、使うツールではなく、隔離されているか（hermetic か）で
  決めます。** 実ブラウザでも、1 台の中の使い捨て環境に当てるなら Medium。
  稼働中の共有スタック（状態が残り、他の操作と状態を共有する）に当てるなら Large。

この repo での対応表:

| 実体 | サイズ | 理由 | スコープ | 付ける印 / 実行 |
|---|---|---|---|---|
| `UnitTestCase`（`tests/src/Unit`） | Small | 1 プロセス。DB・ファイル・ネットワーク無し。依存はテストダブル | 単体 | `#[Small]` / `--group small`。ファイルを使うなら Unit にあっても Medium |
| `KernelTestBase`（`tests/src/Kernel`、SQLite） | Medium | SQLite ファイル（DB + ファイル）。1 台の中 | 単体〜結合 | `#[Medium]` / `--group medium` |
| `BrowserTestBase`（`tests/src/Functional`） | Medium | テスト専用に毎回インストールする隔離サイトへ、同じマシン内の nginx 経由で HTTP | 結合〜E2E | `#[Medium]`。まだ 1 本も無く、動くか未確認。初めて必要になった時に基盤を確かめる |
| `WebDriverTestBase` / Nightwatch | 使わない | Drupal core 自身が Nightwatch から Playwright への移行を決めた（#3467492）。JS / GUI は下の Playwright に寄せる | — | — |
| `tests/e2e/`（pytest + requests） | Large | 稼働中の compose スタックに当てる。DB に投稿が残り、手動操作と状態を共有する（non-hermetic） | E2E | 置き場所で区別 / `python3 -m pytest -q` |
| `tests/e2e/gui/`（pytest-playwright。未導入） | Large | 同上 + 実ブラウザ | E2E（GUI・JS・a11y） | 導入時に marker `large` と `gui` を `pytest.ini` に登録 |

テストを書く場所は、毎回この順で判断します。

1. **Small で書けないか。** ロジックを `\Drupal::` や DB に触れない普通の PHP クラスに
   切り出せば、Unit で書けます。まずこれを考えます。
2. Drupal の仕組みとの配線（entity・config schema・hook・service・access・
   Views / Search API の設定）を確かめる必要がある時だけ **Kernel**。
3. ルーティング・フォーム送信・描画結果・権限を HTTP 越しに確かめる必要がある時
   だけ **Functional**。
4. **Large** は、受け入れ条件（`docs/spec/implementation-steps.md` の完了条件）と、
   Small / Medium では確かめられないもの（nginx の配信、実スタックの JSON:API、
   ファイル配信、JS の動作、React / Vue、キーボード操作や WCAG の自動チェック）
   だけ。double-loop の外側ループとして最初に 1 本書き、内側を Small / Medium で回す。

Small 以外を選んだ時は、その理由を報告に書きます。

Playwright（GUI の Large テスト）:

- Python 版の `pytest-playwright` を使い、既存の `tests/e2e/conftest.py`（管理者
  ログイン・サンプルファイル）と同じ pytest で動かします。Drupal core の Playwright 化
  （#3553673）は 2026-09-15 時点で未マージなので、core の仕組みには乗りません。
- **まだ導入していません。** 最初に必要になる機能（サブテーマ + WCAG 2.1 AA の
  axe 自動チェック、または React / Vue）で、失敗するテストと一緒に導入します。
  その時に `tests/requirements.txt` に `pytest-playwright`（+ axe 用ライブラリ）、
  `pytest.ini` に marker、`.gitignore` に `test-results/` と `playwright-report/` を
  足します。
- GUI テストは見た目の変更で壊れやすいので、要素は role / label で探し、
  1 機能 1〜2 本に抑えます。

サイズの強制:

- すべての PHPUnit テストのクラスに `#[Small]` か `#[Medium]`
  （`PHPUnit\Framework\Attributes`）を付けます。PHPUnit はこれを `small` /
  `medium` グループとして扱うので、`--group small` で選んで実行できます。
  PHPUnit のテストに `#[Large]` は付けません（Large は `tests/e2e/` だけ。上の表）。
- サイズは置き場所ではなく、実行時に使う資源で決めます。`tests/src/Unit` にあっても
  一時ファイルを書くテストは Medium です（例: soarm_lerobot の `TrajectoryFormatDetectorTest`）。
- CI（`.github/workflows/ci.yml`）の「Every test is Small or Medium」が、印の無い
  テストと `#[Large]` のテストを見つけると落ちます。見るのは `phpunit.xml` の testsuite
  （各モジュールの `tests/src/Unit`・`Kernel`・`Functional`）にあるテストだけです。印が合っているか（Small なのに
  ファイルを使っていないか）までは確かめないので、レビューで見ます。
- コンテナに `pcntl` 拡張が無いので、サイズ別の時間制限（`--enforce-time-limit`）は
  今は効きません。有効にするには Dockerfile の変更と再 build が要ります（別タスク）。
- Kernel / E2E に偏ったテストを Small へ押し下げる是正は別タスクです（`docs/plan.md`）。
  2026-09-27 の実行件数で Small 23 / Medium 102 / Large（E2E）50
  （13% / 58% / 29%）。

出典:

- Google Testing Blog「Test Sizes」(2010): https://testing.googleblog.com/2010/12/test-sizes.html
- Software Engineering at Google 11 章 Testing Overview（サイズとスコープ、80/15/5）: https://abseil.io/resources/swe-book/html/ch11.html
- 同 14 章 Larger Testing（Large = 遅い・non-hermetic・非決定的）: https://abseil.io/resources/swe-book/html/ch14.html
- Google Testing Blog「Just Say No to More End-to-End Tests」(70/20/10): https://testing.googleblog.com/2015/04/just-say-no-to-more-end-to-end-tests.html
- 和田卓人「テストサイズ」: https://gihyo.jp/dev/serial/01/savanna-letter/0003
- Drupal「Types of tests」: https://www.drupal.org/docs/develop/automated-testing/types-of-tests
- Drupal core「Replace Nightwatch with Playwright」#3467492 / 移行作業 #3553673
- PHPUnit 11.5 の属性: https://docs.phpunit.de/en/11.5/attributes.html
- Playwright for Python: https://playwright.dev/python/docs/intro

### テストの実行

```sh
# Small
docker exec -u www-data -w /opt/drupal workspace-drupal-1 vendor/bin/phpunit -c phpunit.xml --group small

# Medium 全部
docker exec -u www-data -w /opt/drupal workspace-drupal-1 vendor/bin/phpunit -c phpunit.xml --group medium

# 1 モジュールだけ（Small も Medium も、フォルダで選ぶ）。SQLite のパスはモジュールごとに分ける
docker exec -u www-data -e SIMPLETEST_DB=sqlite://localhost//tmp/<module>.sqlite -w /opt/drupal workspace-drupal-1 vendor/bin/phpunit -c phpunit.xml web/modules/custom/<module>

# CI の Medium のジョブと同じ選び方（testsuite + 名前空間）
docker exec -u www-data -e SIMPLETEST_DB=sqlite://localhost//tmp/<module>.sqlite -w /opt/drupal workspace-drupal-1 vendor/bin/phpunit -c phpunit.xml --group medium --filter '^Drupal\\Tests\\<module>\\'

# Large（E2E）。ホスト側から稼働中のサイトに当てる
python3 -m pip install -r tests/requirements.txt
python3 -m pytest -q

# コーディング規約
docker exec -w /opt/drupal workspace-drupal-1 vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/custom web/themes/custom
```

CI（GitHub Actions、`.github/workflows/ci.yml`）は、main への push と PR のたびに、
`Dockerfile` の `app` ステージのイメージを 1 回 build し、そのイメージの中で
サイズの検査・Small・phpcs のジョブと、モジュールごとの Medium のジョブを並列に回します。
Large（E2E・GUI）は稼働中のスタックが要るので CI では回しません。
Functional（`BrowserTestBase`）は nginx が要るので、最初の Functional テストを足す時に
compose を使うジョブも足します（`docs/plan.md`）。

### agent の使い分け

`.claude/agents/` に 2 つ定義しています。

- `tdd-developer`: 実装の担当。1 回の依頼で 1 つの振る舞いを、Red → Green → Refactor の
  全サイクルで作る。Green だけの実装係ではない。整頓だけの依頼も受ける。
  段階ごとにローカルでコミットする（`git add <パス>` と `git commit` だけ。push などはしない）。
- `drupal-reviewer`: 読み取り専用のレビュー担当。tdd-developer の成果を独立に検証する
  （テスト先行の痕跡、テストサイズの違反、構造と振る舞いの混在、Drupal の作法と
  セキュリティ）。コードは書かない。
- メインのセッション: 振る舞いの分解と依頼（先に整頓・あとに整頓・改めて整頓・整頓しない
  の判断を含む）、レビュー結果の判断、稼働サイトへの反映（`pm:enable`・`cr`・E2E）、
  push、PR の作成とマージ前の説明文の見直し（下の「プルリクエスト」）。

### コミットメッセージ

Conventional Commits の形式で、**英語**で書きます（件名も本文も）。

```text
<type>(<scope>): <summary>

<body: 何をなぜ変えたか。72 桁で折り返す。自明な変更なら省略可>
```

- **type**: `feat` | `fix` | `test` | `refactor` | `tidy` | `docs` | `build` | `ci` | `chore`
  - 構造の変更は `tidy` / `refactor`、振る舞いの変更は `feat` / `fix`。同じコミットに
    混ぜない（上の「Tidy First」）。
  - `tidy` = 整頓。『Tidy First?』の整頓（ガード節、デッドコード、説明変数、ヘルパーを
    抽出する など）を、1 つのクラスとそのテストの中で、公開面（public メソッド・
    `services.yml`・route・plugin ID・config）を変えずに 1 種類だけ行うもの。
    summary に整頓の名前が分かる動詞句を書く。phpcbf の自動修正・docblock・命名の修正もここ。
    例外として、docblock の追加・修正、phpcs が指摘する機械的な整形、テストメソッドの改名は、
    1 つのモジュールの中なら複数のクラスにまたがっても `tidy`（種類は 1 コミット 1 つのまま）。
    テストメソッドの名前は公開面に含めない。
  - `refactor` = それ以外の構造の変更。複数のクラスやモジュールにまたがるもの、DI・
    interface・`services.yml`・alias に触るもの。
  - phpcs の修正でも、意味が変わるもの（`==` → `===`、文字列の `t()` 化など）は
    `fix` か `refactor`。
  - `feat` / `fix` = Red と Green を 1 つにまとめたコミット。Red だけのコミットはしない。
  - `test` = 最初から緑のテストの追加・調整（整頓の前に今の振る舞いを確かめるテスト、
    `#[Small]` / `#[Medium]` の後付けなど）。
  - `build` = `Dockerfile`・`docker-compose.yml`・`docker/`・`composer.json` / `composer.lock`。
- **scope**: モジュール名から `soarm_` を外したもの（`core` `lerobot` `search` `vote`
  `dashboard` `api` `demo`）。モジュール以外は `docker` `e2e` `agents` `spec`。
  複数の場所にまたがる時や repo 全体の時は省略する。
- **summary**: 命令形（`add`。`added` / `adds` ではない）、小文字で始める、末尾にピリオドを
  付けない、72 字以内。
- 1 コミット 1 論理変更。
- Claude Code が付けるトレーラ（`Co-Authored-By:` など）は消さない。

例:

```text
feat: add SO-ARM knowledge portal on Drupal 11 and Docker
feat(vote): reject duplicate replication votes
tidy(lerobot): use guard clauses in TrajectoryFormatDetector::detect
tidy(dashboard): explain the published status with NodeInterface::PUBLISHED
refactor(demo): drop the users_data guard and the database injection
test(vote): mark VoteManagerTest as Medium
build(docker): add pcntl for PHPUnit time limits
docs: record commit message rules in CLAUDE.md
```

### プルリクエスト

- 説明文は**日本語**で、`.github/pull_request_template.md` の見出しに沿って書く。
  タイトルは上のコミットメッセージと同じ規約（英語・Conventional Commits）。
- `gh pr create` は `--body` / `--body-file` を付けるとテンプレートを使わない。
  テンプレートを写したファイルに本文を書き、`--body-file` で渡す。
- 1 行目のタイトルだけで何をするかが分かるようにする。本文には何を・なぜを書き、
  ほかの案を選ばなかった理由と既知の制限も書く。リンク先が読めなくても分かるようにする。
- 構造の変更と振る舞いの変更を同じ PR に混ぜない（上の「Tidy First」）。
- 元に戻しにくい変更（画面・API・データモデル）は、テンプレートの該当欄で明示する。
- マージの前に、説明文が最終的な変更と合っているか見直す。

出典:

- GitHub Docs「Creating a pull request template for your repository」: https://docs.github.com/en/communities/using-templates-to-encourage-useful-issues-and-pull-requests/creating-a-pull-request-template-for-your-repository
- Google Engineering Practices「Writing good CL descriptions」: https://google.github.io/eng-practices/review/developer/cl-descriptions.html
- Drupal「Issue summary template -- bare」（Problem/Motivation・Proposed resolution・Remaining tasks・User interface changes・API changes・Data model changes）: https://www.drupal.org/docs/develop/issues/fields-and-other-parts-of-an-issue/special-issue-summary-templates/issue-summary-template-bare
- Kent Beck「Structure and Behavior PRs」: https://newsletter.kentbeck.com/p/structure-and-behavior-prs

## Drupal 11 の追加の注意

上の「Drupal コーディング方針」に足す注意です。

- OOP Hook（`src/Hook/*Hooks.php` の `#[Hook]`）のクラスは autowire されます。
  コンストラクタで受け取る独自サービスには、`<module>.services.yml` に FQCN の
  alias が必須です（例: `Drupal\soarm_vote\VoteManagerInterface: '@soarm_vote.manager'`）。
  無いと `drush cr` でサイト全体が 500 になります。

## 落とし穴

- ホストを再起動した後、drupal コンテナの bind mount が空（root 所有の空
  ディレクトリ）になることがあります。soarm モジュールが見えなくなり、500 や 404
  になります（watchdog に `soarm_markdown` plugin が無いというエラー）。
  `docker compose up -d --force-recreate drupal` で直ります。nginx だけ Exited の
  まま残ることもあるので、その時は `docker compose up -d`。
- compose の `name: workspace` は固定です。同じマシンで別の `name: workspace` の
  compose を `up` すると同じコンテナ名を取り合い、コンテナがそちらの mount で
  作り直されます。どのディレクトリの mount で動いているかは次で確かめます。

  ```sh
  docker inspect workspace-drupal-1 --format '{{ index .Config.Labels "com.docker.compose.project.working_dir" }}'
  ```

- `phpunit.xml` と `docker/drupal/settings.php` はイメージに COPY されています。
  編集したら、再 build（`docker compose up -d --build`）するまで反映されません。
- Kernel テストの SQLite パスはモジュールごとに分けます（`/tmp/<module>.sqlite`）。
  既定の `/tmp/soarm-test.sqlite` を複数の実行が同時に使うと壊れます。
- E2E（`tests/e2e/`）は、稼働中のサイトの DB に投稿やユーザーを作って消します。

## PUBLIC repo の注意

この repo は GitHub で公開しています。

- 秘密（`.env`、パスワード、トークン、鍵）、DB ダンプ、個人のパス（ホーム
  ディレクトリなど）をコミットしない。
- コミット前に `git diff --cached` で中身を確かめる。
- 認証情報は `docker-compose.yml` の既定値（ローカル専用）か `.env` に置き、
  コードや文書には値を書かない。

## 残スコープ

残タスク・順序・仕様からの逸脱は `docs/plan.md`（開発計画）にまとめています。
順序が未承認の項目は、着手する前にユーザーに確かめてください。
