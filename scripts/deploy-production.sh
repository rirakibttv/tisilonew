#!/usr/bin/env bash

set -Eeuo pipefail
umask 077

readonly REPOSITORY="/home/rirakib/tisilonew-release"
readonly PUBLIC_ROOT="/home/rirakib/public_html"
readonly BACKUP_ROOT="/home/rirakib/TisiloBackup"
readonly ENV_BACKUP_FILE="${BACKUP_ROOT}/.env.production"
readonly BOOTSTRAP_UPLOAD_ARCHIVE="${BACKUP_ROOT}/bootstrap-storage.tar.gz"
readonly BOOTSTRAP_UPLOAD_MARKER="${REPOSITORY}/storage/app/.bootstrap-media-imported"
readonly LOCK_FILE="${REPOSITORY}/storage/framework/tisilo-auto-deploy.lock"
readonly DEPLOY_LOG="${REPOSITORY}/storage/logs/deploy.log"
readonly SCHEDULER_LOG="${REPOSITORY}/storage/logs/scheduler.log"
readonly INDEX_SIGNATURE="tisilo-cpanel-front-controller-v1"
readonly PUBLIC_MEDIA_MARKER=".tisilo-public-media-mirror"

PHP_BIN=""
COMPOSER_BIN=""
MYSQLDUMP_BIN=""
APPLICATION_BACKUP_FILE=""
APPLICATION_BACKUP_HAS_UPLOADS=0
APPLICATION_BACKUP_HAS_CATALOG=0

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

locate_php() {
    local candidate resolved

    [[ -n "${PHP_BIN}" ]] && return 0

    for candidate in \
        "${TISILO_PHP_BIN:-}" \
        php \
        /usr/local/bin/php \
        /opt/cpanel/ea-php82/root/usr/bin/php \
        /opt/cpanel/ea-php83/root/usr/bin/php \
        /opt/cpanel/ea-php84/root/usr/bin/php \
        ea-php82 ea-php83 ea-php84 \
        /usr/bin/php; do
        resolved="$(resolve_executable "${candidate}" || true)"
        [[ -n "${resolved}" ]] || continue

        if "${resolved}" -r 'exit(PHP_VERSION_ID >= 80200 ? 0 : 1);' >/dev/null 2>&1; then
            PHP_BIN="${resolved}"
            return 0
        fi
    done

    log "PHP 8.2 or newer could not be found. Set TISILO_PHP_BIN to the cPanel PHP CLI path."
    return 1
}

locate_composer() {
    local candidate resolved

    [[ -n "${COMPOSER_BIN}" ]] && return 0
    locate_php

    for candidate in \
        "${TISILO_COMPOSER_BIN:-}" \
        composer \
        /home/rirakib/bin/composer \
        /opt/cpanel/composer/bin/composer \
        /usr/local/bin/composer \
        /usr/bin/composer \
        "${REPOSITORY}/composer.phar"; do
        resolved="$(resolve_executable "${candidate}" || true)"
        [[ -n "${resolved}" ]] || continue

        if "${PHP_BIN}" "${resolved}" --version >/dev/null 2>&1; then
            COMPOSER_BIN="${resolved}"
            return 0
        fi
    done

    log "Composer could not be found. Set TISILO_COMPOSER_BIN to its executable path."
    return 1
}

locate_mysqldump() {
    local candidate resolved

    [[ -n "${MYSQLDUMP_BIN}" ]] && return 0

    for candidate in \
        "${TISILO_MYSQLDUMP_BIN:-}" \
        mysqldump mariadb-dump \
        /usr/bin/mysqldump \
        /usr/bin/mariadb-dump \
        /usr/local/bin/mysqldump \
        /usr/local/mysql/bin/mysqldump; do
        resolved="$(resolve_executable "${candidate}" || true)"
        [[ -n "${resolved}" ]] || continue

        if "${resolved}" --version >/dev/null 2>&1; then
            MYSQLDUMP_BIN="${resolved}"
            return 0
        fi
    done

    log "mysqldump or mariadb-dump could not be found. Set TISILO_MYSQLDUMP_BIN to its executable path."
    return 1
}

public_index_is_managed() {
    [[ -f "${PUBLIC_ROOT}/index.php" ]] \
        && grep -Fq -- "${INDEX_SIGNATURE}" "${PUBLIC_ROOT}/index.php"
}

