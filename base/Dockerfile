# wp-swe-bench base image
#
# Single-container WordPress development + grading environment:
#   - WordPress (pinned) installed on disk at /wordpress, backed by SQLite
#     (sqlite-database-integration drop-in), so that BOTH
#       * WordPress Playground CLI (PHP-WASM in Node) can serve it over HTTP, and
#       * native PHP 8.3 (WP-CLI, PHPUnit, PHPCS) can load the very same site.
#   - Node 24, @wp-playground/cli, @wordpress/scripts, Playwright + Chromium.
#   - Composer tooling: PHPUnit, PHPCS + WordPress Coding Standards.
#
# Build:   docker build -t ghcr.io/swissspidy/wp-swe-bench-base:0.1.0 base/
# Tasks:   FROM ${WPSB_BASE_IMAGE} (see tasks/*/environment/Dockerfile)

ARG OS_IMAGE=ubuntu:24.04
FROM ${OS_IMAGE}

ARG DEBIAN_FRONTEND=noninteractive
ARG TARGETARCH

# Optional extra CA certificates (*.crt, one PEM certificate per file) for building behind a
# TLS-intercepting proxy; tools/build-base.sh fills base/certs/ from $WPSB_EXTRA_CA_CERT_FILE.
COPY certs/ /usr/local/share/ca-certificates/wpsb-extra/

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      ca-certificates curl git unzip zip xz-utils jq less nano patch procps sqlite3 \
      python3 \
      php8.3-cli php8.3-sqlite3 php8.3-mbstring php8.3-xml php8.3-curl php8.3-gd \
      php8.3-intl php8.3-zip php8.3-bcmath php8.3-opcache \
 && update-ca-certificates \
 && rm -rf /var/lib/apt/lists/*

ENV NODE_EXTRA_CA_CERTS=/etc/ssl/certs/ca-certificates.crt \
    SSL_CERT_FILE=/etc/ssl/certs/ca-certificates.crt \
    npm_config_cafile=/etc/ssl/certs/ca-certificates.crt

# ---------------------------------------------------------------------------
# Pinned versions
# ---------------------------------------------------------------------------
ARG NODE_VERSION=24.21.0
ARG WP_VERSION=7.1.2
ARG SQLITE_PLUGIN_VERSION=3.0.2
ARG WP_CLI_VERSION=2.12.0
ARG COMPOSER_VERSION=2.10.3

ENV WPSB_WP_VERSION=${WP_VERSION} \
    WPSB_HOME=/opt/wpsb \
    WPSB_PORT=9400 \
    WPSB_URL=http://127.0.0.1:9400 \
    WP_ROOT=/wordpress \
    COMPOSER_ALLOW_SUPERUSER=1 \
    PLAYWRIGHT_BROWSERS_PATH=/opt/wpsb/ms-playwright \
    PATH=/opt/wpsb/bin:/opt/wpsb/composer/vendor/bin:/opt/wpsb/node/node_modules/.bin:$PATH

# Node.js (official tarball, pinned)
RUN case "${TARGETARCH:-amd64}" in amd64) a=x64;; arm64) a=arm64;; *) echo "unsupported arch" && exit 1;; esac \
 && curl -fsSL --retry 5 --retry-all-errors --retry-delay 3 "https://nodejs.org/dist/v${NODE_VERSION}/node-v${NODE_VERSION}-linux-${a}.tar.xz" \
    | tar -xJ -C /usr/local --strip-components=1 \
 && node --version && npm --version

# Composer + WP-CLI (pinned)
RUN curl -fsSL --retry 5 --retry-all-errors --retry-delay 3 -o /usr/local/bin/composer "https://getcomposer.org/download/${COMPOSER_VERSION}/composer.phar" \
 && chmod +x /usr/local/bin/composer \
 && curl -fsSL --retry 5 --retry-all-errors --retry-delay 3 -o /usr/local/bin/wp-cli.phar "https://github.com/wp-cli/wp-cli/releases/download/v${WP_CLI_VERSION}/wp-cli-${WP_CLI_VERSION}.phar" \
 && chmod +x /usr/local/bin/wp-cli.phar

# Node tooling: Playground CLI, @wordpress/scripts, Playwright (lockfile-pinned)
COPY node/package.json node/package-lock.json /opt/wpsb/node/
RUN cd /opt/wpsb/node && npm ci --no-audit --no-fund \
 && npx playwright install --with-deps chromium \
 && rm -rf /root/.npm /var/lib/apt/lists/*

# PHP tooling: PHPUnit, PHPCS + WPCS (lockfile-pinned)
COPY composer/composer.json composer/composer.lock /opt/wpsb/composer/
RUN cd /opt/wpsb/composer \
 && (composer install --no-interaction --no-progress || composer install --no-interaction --no-progress --prefer-source) \
 && find /opt/wpsb/composer/vendor -name .git -type d -prune -exec rm -rf {} + \
 && rm -rf /root/.cache/composer \
 && phpcs -i && phpunit --version

# WordPress core + SQLite integration drop-in (not yet installed; tasks run wpsb-provision)
RUN curl -fsSL --retry 5 --retry-all-errors --retry-delay 3 -o /tmp/wp.zip "https://downloads.wordpress.org/release/wordpress-${WP_VERSION}.zip" \
 && curl -fsSL --retry 5 --retry-all-errors --retry-delay 3 -o /tmp/sqlite.zip "https://downloads.wordpress.org/plugin/sqlite-database-integration.${SQLITE_PLUGIN_VERSION}.zip" \
 && unzip -q /tmp/wp.zip -d / \
 && unzip -q /tmp/sqlite.zip -d /wordpress/wp-content/plugins/ \
 && cp /wordpress/wp-content/plugins/sqlite-database-integration/db.copy /wordpress/wp-content/db.php \
 && rm -f /tmp/wp.zip /tmp/sqlite.zip /wordpress/wp-config-sample.php \
 && rm -rf /wordpress/wp-content/plugins/akismet /wordpress/wp-content/plugins/hello.php \
 && mkdir -p /wordpress/wp-content/uploads /wordpress/wp-content/mu-plugins /wordpress/wp-content/database \
 && (cd /wordpress && find wp-admin wp-includes -type f -name '*' -print0 | sort -z | xargs -0 md5sum > /opt/wpsb/core.md5)

COPY wordpress/wp-config.php /wordpress/wp-config.php
COPY wordpress/mu-plugins/ /wordpress/wp-content/mu-plugins/
COPY bin/ /opt/wpsb/bin/
COPY php.ini /etc/php/8.3/cli/conf.d/99-wpsb.ini
RUN chmod +x /opt/wpsb/bin/* && mkdir -p /var/log/wpsb /logs/verifier

WORKDIR /wordpress
CMD ["sleep", "infinity"]
