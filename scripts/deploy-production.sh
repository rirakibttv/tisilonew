#!/usr/bin/env bash

set -Eeuo pipefail
umask 077

readonly REPOSITORY="/home/rirakib/tisilonew-release-b98e1db"
readonly PUBLIC_ROOT="/home/rirakib/public_html"
readonly BACKUP_ROOT="/home/rirakib/tisilo-deploy-backups"
readonly PHP_BIN="/usr/local/bin/php"
readonly LOCK_FILE="${REPOSITORY}/storage/framework/tisilo-auto-deploy.lock"
readonly DEPLOY_LOG="${REPOSITORY}/storage/logs/deploy.log"

log() {
    printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S %Z')" "$*"
}

ensure_minute_auto_deploy_cron() {
    local current_crontab desired_entry temporary_crontab

    if ! command -v crontab >/dev/null 2>&1; then
        log "crontab executable was not found; the one-minute auto-deploy schedule could not be verified."
        return 1
    fi

    desired_entry="* * * * * /usr/bin/env bash ${REPOSITORY}/scripts/deploy-production.sh >> ${DEPLOY_LOG} 2>&1"
    current_crontab="$(crontab -l 2>/dev/null || true)"

    if grep -Fqx -- "${desired_entry}" <<< "${current_crontab}"; then
        return 0
    fi

    temporary_crontab="$(mktemp "${TMPDIR:-/tmp}/tisilo-auto-deploy-cron.XXXXXX")"
    awk -v script="${REPOSITORY}/scripts/deploy-production.sh" 'index($0, script) == 0' \
        <<< "${current_crontab}" > "${temporary_crontab}"
    printf '%s\n' "${desired_entry}" >> "${temporary_crontab}"
    crontab "${temporary_crontab}"
    rm -f "${temporary_crontab}"

    log "Auto-deploy cron verified: GitHub main is checked once per minute."
}

sync_public_files() {
    rsync -a --delete \
        --exclude='index.php' \
        --exclude='storage' \
        --exclude='.well-known' \
        --exclude='cgi-bin' \
        --exclude='.htaccess.pre-litespeed-20260825' \
        "${REPOSITORY}/public/" "${PUBLIC_ROOT}/"

    chmod 755 "${PUBLIC_ROOT}"
    find "${PUBLIC_ROOT}" -type d -exec chmod 755 {} +
    find "${PUBLIC_ROOT}" -type f -exec chmod 644 {} +

    local media_directory
    for media_directory in brands categories products settings vendors; do
        if [[ -d "${REPOSITORY}/storage/app/public/${media_directory}" ]]; then
            find "${REPOSITORY}/storage/app/public/${media_directory}" -type d -exec chmod 755 {} +
            find "${REPOSITORY}/storage/app/public/${media_directory}" -type f -exec chmod 644 {} +
        fi
    done
}

install_dependencies() {
    local composer_bin candidate
    composer_bin="$(command -v composer || true)"

    if [[ -z "${composer_bin}" ]]; then
        for candidate in \
            /home/rirakib/bin/composer \
            /opt/cpanel/composer/bin/composer \
            /usr/local/bin/composer; do
            if [[ -f "${candidate}" ]]; then
                composer_bin="${candidate}"
                break
            fi
        done
    fi

    if [[ -z "${composer_bin}" ]]; then
        log "Composer executable was not found."
        return 1
    fi

    "${PHP_BIN}" "${composer_bin}" install \
        --no-dev \
        --prefer-dist \
        --no-interaction \
        --no-progress \
        --optimize-autoloader \
        --ignore-platform-req=php+
}