prepare_bootstrap_environment() {
    mkdir -p \
        "${REPOSITORY}/storage/app/public" \
        "${REPOSITORY}/storage/framework/cache" \
        "${REPOSITORY}/storage/framework/sessions" \
        "${REPOSITORY}/storage/framework/views" \
        "${REPOSITORY}/storage/logs" \
        "${REPOSITORY}/bootstrap/cache" \
        "${BACKUP_ROOT}/database" \
        "${BACKUP_ROOT}/files" \
        "${BACKUP_ROOT}/legacy-public"

    chmod 700 "${BACKUP_ROOT}" "${BACKUP_ROOT}/database" \
        "${BACKUP_ROOT}/files" "${BACKUP_ROOT}/legacy-public"
    chmod -R u+rwX,go-rwx "${REPOSITORY}/storage" "${REPOSITORY}/bootstrap/cache"

    if [[ ! -f "${REPOSITORY}/.env" ]]; then
        if [[ -f "${ENV_BACKUP_FILE}" ]]; then
            cp -p -- "${ENV_BACKUP_FILE}" "${REPOSITORY}/.env"
            chmod 600 "${REPOSITORY}/.env"
            log "Production .env restored from the protected backup directory."
        elif [[ -f "${PUBLIC_ROOT}/.env" ]]; then
            cp -p -- "${PUBLIC_ROOT}/.env" "${REPOSITORY}/.env"
            chmod 600 "${REPOSITORY}/.env"
            log "Production .env copied into the private release directory."
        else
            log "Production .env was not found in the release, public root, or protected backup; deployment stopped."
            return 1
        fi
    fi

    chmod 600 "${REPOSITORY}/.env"
    if [[ ! -f "${ENV_BACKUP_FILE}" ]]; then
        cp -p -- "${REPOSITORY}/.env" "${ENV_BACKUP_FILE}"
        chmod 600 "${ENV_BACKUP_FILE}"
        log "Production .env backed up outside the web root."
    fi
}

import_bootstrap_uploads() {
    [[ -f "${BOOTSTRAP_UPLOAD_ARCHIVE}" ]] || return 0
    [[ ! -f "${BOOTSTRAP_UPLOAD_MARKER}" ]] || return 0

    if ! tar -tzf "${BOOTSTRAP_UPLOAD_ARCHIVE}" >/dev/null; then
        log "Bootstrap media archive is invalid; deployment stopped."
        return 1
    fi

    tar -C "${REPOSITORY}" -xzf "${BOOTSTRAP_UPLOAD_ARCHIVE}" \
        --exclude='storage/app/public/.gitignore' \
        storage/app/public
    touch "${BOOTSTRAP_UPLOAD_MARKER}"
    chmod 600 "${BOOTSTRAP_UPLOAD_MARKER}"
    log "Initial media migration restored from the protected backup directory."
}

prepare_public_storage_link() {
    local timestamp legacy_storage

    mkdir -p "${REPOSITORY}/storage/app/public"

    if [[ -d "${PUBLIC_ROOT}/storage" && ! -L "${PUBLIC_ROOT}/storage" ]]; then
        # Shared cPanel/LiteSpeed hosts can deny public symbolic links with a
        # 403 response. A directory carrying our marker is the managed media
        # mirror and must remain in place between deployments.
        if [[ -f "${PUBLIC_ROOT}/storage/${PUBLIC_MEDIA_MARKER}" ]]; then
            return 0
        fi

        if [[ -d "${PUBLIC_ROOT}/storage/app/public" ]]; then
            rsync -a --exclude='.gitignore' \
                "${PUBLIC_ROOT}/storage/app/public/" "${REPOSITORY}/storage/app/public/"
        fi

        timestamp="$(date '+%Y%m%d-%H%M%S')"
        legacy_storage="${BACKUP_ROOT}/legacy-public/storage-${timestamp}"
        mv -- "${PUBLIC_ROOT}/storage" "${legacy_storage}"
        log "Legacy public storage moved intact to ${legacy_storage}."
    fi

    if [[ -e "${PUBLIC_ROOT}/storage" && ! -L "${PUBLIC_ROOT}/storage" ]]; then
        log "${PUBLIC_ROOT}/storage could not be prepared safely."
        return 1
    fi
}

