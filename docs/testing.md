# Testing strategy

Run focused suites while developing and `make quality` before finishing.

## Test layers

- **Unit tests** cover WordPress-independent domain behavior without loading
  WordPress: identifier validation, price validation, strict `YYYY-MM` start
  dates, canonical representation, and chronological comparison.
- **Integration tests** boot the real WordPress test framework. They verify post
  type supports, taxonomy registration and attachment, metadata schemas,
  repeatable metadata representation, and registration hook customization. Do
  not mock WordPress in this suite.
- **Feature tests** cover complete plugin behavior across multiple WordPress
  boundaries. Add them when a user-facing workflow, REST operation, or complete
  application use case exists; do not duplicate narrower integration tests.
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

Search filters are not part of the current content-model work. When multiple
filter or storage implementations are introduced, they must reuse shared
contract tests so identical criteria produce identical results across every
implementation. Add targeted integration tests for WordPress query translation
and feature tests for combined user-visible filtering, but do not build those
tests before the feature exists.
