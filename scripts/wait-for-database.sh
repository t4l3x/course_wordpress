#!/usr/bin/env bash

set -Eeuo pipefail

project_root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$project_root"

for ((attempt = 1; attempt <= 60; attempt++)); do
    if docker compose exec -T database healthcheck.sh --connect --innodb_initialized >/dev/null 2>&1; then
        printf 'Database is ready.\n'
        exit 0
    fi

    sleep 2
done

printf 'Database was not ready after 120 seconds.\n' >&2
docker compose logs --tail=100 database >&2
exit 1

