# Oxford Course Discovery

A Docker-based WordPress course catalogue with a portable Course Discovery
plugin. The plugin provides server-rendered search, filters, responsive result
cards, pagination, automatic page setup, and deterministic demo data.

## Quick start for testers

Run these commands from the repository root. You need Docker with the Compose v2
plugin and GNU Make; host PHP, Composer, a web server, and a database are not
required.

### 1. Initialize WordPress

```bash
make init
```

This creates the local environment when needed, installs the plugin's locked
dependencies, starts WordPress, activates the plugin, and creates the public
Course Discovery page. It is safe to run again without deleting existing data.

### 2. Confirm the plugin page

```bash
make setup
```

This idempotent command activates the plugin if necessary and normalizes the
managed page to the full-width Course Discovery layout. It is useful after
pulling frontend changes or when testing against an existing local database.

### 3. Load demo catalogue data

```bash
make seed
```

This resets only plugin-owned demo records and creates 40 varied Courses with
Providers, Instructors, Locations, hierarchical Categories, prices, and start
dates. Repeating the command does not accumulate duplicates.

### 4. Open the application

- Course catalogue: <http://localhost:8080/course-discovery/>
- WordPress Admin: <http://localhost:8080/wp-admin>
- Admin username: `admin`
- Admin password: `admin`

The credentials above are development defaults only. After setup, the catalogue
should show 40 Courses and enough filter options to exercise filtering and
pagination. Use `make up` to start an already initialized environment later.

## Common commands

| Command | Description |
| --- | --- |
| `make help` | List available commands |
| `make setup` | Activate the plugin and normalize the Course Discovery page |
| `make up` | Build and start the application |
| `make down` | Stop containers without deleting data |
| `make restart` | Restart application containers |
| `make status` | Show container status |
| `make logs` | Follow application logs |
| `make shell` | Open a shell in the WordPress container |
| `make wp ARGS="plugin list"` | Run WP-CLI |
| `make seed` | Reset and generate a deterministic 40-Course demo catalogue |
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

`php-stubs/wordpress-stubs` provides development-only WordPress declarations
for IDEs and PHPStan. It is not loaded as the WordPress runtime; the real
WordPress installation continues to come from Docker.

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

## Content model

The plugin uses native WordPress content structures for its initial model:

- `cd_course`, `cd_provider`, and `cd_instructor` custom post types
- hierarchical `cd_course_category` terms assigned to courses
- `cd_location` terms assigned to providers; course locations are derived from
  related providers and are not duplicated on courses
- one canonical decimal course price with no assumed currency, plus repeatable
  provider relationships, instructor relationships, and `YYYY-MM` start dates

Metadata keys and WordPress registration live in the infrastructure layer.
Domain value objects remain independent of WordPress so persistence can evolve
without changing domain-facing code. Typed search criteria and filter
composition are backend-independent. WordPress search execution uses a
condition-translator registry and standard `WP_Query` pagination behind
`CourseSearchInterface`. The plugin-owned public shortcode parses validated GET
input into those typed criteria and renders prepared results without exposing
WordPress queries to templates. Persistent caching and dedicated search storage
remain deferred.

## WordPress administration

Administrators manage Courses, Providers, and Instructors through their native
WordPress edit screens:

- Courses use the title for the Course name, the excerpt for the short
  description, the editor for the long description, and hierarchical Course
  Categories. Separate panels manage price, Providers and Instructors, and
  start months.
- Providers use the title for their name, with one or more Locations assigned
  through the native taxonomy control. Their edit screen is intentionally
  minimal because a description is not part of the current content model.
- Instructors use the title for their name. Their edit screen is intentionally
  minimal because biographies are not part of the current content model.

Course locations are always derived from the selected Providers and are never
entered or stored directly on a Course.

### Demo catalogue data

Generate a realistic local catalogue with 40 Courses:

```bash
make seed
```

The Make target runs `wp course-discovery seed --reset`, deleting only content
marked as belonging to the demo seed before recreating it. It creates varied
Providers, Instructors, Locations, hierarchical Categories, prices,
relationships, descriptions, and start months across 2027 and 2028. To exercise
a different page count, generate between 30 and 50 Courses:

```bash
make seed ARGS="--count=50"
```

The underlying command can also update the same deterministic records without a
reset:

```bash
make wp ARGS="course-discovery seed"
```

The command refuses to run unless WordPress reports a `local` or `development`
environment. Its ownership marker is used only to make demo reset safe; modeled
Course values still pass through the plugin's existing content structures and
`CourseMetadataStore`.

## Course Discovery page

Plugin activation creates a published page containing a full-width Group block
and the Shortcode block when one does not already exist. For an existing local
installation, activate the plugin and normalize that page with one command:

```bash
make setup
```

The setup is idempotent. The Make wrapper runs `wp plugin activate
course-discovery` followed by `wp course-discovery setup --force`; `--force`
replaces a matching page's content with the canonical full-width Group and
Shortcode blocks. Without `--force`, the plugin preserves existing page content
and adds the shortcode only when it is missing.

When the plugin folder is copied into another WordPress project, the portable
WP-CLI equivalent is:

```bash
wp plugin activate course-discovery
wp course-discovery setup
```

Alternatively, activate it in WordPress Admin; the activation hook creates the
page automatically. The shortcode can still be placed manually on any page:

```text
[course_discovery]
```

The plugin renders the complete responsive search, filter, result-card, and
pagination interface; no custom theme is required. Searches use validated GET
parameters, so filtered result URLs can be shared or bookmarked and the form
works without JavaScript. A small plugin script progressively enhances the
native filter panel into a mobile drawer while the desktop filter panel remains
sticky. Active filter chips are links that remove one canonical GET value at a
time. The generated page and shortcode root use WordPress's native `alignfull`
signal, with a stronger scoped full-viewport CSS fallback for themes that still
force constrained content widths. Theme navigation and the page title remain
theme-owned.

The public cards display only modeled Course data. They do not add course detail
pages, comparison, application actions, images, duration, degree level, or an
assumed price currency.

## Documentation

- [Architecture and storage decisions](docs/architecture.md)
- [Coding conventions](docs/conventions.md)
- [Frontend design mapping](docs/design.md)
- [Testing strategy](docs/testing.md)
- [Scalability path](docs/scalability.md)
- [Instructions for coding agents](AGENTS.md)

## Project structure

```text
.
├── docs/
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
├── AGENTS.md
├── compose.yaml
├── Makefile
└── README.md
```
