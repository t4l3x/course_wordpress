# Testing strategy

Run focused suites while developing and `make quality` before finishing.

## Test layers

- **Unit tests** cover WordPress-independent domain and application behavior
  without loading WordPress: identifier validation, exact price-amount
  canonicalization, supported-currency validation, strict `YYYY-MM` start
  dates, search criteria normalization, filter semantics, registry behavior,
  and query composition.
- **Integration tests** boot the real WordPress test framework. They verify post
  type supports, taxonomy registration and attachment, metadata schemas,
  repeatable metadata representation, registration hook customization, and real
  `WP_Query` search execution. Do not mock WordPress in this suite.
- **Feature tests** cover complete plugin behavior across multiple WordPress
  boundaries. The Course Discovery shortcode tests exercise public GET input
  through the real filter/search pipeline, result preparation, template
  rendering, and asset enqueueing without duplicating translator semantics.
- **Example integration tests** load optional extension code in the real
  WordPress environment. The price-ceiling proof covers supported input,
  currency isolation, translator registration, and conservative exclusion at
  its exact `DECIMAL(65,30)` boundary.
- **End-to-end tests** use TypeScript and headless Chromium through Playwright
  against the running, deterministically seeded public catalogue. They cover
  user-visible rendering, GET search and filtering, pagination, the accessible
  mobile drawer, and the no-JavaScript baseline. They complement rather than
  replace lower test layers.

Use:

```bash
make test-unit
make test-integration
make test-feature
make test-examples
make test
```

The browser suite requires the initialized development HTTP stack and seeded
demo catalogue:

```bash
make init
make seed
make test-e2e
```

This target installs locked npm dependencies in the Playwright tools container,
type-checks the TypeScript suite, and runs Chromium headlessly. It is separate
from `make quality` because browser tests require a running seeded site. The
seed reset touches only plugin-owned demo records; GitHub Actions uses a fresh
ephemeral stack.

## End-to-end conventions

- Keep shared environment values and public routes in `tests/e2e/config.ts`.
  Specs must not read `process.env` directly or duplicate route strings.
- Name executable tests `*.spec.ts`, keep E2E support code under `tests/e2e`,
  and pass strict TypeScript checking before starting a browser.
- Seed deterministic demo data before the suite. Tests should remain
  independent, anonymous, and read-only unless a scenario explicitly owns its
  cleanup.
- Prefer accessible role, label, and visible-text locators. Use
  `[data-course-discovery]` only to scope the plugin surface; do not couple
  assertions to layout CSS.
- Use Playwright web-first assertions and navigation waits instead of fixed
  sleeps.
- Test the server-rendered no-JavaScript baseline separately from progressively
  enhanced behavior.
- Keep the Playwright package and Docker image versions identical. Run
  headlessly by default and use retry traces and failure screenshots for
  diagnosis.

Integration and feature suites must use the isolated test database configured
by the project, never a development or production database.

`tests/stubs/wp-phpunit.php` is scanned only by PHPStan. Runtime integration and
feature suites load the installed WordPress PHPUnit functions and bootstrap, so
their `WP_UnitTestCase`, factories, functions, hooks, and metadata behavior are
the real WordPress implementations rather than test doubles.

## Continuous integration

GitHub Actions keeps failures attributable to one quality layer. Separate jobs
run strict Composer validation, locked-dependency audit, PHP syntax checks,
WordPress Coding Standards, PHPStan, unit tests, real-WordPress integration
tests, optional example integration tests, feature tests, and vanilla
JavaScript syntax checks. A separate Playwright job provisions and seeds the
full HTTP stack, type-checks the E2E suite, runs Chromium headlessly, and uploads
the report and retry traces. PHP lint includes the optional example plugin and
distribution helper rather than checking only production plugin classes.

