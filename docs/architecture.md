# Architecture

## Native WordPress content

Courses, providers, and instructors are custom post types because each is an
independently managed content entity that WordPress administration already
understands. Native fields hold the editorial content:

| Content type | Native fields |
| --- | --- |
| Course | title, excerpt, content |
| Provider | title |
| Instructor | title |

All three post types are admin-manageable but are not publicly queryable and do
not expose native single pages, archives, or rewrite rules. Provider and
Instructor are internal reference entities and are not exposed through the
default REST API. Course remains available to REST for the block editor and its
registered metadata; the Course Discovery feature, rather than native WordPress
templates, owns future frontend exposure.

Course categories are a hierarchical taxonomy on courses, allowing more than
one category per course. Locations are a taxonomy on providers. A course may
relate to one or more providers, so its locations are derived from those
providers. Copying locations into course metadata would introduce two sources
of truth and make updates prone to drift.

Domain identifiers use positive integers because WordPress post IDs are the
current persisted identity. `CourseId`, `ProviderId`, and `InstructorId` remain
separate types so callers cannot accidentally mix entities with different
meaning. They do not share a base type because there is no polymorphic consumer,
and they do not query WordPress for existence or post type; those checks belong
at a boundary that has the necessary use-case context.

## Metadata and relationships

WordPress posts, taxonomies, and metadata are the source of truth for this
initial version; no custom tables or schema migrations are needed.

A course has one canonical, non-negative decimal price stored as a string so
business logic does not rely on floating point; no currency is assumed. The
metadata contract is:

| Constant | Key | Type and cardinality |
| --- | --- | --- |
| `PRICE_KEY` | `_course_discovery_price` | one decimal string |
| `PROVIDER_ID_KEY` | `_course_discovery_provider_id` | repeatable positive integer |
| `INSTRUCTOR_ID_KEY` | `_course_discovery_instructor_id` | repeatable positive integer |
| `START_DATE_KEY` | `_course_discovery_start_date` | repeatable `YYYY-MM` string |

Each provider ID, instructor ID, and start date uses its own metadata row rather
than an opaque serialized array. `CourseMeta` centralizes the keys and explicit
WordPress type, sanitization, authorization, and REST contracts.

WordPress metadata access is isolated in `CourseMetadataStore`, including
normalization, de-duplication, deterministic date ordering, and replacement of
repeatable relationships and dates. Callers work with typed domain identifiers,
`Price`, and `StartDate` values rather than `WP_Post`, `WP_Query`, globals, or
raw metadata arrays. It is the supported metadata write boundary; registered
REST schemas and sanitizers reject invalid external input.

Registration arguments can be changed through five deliberately narrow filters:

- `course_discovery/course_post_type_args`
- `course_discovery/provider_post_type_args`
- `course_discovery/instructor_post_type_args`
- `course_discovery/course_category_taxonomy_args`
- `course_discovery/location_taxonomy_args`

Each filter must return the corresponding WordPress registration argument
array. Invalid post-type filter output is reported as incorrect extension usage
and the built-in arguments are used, so an extension cannot silently disable a
core content type.

## WordPress administration boundary

The native post edit screens remain the administration interface. WordPress
owns titles, Course excerpts and editor content, Course Categories, and
Provider Locations. Provider and Instructor screens are intentionally
name-focused. Separate Course meta boxes add only price, Provider and
Instructor relationships, and start months. Course retains WordPress
`custom-fields` support only so its registered metadata is present in the REST
schema; the generic Custom Fields meta box is removed from its editor.

Custom Course metadata follows one write path:

```text
WordPress Course edit form
    -> post type, autosave/revision, nonce, capability, and input validation
    -> Price, ProviderId, InstructorId, and StartDate values
    -> CourseMetadataStore
    -> WordPress post metadata
```

Relationship IDs are checked against their expected WordPress post type before
they become domain identifiers. The complete submission is validated before
metadata replacement begins, so invalid input does not partially update the
Course. Locations do not pass through this flow: they remain terms on Providers
and are derived for a Course through its Provider relationships.

## Course search composition

Search composition remains separate from request parsing and backend execution:

```text
request parsing (future)
    -> SearchCriteria
    -> course_discovery/search_criteria
    -> core CourseFilterRegistry + extension filters
    -> CourseFilterPipeline
    -> CourseQuery
    -> course_discovery/course_query
    -> course_discovery/result_order
    -> CourseSearchInterface
    -> WordPressCourseSearch
    -> core WordPressConditionTranslatorRegistry + extension translators
    -> WP_Query
    -> CourseSearchResult
```

