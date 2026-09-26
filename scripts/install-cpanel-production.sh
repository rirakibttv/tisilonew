#!/usr/bin/env bash

set -Eeuo pipefail
umask 077

readonly REPOSITORY_URL="https://github.com/rirakibttv/tisilonew.git"
readonly REPOSITORY="/home/rirakib/tisilonew-release"
readonly PUBLIC_ROOT="/home/rirakib/public_html"
readonly BACKUP_ROOT="/home/rirakib/TisiloBackup"
readonly ENV_BACKUP="${BACKUP_ROOT}/.env.production"
readonly INSTALL_LOCK="${BACKUP_ROOT}/install-cpanel-production.lock"
readonly INSTALL_MARKER="${BACKUP_ROOT}/install-cpanel-production.done"

log() {
    printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S %Z')" "$*"
}

safe_legacy_release_destination() {
    local timestamp destination
    timestamp="$(date '+%Y%m%d-%H%M%S')"
    destination="${BACKUP_ROOT}/legacy-release-${timestamp}"
    printf '%s\n' "${destination}"
}

backup_existing_public_root_once() {
    local timestamp backup_file

    [[ -d "${PUBLIC_ROOT}" ]] || return 0
    [[ ! -f "${INSTALL_MARKER}" ]] || return 0

    timestamp="$(date '+%Y%m%d-%H%M%S')"
    backup_file="${BACKUP_ROOT}/files/public-html-before-private-release-${timestamp}.tar.gz"

    tar -C "$(dirname -- "${PUBLIC_ROOT}")" -czf "${backup_file}.partial" \
        --exclude='public_html/storage/framework/cache/*' \
        --exclude='public_html/storage/framework/sessions/*' \
        --exclude='public_html/storage/framework/views/*' \
        --exclude='public_html/storage/logs/*' \
        "$(basename -- "${PUBLIC_ROOT}")"
    tar -tzf "${backup_file}.partial" >/dev/null
    mv -- "${backup_file}.partial" "${backup_file}"
    chmod 600 "${backup_file}"
    log "Pre-deployment public_html backup created: ${backup_file}"
}

prepare_production_environment() {
    if [[ -f "${ENV_BACKUP}" ]]; then
        chmod 600 "${ENV_BACKUP}"
        return 0
    fi

    if [[ ! -f "${PUBLIC_ROOT}/.env" ]]; then
        log "Production .env is missing from both ${PUBLIC_ROOT} and ${BACKUP_ROOT}."
        return 1
    fi

    cp -p -- "${PUBLIC_ROOT}/.env" "${ENV_BACKUP}"
    chmod 600 "${ENV_BACKUP}"
    log "Production .env copied to the protected backup directory."
}

prepare_repository() {
    local legacy_destination

    if [[ -e "${REPOSITORY}" && ! -d "${REPOSITORY}/.git" ]]; then
        legacy_destination="$(safe_legacy_release_destination)"
        mv -- "${REPOSITORY}" "${legacy_destination}"
        log "Incomplete legacy release directory preserved at ${legacy_destination}."
    fi

    if [[ ! -d "${REPOSITORY}/.git" ]]; then
        git clone --branch main --single-branch "${REPOSITORY_URL}" "${REPOSITORY}"
    else
        git -C "${REPOSITORY}" remote set-url origin "${REPOSITORY_URL}"
        git -C "${REPOSITORY}" fetch --quiet origin main
        if [[ "$(git -C "${REPOSITORY}" rev-parse HEAD)" != "$(git -C "${REPOSITORY}" rev-parse origin/main)" ]]; then
            git -C "${REPOSITORY}" merge --ff-only origin/main
        fi
    fi

    cp -p -- "${ENV_BACKUP}" "${REPOSITORY}/.env"
    chmod 600 "${REPOSITORY}/.env"
}

reuse_verified_dependencies() {
    [[ ! -f "${REPOSITORY}/vendor/autoload.php" ]] || return 0
    [[ -f "${PUBLIC_ROOT}/vendor/autoload.php" ]] || return 0

    rsync -a "${PUBLIC_ROOT}/vendor/" "${REPOSITORY}/vendor/"
    log "Existing production dependencies copied into the private release directory."
}

main() {
    mkdir -p "${BACKUP_ROOT}/files"
    chmod 700 "${BACKUP_ROOT}" "${BACKUP_ROOT}/files"

    exec 9>"${INSTALL_LOCK}"
    if ! flock -n 9; then
        log "Another production installer is already running."
        exit 0
    fi

    prepare_production_environment
    backup_existing_public_root_once
    prepare_repository
    reuse_verified_dependencies

    /usr/bin/env bash "${REPOSITORY}/scripts/deploy-production.sh"

    printf 'installed_at=%s\ncommit=%s\n' \
        "$(date --iso-8601=seconds)" \
        "$(git -C "${REPOSITORY}" rev-parse HEAD)" > "${INSTALL_MARKER}"
    chmod 600 "${INSTALL_MARKER}"
    log "cPanel production installation completed successfully."
}

main "$@"
