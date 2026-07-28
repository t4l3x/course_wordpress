#!/usr/bin/env bash

set -Eeuo pipefail

repository_root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)"
plugin_root="${repository_root}/app/plugins/course-discovery"
dist_root="${repository_root}/dist"

if [[ ! -f "${plugin_root}/composer.json" || ! -f "${plugin_root}/composer.lock" ]]; then
	printf 'Plugin Composer files are missing from %s.\n' "${plugin_root}" >&2
	exit 1
fi

build_root="$(mktemp -d "${plugin_root}/.dist-build.XXXXXX")"
if [[ "${build_root}" != "${plugin_root}"/.dist-build.* ]]; then
	printf 'Refusing to use unexpected build directory: %s\n' "${build_root}" >&2
	exit 1
fi

stage_root="${build_root}/course-discovery"
archive_path="${dist_root}/course-discovery.zip"
archive_temporary_path="${dist_root}/.course-discovery.zip.tmp"

cleanup() {
	rm -rf -- "${build_root}"
	rm -f -- "${archive_temporary_path}"
}
trap cleanup EXIT

mkdir -p -- "${stage_root}" "${dist_root}"
rm -f -- "${archive_path}" "${archive_temporary_path}"

tar \
	--directory="${plugin_root}" \
	--exclude='./.cache' \
	--exclude='./.dist-build.*' \
	--exclude='./.git' \
	--exclude='./.github' \
	--exclude='./.idea' \
	--exclude='./.phpcs-cache' \
	--exclude='./.phpunit.cache' \
	--exclude='./.phpunit.result.cache' \
	--exclude='./.vscode' \
	--exclude='./build' \
	--exclude='./coverage' \
	--exclude='./node_modules' \
	--exclude='./stitch_university_course_discovery_portal' \
	--exclude='./tests' \
	--exclude='./vendor' \
	--exclude='./Dockerfile*' \
	--exclude='./compose*.yml' \
	--exclude='./compose*.yaml' \
	--exclude='./docker-compose*' \
	--exclude='./phpcs.xml*' \
	--exclude='./phpstan.neon*' \
	--exclude='./phpunit.xml*' \
	--exclude='*.fig' \
	--exclude='*.figma' \
	--exclude='*.psd' \
	--exclude='*.Zone.Identifier*' \
	--exclude='.DS_Store' \
	--exclude='.gitkeep' \
	--create \
	--file=- \
	. | tar --directory="${stage_root}" --extract --file=-

stage_container_path="/app/$(basename -- "${build_root}")/course-discovery"

docker compose \
	--project-directory "${repository_root}" \
	--file "${repository_root}/compose.yaml" \
	run --rm --no-deps composer \
	install \
	--working-dir="${stage_container_path}" \
	--no-dev \
	--classmap-authoritative \
	--no-interaction \
	--no-progress

rm -f -- "${stage_root}/composer.lock"

if [[ ! -r "${stage_root}/vendor/autoload.php" ]]; then
	printf 'Production Composer autoloader was not created.\n' >&2
	exit 1
fi

docker compose \
	--project-directory "${repository_root}" \
	--file "${repository_root}/compose.yaml" \
	run --rm --no-deps \
	--volume "${repository_root}/scripts:/workspace/scripts:ro" \
	--volume "${dist_root}:/workspace/dist" \
	--entrypoint php \
	composer \
	/workspace/scripts/create-plugin-zip.php \
	"${stage_container_path}" \
	/workspace/dist/.course-discovery.zip.tmp

mv -f -- "${archive_temporary_path}" "${archive_path}"
printf 'Plugin archive created at %s\n' "${archive_path}"