`SearchCriteria`, `SearchTerm`, and `ResultOrder` live in `Application/Search`
because they describe the search use case rather than durable business state.
Built-in `SearchCriteria` inputs remain first-class typed properties: optional
`SearchTerm`, Provider, Category, and Location identities, canonical domain
`StartDate` values, and semantic `ResultOrder`. A future request parser must map
blank text to no `SearchTerm`; empty selections already mean no constraint.
Duplicate built-in selections are normalized before filters receive them.
`CategoryId` and `LocationId` remain domain value objects because those
identities exist independently of searching.

Third-party input uses `SearchCriterionInterface`, not an
`array<string, mixed>`. Immutable `SearchCriteria` copies can add, inspect,
check, or explicitly replace typed custom criteria by stable key. Custom
criteria participate in `is_empty()` but do not extend the built-in collection
normalization union. A Difficulty, Delivery Mode, or Language filter can
therefore add its own criterion, filter, and condition without adding a
property to `SearchCriteria` or changing any existing filter or pipeline code.

Each `CourseFilterInterface` implementation contributes at most one typed query
condition and knows nothing about other filters. Values inside a condition use
OR semantics, while separate conditions in `CourseQuery` use AND semantics. For
example:

```text
(provider=1 OR provider=2)
AND
(location=10 OR location=20)
AND
(category=5)
```

The WordPress text translator uses native `WP_Query` search with explicit
`post_title`, `post_excerpt`, and `post_content` columns. The Location
translator first finds Providers carrying any selected Location term and then
matches Courses carrying any of those Provider relationship IDs. Locations
remain derived and are never copied to Course metadata. Category translation
uses `include_children => true`, so selecting a parent Category also includes
Courses assigned to its descendant Categories.

AND across conditions and OR among a condition's selected values are fixed
Course Discovery rules, not configurable query operators. `CourseQuery` is a
domain-specific search specification, not a generic boolean tree, SQL AST, or
`WP_Query` wrapper. It contains no `WP_Query`, `meta_query`, or `tax_query`
concepts. `CourseSearchInterface` is the Application execution boundary;
`WordPressCourseSearch` implements it with raw query arrays contained entirely
in Infrastructure. Another adapter can therefore translate the same intent to
indexed lookup tables or an external search service without changing criteria
or filter implementations.

Custom filter registration is only complete when the selected backend can
translate its condition. Each execution backend must therefore provide a
translator for every condition it accepts, including third-party conditions:

```text
CustomCriterion
    -> CustomFilter
    -> CustomCondition
    -> backend ConditionTranslator
    -> WP_Query or another search implementation
```

The WordPress adapter uses one small Strategy registry keyed by condition key.
Each translator returns WordPress metadata clauses, taxonomy clauses, or
additional constraints for exactly one condition. The executor sets a
top-level `relation => AND` for metadata and taxonomy groups; each built-in
multi-value translator uses `IN`, preserving OR within that filter. Unknown
conditions fail instead of being ignored.

WordPress Infrastructure publishes these typed extension contracts:

- `course_discovery/register_filters` receives a `CourseFilterRegistry` action
  argument containing the core filters; extensions add unique
  `CourseFilterInterface` implementations.
- `course_discovery/search_criteria` must return `SearchCriteria`.
  Extensions may use this hook to add or replace their typed custom criteria.
- `course_discovery/course_query` must return `CourseQuery` and receives the
  transformed criteria as context. Its immutable API supports adding,
  inspecting, removing, and replacing conditions.
- `course_discovery/result_order` must return `ResultOrder`; ordering keys are
  application semantics, not raw WordPress `orderby` values.
- `course_discovery/register_wordpress_condition_translators` receives a fresh
  copy of the core `WordPressConditionTranslatorRegistry` for each search. A
  custom condition must register its WordPress translator here.
- `course_discovery/wordpress_result_order_args` maps semantic `ResultOrder`
  keys to validated WordPress ordering arguments.
- `course_discovery/filter_options/{filter_key}` is the reserved public naming
  convention for future option-provider hooks. No `list<mixed>` contract is
  published yet; a concrete, typed option contract will be introduced only when
  option providers are implemented, including the information hierarchical
  Categories require.

`Plugin` composes core filters and core translators directly into their base
registries. Public registration actions extend fresh copies of those registries;
core behavior therefore does not depend on extension hooks. Invalid duplicate
keys are rejected instead of silently replacing core behavior. The same
application search intent can later be translated by `WP_Query`, custom lookup
tables, or an external search service without weakening its typed criteria.

## Boundaries and evolution

`Plugin` is the composition root. Registration and persistence implementations
live under `Infrastructure/WordPress`; search orchestration lives under
`Application`; durable value objects and rules live under `Domain`.
Dependencies point inward:

```text
WordPress hooks and storage -> infrastructure adapters -> application -> domain
```

This boundary allows relationship or date persistence to move to indexed lookup
tables later while callers keep the same domain-facing contract. A denormalized
search projection can likewise be rebuilt from WordPress source data without
moving WordPress concerns into the domain. Any future custom schema must use
versioned, repeatable migrations.
