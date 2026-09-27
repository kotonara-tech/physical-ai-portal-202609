> 出典: 旧 repo コミット d41322c『CLAUDE.md created by Mike Richardson』。原文のまま保存。

# CLAUDE.md

このリポジトリで Claude Code が作業するときのガイドです。

この環境は、CodeSandbox 上で Drupal を学ぶためのワークショップ用環境です。
利用者は Drupal が初めてで、Web 開発、コードエディタ、ターミナル、HTML、
CSS、PHP、YAML、データベースにも慣れていない可能性があります。

説明は具体的にしてください。変更は小さく保ち、Drush で確認し、ファイルパス、
Drupal 管理画面のパス、Preview のポート、次に開く場所をやさしい日本語で
説明してください。

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

## 学びを最優先にする

これはワークショップです。ゴールは「動くサイト」だけではなく、学生が Drupal と
Web 開発を少しでも理解して帰ることです。学生の代わりに全部作って終わり、には
しないでください。次の流れを意識してください。

- **作る前**: これから何をするのかを 1〜2 文で予告する。
- **作った後**: なぜそれで動くのかを、初心者に分かる言葉で短く説明する。
- **次の一歩**: 学生が自分で試せる小さな変更を 1 つ提案する。例:「この見出しの
  文字を変えてみましょう」「この block をもう 1 か所に置いてみましょう」。
- コードや file を一度に大量に出力しない。1 つずつ、その file が何のための
  ものかを言いながら進める。
- 時間がかかる、または元に戻しにくいコマンドは、実行する前に何をするのかを
  一言添える。
- 学生が間違えても否定しない。エラーは学習の当たり前の一部として扱い、原因と
  直し方をやさしく説明する。詳しくは「サイトが壊れたときの直し方」を参照。
- 「なぜ?」には正直に、しかし初心者に分かる言葉で答える。自分が確信を持てない
  ことは、決めつけずに確認してから答える。

## 環境の概要

ワークスペースのルート:

```sh
/project/workspace
```

Drupal はホスト上で直接動いているのではなく、Docker コンテナ内で動いています。

重要なコンテナ:

```text
workspace-drupal-1   Drupal + Apache + PHP
workspace-mysql-1    MariaDB
```

`docker-compose.yml` 上のサービス名:

```text
drupal
mysql
```

Drupal サイトはホスト側のポート `8080` に公開されています。

```text
container port 80 -> host port 8080
```

CodeSandbox では、Preview パネルでポート `8080` を開きます。
各学生の CodeSandbox URL は異なります。特定の sandbox host を固定で書かないで
ください。

URL の形式:

```text
https://<sandbox-host>-8080.csb.app
```

Drush が `http://default` で始まる URL を出した場合、そのホスト部分を学生自身の
CodeSandbox Preview のホストに置き換えるよう説明してください。

## 確認済みのバージョン

実行中のコンテナで確認したバージョン:

```text
Drupal       11.4.0
PHP          8.5.7
Drush        13.7.4.0
Composer     2.10.1
MariaDB      12.3.2
PHPUnit      11.5.55
PHPCS        3.13.5
PHPStan      2.2.4
```

Drupal のインストールプロファイル:

```text
standard
```

サイト名:

```text
Drupal App
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
password: admin
uid: 1
```

ただし、パスワード入力を案内するよりも、ワンタイムログイン URL を作るほうを
優先してください。

```sh
docker exec workspace-drupal-1 drush uli --uid=1
```

生成された URL が `http://default/...` で始まる場合は、`http://default` を
学生自身の CodeSandbox Preview URL に置き換える必要があります。

## コマンド実行のルール

ホスト側で PHP、Composer、PHPUnit、PHPCS、Drush を直接実行しないでください。
ホスト環境には PHP や Composer が PATH 上にありません。

Drupal 関連のコマンドは `workspace-drupal-1` コンテナの中で実行します。

よく使うコマンド:

```sh
docker compose ps
docker logs --tail 80 workspace-drupal-1
docker logs --tail 80 workspace-mysql-1
docker exec workspace-drupal-1 drush status
docker exec workspace-drupal-1 drush cr
docker exec workspace-drupal-1 drush uli --uid=1
docker exec workspace-drupal-1 drush watchdog:show --count=20
docker exec workspace-drupal-1 drush core:requirements
docker exec workspace-drupal-1 drush updatedb:status
docker exec workspace-drupal-1 drush pm:list
docker exec workspace-drupal-1 drush pm:list --type=module --status=enabled
docker exec workspace-drupal-1 drush pm:list --type=theme
docker exec workspace-drupal-1 drush config:status
```

