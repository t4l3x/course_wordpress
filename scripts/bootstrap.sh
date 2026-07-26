#!/usr/bin/env bash

set -Eeuo pipefail

project_root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$project_root"

if ! command -v docker >/dev/null 2>&1; then
    printf 'Docker is required but was not found in PATH.\n' >&2
    exit 1
fi

if ! docker compose version >/dev/null 2>&1; then
    printf 'Docker Compose v2 is required. Install the Docker Compose plugin.\n' >&2
    exit 1
fi

if ! docker info >/dev/null 2>&1; then
    printf 'The Docker daemon is not running or is not accessible.\n' >&2
    exit 1
fi

if [[ ! -f .env ]]; then
    cp .env.example .env
    printf 'Created .env from .env.example.\n'
fi

docker compose run --rm --no-deps composer install --no-interaction
docker compose up --detach --build database wordpress web

"$project_root/scripts/wait-for-database.sh"
"$project_root/scripts/install-wordpress.sh"

# shellcheck disable=SC1091
source .env

printf '\nWordPress is ready.\n'
printf 'Frontend: %s\n' "$APP_URL"
printf 'Admin:    %s/wp-admin\n' "$APP_URL"
printf 'User:     %s\n' "$WP_ADMIN_USER"
