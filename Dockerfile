# SO-ARM Portal — Drupal 11 イメージ（multi-stage）
#
#   app : PHP-FPM + Composer 依存 + Drush + テストツール（Drupal 本体が動く）
#   web : Nginx + 静的ファイル（CSS / JS / 画像を配信し、PHP は app へ渡す）
#
# Composer 依存は composer.json / composer.lock から `composer install` で入れます。
# lock ファイルどおりに入るので、いつ build しても同じバージョンになります。
ARG DRUPAL_IMAGE_TAG=11-fpm
ARG NGINX_IMAGE_TAG=stable-alpine

# =============================================================================
# app: Drupal 本体（PHP-FPM）
# =============================================================================
FROM drupal:${DRUPAL_IMAGE_TAG} AS app

# bind mount した custom コードをホスト側からも編集できるように、
# コンテナ内の www-data をホストのユーザー ID に合わせ替えます。
ARG HOST_UID=1000
ARG HOST_GID=1000

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1 \
    COMPOSER_NO_INTERACTION=1

# --- OS パッケージ -----------------------------------------------------------
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        mariadb-client \
        git \
        unzip \
        less \
        procps \
        ca-certificates; \
    rm -rf /var/lib/apt/lists/*

# --- www-data の UID/GID をホストに合わせる ----------------------------------
RUN set -eux; \
    if [ "${HOST_GID}" != "$(id -g www-data)" ]; then groupmod -o -g "${HOST_GID}" www-data; fi; \
    if [ "${HOST_UID}" != "$(id -u www-data)" ]; then usermod  -o -u "${HOST_UID}" -g "${HOST_GID}" www-data; fi

# --- PHP 設定 ----------------------------------------------------------------
COPY docker/php/zz-soarm.ini /usr/local/etc/php/conf.d/zz-soarm.ini

# --- Composer 依存 -----------------------------------------------------------
WORKDIR /opt/drupal

# 依存の定義だけを先に COPY すると、custom コードを変えても composer install の
# レイヤーがキャッシュから再利用され、build が速くなります。
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress

# --- サイト設定と custom コード ----------------------------------------------
COPY docker/drupal/settings.php web/sites/default/settings.php
COPY phpunit.xml ./phpunit.xml
COPY web/modules/custom web/modules/custom
COPY web/themes/custom web/themes/custom
COPY docker/drupal/entrypoint.sh /usr/local/bin/soarm-entrypoint

# --- 仕上げ ------------------------------------------------------------------
RUN set -eux; \
    chmod +x /usr/local/bin/soarm-entrypoint; \
    for tool in drush phpcs phpcbf phpstan phpunit; do \
        ln -sf "/opt/drupal/vendor/bin/${tool}" "/usr/local/bin/${tool}"; \
    done; \
    mkdir -p /opt/drupal/web/sites/default/files \
             /opt/drupal/private \
             /opt/drupal/config/sync; \
    chown -R www-data:www-data /opt/drupal

EXPOSE 9000
ENTRYPOINT ["soarm-entrypoint"]
CMD ["php-fpm"]

# =============================================================================
# web: Nginx（静的ファイル配信 + PHP を app へ中継）
# =============================================================================
FROM nginx:${NGINX_IMAGE_TAG} AS web

# core / contrib の CSS・JS・画像をイメージに焼き込みます。
# （ボリュームで共有すると、再 build しても古いファイルが残ってしまうため）
COPY --from=app /opt/drupal/web /opt/drupal/web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
