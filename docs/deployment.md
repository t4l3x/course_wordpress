# Deployment and distribution

The repository Docker stack is a reproducible development and assessment
environment. The portable deployment unit is the Course Discovery plugin, not
the repository's Nginx, MariaDB, or WordPress containers.

## Build reviewer artifacts

You need Docker with Compose v2, GNU Make, and an initialized, running local
database. `make init` supplies that state for a fresh checkout; `make seed` is
optional if the database snapshot should contain the deterministic demo
catalogue.

Run the quality suite, then build the artifacts:

```bash
make quality
make dist
```

`make dist` replaces only its two previous outputs and creates:

```text
dist/
├── course-discovery.zip
└── database.sql
```

The command reuses `make db-export` for the current development database. It
stages an isolated plugin copy, installs the locked production Composer set with
`--no-dev --classmap-authoritative`, and creates a ZIP whose top-level directory
is `course-discovery/`.

The plugin ZIP includes the generated production autoloader and runtime files.
It excludes tests, development dependencies, coverage and cache output,
PHPUnit/PHPStan/PHPCS configuration, the Composer lock file, Stitch/Figma design
exports, Docker files, and IDE, VCS, or operating-system metadata. `dist/` is
ignored by Git and by the Docker build context.

## Install the plugin

The packaged plugin requires WordPress 7.0 or later and PHP 8.3 or later. The
development containers intentionally remain on PHP 8.5 while CI exercises the
supported PHP versions.

Back up the target site, then install `course-discovery.zip` through WordPress
Admin or WP-CLI and activate it. The ZIP already contains its production
Composer autoloader; do not run a development Composer install on the server.
Activation idempotently creates the managed Course Discovery page when needed.
For an existing installation, the same setup can be requested explicitly:

```bash
wp course-discovery setup
```

Alternatively, place `[course_discovery]` on a page managed by the site. Course
posts intentionally do not expose native public single pages or archives.

## Treat the SQL file as a local snapshot

`database.sql` is a snapshot of the complete current development database. It
is reviewer convenience data, not a plugin schema migration and not a required
part of installing the ZIP. It can include local users, password hashes, site
URLs, options, and any other data present in the development database.

Import it only into an isolated local environment that may be replaced:

```bash
make db-import FILE=dist/database.sql
```

Do not import the snapshot over an existing site, publish it as a release
asset, or treat its development defaults as production credentials.

## Production boundary

The supplied Compose configuration has deliberately convenient local defaults;
it is not a production orchestration template. A production WordPress platform
must provide its own HTTPS termination, backups, database lifecycle, strong
secrets, observability, and update/rollback process. Disable debug display, set
the environment type to `production`, and keep `.env`, SQL snapshots, and real
credentials outside source control and public artifacts.

Native WordPress posts, taxonomies, and metadata remain the source of truth.
There is currently no external search service or derived projection to deploy
or synchronize. See [Scalability](scalability.md) for the measurement triggers
that would justify adding one later.
