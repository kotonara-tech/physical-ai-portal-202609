#!/bin/bash
# SO-ARM Portal — はじめてのセットアップ
#
#   ./setup.sh               サイトを build して起動する（docker compose up -d --build と同じ）
#   ./setup.sh --with-tools  あわせて GitHub CLI もホストに入れる
#
# Drupal 本体・contrib モジュール・Drush・PHPUnit は Dockerfile の中で
# composer.lock どおりにインストールされます。サイトのインストールと
# soarm_* モジュールの有効化は、コンテナの起動時に自動で行われます
# （docker/drupal/entrypoint.sh）。
set -euo pipefail
cd "$(dirname "$0")"

HTTP_PORT="${HTTP_PORT:-8080}"

echo "Start: docker compose up (初回は build と Drupal のインストールで数分かかります)"
HOST_UID="$(id -u)" HOST_GID="$(id -g)" docker compose up -d --build
echo "End: docker compose up"

echo "Site : http://localhost:${HTTP_PORT}"
echo "Login: docker exec -u www-data workspace-drupal-1 drush uli --uid=1"

if [ "${1:-}" = "--with-tools" ]; then
  echo "Start: install gh (GitHub CLI)"
  if ! command -v gh &> /dev/null; then
    (type -p wget >/dev/null || (sudo apt update && sudo apt install wget -y)) \
      && sudo mkdir -p -m 755 /etc/apt/keyrings \
      && out=$(mktemp) && wget -nv -O"$out" https://cli.github.com/packages/githubcli-archive-keyring.gpg \
      && cat "$out" | sudo tee /etc/apt/keyrings/githubcli-archive-keyring.gpg > /dev/null \
      && sudo chmod go+r /etc/apt/keyrings/githubcli-archive-keyring.gpg \
      && sudo mkdir -p -m 755 /etc/apt/sources.list.d \
      && echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/githubcli-archive-keyring.gpg] https://cli.github.com/packages stable main" | sudo tee /etc/apt/sources.list.d/github-cli.list > /dev/null \
      && sudo apt update \
      && sudo apt install gh -y
  else
    echo "    gh already installed: $(gh --version | head -n1)"
  fi
  echo "End: install gh (GitHub CLI)"
fi
