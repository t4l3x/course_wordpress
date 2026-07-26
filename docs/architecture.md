# Architecture

## Native WordPress content

Courses, providers, and instructors are custom post types because each is an
independently managed content entity with native editorial, administration, and
REST behavior that WordPress already understands. Native
fields hold the editorial content:

| Content type | Native fields |
| --- | --- |
| Course | title, excerpt, content |
| Provider | title, content |
| Instructor | title, content |

The registrations support administration and the WordPress REST API. Public
archive and pretty-permalink rules are deferred with the frontend experience.

Course categories are a hierarchical taxonomy on courses, allowing more than
one category per course. Locations are a taxonomy on providers. A course may
relate to one or more providers, so its locations are derived from those
providers. Copying locations into course metadata would introduce two sources
of truth and make updates prone to drift.

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
replacement of repeatable relationships and dates. Callers work with typed
domain identifiers, `Price`, and `StartDate` values rather than `WP_Post`,
`WP_Query`, globals, or raw metadata arrays. It is the supported metadata write
boundary; registered REST schemas and sanitizers reject invalid external input.

Registration arguments can be changed through five deliberately narrow filters:

- `course_discovery/course_post_type_args`
- `course_discovery/provider_post_type_args`
- `course_discovery/instructor_post_type_args`
- `course_discovery/course_category_taxonomy_args`
- `course_discovery/location_taxonomy_args`

## Boundaries and evolution

`Plugin` is the composition root. Registration and persistence implementations
live under `Infrastructure/WordPress`; value objects and rules live under
`Domain`. Dependencies point toward the domain:

```text
WordPress hooks and storage -> infrastructure adapters -> domain values
```

This boundary allows relationship or date persistence to move to indexed lookup
tables later while callers keep the same domain-facing contract. A denormalized
search projection can likewise be rebuilt from WordPress source data without
moving WordPress concerns into the domain. Any future custom schema must use
versioned, repeatable migrations.