backup_database() {
    local timestamp backup_file database_settings
    local DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD DB_SOCKET

    timestamp="$(date '+%Y%m%d-%H%M%S')"
    backup_file="${BACKUP_ROOT}/tisilo-${timestamp}.sql.gz"

    mkdir -p "${BACKUP_ROOT}"
    chmod 700 "${BACKUP_ROOT}"

    database_settings="$("${PHP_BIN}" -r '
        require "vendor/autoload.php";
        $app = require "bootstrap/app.php";
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $connection = config("database.connections." . config("database.default"));
        $settings = [
            "DB_HOST" => $connection["host"] ?? "localhost",
            "DB_PORT" => $connection["port"] ?? "3306",
            "DB_DATABASE" => $connection["database"] ?? "",
            "DB_USERNAME" => $connection["username"] ?? "",
            "DB_PASSWORD" => $connection["password"] ?? "",
            "DB_SOCKET" => $connection["unix_socket"] ?? "",
        ];
        foreach ($settings as $name => $value) {
            echo $name . "=" . escapeshellarg((string) $value) . PHP_EOL;
        }
    ')"

    eval "${database_settings}"

    if [[ -z "${DB_DATABASE}" || -z "${DB_USERNAME}" ]]; then
        log "Database backup aborted: database configuration is incomplete."
        return 1
    fi

    local dump_args=(
        --single-transaction
        --quick
        --routines
        --triggers
        --no-tablespaces
        --default-character-set=utf8mb4
        "--user=${DB_USERNAME}"
    )

    if [[ -n "${DB_SOCKET}" ]]; then
        dump_args+=("--socket=${DB_SOCKET}")
    else
        dump_args+=("--host=${DB_HOST}" "--port=${DB_PORT}")
    fi

    MYSQL_PWD="${DB_PASSWORD}" mysqldump "${dump_args[@]}" "${DB_DATABASE}" \
        | gzip -9 > "${backup_file}.partial"

    mv "${backup_file}.partial" "${backup_file}"
    chmod 600 "${backup_file}"
    log "Database backup created: ${backup_file}"
}

rollback_code() {
    local previous_commit="$1"

    log "Deployment failed; rolling code back to ${previous_commit}."
    git reset --hard "${previous_commit}"
    install_dependencies
    sync_public_files
    "${PHP_BIN}" artisan filament:optimize-clear
    "${PHP_BIN}" artisan optimize
    "${PHP_BIN}" artisan up || true
    log "Code rollback completed. Database backup is available for manual recovery."
}

handle_failure() {
    local status="${1:-1}"
    trap - ERR INT TERM

    if [[ ${deployment_started} -eq 1 ]]; then
        rollback_code "${previous_commit}" \
            || log "Automatic code rollback needs manual attention."
    fi

    exit "${status}"
}

main() {
    cd "${REPOSITORY}"

    exec 9>"${LOCK_FILE}"
    if ! flock -n 9; then
        log "Another deployment is already running; exiting."
        exit 0
    fi

    ensure_minute_auto_deploy_cron

    if [[ -n "$(git status --porcelain --untracked-files=no)" ]]; then
        log "Tracked server files have local changes; automatic deployment was stopped."
        exit 1
    fi

    git fetch --quiet origin main

    local previous_commit target_commit deployment_started=0 dependencies_changed=0
    previous_commit="$(git rev-parse HEAD)"
    target_commit="$(git rev-parse origin/main)"

    if [[ "${previous_commit}" == "${target_commit}" ]]; then
        exit 0
    fi

    if ! git diff --quiet "${previous_commit}" "${target_commit}" -- composer.json composer.lock; then
        dependencies_changed=1
    fi

    trap 'handle_failure $?' ERR INT TERM

    log "Deploying ${previous_commit} -> ${target_commit}."
    backup_database

    deployment_started=1
    "${PHP_BIN}" artisan down --retry=60

    git merge --ff-only "${target_commit}"
    if [[ ${dependencies_changed} -eq 1 ]]; then
        install_dependencies
    else
        log "Composer files are unchanged; dependency installation skipped."
    fi
    "${PHP_BIN}" artisan filament:optimize-clear
    "${PHP_BIN}" artisan migrate --force
    "${PHP_BIN}" artisan db:seed --class='Database\Seeders\ProductionRequiredDataSeeder' --force
    sync_public_files
    "${PHP_BIN}" artisan optimize
    "${PHP_BIN}" artisan up

    deployment_started=0
    trap - ERR INT TERM
    log "Deployment completed successfully at ${target_commit}."
}

main "$@"