The supported runtime minimum is PHP 8.3; Docker development remains on PHP
8.5. Unit tests run on PHP 8.3, 8.4, and 8.5. Integration tests run on PHP 8.3
and 8.5 with WordPress 7.0.2 and an isolated MariaDB 12.3.2 service. Feature
tests run the same real stack on PHP 8.5, and JavaScript syntax is checked with
Node 22.23.1. Browser coverage uses Playwright 1.62.0 with its matching Chromium
build. This matrix checks the minimum and development runtime without claiming
that isolated unit tests alone prove WordPress compatibility.

## Risk and regression coverage

High-risk areas include hook timing and customized registration arguments,
taxonomy attachment, metadata type and cardinality, paired price amount and
currency persistence, legacy-only price handling, corrupted persisted values,
relationship identifier validation and replacement behavior, de-duplication
and ordering, empty replacement, date/price validation, authorization at write
boundaries, REST price-pair final-state validation, isolation of invalid values
from unrelated presenter data, and accidental WordPress dependencies in the
domain. Put each regression test at the lowest layer that can reproduce the
failure, adding a broader test only when the integration itself is significant.

For Course administration, regression coverage must include meta-box
registration scope, post-type checks, nonce and capability enforcement,
autosave and revision guards, supported and unsupported currencies, paired
amount-and-currency validation, clearing optional metadata, wrong-post-type
Provider and Instructor IDs, and validation of the complete submission before
any destructive replacement. These paths belong in the real WordPress
integration environment rather than mocked unit tests.

Filter composition is a high-risk regression area because one operator change
can silently broaden or narrow every result. Every new Course filter must test:

- empty selection adds no condition;
- one selected value produces one condition;
- multiple values remain alternatives inside that condition using OR;
- composition with another filter creates separate top-level AND conditions;
- invalid values are rejected at the typed boundary where relevant;
- registration through `course_discovery/register_filters` works without
  modifying existing filters, while core filters remain present;
- third-party typed criteria flow through their filter into a custom condition
  without changing built-in `SearchCriteria` fields or filters;
- custom conditions remain present in the composed query;
- query extension hooks can add, remove, or replace conditions without mutating
  the original query;
- the execution backend has translator coverage for the condition once a
  backend exists.

Tests for the filter contract remain isolated from WordPress. Integration tests
boot real WordPress to verify hook registration and typed hook transformations.
WordPress translator tests must use real posts, repeatable metadata rows,
taxonomy terms, and `WP_Query`; cover OR within Provider, Location, StartDate,
and Category conditions, AND across conditions, parent Categories including
descendants, derived Locations with and without matching Providers, all three
text columns, ordering, pagination, empty queries, custom translator
registration, preservation of core translators, and deterministic duplicate-key
rejection. Filter composition and backend condition translation are both
high-risk regression areas. Custom-criteria tests must also cover empty
semantics, duplicate keys, and immutable replacement.

The public frontend adds boundary-focused integration coverage for typed GET
parsing, positive ID validation, canonical start-date validation, the sanitized
search-term limit, bounded and deduplicated multi-select values, page and
per-page bounds, published Provider options, Location terms, distinct
chronological start months, hierarchical Categories, and typed option extension
hooks. Feature coverage verifies shortcode
registration, selected-value persistence, modeled result fields, result counts,
both empty states, escaping, asset enqueueing, and pagination URLs preserving
every active filter. CSS implementation details and simple drawer event wiring
are intentionally not asserted.

The development catalogue seeder is exercised in the real WordPress integration
suite. Its regression verifies two identical runs do not duplicate content,
modeled metadata is written through `CourseMetadataStore`, Provider Locations
and hierarchical Course Categories are assigned through native taxonomies, all
of GBP, EUR, and USD appear in the generated catalogue, and reset removes the
marked seed catalogue.

Landing-page installer integration tests verify canonical full-width Group and
Shortcode-block creation, repeatable page reuse, preservation of existing
content during normal setup, publication of an existing draft, and explicit
`--force`-equivalent normalization. Feature coverage also verifies the rendered
shortcode root carries WordPress's full-alignment class. The activation hook and
WP-CLI command share this installer so their persistence behavior is covered
without faking WordPress.
