# Testing strategy

Run focused suites while developing and `make quality` before finishing.

## Test layers

- **Unit tests** cover WordPress-independent domain and application behavior
  without loading WordPress: identifier validation, price validation, strict
  `YYYY-MM` start dates, search criteria normalization, filter semantics,
  registry behavior, and query composition.
- **Integration tests** boot the real WordPress test framework. They verify post
  type supports, taxonomy registration and attachment, metadata schemas,
  repeatable metadata representation, registration hook customization, and real
  `WP_Query` search execution. Do not mock WordPress in this suite.
- **Feature tests** cover complete plugin behavior across multiple WordPress
  boundaries. The Course Discovery shortcode tests exercise public GET input
  through the real filter/search pipeline, result preparation, template
  rendering, and asset enqueueing without duplicating translator semantics.
- **End-to-end tests** exercise the deployed HTTP/browser stack when a frontend
  or public API makes that cost worthwhile. They complement rather than replace
  unit, integration, and feature coverage.

Use:

```bash
make test-unit
make test-integration
make test-feature
make test
```

Integration and feature suites must use the isolated test database configured
by the project, never a development or production database.

`tests/stubs/wp-phpunit.php` is scanned only by PHPStan. Runtime integration and
feature suites load the installed WordPress PHPUnit functions and bootstrap, so
their `WP_UnitTestCase`, factories, functions, hooks, and metadata behavior are
the real WordPress implementations rather than test doubles.

## Risk and regression coverage

High-risk areas include hook timing and customized registration arguments,
taxonomy attachment, metadata type and cardinality, corrupted persisted values,
relationship identifier validation and replacement behavior, de-duplication and
ordering, empty replacement, date/price validation, authorization at write
boundaries, and accidental WordPress dependencies in the domain. Put each
regression test at the lowest layer that can reproduce the failure, adding a
broader test only when the integration itself is significant.

For Course administration, regression coverage must include meta-box
registration scope, post-type checks, nonce and capability enforcement,
autosave and revision guards, clearing optional metadata, wrong-post-type
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
parsing, positive ID validation, canonical start-date validation, pagination
bounds, published Provider options, Location terms, distinct chronological
start months, hierarchical Categories, and typed option extension hooks. Feature
coverage verifies shortcode registration, selected-value persistence, modeled
result fields, result counts, both empty states, escaping, asset enqueueing, and
pagination URLs preserving every active filter. CSS implementation details and
simple drawer event wiring are intentionally not asserted.

The development catalogue seeder is exercised in the real WordPress integration
suite. Its regression verifies two identical runs do not duplicate content,
modeled metadata is written through `CourseMetadataStore`, Provider Locations
and hierarchical Course Categories are assigned through native taxonomies, and
reset removes the marked seed catalogue.

Landing-page installer integration tests verify canonical full-width Group and
Shortcode-block creation, repeatable page reuse, preservation of existing
content during normal setup, publication of an existing draft, and explicit
`--force`-equivalent normalization. Feature coverage also verifies the rendered
shortcode root carries WordPress's full-alignment class. The activation hook and
WP-CLI command share this installer so their persistence behavior is covered
without faking WordPress.
