#!/usr/bin/env bash

set -Eeuo pipefail

project_root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$project_root"

printf 'This will permanently delete the local WordPress and database volumes.\n'
read -r -p 'Type "reset" to continue: ' confirmation

if [[ "$confirmation" != "reset" ]]; then
    printf 'Reset cancelled.\n'
    exit 1
fi

docker compose down --volumes --remove-orphans
printf 'Local containers and data volumes were deleted. Run make init to rebuild them.\n'

