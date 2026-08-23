#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "${SCRIPT_DIR}"

# Load environment variables from .env, if present
if [[ -f "${SCRIPT_DIR}/.env" ]]; then
    set -a
    # shellcheck disable=SC1091
    source "${SCRIPT_DIR}/.env"
    set +a
fi

require_env() {
    local name="$1"

    if [[ -z "${!name:-}" ]]; then
        echo "Missing environment variable ${name}." >&2
        exit 1
    fi
}

require_env DEPLOY_SSH_HOSTNAME
require_env DEPLOY_SSH_USERNAME
require_env DEPLOY_SSH_TARGET

rsync \
    -vrlt \
    -R \
    --exclude=".DS_Store" \
    --exclude="app/config/config.json" \
    .htaccess \
    index.php \
    vendor \
    app \
    assets \
    "${DEPLOY_SSH_USERNAME}@${DEPLOY_SSH_HOSTNAME}:${DEPLOY_SSH_TARGET}"
