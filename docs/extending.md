# Extending Course Discovery

Course Discovery exposes a small typed extension surface for site-specific
plugins. Extensions can change WordPress content registration, transform search
intent, add backend-independent filters and conditions, translate those
conditions for WordPress, alter the options shown by existing controls, and map
semantic result orders to `WP_Query`.

These are extension boundaries, not dependency-injection points for core
services. Core filters and translators are always composed first, and each
search receives a fresh copy of their registries.

## Start with the example plugin

[`examples/course-discovery-price-ceiling`](../examples/course-discovery-price-ceiling)
is a standalone, optional WordPress plugin. It adds a currency-specific
maximum-price criterion without changing Course Discovery production code or
adding another storage field:

```text
validated ?example_max_price=2500&example_currency=GBP
    -> PriceCeilingCriterion
    -> PriceCeilingFilter
    -> PriceCeilingCondition
    -> PriceCeilingTranslator
    -> representable amount AND amount <= 2500 AND currency = GBP clauses
```

The example demonstrates these public hooks:

- `course_discovery/search_criteria`
- `course_discovery/register_filters`
- `course_discovery/register_wordpress_condition_translators`

Copy its directory to `wp-content/plugins/`, activate Course Discovery, then
activate the example. A request such as
`/course-discovery/?example_max_price=2500&example_currency=GBP` exercises the
extension.

The explicit currency is essential: the example compares amounts only within
the selected currency and performs no conversion. A GBP ceiling never matches
EUR or USD Courses. Legacy `_course_discovery_price` rows have no currency and
therefore cannot match this extension.

The core `Price` model retains arbitrary exact decimal precision. WordPress
metadata queries do not: this example uses `DECIMAL(65,30)`, so it accepts only
ceilings with at most 35 integer digits and 30 fractional digits. It adds a
canonical-string `REGEXP` clause that excludes stored values outside the same
range before comparison. Out-of-range values are omitted or excluded, never
rounded into matches. Use a different exact-comparison backend if a production
extension must query the full precision supported by `Price`.

