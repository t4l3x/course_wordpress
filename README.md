# Oxford Course Discovery

A Docker-based WordPress course discovery application. The Course Discovery
plugin is a self-contained Composer package that can be published independently.

## Prerequisites

- Docker with the Compose v2 plugin
- GNU Make

No host PHP, Composer, web server, or database installation is required.

## Installation

```bash
make init
```

This command creates `.env` when needed, installs the plugin's locked Composer
dependencies, starts WordPress and its supporting services, and activates the
Course Discovery plugin. It can be run again without deleting existing data.

## Local access

- Frontend: <http://localhost:8080>
- Admin: <http://localhost:8080/wp-admin>
- Username: `admin`
- Password: `admin`

These credentials are local defaults only.

## Development commands

| Command | Description |
| --- | --- |
| `make help` | List available commands |
| `make up` | Build and start the application |
| `make down` | Stop containers without deleting data |
| `make restart` | Restart application containers |
| `make status` | Show container status |
| `make logs` | Follow application logs |
| `make shell` | Open a shell in the WordPress container |
| `make wp ARGS="plugin list"` | Run WP-CLI |
| `make composer ARGS="--version"` | Run Composer in the plugin directory |
| `make composer-install` | Install locked plugin dependencies |
| `make composer-update` | Update plugin dependencies and the lock file |
| `make composer-validate` | Validate and normalize `composer.json` |
| `make composer-audit` | Audit locked dependencies |
| `make lint` | Check plugin PHP syntax |
| `make cs` | Check WordPress coding standards |
| `make cs-fix` | Fix automatically correctable coding-style violations |
| `make analyse` | Run WordPress-aware PHPStan analysis |
| `make test-unit` | Run isolated unit tests without WordPress |
| `make test-integration` | Run tests with WordPress and the isolated test database |
| `make test-feature` | Run feature tests with WordPress |
| `make test` | Run all test suites |
| `make quality` | Run the complete non-destructive quality pipeline |
| `make db-export FILE=exports/database.sql` | Export the development database |
| `make db-import FILE=exports/database.sql` | Import the development database |
| `make reset` | Delete local containers and development data after confirmation |

## Plugin development

Docker supplies WordPress. Composer belongs only to
`app/plugins/course-discovery` and manages its autoloader and development tools.
The generated plugin `vendor/` directory is not committed; `composer.lock` is
committed so every environment uses the same tool versions.

The same commands can be run directly from the plugin directory:

```bash
composer install
composer run composer:validate
composer run lint
composer run cs
composer run cs:fix
composer run analyse
composer run test:unit
composer run test:integration
composer run test:feature
composer run test
composer run composer:audit
composer run quality
```

Unit tests are isolated and do not load WordPress. Integration and feature tests
load the WordPress test framework and use a separate disposable database. The
root Make targets start this database automatically.

For standalone integration testing outside this Docker project, provide:

```text
WP_CORE_DIR=/path/to/wordpress
WP_TESTS_DIR=/path/to/wp-phpunit
WP_TESTS_DB_HOST=127.0.0.1
WP_TESTS_DB_NAME=wordpress_test
WP_TESTS_DB_USER=wordpress_test
WP_TESTS_DB_PASSWORD=secret
WP_PHPUNIT__TABLE_PREFIX=wptests_
```

Never point integration tests at a development or production database. The
WordPress test framework creates and removes tables in the configured database.

When building a release ZIP, run:

```bash
composer install --no-dev --classmap-authoritative
```

Run it inside the plugin and include the resulting production autoload files and
dependencies. WordPress core must not be included.

## Project structure

```text
.
├── app/plugins/course-discovery/
│   ├── assets/
│   ├── src/
│   ├── templates/
│   ├── tests/
│   ├── composer.json
│   ├── composer.lock
│   ├── phpcs.xml.dist
│   ├── phpstan.neon.dist
│   ├── phpunit.xml.dist
│   └── course-discovery.php
├── docker/
├── scripts/
├── compose.yaml
├── Makefile
└── README.md
```