quarantine_legacy_public_env() {
    local timestamp destination

    [[ -f "${PUBLIC_ROOT}/.env" ]] || return 0
    timestamp="$(date '+%Y%m%d-%H%M%S')"
    destination="${BACKUP_ROOT}/legacy-public/env-${timestamp}"
    mv -- "${PUBLIC_ROOT}/.env" "${destination}"
    chmod 600 "${destination}"
    log "Legacy public_html .env moved to ${destination}."
}

ensure_minute_auto_deploy_cron() {
    local current_crontab desired_deploy_entry desired_scheduler_entry temporary_crontab

    if ! command -v crontab >/dev/null 2>&1; then
        log "crontab executable was not found; the one-minute auto-deploy schedule could not be verified."
        return 1
    fi

    desired_deploy_entry="* * * * * /usr/bin/env bash ${REPOSITORY}/scripts/deploy-production.sh >> ${DEPLOY_LOG} 2>&1"
    desired_scheduler_entry="* * * * * ${PHP_BIN} ${REPOSITORY}/artisan schedule:run --no-interaction >> ${SCHEDULER_LOG} 2>&1"
    current_crontab="$(crontab -l 2>/dev/null || true)"

    if grep -Fqx -- "${desired_deploy_entry}" <<< "${current_crontab}" \
        && grep -Fqx -- "${desired_scheduler_entry}" <<< "${current_crontab}"; then
        return 0
    fi

    temporary_crontab="$(mktemp "${TMPDIR:-/tmp}/tisilo-auto-deploy-cron.XXXXXX")"
    awk \
        -v deploy_script="${REPOSITORY}/scripts/deploy-production.sh" \
        -v scheduler_command="${REPOSITORY}/artisan schedule:run" \
        'index($0, deploy_script) == 0 && index($0, scheduler_command) == 0' \
        <<< "${current_crontab}" > "${temporary_crontab}"
    printf '%s\n' "${desired_deploy_entry}" "${desired_scheduler_entry}" >> "${temporary_crontab}"
    crontab "${temporary_crontab}"
    rm -f "${temporary_crontab}"

    log "Auto-deploy and Laravel scheduler cron entries verified for every minute."
}

sync_public_media() {
    if [[ -e "${PUBLIC_ROOT}/storage" \
        && ! -L "${PUBLIC_ROOT}/storage" \
        && ! -d "${PUBLIC_ROOT}/storage" ]]; then
        log "${PUBLIC_ROOT}/storage exists but is not a directory or symlink; refusing to overwrite it."
        return 1
    fi

    if [[ -L "${PUBLIC_ROOT}/storage" \
        && "$(readlink -f "${PUBLIC_ROOT}/storage")" != "$(readlink -f "${REPOSITORY}/storage/app/public")" ]]; then
        log "${PUBLIC_ROOT}/storage points somewhere unexpected; refusing to replace it."
        return 1
    fi

    if [[ -L "${PUBLIC_ROOT}/storage" ]]; then
        # LiteSpeed on this shared host returns 403 for the otherwise-correct
        # Laravel storage symlink. Replace only the validated link itself with
        # a real public mirror; the private source remains canonical.
        rm -- "${PUBLIC_ROOT}/storage"
    fi

    mkdir -p "${PUBLIC_ROOT}/storage"
    rsync -a --chmod=Du=rwx,Dgo=rx,Fu=rw,Fgo=r \
        --exclude='.gitignore' \
        "${REPOSITORY}/storage/app/public/" "${PUBLIC_ROOT}/storage/"
    touch "${PUBLIC_ROOT}/storage/${PUBLIC_MEDIA_MARKER}"
    chmod 644 "${PUBLIC_ROOT}/storage/${PUBLIC_MEDIA_MARKER}"
}