The example is intentionally backend-focused. It does not add a form control,
persist the custom query parameters in the built-in pagination links, or add a
new Course field. See [the current frontend boundary](#current-frontend-boundary)
before building a user-facing third-party filter.

## Stable keys and fixed composition rules

Public criterion, filter, condition, translator, and result-order keys must be
non-empty canonical lowercase identifiers containing only letters, numbers,
underscores, or hyphens. Use the same unique key through one custom filter
chain. Duplicate filter and translator keys are rejected; extensions cannot
silently replace core registrations.

Values inside one condition are alternatives (OR). Independent conditions in a
`CourseQuery` are combined (AND). Extensions must not use hooks to reinterpret
those operators.

`SearchCriteria` and `CourseQuery` are immutable. Always return the instance
produced by a `with_*()`, `without_*()`, or replacement method rather than
expecting an object to be mutated.

## Search criteria

`course_discovery/search_criteria` runs after the built-in public request has
been parsed and before any Course filters run. It must return
`OxfordInternational\CourseDiscovery\Application\Search\SearchCriteria`.

An extension may transform the built-in typed values with:

- `with_search_term()`
- `with_providers()`
- `with_locations()`
- `with_start_dates()`
- `with_categories()`
- `with_result_order()`

Third-party input belongs in an immutable implementation of
`SearchCriterionInterface`, added through `with_custom_criterion()` or updated
through `with_replaced_custom_criterion()`. The example plugin validates its
own amount and supported currency GET values at the WordPress boundary and
stores a domain `Price` rather than raw request data:

```php
add_filter(
	'course_discovery/search_criteria',
	static function ( SearchCriteria $criteria ): SearchCriteria {
		$criterion = new PriceCeilingCriterion(
			Price::from_decimal( '2500', Currency::GBP )
		);

		return $criteria->has_custom_criterion( PriceCeilingCriterion::KEY )
			? $criteria->with_replaced_custom_criterion( $criterion )
			: $criteria->with_custom_criterion( $criterion );
	}
);
```

The complete request-parsing example is in
[`course-discovery-price-ceiling.php`](../examples/course-discovery-price-ceiling/course-discovery-price-ceiling.php).
If an extension reads another public input, it owns unslashing once, strict
validation, reasonable bounds, and conversion to a typed value. Do not put
`$_GET`, `WP_REST_Request`, or another WordPress request object into
`SearchCriteria`.

## Registering a Course filter

`course_discovery/register_filters` is an action. Its one argument is the
mutable `CourseFilterRegistry` for the current composition run:

```php
add_action(
	'course_discovery/register_filters',
	static function ( CourseFilterRegistry $registry ): void {
		$registry->register( new PriceCeilingFilter() );
	}
);
```

A `CourseFilterInterface` implementation owns one criterion. `supports()`
checks whether that typed criterion is meaningful, and `apply()` returns a new
`CourseQuery` with at most one condition. It must not read WordPress state,
inspect other filters, or build `meta_query` or `tax_query` arrays.

The example implementation is
[`PriceCeilingFilter.php`](../examples/course-discovery-price-ceiling/src/PriceCeilingFilter.php).

## Transforming the composed Course query

`course_discovery/course_query` runs after all registered filters. It receives
the composed `CourseQuery` and transformed `SearchCriteria`, and it must return
a `CourseQuery`. Its immutable API can inspect, add, remove, or replace
conditions:

```php
add_filter(
	'course_discovery/course_query',
	static function (
		CourseQuery $query,
		SearchCriteria $criteria
	): CourseQuery {
		if ( array() === $criteria->providers() ) {
			return $query;
		}

		// This site ignores the Provider constraint while retaining the selection.
		return $query->without_condition( ProviderCondition::KEY );
	},
	10,
	2
);
```

Use `with_condition()` only for a unique new key,
`with_replaced_condition()` only when that key is already present, and
`without_condition()` for an idempotent removal. Every condition left in the
query must have translator support in the selected execution backend.

## WordPress condition translators

`course_discovery/register_wordpress_condition_translators` is an action. Its
one argument is a fresh `WordPressConditionTranslatorRegistry` for the current
WordPress search:

```php
add_action(
	'course_discovery/register_wordpress_condition_translators',
	static function (
		WordPressConditionTranslatorRegistry $registry
	): void {
		$registry->register( new PriceCeilingTranslator() );
	}
);
```

A `WordPressConditionTranslatorInterface` implementation handles exactly one
condition key. It verifies the concrete condition type and returns a
`WordPressQueryConstraint` containing zero or more:

- metadata clauses;
- taxonomy clauses;
- additional non-reserved `WP_Query` arguments.

Course Discovery combines metadata and taxonomy clauses with top-level AND.
Translators cannot set executor-owned arguments such as post type, status,
pagination, cache flags, `meta_query`, `tax_query`, or result ordering. Two
translators also cannot contribute the same additional query argument.

The complete metadata translator is
[`PriceCeilingTranslator.php`](../examples/course-discovery-price-ceiling/src/PriceCeilingTranslator.php).
It emits three clauses in the same top-level AND group: the stored amount must
match the exact `DECIMAL(65,30)` subset, that amount must be at or below the
ceiling, and the stored ISO code must equal the selected currency. An unknown
condition fails explicitly at execution time instead of being ignored.

Since version 0.2.0 of the example, programmatic construction of
`PriceCeilingCriterion` or `PriceCeilingCondition` throws
`InvalidArgumentException` for an amount outside that exact-comparison subset.
Existing third-party callers should treat that as input rejection. This is an
example-specific backend bound, not a new limit on the core `Price` value
object.

This action extends only `WordPressCourseSearch`. If another
`CourseSearchInterface` backend is composed, that backend needs its own
translation for every custom condition. A WordPress translator does not make a
condition portable to an external index automatically.

## Result ordering

Result order is a two-stage contract:

1. `course_discovery/result_order` returns a semantic `ResultOrder`.
2. `course_discovery/wordpress_result_order_args` maps that semantic key to
   validated WordPress ordering arguments.

For example, a site can make unconstrained searches show newest Courses first:

```php
add_filter(
	'course_discovery/result_order',
	static function (
		ResultOrder $order,
		SearchCriteria $criteria,
		CourseQuery $query
	): ResultOrder {
		unset( $query );

		return $criteria->is_empty()
			? new ResultOrder( 'newest' )
			: $order;
	},
	10,
	3
);

add_filter(
	'course_discovery/wordpress_result_order_args',
	static function (
		array $arguments,
		ResultOrder $order,
		CourseQuery $query
	): array {
		unset( $query );

		if ( 'newest' !== $order->key() ) {
			return $arguments;
		}

		return array(
			'orderby' => array(
				'date' => 'DESC',
				'ID'   => 'DESC',
			),
		);
	},
	10,
	3
);
```

The semantic key is application intent, never a raw WordPress `orderby` value.
The WordPress mapping may return only `orderby`, `order`, `meta_key`, and
`meta_type`, and it must include `orderby`. Invalid output is reported and the
deterministic core ordering is used.

The built-in shortcode currently accepts only the core default order from its
public `order` parameter and renders no ordering control. A custom order can be
selected by a typed caller or by the `course_discovery/result_order` hook, as
above; adding a selectable frontend order requires presentation work too.

## Existing filter options

The dynamic `course_discovery/filter_options/{filter_key}` filter changes the
typed options loaded for an existing built-in control. The current concrete
hooks are:

| Hook | Built-in submitted parameter |
| --- | --- |
| `course_discovery/filter_options/provider` | `provider[]` |
| `course_discovery/filter_options/location` | `location[]` |
| `course_discovery/filter_options/start_date` | `start_date[]` |
| `course_discovery/filter_options/category` | `category[]` |

Each hook receives and must return a list of
`Infrastructure\WordPress\Frontend\CourseFilterOption` objects. For example,
this site-specific callback gives one Provider a public label without changing
the Provider post title:

```php
add_filter(
	'course_discovery/filter_options/provider',
	static function ( array $options ): array {
		return array_map(
			static function ( CourseFilterOption $option ): CourseFilterOption {
				if ( '42' !== $option->value() ) {
					return $option;
				}

				return new CourseFilterOption(
					$option->value(),
					'Online learning partner',
					$option->depth()
				);
			},
			$options
		);
	}
);
```

An option carries its canonical submitted value, display label, and
non-negative hierarchy depth. Extension labels are escaped by the core
template. Invalid hook output is reported and the original built-in list is
retained.

Changing options affects presentation only. It does not change request parsing,
criteria semantics, backend conditions, or result counts. Removing an option
from the form also does not make its otherwise valid URL value invalid.

## Content registration arguments

Five filters expose the corresponding WordPress registration argument array on
`init`:

| Hook | Registration |
| --- | --- |
| `course_discovery/course_post_type_args` | `cd_course` post type |
| `course_discovery/provider_post_type_args` | `cd_provider` post type |
| `course_discovery/instructor_post_type_args` | `cd_instructor` post type |
| `course_discovery/course_category_taxonomy_args` | `cd_course_category` taxonomy |
| `course_discovery/location_taxonomy_args` | `cd_location` taxonomy |

Use these for narrow WordPress registration changes, such as admin placement:

```php
add_filter(
	'course_discovery/provider_post_type_args',
	static function ( array $arguments ): array {
		$arguments['menu_position'] = 21;

		return $arguments;
	}
);
```

Register these callbacks before `init` runs and always return the complete
array. The three post-type filters report non-array output and retain the core
arguments. The two taxonomy registrations require an array; non-array output
currently prevents that taxonomy from registering.

These hooks do not change the documented content meaning automatically.
Adding editor support, a thumbnail, metadata, or another field still requires
the relevant secure persistence or native WordPress handling, REST contract,
tests, and content-model documentation. Registration arguments alone do not
make a new value searchable or render it in Course Discovery results.

## Current frontend boundary

The backend extension architecture is deliberately broader than the current
public form:

- The built-in shortcode parses and renders search text plus Provider,
  Location, Start Date, and Course Category selections.
- Its selected option values, active-filter chips, and active-filter count are
  built from the four option groups; pagination serializes those groups plus
  search text and the core order.
- A custom criterion can affect backend results and `SearchCriteria::is_empty()`
  without automatically receiving a control, selected state, chip, or label.
- A third-party custom query parameter is not automatically emitted by the
  built-in form or preserved in its pagination links.
- Filter-option hooks alter only one of the four existing option lists. A new
  hook suffix does not create a new option loader or form control.
- Condition translators affect backend execution only; they do not expose
  fields to the request parser or template.
- The shortcode template and assets are plugin-owned. There is currently no
  generic public control registry or automatic third-party result-field
  renderer.

An extension that needs a complete user-facing custom filter must currently own
the additional validated input/control and arrange for its state to survive
submission and pagination, while using the typed backend hooks described above.
It must preserve the server-rendered GET baseline, escape output for its final
context, and remain accessible without JavaScript.

If arbitrary third-party filters must render automatically in the core form, a
future feature can introduce a typed control registry covering parsing,
options, selected state, chips, and URL serialization together. That registry
is intentionally deferred; the present backend contracts should not be
misrepresented as a generic frontend form framework.

## Failure behavior and testing

Search-criteria, Course-query, result-order, and filter-option hooks validate
their return types and fall back to the prior core value after reporting
incorrect extension usage. Registry duplicate keys and missing backend
translators fail explicitly.

Keep criterion, filter, condition, and query-composition tests
WordPress-independent. Use the real WordPress integration environment for hook
registration and actual `WP_Query` translation:

```bash
make test-unit
make test-integration
make test-feature
make test-examples
make quality
```

For a custom filter, cover absent input, valid and invalid boundary values,
supported and unsupported currencies, same-currency matches, cross-currency
non-matches, typed criteria replacement, condition composition, translator
registration, real matching and non-matching Courses, duplicate-key rejection,
ordering if used, and any custom state preservation added to a frontend.