Composer は必ず `/opt/drupal` から実行してください。

```sh
docker exec -w /opt/drupal workspace-drupal-1 composer show --direct
docker exec -w /opt/drupal workspace-drupal-1 composer require drupal/example_module
```

Composer コマンドが `/workspace/var/www/html` で失敗した場合は、次の形で実行し直して
ください。

```sh
docker exec -w /opt/drupal workspace-drupal-1 composer ...
```

コマンドが `command not found` やエラーを返した場合、それを学生の操作ミスとして
扱わないでください。まず `docker exec workspace-drupal-1 drush list` などで正しい
コマンド名やオプションを確認し、直してから実行し直してください。生の stack trace
をそのまま貼り付けて学生を不安にさせないでください。何が起きたのかを一言で
やさしく伝えてください。

## 重要なパス

ホスト側のワークスペース:

```text
/project/workspace
```

コンテナ内の Drupal プロジェクトルート:

```text
/opt/drupal
```

コンテナ内の Drupal web root:

```text
/opt/drupal/web
```

Apache の document root:

```text
/var/www/html -> /opt/drupal/web
```

コンテナのデフォルト作業ディレクトリ:

```text
/workspace/var/www/html
```

デフォルト作業ディレクトリが `/opt/drupal` だと思い込まないでください。

## bind mount

このリポジトリでは Drupal プロジェクト全体ではなく、一部のディレクトリだけが
ホスト側に bind mount されています。

ホストからコンテナへの mount:

```text
./web/themes      -> /var/www/html/themes
./web/modules     -> /var/www/html/modules
./web/sites       -> /var/www/html/sites
./config          -> /opt/drupal/config
./composer.json   -> /opt/drupal/composer.json
./composer.lock   -> /opt/drupal/composer.lock
```

MariaDB の永続化:

```text
./mysql/data      -> /var/lib/mysql
./mysql/dump      -> /docker-entrypoint-initdb.d
```

Drupal core と vendor の多くはコンテナイメージ内にあり、ホスト側の通常ファイル
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

ワークショップで作るコードを直接置かない場所:

```text
web/core
vendor
mysql/data
web/modules/contrib
web/themes/contrib
```

ユーザーが環境設定を明示的に依頼した場合を除き、
`web/sites/default/settings.php` は編集しないでください。

`.env`、API キー、データベースダンプ、その他の秘密情報を表示したり説明に
貼り付けたりしないでください。

## 確認済みの Drupal 状態

有効化されている主な core module:

```text
block
block_content
ckeditor5
config
contextual
dblog
field
field_ui
file
filter
image
layout_builder
layout_discovery
link
menu_ui
mysql
navigation
node
path
taxonomy
text
user
views
views_ui
```

有効化されているテーマ:

```text
olivero
claro
```

現在、contrib module はインストールされていません。

`Article` や `Basic Page` のようなデフォルトのコンテンツタイプが存在すると
決めつけないでください。特定の bundle に依存する作業をする前に確認してください。

```sh
docker exec workspace-drupal-1 drush config:get node.type.article
docker exec workspace-drupal-1 drush config:get node.type.page
```

これらのコマンドが失敗した場合は、管理画面でコンテンツタイプを作るか、
必要な設定を明示的に作ってください。

## 設定管理の注意

`drush status` で確認した現在の config sync directory:

```text
sites/default/files/config_GGsd-ij17TGpByxByVkzhkrMz-0Tai4jBFTG83cBAEpdWI3acvxr4EwRXfyFHBk-R23uXSUkCw/sync
```

このリポジトリには次の mount もあります。

```text
./config -> /opt/drupal/config
```

ただし、Drupal は現在 `/opt/drupal/config` を active sync directory として使って
いません。`drush config:export` が root の `config/` ディレクトリに書き出すと
思い込まないでください。

設定を import/export する前に確認してください。

```sh
docker exec workspace-drupal-1 drush status
docker exec workspace-drupal-1 drush config:status
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
docker exec workspace-drupal-1 drush status
docker exec workspace-drupal-1 drush cr
docker exec workspace-drupal-1 drush watchdog:show --count=20
```

新しいカスタムモジュールを有効化する場合:

```sh
docker exec workspace-drupal-1 drush pm:enable <module_name> -y
docker exec workspace-drupal-1 drush cr
docker exec workspace-drupal-1 drush pm:list --type=module --status=enabled
```

database update を追加または変更した場合:

```sh
docker exec workspace-drupal-1 drush updatedb:status
docker exec workspace-drupal-1 drush updatedb -y
docker exec workspace-drupal-1 drush cr
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
docker exec workspace-drupal-1 drush watchdog:show --count=20
docker logs --tail 80 workspace-drupal-1
docker exec workspace-drupal-1 drush status
```

原因はたいてい、直前に作った custom module や theme の小さな間違いです。例:
`.info.yml` の書き間違い、PHP の syntax error、class 名や namespace の不一致。
エラーメッセージに出ている file 名と行番号が手がかりになります。

直し方の基本:

1. 直前に変更した file を開いて直す。custom の file はホスト側に bind mount
   されているので、直接編集できます。
2. cache をクリアする。

```sh
docker exec workspace-drupal-1 drush cr
```

module が原因で、直すより一度止めたい場合:

```sh
docker exec workspace-drupal-1 drush pm:uninstall <module_name> -y
docker exec workspace-drupal-1 drush cr
```

PHP の致命的なエラーで `drush` コマンド自体が動かないことがあります。このときは
Drupal が起動できていないので、`drush pm:uninstall` も失敗します。原因の file を
直すか、その module のフォルダを `web/modules/custom/` から一時的に外へ移動
(またはリネーム)してから、cache をクリアしてください。

```sh
docker exec workspace-drupal-1 drush cr
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
docker exec workspace-drupal-1 drush pm:enable event_demo -y
docker exec workspace-drupal-1 drush cr
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
docker exec workspace-drupal-1 drush cr
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
docker exec workspace-drupal-1 drush cr
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
docker exec workspace-drupal-1 drush cr
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
docker exec workspace-drupal-1 drush config:get node.type.<machine_name>
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
docker exec workspace-drupal-1 drush theme:enable <theme_name>
docker exec workspace-drupal-1 drush config:set system.theme default <theme_name> -y
docker exec workspace-drupal-1 drush cr
```

ユーザーが明示的に希望しない限り、管理画面テーマは Claro のままにしてください。

## Drush Code Generator

Drush には Drupal Code Generator 4.2.0 が入っています。

generator 一覧:

```sh
docker exec workspace-drupal-1 drush generate --dry-run
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

PHPUnit は入っていますが、プロジェクトルートにプロジェクト用の `phpunit.xml` は
見つかっていません。小さなワークショップ例のために大きなテスト基盤を追加しないで
ください。ユーザーがテストを書きたい場合は別です。

PHPStan は入っていますが、このプロジェクト専用の PHPStan 設定は見つかっていません。
高度な作業でない限り、PHPStan は任意と考えてください。

## HTTP 確認

Drupal コンテナ内から確認する場合:

```sh
docker exec workspace-drupal-1 curl -I http://localhost
```

正常な場合に期待できるレスポンス:

```text
HTTP/1.1 200 OK
X-Generator: Drupal 11
```

ホスト側 shell から `http://localhost:8080` に直接アクセスできないことがあります。
ユーザーには CodeSandbox の Preview パネルを案内してください。

## データベース情報

Drupal は MariaDB に次の設定で接続しています。

```text
host: mysql
port: 3306
database: drupal
username: root
password: password
```

これらは `docker-compose.yml` と `settings.php` に由来します。この認証情報を
学生向けの説明にそのまま貼り付けないでください。

データベース確認には、できるだけ Drush を使ってください。

```sh
docker exec workspace-drupal-1 drush sql:connect
docker exec workspace-drupal-1 drush sql:query "show tables"
```

`mysql/data` 以下のファイルを手動で編集しないでください。

## 既知の警告

次の警告は確認済みです。基本的なワークショップ作業を止める理由にはなりません。

1. Drupal の status report で `Trusted Host Settings` が有効でないと表示されます。
   local/sandbox 環境ではよくあります。セキュリティ強化がテーマでない限り、
   主作業にしないでください。

2. Drupal core update status が、現在は Drupal `11.4.0` なのに `11.4.1 available`
   と表示することがあります。ファシリテーターが依頼しない限り、初心者向け
   ワークショップ中に core update をしないでください。

3. Apache log に次の警告が出ることがあります。

   ```text
   Could not reliably determine the server's fully qualified domain name
   ```

   ワークショップでは致命的ではありません。

4. MariaDB が `io_uring` の警告を出し、`libaio` に fallback することがあります。
   制限のあるコンテナ環境ではよくあります。