sync_public_files() {
    if ! grep -Fq -- "${INDEX_SIGNATURE}" "${REPOSITORY}/scripts/cpanel-index.php"; then
        log "The managed cPanel index signature is missing; refusing to replace public_html/index.php."
        return 1
    fi

    mkdir -p "${PUBLIC_ROOT}"

    # Never use --delete here: public_html can also contain cPanel-managed files,
    # domain verification files, or addon-domain roots.
    rsync -a --chmod=Du=rwx,Dgo=rx,Fu=rw,Fgo=r \
        --exclude='index.php' \
        --exclude='storage' \
        --exclude='.well-known' \
        --exclude='cgi-bin' \
        --exclude='.htaccess.pre-litespeed-20260825' \
        "${REPOSITORY}/public/" "${PUBLIC_ROOT}/"

    cp "${REPOSITORY}/scripts/cpanel-index.php" "${PUBLIC_ROOT}/index.php"
    sync_public_media

    chmod 755 "${PUBLIC_ROOT}"
    chmod 644 "${PUBLIC_ROOT}/index.php"
}

install_dependencies() {
    locate_composer

    "${PHP_BIN}" "${COMPOSER_BIN}" install \
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

    locate_php
    locate_mysqldump

    timestamp="$(date '+%Y%m%d-%H%M%S')"
    backup_file="${BACKUP_ROOT}/database/tisilo-${timestamp}.sql.gz"

    mkdir -p "${BACKUP_ROOT}/database"
    chmod 700 "${BACKUP_ROOT}"
    chmod 700 "${BACKUP_ROOT}/database"

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

    if ! MYSQL_PWD="${DB_PASSWORD}" "${MYSQLDUMP_BIN}" "${dump_args[@]}" "${DB_DATABASE}" \
        | gzip -9 > "${backup_file}.partial"; then
        rm -f -- "${backup_file}.partial"
        log "Database backup failed; deployment stopped before code or production data changed."
        return 1
    fi

    mv "${backup_file}.partial" "${backup_file}"
    chmod 600 "${backup_file}"
    log "Database backup created: ${backup_file}"
}

