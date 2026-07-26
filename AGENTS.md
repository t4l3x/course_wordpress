# Coding agent guidance

These instructions apply to the whole repository.

## Architecture

- Keep WordPress-independent concepts in
  `app/plugins/course-discovery/src/Domain`.
- Keep post type, taxonomy, metadata, hook, and other WordPress integration code
  under `src/Infrastructure/WordPress`.
- Use `src/Plugin.php` as the composition root. Keep the plugin bootstrap small.
- Domain code must not depend on WordPress classes, globals, functions, queries,
  or raw metadata arrays.
- Add abstractions only for a present use case. Prefer small, composable classes
  over inheritance and speculative framework layers.
- Preserve existing behavior and keep changes scoped to the requested feature.

## PHP conventions

- Use the PSR-4 namespace prefix
  `OxfordInternational\CourseDiscovery\`, matching paths below `src/`.
- Use `StudlyCaps` class names and one class per file.
- Add `declare(strict_types=1);` to plugin-owned PHP files and type public APIs.
- Follow the configured WordPress Coding Standards and use the
  `course-discovery` text domain.
- Prefix WordPress identifiers with `course_discovery` or `cd_` as established.
  Name public hooks consistently as `course_discovery/...`.
- Sanitize and validate at input boundaries, check capabilities and nonces for
  state changes, and escape output for its final context.

## Tests and quality

- Put WordPress-independent behavior in unit tests.
- Use the real WordPress test environment for integration tests; do not mock
  WordPress in tests labelled integration.
- Use feature tests for complete plugin behaviors that cross multiple
  integration boundaries.
- Add regression coverage at the lowest useful layer for every behavior change.
- Do not weaken quality rules or add broad suppressions to make checks pass.
- Before finishing, run `make quality`. During development, use the focused
  `make lint`, `make cs`, `make analyse`, `make test-unit`,
  `make test-integration`, and `make test-feature` targets as appropriate.

Do not commit generated dependencies, caches, coverage output, or WordPress core.
