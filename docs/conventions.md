# Coding conventions

## PHP structure

Production code uses the PSR-4 prefix
`OxfordInternational\CourseDiscovery\` rooted at
`app/plugins/course-discovery/src/`. Namespace segments and `StudlyCaps` class
names must match their directories and filenames. Use one class per file.

Every plugin-owned PHP file must declare strict types. Public methods, parameters,
and return values should be typed; use precise PHPDoc for collection shapes that
PHP cannot express. Prefer a small value object when a primitive has meaningful
validation or behavior, such as an identifier, price, or start date. Do not wrap
primitives that have no domain rules.

Prefer composition and explicit collaborators over inheritance, service
locators, or global state.

## WordPress boundaries

Domain code is WordPress-independent. WordPress post types, taxonomies,
metadata, hooks, and persistence adapters belong under
`Infrastructure/WordPress`. Public hook names use the
`course_discovery/...` prefix; global identifiers use the established
`course_discovery` or `cd_` prefixes.

Validate and sanitize untrusted input at the boundary, then preserve the
canonical domain value internally. Escape as late as possible for the output
context, using functions such as `esc_html()`, `esc_attr()`, `esc_url()`, or
`wp_kses_post()` as appropriate. State-changing requests require capability and
nonce checks, and REST routes require explicit permission callbacks. Prepare
dynamic SQL with `$wpdb->prepare()` if direct queries are ever necessary.

Follow the configured WordPress Coding Standards and use `course-discovery` for
translatable strings.
