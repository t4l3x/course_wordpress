#!/usr/bin/env bash

set -Eeuo pipefail

project_root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$project_root"

if [[ ! -f .env ]]; then
    printf '.env is missing. Run make init first.\n' >&2
    exit 1
fi

# shellcheck disable=SC1091
source .env

run_wp() {
    docker compose run --rm --no-deps wp-cli "$@"
}

wordpress_files_ready=false

for ((attempt = 1; attempt <= 60; attempt++)); do
    if docker compose exec -T wordpress test -f /var/www/html/wp-includes/version.php \
        && docker compose exec -T wordpress test -f /var/www/html/wp-config.php; then
        wordpress_files_ready=true
        break
    fi

    sleep 2
done

if [[ "$wordpress_files_ready" != true ]]; then
    printf 'WordPress files were not ready after 120 seconds.\n' >&2
    exit 1
fi

if ! run_wp core is-installed >/dev/null 2>&1; then
    run_wp core install \
        --url="$APP_URL" \
        --title="$WP_TITLE" \
        --admin_user="$WP_ADMIN_USER" \
        --admin_password="$WP_ADMIN_PASSWORD" \
        --admin_email="$WP_ADMIN_EMAIL" \
        --skip-email
fi

run_wp option update timezone_string UTC
run_wp option update blogdescription "A maintainable and extensible course discovery platform."
run_wp rewrite structure '/%postname%/'
run_wp rewrite flush
run_wp plugin activate course-discovery

active_theme="$(run_wp option get stylesheet)"
if ! docker compose exec -T wordpress test -f "/var/www/html/wp-content/themes/$active_theme/style.css"; then
    run_wp theme activate twentytwentyfive
fi
