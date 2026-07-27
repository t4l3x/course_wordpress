# Scalability

The current native WordPress model is intentionally optimized for clarity and
editorial integration, not yet for a catalogue of hundreds of thousands or
millions of courses. Current execution uses standard paginated `WP_Query`
through the `CourseSearchInterface` boundary. Caching, custom indexes, and
external search are not implemented in this version.

## Expected pressure points

`WP_Query` over `wp_postmeta` becomes expensive when several metadata filters
add joins, values need casting, results are sorted by metadata, or total counts
scan a large result set. The standard metadata indexes help locate keys and
posts but are not tailored to every value/range query. Repeated relationship and
date rows also increase join volume. Term and post hydration can cause further
queries if callers do not prime or reuse WordPress caches.

Location filtering currently performs a Provider taxonomy lookup before the
Course metadata query. This keeps Location derivation correct and simple, but
uses `posts_per_page => -1` to resolve every matching Provider and can produce a
large Provider-ID `IN` list for the Course metadata query. Both become
bottlenecks at high scale. Compound metadata and taxonomy filters also require
multiple joins, and standard page-number pagination becomes progressively
slower at large offsets.

Measure slow queries, examined rows, cardinality, response time, and cache hit
rates before selecting an optimization. Keep common filters selective and add
purpose-built composite indexes only with evidence and a versioned migration.

Repeatable course metadata is replaced with WordPress's native delete-then-add
operations. Incoming values are validated and de-duplicated before deletion,
but the sequence is not transactionally atomic: concurrent writers can
interleave, and an add failure can leave a partial new set. Transactional
relationship or lookup tables become worthwhile only when measured scale,
write concurrency, or consistency requirements justify their migration cost.

The Course editor currently renders all available Providers and Instructors in
native multi-select controls. This is appropriate for the assessment and for
small-to-moderate catalogues. At larger volumes, those controls should use a
server-backed autocomplete instead; that UI change can continue passing the
same selected IDs to `CourseMetadataStore` without changing the persistence
representation.

The public filter form likewise loads finite Provider and taxonomy option lists,
while start months use one distinct metadata query. Result presentation
bulk-loads Course and related posts so normal WordPress object and term caches
can serve per-result access. If option lists or repeated requests become a
measured bottleneck, cache the typed option lists with explicit invalidation or
replace only the affected control with a server-backed option source; do not
move filtering into the browser or bypass the search boundary.

## Practical evolution

As volume and query complexity grow, the system can evolve in stages:

1. Tune query shape, request only required fields, avoid unnecessary total
   counts, and use WordPress object caching effectively.
2. Cache stable query results and hydrated entities, with explicit invalidation
   when courses, relationships, or terms change. Redis can later provide a
   persistent object cache.
3. Move high-volume filters and relationships to indexed lookup tables behind
   the existing persistence boundary.
4. Build a denormalized, rebuildable search projection for compound filtering,
   facets, and sorting. Elasticsearch or OpenSearch becomes appropriate when
   relevance, faceting, or catalogue scale exceeds efficient database queries.

WordPress remains the editorial source of truth; derived projections must have
repeatable rebuild and synchronization processes.

## Elasticsearch or OpenSearch path

Introduce external search only after measurements show sustained unacceptable
`WP_Query` latency, examined rows, large Location-to-Provider expansions, or a
product requirement for relevance scoring and facets that WordPress cannot
serve efficiently. It is unnecessary for the assessment-sized catalogue and
would otherwise add synchronization and operational failure modes.

The migration path is to implement another `CourseSearchInterface` adapter that
translates the existing `CourseQuery` conditions into an external query DSL.
Build a versioned projection containing Course text, Provider IDs, derived
Location IDs, start months, Category ancestry, and stable ordering fields.
Synchronize it from WordPress changes, provide a repeatable full rebuild, and
switch indexes through an alias only after validation. Custom conditions still
require a translator for that backend. Application criteria, filters,
conditions, pagination result types, and callers do not need to depend on the
external client.

For future search results, prefer keyset/cursor pagination with a stable,
deterministic order and unique tie-breaker. Large `OFFSET` values force the
database or search engine to scan and discard increasingly many records, so
offset pagination should be limited to shallow result sets where its simplicity
is useful.
