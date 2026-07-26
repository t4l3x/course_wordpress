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
