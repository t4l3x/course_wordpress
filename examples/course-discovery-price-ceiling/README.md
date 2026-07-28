# Course Discovery price ceiling example

This optional WordPress plugin proves that a third party can add a typed Course
search condition without editing Course Discovery or adding a new content
field. It filters the existing amount and currency metadata through:

```text
?example_max_price=2500&example_currency=GBP
    -> PriceCeilingCriterion
    -> PriceCeilingFilter
    -> PriceCeilingCondition
    -> PriceCeilingTranslator
    -> WP_Query representable amount AND amount <= 2500 AND currency = GBP
```

## Try it

1. Copy this directory to
   `wp-content/plugins/course-discovery-price-ceiling`.
2. Activate Oxford Course Discovery.
3. Activate Course Discovery Price Ceiling Example.
4. Visit the Course Discovery page with a validated decimal amount and explicit
   supported currency, for example
   `?example_max_price=2500&example_currency=GBP`.

The plugin declares Course Discovery as a WordPress plugin dependency and has
no Composer dependencies of its own.

Only GBP, EUR, and USD are accepted. The comparison is deliberately
currency-exact: a GBP ceiling excludes EUR and USD values instead of converting
them. Legacy `_course_discovery_price` rows carry no currency and do not match.
This example adds no exchange-rate or conversion behavior.

Core `Price` values retain arbitrary exact decimal precision. This optional
`WP_Query` example deliberately supports only canonical amounts that fit
`DECIMAL(65,30)` exactly: at most 35 integer digits and 30 fractional digits.
An out-of-range request ceiling is ignored. A stored Course price outside that
range is excluded by a `REGEXP` guard before the decimal comparison, so it is
never rounded into a match. Production extensions that need arbitrary-precision
range queries require a backend capable of exact comparison instead of this
metadata-query example.

### Upgrade note for example callers

Version 0.2.0 rejects direct `PriceCeilingCriterion` or
`PriceCeilingCondition` construction when its `Price` exceeds the comparison
bounds above. Third-party PHP callers should catch `InvalidArgumentException`
at their input boundary or pre-validate to the same
35-integer/30-fractional-digit limit. This restriction belongs only to the
example filter; the core `Price` model is unchanged.

Run its real-WordPress behavior tests with `make test-examples` from the
repository root.

This is a backend extension proof, not production UI. The built-in shortcode
does not render a price-ceiling control and does not automatically preserve
these third-party parameters in its pagination links. See
[`docs/extending.md`](../../docs/extending.md) for the complete extension
contracts and frontend boundary.
