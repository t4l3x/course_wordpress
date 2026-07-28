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
Providers, Instructors, Locations, hierarchical Categories, GBP, EUR, and USD
prices, and start dates. Repeating the command does not accumulate duplicates.

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
| `make lint-examples` | Check PHP syntax in optional example plugins |
| `make js-check` | Check vanilla JavaScript syntax with Node |
| `make cs` | Check WordPress coding standards |
| `make cs-fix` | Fix automatically correctable coding-style violations |
| `make analyse` | Run WordPress-aware PHPStan analysis |
| `make test-unit` | Run isolated unit tests without WordPress |
| `make test-integration` | Run tests with WordPress and the isolated test database |
| `make test-feature` | Run feature tests with WordPress |
| `make test-examples` | Run real-WordPress behavior tests for optional examples |
| `make test-e2e` | Type-check and run headless Chromium tests against the seeded site |
| `make test` | Run all plugin and example test suites |
| `make quality` | Run the complete non-destructive quality pipeline |
| `make dist` | Build the reviewer plugin ZIP and database snapshot in `dist/` |
| `make db-export FILE=exports/database.sql` | Export the development database |
| `make db-import FILE=exports/database.sql` | Import the development database |
| `make reset` | Delete local containers and development data after confirmation |

## Testing and plugin development

Docker supplies WordPress. Composer belongs only to
`app/plugins/course-discovery` and manages its autoloader and development tools.
The generated plugin `vendor/` directory is not committed; `composer.lock` is
committed so every environment uses the same tool versions.

`php-stubs/wordpress-stubs` provides development-only WordPress declarations
for IDEs and PHPStan. It is not loaded as the WordPress runtime; the real
WordPress installation continues to come from Docker.

The plugin-local PHP checks can also be run directly from the plugin directory:

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

The TypeScript Playwright suite exercises the running public catalogue in
headless Chromium. Initialize and seed the development site before running it:

```bash
make init
make seed
make test-e2e
```

