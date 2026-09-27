<?php

/**
 * @file
 * SO-ARM Portal の Drupal 設定.
 *
 * 値は docker-compose.yml の環境変数から読みます。秘密の値をこのファイルに
 * 直接書かないでください。
 */

$databases['default']['default'] = [
  'driver' => 'mysql',
  'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
  'autoload' => 'core/modules/mysql/src/Driver/Database/mysql/',
  'host' => getenv('DRUPAL_DB_HOST') ?: 'mysql',
  'port' => getenv('DRUPAL_DB_PORT') ?: '3306',
  'database' => getenv('DRUPAL_DB_NAME') ?: 'drupal',
  'username' => getenv('DRUPAL_DB_USER') ?: 'drupal',
  'password' => getenv('DRUPAL_DB_PASSWORD') ?: '',
  'prefix' => '',
  'collation' => 'utf8mb4_general_ci',
  'isolation_level' => 'READ COMMITTED',
];

// hash_salt はログイン Cookie などの署名に使う秘密の値。
// 環境変数が無ければ、entrypoint が private ボリュームに作ったファイルを読む。
$soarm_hash_salt_file = '/opt/drupal/private/hash_salt.txt';
$settings['hash_salt'] = getenv('DRUPAL_HASH_SALT')
  ?: (is_readable($soarm_hash_salt_file) ? trim((string) file_get_contents($soarm_hash_salt_file)) : '');

$settings['config_sync_directory'] = '../config/sync';
$settings['file_private_path'] = '/opt/drupal/private';
$settings['update_free_access'] = FALSE;
$settings['container_yamls'][] = $app_root . '/' . $site_path . '/services.yml';
$settings['file_scan_ignore_directories'] = ['node_modules', 'bower_components'];
$settings['entity_update_batch_size'] = 50;
$settings['entity_update_backup'] = TRUE;
$settings['state_cache'] = TRUE;
$settings['migrate_node_migrate_type_classic'] = FALSE;

// このサイトとして受け付けるホスト名（カンマ区切りの正規表現）。
$soarm_trusted_hosts = getenv('DRUPAL_TRUSTED_HOSTS') ?: '^localhost$,^127\.0\.0\.1$,^nginx$';
$settings['trusted_host_patterns'] = array_values(array_filter(array_map('trim', explode(',', $soarm_trusted_hosts))));

// 個人用の上書き（任意・git では追跡しない）。
if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
  include $app_root . '/' . $site_path . '/settings.local.php';
}
