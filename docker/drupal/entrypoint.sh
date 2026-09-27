#!/usr/bin/env bash
# SO-ARM Portal — コンテナ起動時の準備
#
#   1. 書き込み用ディレクトリと hash_salt を用意する
#   2. データベースを待つ
#   3. Drupal が未インストールならインストールし、soarm_* モジュールを有効化する
#   4. 準備完了のしるし（/tmp/soarm-ready）を置いて php-fpm を起動する
#
# drush は必ず www-data で動かします。root で動かすと、Web サーバーが
# 書き込めない root 所有のファイルができてしまうためです。
set -euo pipefail

READY_MARKER=/tmp/soarm-ready
DRUPAL_ROOT=/opt/drupal
PRIVATE_DIR="${DRUPAL_ROOT}/private"
FILES_DIR="${DRUPAL_ROOT}/web/sites/default/files"

log() { echo "[soarm-entrypoint] $*"; }
as_www() { runuser -u www-data -- "$@"; }
drush() { as_www "${DRUPAL_ROOT}/vendor/bin/drush" --root="${DRUPAL_ROOT}" "$@"; }

# php-fpm 以外（例: docker compose run drupal bash）はそのまま実行する
if [ "${1:-}" != "php-fpm" ]; then
  exec "$@"
fi

rm -f "${READY_MARKER}"

# --- 1. ディレクトリと hash_salt --------------------------------------------
mkdir -p "${PRIVATE_DIR}" "${FILES_DIR}" "${DRUPAL_ROOT}/config/sync"
chown www-data:www-data "${PRIVATE_DIR}" "${FILES_DIR}"
# bind mount された config はホスト側の所有者のままにする（書けない場合だけ警告）
if ! as_www test -w "${DRUPAL_ROOT}/config/sync"; then
  log "warning: config/sync is not writable by www-data (drush config:export will fail)"
fi

if [ -z "${DRUPAL_HASH_SALT:-}" ] && [ ! -s "${PRIVATE_DIR}/hash_salt.txt" ]; then
  log "generating hash salt"
  as_www sh -c "umask 077; php -r 'echo bin2hex(random_bytes(32));' > '${PRIVATE_DIR}/hash_salt.txt'"
fi

# --- 2. データベースを待つ ----------------------------------------------------
log "waiting for database"
db_ready=0
for _ in $(seq 1 60); do
  if drush sql:query 'SELECT 1' >/dev/null 2>&1; then
    db_ready=1
    break
  fi
  sleep 2
done
if [ "${db_ready}" != "1" ]; then
  log "error: database did not become reachable"
  exit 1
fi

# --- 3. インストール ----------------------------------------------------------
# 「インストール済みか」はテーブルの有無で判断する。Drupal の起動可否で判断すると、
# custom コードのエラーで起動できないだけのサイトを再インストールで消してしまう。
if [ -z "$(drush sql:query "SHOW TABLES LIKE 'key_value'" 2>/dev/null)" ]; then
  log "installing Drupal (standard profile)"
  drush site:install standard \
    --site-name="${DRUPAL_SITE_NAME:-SO-ARM Knowledge Portal}" \
    --account-name="${DRUPAL_ADMIN_USER:-admin}" \
    --account-pass="${DRUPAL_ADMIN_PASS:-admin}" \
    --yes

  # web/modules/custom/soarm_* を見つけた分だけ有効化する（依存関係は Drupal が解決）
  mapfile -t soarm_modules < <(
    find "${DRUPAL_ROOT}/web/modules/custom" -maxdepth 2 -name 'soarm_*.info.yml' \
      -exec basename {} .info.yml \; | sort
  )
  if [ "${#soarm_modules[@]}" -gt 0 ]; then
    log "enabling modules: ${soarm_modules[*]}"
    drush pm:enable "${soarm_modules[@]}" --yes
  fi
  drush cache:rebuild
else
  log "Drupal is already installed; applying pending updates"
  drush updatedb --yes || log "warning: updatedb failed (check: drush watchdog:show)"
  drush cache:rebuild || log "warning: cache rebuild failed"
fi

# --- 4. 準備完了 --------------------------------------------------------------
touch "${READY_MARKER}"
log "ready"
exec docker-php-entrypoint "$@"
