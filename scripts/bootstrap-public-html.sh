#!/usr/bin/env bash

set -Eeuo pipefail
umask 077

readonly APP_ROOT="/home/rirakib/public_html"
readonly BACKUP_ROOT="/home/rirakib/TisiloBackup"
readonly ENV_BACKUP="${BACKUP_ROOT}/.env.production"
readonly MEDIA_ARCHIVE="${BACKUP_ROOT}/bootstrap-storage.tar.gz"
readonly MEDIA_MARKER="${APP_ROOT}/storage/app/.bootstrap-media-imported"
readonly COMPLETE_MARKER="${BACKUP_ROOT}/manual-deploy-current.done"

log() {
    printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S %Z')" "$*"
}

resolve_executable() {
    local candidate resolved

    for candidate in "$@"; do
        [[ -n "${candidate}" ]] || continue
        if [[ "${candidate}" == */* ]]; then
            [[ -x "${candidate}" ]] || continue
            printf '%s\n' "${candidate}"
            return 0
        fi
        resolved="$(command -v "${candidate}" 2>/dev/null || true)"
        if [[ -n "${resolved}" && -x "${resolved}" ]]; then
            printf '%s\n' "${resolved}"
            return 0
        fi
    done

    return 1
}

cd "${APP_ROOT}"

mkdir -p "${BACKUP_ROOT}"
chmod 700 "${BACKUP_ROOT}"

if [[ -f .env ]]; then
    chmod 600 .env
    if [[ ! -f "${ENV_BACKUP}" ]]; then
        cp -p -- .env "${ENV_BACKUP}"
        chmod 600 "${ENV_BACKUP}"
        log "Production environment was backed up outside the web root."
    fi
elif [[ -f "${ENV_BACKUP}" ]]; then
    cp -p -- "${ENV_BACKUP}" .env
    chmod 600 .env
    log "Production environment was restored from the protected backup."
else
    log "Production .env is missing and no protected backup is available."
    exit 1
fi

[[ ! -f "${COMPLETE_MARKER}" ]] || exit 0

PHP_BIN="$(resolve_executable \
    /opt/cpanel/ea-php84/root/usr/bin/php \
    /opt/cpanel/ea-php83/root/usr/bin/php \
    /opt/cpanel/ea-php82/root/usr/bin/php \
    php /usr/local/bin/php /usr/bin/php)"

if ! "${PHP_BIN}" -r 'exit(PHP_VERSION_ID >= 80200 ? 0 : 1);'; then
    log "PHP 8.2 or newer is required."
    exit 1
fi

mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    "${BACKUP_ROOT}"

chmod -R u+rwX storage bootstrap/cache

if [[ -f "${MEDIA_ARCHIVE}" && ! -f "${MEDIA_MARKER}" ]]; then
    tar -tzf "${MEDIA_ARCHIVE}" >/dev/null
    tar -C "${APP_ROOT}" -xzf "${MEDIA_ARCHIVE}" \
        --exclude='storage/app/public/.gitignore' \
        storage/app/public
    touch "${MEDIA_MARKER}"
    log "Protected media backup restored."
fi

COMPOSER_BIN="$(resolve_executable \
    composer \
    /opt/cpanel/composer/bin/composer \
    /usr/local/bin/composer \
    /usr/bin/composer \
    "${APP_ROOT}/composer.phar" || true)"

if [[ -n "${COMPOSER_BIN}" ]]; then
    "${PHP_BIN}" "${COMPOSER_BIN}" install \
        --no-dev \
        --prefer-dist \
        --no-interaction \
        --no-progress \
        --optimize-autoloader \
        --ignore-platform-req=php+
elif [[ ! -f vendor/autoload.php ]]; then
    log "Composer is unavailable and vendor/autoload.php is missing."
    exit 1
else
    log "Composer is unavailable; verified existing vendor dependencies will be used."
fi

"${PHP_BIN}" artisan optimize:clear
"${PHP_BIN}" artisan storage:link --force
"${PHP_BIN}" artisan migrate --force
"${PHP_BIN}" artisan up || true

printf 'deployed_at=%s\nsource=copy-ready-production-bundle\n' \
    "$(date --iso-8601=seconds)" > "${COMPLETE_MARKER}"
chmod 600 "${COMPLETE_MARKER}"
log "Manual production bootstrap completed successfully."