`make test-e2e` installs the locked root npm dependencies in the Playwright
tools container, type-checks the tests, and runs them against the local HTTP
stack. It remains separate from `make quality` because it requires an
initialized site and deterministic demo data. CI creates a fresh stack for it.
Shared configuration, selectors, isolation, and debugging practices are
documented in the
[end-to-end testing conventions](docs/testing.md#end-to-end-conventions).

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

Use `make dist` for a production-dependency plugin ZIP and reviewer database
snapshot; do not package the development `vendor/` directory by hand. See the
[deployment and distribution guide](docs/deployment.md) for prerequisites,
contents, installation, and the SQL snapshot's security boundary.

## Architecture overview

`Plugin` is the composition root. WordPress hooks, storage, queries, and view
preparation stay in `Infrastructure/WordPress`; search orchestration and typed
query intent live in `Application`; WordPress-independent values and rules live
in `Domain`. Dependencies point inward, so Domain never calls WordPress and a
future search adapter need not change filter semantics. The detailed decisions
and request flow are documented in [the architecture guide](docs/architecture.md).

The plugin uses native WordPress content structures for its initial model:

- `cd_course`, `cd_provider`, and `cd_instructor` custom post types
- hierarchical `cd_course_category` terms assigned to courses
- `cd_location` terms assigned to providers; course locations are derived from
  related providers and are not duplicated on courses
- one Course `Price` composed from an exact non-negative decimal amount and a
  supported `Currency` (`GBP`, `EUR`, or `USD`), plus repeatable Provider and
  Instructor relationships and `YYYY-MM` start dates

Metadata keys and WordPress registration live in the infrastructure layer.
Domain value objects remain independent of WordPress so persistence can evolve
without changing domain-facing code. Typed search criteria and filter
composition are backend-independent. WordPress search execution uses a
condition-translator registry and standard `WP_Query` pagination behind
`CourseSearchInterface`. The plugin-owned public shortcode parses validated GET
input into those typed criteria and renders prepared results without exposing
WordPress queries to templates. Persistent caching and dedicated search storage
remain deferred.

Price is intentionally one amount-and-currency value today. If requirements
later call for `SinglePrice`, `PriceRange`, or multiple offers, those models can
compose the same exact amount-and-currency values without changing the decimal
representation. Ranges and multiple prices are not implemented now.

## Extending

The backend exposes typed WordPress hooks for criteria transformation, filter
and condition composition, backend condition translators, semantic result
ordering, and the option lists of existing filters. A minimal companion plugin
can therefore extend search without editing Course Discovery production code.
See [Extending Course Discovery](docs/extending.md) for every supported hook and
the runnable example under `examples/`.

This extension boundary is deliberately not a generic frontend framework. The
shortcode currently parses and renders only its built-in Provider, Location,
Start Date, and Category controls. Option hooks change the choices in those
controls; they do not render a new control. A third-party criterion must own its
additional public input and presentation until a concrete requirement justifies
a generic control registry.

## WordPress administration

Administrators manage Courses, Providers, and Instructors through their native
WordPress edit screens:

- Courses use the title for the Course name, the excerpt for the short
  description, the editor for the long description, and hierarchical Course
  Categories. Separate panels manage the price amount and currency, Providers
  and Instructors, and start months.
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
Providers, Instructors, Locations, hierarchical Categories, realistic prices
distributed across GBP, EUR, and USD, relationships, descriptions, and start
months across 2027 and 2028. To exercise a different page count, generate
between 30 and 50 Courses:

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

### Legacy development price data

Current price reads require both `_course_discovery_price_amount` and
`_course_discovery_price_currency`. A legacy `_course_discovery_price` value on
its own is ignored: the plugin does not invent a currency for it. For the local
demo catalogue, run `make seed` to reset and recreate plugin-owned records with
the current two-key representation. For a manually edited Course, open it in
WordPress Admin and explicitly save both Price amount and Currency. An old
legacy metadata row may remain, but it is inert.

For PHP integrations upgrading to 0.3.0, construct prices with
`Price::from_decimal( $amount, Currency::GBP )` (or `EUR` / `USD`) and read the
canonical amount through `$price->amount()`. This replaces the former
one-argument factory and `decimal()` accessor. Metadata integrations must also
treat the new amount and currency keys as one logical pair; no legacy value is
assigned a currency automatically.

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
native filter panel into a mobile drawer while the wide-layout filter panel
remains sticky. Active filter chips are links that remove one canonical GET
value at a time. The generated page and shortcode root use WordPress's native
`alignfull` signal. Scoped CSS fills the parent supplied by the theme and uses
container queries to adapt to the shortcode's actual width; it does not force a
viewport breakout or modify global theme CSS. Theme navigation, page title, and
available content width remain theme-owned.

## Assumptions and known limitations

The current behavior is based on these explicit assumptions:

- Course start dates have month precision (`YYYY-MM`), not a day or time.
- A Course has at most one price, and its supported currencies are currently
  exactly GBP, EUR, and USD; there is no conversion or exchange-rate behavior.
- Locations are assigned only to Providers and are derived for their Courses;
  Courses do not store a second Location value.
- Selecting a parent Course Category includes Courses assigned to descendant
  Categories.
- Only published Courses are discoverable in public search results.
- Native WordPress posts, taxonomies, and metadata are intentional for the
  current catalogue scale and editorial workflow.
- Custom lookup tables or external search projections are deferred until
  measured query volume, latency, or product requirements justify them.
- The frontend is the plugin-owned `[course_discovery]` shortcode; Course posts
  intentionally have no native public single pages or archives.

Known limitations follow from that scope. The public form has built-in controls
rather than arbitrary extension-provided controls. Search uses page-number
pagination and has no persistent result cache, contextual facet counts, or
dedicated search index. Cards show only modeled Course data and provide no
detail, comparison, application, image, duration, degree-level, exchange-rate,
conversion, price-range, or multiple-offer features. See
[frontend design](docs/design.md) and
[scalability](docs/scalability.md) for the detailed boundaries and measured
evolution path.

## Scalability

Current search uses paginated `WP_Query`; finite option lists and related posts
are bulk-loaded where implemented, but persistent caching, custom indexes, and
external search are not. No fixed catalogue-size or throughput claim is made
without measurements from the target hosting environment. The staged options
and their triggers are documented in [the scalability guide](docs/scalability.md).

## Deployment

The Compose stack is for local development and assessment; it is not a
production orchestration template. `make dist` builds
`dist/course-discovery.zip` with production Composer files and exports the
current local database to `dist/database.sql`. Install the ZIP on WordPress 7.0+
with PHP 8.3+; treat the SQL file only as disposable reviewer data, never as a
production migration or public release asset. See
[Deployment and distribution](docs/deployment.md) for the exact workflow.

## Documentation

- [Architecture and storage decisions](docs/architecture.md)
- [Coding conventions](docs/conventions.md)
- [Deployment and distribution](docs/deployment.md)
- [Extension hooks and example](docs/extending.md)
- [Frontend design mapping](docs/design.md)
- [Testing strategy](docs/testing.md)
- [Scalability path](docs/scalability.md)
- [Instructions for coding agents](AGENTS.md)

## Project structure

```text
.
├── .github/workflows/
├── docs/
├── examples/
├── tests/e2e/
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
├── package.json
├── playwright.config.ts
├── tsconfig.playwright.json
└── README.md
```