5. Drupal watchdog に、Drupal PHP storage からの `mkdir(): Permission Denied` が
   出ていました。生成ファイル、Twig cache、CSS/JS aggregation、upload が失敗する
   場合は確認してください。

   ```sh
   docker exec workspace-drupal-1 drush watchdog:show --count=20
   docker exec workspace-drupal-1 ls -la /opt/drupal/web/sites/default/files
   ```

   実際に file permission が作業を止めている場合のローカル修正:

   ```sh
   docker exec workspace-drupal-1 chown -R www-data:www-data /opt/drupal/web/sites/default/files
   docker exec workspace-drupal-1 drush cr
   ```

   この修正は、実際に permission 問題がブロックしているときだけ実行してください。

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

## 初心者への説明スタイル

学生を助けるときは、Drupal や Web 開発の前提知識がないものとして対応して
ください。file path、route、module、block、cache、content type、field、
terminal command、YAML file、PHP class、browser preview などの意味を知っていると
仮定しないでください。

- 正確な file path を書く。
- 正確な Drush command を書く。
- Claude がコマンドを実行するのか、学生が画面でクリックする必要があるのかを
  明確にする。
- cache rebuild が必要なときはそう伝え、Drupal は速度のために code や config を
  cache する、と短く説明する。
- Drupal 管理画面でクリックする場所がある場合は、`/admin/structure/types` のような
  パスで示す。
- Drupal 用語を短く説明する。例: route、block、content type、field、theme、
  cache、module。
- 多くの選択肢を並べるより、まず 1 つ動く例を作る。
- UI でやるほうが簡単な作業なら、そう伝える。
- 学習のために code でやるほうがよい作業なら、理由を説明する。
- 説明は短く。ただし、初めての Web 開発者が「何が変わったか」を理解できるだけの
  文脈は入れる。
- "entity"、"bundle"、"render array"、"service container"、
  "dependency injection"、"cache metadata" のような専門用語は、説明なしに使わない。
  必要な場合は 1 文で定義してから使う。
- 学生が自分のアイデアを持っている場合、そのアイデアを尊重する。一般的なデモに
  置き換えず、そのアイデアを最初の小さな Drupal ステップに分ける。
- 依頼が広すぎる場合は、確認質問は最大 1 つにするか、ユーザーのアイデアに直接
  つながる小さな最初の実装単位を提案する。
- 画面に出る文字(ページタイトル、ボタン、メッセージなど)は日本語にしてよい。
  学生が自分の言葉で結果を画面で見られると、コードと表示のつながりが実感
  できる。
- 小さくても動いたら、それを一緒に喜ぶ。成功体験が次に進む自信につながる。

ユーザーが日本語で書いた場合は、日本語で回答してください。

## すすめたいプロンプトの形

以下は、明確に依頼するための例です。作るものの提案やカリキュラムではありません。

明確な例:

```text
Drupal サイトで自分のアイデアを作りたいですが、Drupal も Web 開発も初めてです。
最初の小さな一歩を一緒に決めて、何をしているのか説明してください。
```

明確な例:

```text
event_demo という Drupal 11 のカスタムモジュールを作り、/event-demo にページを
追加してください。web/modules/custom/event_demo に置き、Drush で有効化し、
キャッシュをクリアして、どう開けばよいか教えてください。
```

明確な例:

```text
event_demo モジュールに、今日の日付を表示するカスタム block plugin を追加して
ください。キャッシュをクリアし、Drupal 管理画面でどこから配置するか教えて
ください。
```

明確な例:

```text
変更を始める前に、Drush で Drupal サイトを確認し、有効な modules と themes を
教えてください。
```

広いけれど有効な例:

```text
Drupal サイトを作りたいです。
```

依頼が広い場合でも拒否しないでください。学生が最初の小さな機能を選べるように
助けてください。例: 「訪問者が読めるページ」、「送信できるフォーム」、
「公開したいもののためのコンテンツタイプ」。

## 完了前の確認チェックリスト

Drupal のコード作業が完了したと言う前に、関連するものを確認してください。

```sh
docker exec workspace-drupal-1 drush status
docker exec workspace-drupal-1 drush cr
docker exec workspace-drupal-1 drush updatedb:status
docker exec workspace-drupal-1 drush watchdog:show --count=20
```

カスタムモジュールの場合:

```sh
docker exec workspace-drupal-1 drush pm:list --type=module --status=enabled
```

route/page の場合:

```sh
docker exec workspace-drupal-1 drush route --path=/<path>
```

`/event-demo` のように、実際の route path を指定してください。

最後の回答には次を含めてください。

- 何を変更したか。
- どう確認したか。
- 次に学生が開くべき CodeSandbox Preview の port、Drupal path、または管理画面の
  path。各学生の sandbox URL は異なるため、固定の sandbox host は書かないで
  ください。
