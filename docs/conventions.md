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

Prefer native PHP or WordPress validators when they express the complete
invariant without lossy conversion. Keep explicit parsing when representation
is itself part of the contract, such as an exact decimal string. Structurally
similar domain types should remain separate when they prevent semantic mix-ups;
do not add a common interface or base class without a polymorphic consumer.
Sanitization must not stand in for validation when silently changing a public
value would hide invalid input. Use an explicit format check when no built-in
validator expresses the exact invariant.

PHPDoc and exception contracts must describe behavior that can actually occur.
When translating a lower-level validation exception at a persistence boundary,
preserve it as the previous exception so diagnostic context is not lost.

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

## Course search filters

Course filters implement `CourseFilterInterface` and return a new `CourseQuery`;
they do not mutate shared state or call WordPress query APIs. One filter owns one
criterion and must not inspect or coordinate with another filter.

Do not introduce `BaseFilter`, `BaseCondition`, or generic query-expression
inheritance. Add an interface only when there is a real consumer that benefits
from substituting multiple implementations. Small repetition across typed,
semantically distinct criteria and identity APIs is preferable to collapsing
them into generic collections or shared ID abstractions.

New filters register through `course_discovery/register_filters` with a unique,
stable lowercase key. Adding one must not require edits to existing filter
implementations or the pipeline. Values within one condition are alternatives
using OR, and independent conditions are combined using AND.

Built-in filter inputs remain explicit typed `SearchCriteria` properties.
Third-party filters must carry their input through a typed
`SearchCriterionInterface`; do not add a built-in property solely for an
extension, and do not replace the typed API with `array<string, mixed>` or a
generic key/value getter. Custom conditions must have translator support in
every backend that executes them.

Application and Domain search classes must not expose `WP_Query`, `meta_query`,
`tax_query`, posts, request globals, or REST request objects. WordPress hooks
belong in the Infrastructure adapter, use stable public constants, and document
their typed return contracts. Hooks belong at deliberate extension boundaries,
not inside each filter or condition. Public extension keys must be stable and
validated without silent normalization. `CourseQuery` must remain specific to
Course Discovery search intent rather than becoming a generic query language or
query AST. Stable public hooks are API contracts; add hooks only at intentional
registration or transformation boundaries.