backup_application_files() {
    local timestamp backup_file

    timestamp="$(date '+%Y%m%d-%H%M%S')"
    backup_file="${BACKUP_ROOT}/files/tisilo-files-${timestamp}.tar.gz"

    mkdir -p "${BACKUP_ROOT}/files"
    chmod 700 "${BACKUP_ROOT}" "${BACKUP_ROOT}/files"

    local backup_targets=()
    [[ -f "${REPOSITORY}/.env" ]] && backup_targets+=(".env")
    if [[ -d "${REPOSITORY}/storage/app/public" ]]; then
        backup_targets+=("storage/app/public")
        APPLICATION_BACKUP_HAS_UPLOADS=1
    fi
    if [[ -f "${REPOSITORY}/database/data/deployable-catalog.json" ]]; then
        backup_targets+=("database/data/deployable-catalog.json")
        APPLICATION_BACKUP_HAS_CATALOG=1
    fi

    if [[ ${#backup_targets[@]} -eq 0 ]]; then
        log "Application file backup skipped: no production-local files exist yet."
        return 0
    fi

    tar -C "${REPOSITORY}" -czf "${backup_file}.partial" "${backup_targets[@]}"
    tar -tzf "${backup_file}.partial" >/dev/null
    mv "${backup_file}.partial" "${backup_file}"
    chmod 600 "${backup_file}"
    APPLICATION_BACKUP_FILE="${backup_file}"
    log "Application file backup created: ${backup_file}"
}

restore_production_files() {
    local -a restore_targets=()

    [[ -n "${APPLICATION_BACKUP_FILE}" && -f "${APPLICATION_BACKUP_FILE}" ]] || return 0

    [[ ${APPLICATION_BACKUP_HAS_UPLOADS} -eq 1 ]] \
        && restore_targets+=("storage/app/public")
    [[ ${APPLICATION_BACKUP_HAS_CATALOG} -eq 1 ]] \
        && restore_targets+=("database/data/deployable-catalog.json")

    [[ ${#restore_targets[@]} -gt 0 ]] || return 0

    tar -C "${REPOSITORY}" -xzf "${APPLICATION_BACKUP_FILE}" \
        --exclude='storage/app/public/.gitignore' \
        "${restore_targets[@]}"

    log "Production uploads restored from the verified pre-deploy snapshot."
}

target_has_tracked_production_files() {
    local target_commit="$1" tracked_path

    while IFS= read -r tracked_path; do
        [[ -n "${tracked_path}" ]] || continue
        [[ "${tracked_path}" == "storage/app/public/.gitignore" ]] && continue
        return 0
    done < <(
        git ls-tree -r --name-only "${target_commit}" -- \
            storage/app/public database/data/deployable-catalog.json
    )

    return 1
}

has_tracked_code_changes() {
    local changes

    changes="$(
        git status --porcelain --untracked-files=no -- \
            . \
            ':(exclude)storage/app/public/**' \
            ':(exclude)database/data/deployable-catalog.json'
        git status --porcelain --untracked-files=no -- storage/app/public/.gitignore
    )"

    [[ -n "${changes}" ]]
}

rollback_code() {
    local previous_commit="$1"

    log "Deployment failed; rolling code back to ${previous_commit}."
    git reset --hard "${previous_commit}"
    restore_production_files
    install_dependencies
    sync_public_files
    "${PHP_BIN}" artisan optimize:clear
    "${PHP_BIN}" artisan filament:optimize-clear
    "${PHP_BIN}" artisan up || true
    log "Code rollback completed. Database backup is available for manual recovery."
}

quarantine_legacy_public_source() {
    local timestamp destination candidate basename
    local -a legacy_candidates=(
        app bootstrap config database resources routes tests vendor public
        artisan composer.json composer.lock composer.phar package.json package-lock.json
        phpunit.xml vite.config.js server.php .env.example
    )

    timestamp="$(date '+%Y%m%d-%H%M%S')"
    destination="${BACKUP_ROOT}/legacy-public/source-${timestamp}"

    for candidate in "${legacy_candidates[@]}"; do
        [[ -e "${PUBLIC_ROOT}/${candidate}" || -L "${PUBLIC_ROOT}/${candidate}" ]] || continue
        mkdir -p "${destination}"
        basename="$(basename -- "${candidate}")"
        mv -- "${PUBLIC_ROOT}/${candidate}" "${destination}/${basename}"
    done

    if [[ -d "${destination}" ]]; then
        chmod -R u+rwX,go-rwx "${destination}"
        log "Legacy public_html application source moved intact to ${destination}."
    fi
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

    prepare_bootstrap_environment
    import_bootstrap_uploads
    locate_php
    ensure_minute_auto_deploy_cron

    if has_tracked_code_changes; then
        log "Tracked server files have local changes; automatic deployment was stopped."
        exit 1
    fi

    git fetch --quiet origin main

    local previous_commit target_commit deployment_started=0 dependencies_changed=0 bootstrap_required=0
    previous_commit="$(git rev-parse HEAD)"
    target_commit="$(git rev-parse origin/main)"

    if target_has_tracked_production_files "${target_commit}"; then
        log "The target commit tracks production uploads or catalog data; deployment refused."
        exit 1
    fi

    if [[ ! -f "${REPOSITORY}/vendor/autoload.php" ]]; then
        bootstrap_required=1
        dependencies_changed=1
    fi

    if ! public_index_is_managed; then
        bootstrap_required=1
    fi

    if [[ "${previous_commit}" == "${target_commit}" && ${bootstrap_required} -eq 0 ]]; then
        prepare_public_storage_link
        sync_public_media
        exit 0
    fi

    if ! git diff --quiet "${previous_commit}" "${target_commit}" -- composer.json composer.lock; then
        dependencies_changed=1
    fi

    trap 'handle_failure $?' ERR INT TERM

    log "Deploying ${previous_commit} -> ${target_commit}."

    if [[ ! -f "${REPOSITORY}/vendor/autoload.php" ]]; then
        install_dependencies
        dependencies_changed=0
    fi

    backup_database
    backup_application_files

    deployment_started=1
    "${PHP_BIN}" artisan down --retry=60

    if [[ "${previous_commit}" != "${target_commit}" ]]; then
        git merge --ff-only "${target_commit}"
    fi
    restore_production_files
    if [[ ${dependencies_changed} -eq 1 ]]; then
        install_dependencies
    else
        log "Composer files are unchanged; dependency installation skipped."
    fi
    "${PHP_BIN}" artisan optimize:clear
    "${PHP_BIN}" artisan filament:optimize-clear
    "${PHP_BIN}" artisan migrate --force
    prepare_public_storage_link
    quarantine_legacy_public_source
    sync_public_files
    quarantine_legacy_public_env
    "${PHP_BIN}" artisan up

    deployment_started=0
    trap - ERR INT TERM
    log "Deployment completed successfully at ${target_commit}."
}

main "$@"
