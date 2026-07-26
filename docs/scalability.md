# Scalability

The current native WordPress model is intentionally optimized for clarity and
editorial integration, not yet for a catalogue of hundreds of thousands or
millions of courses. Search, caching, custom indexes, and external search are
not implemented in this version.

## Expected pressure points

`WP_Query` over `wp_postmeta` becomes expensive when several metadata filters
add joins, values need casting, results are sorted by metadata, or total counts
scan a large result set. The standard metadata indexes help locate keys and
posts but are not tailored to every value/range query. Repeated relationship and
date rows also increase join volume. Term and post hydration can cause further
queries if callers do not prime or reuse WordPress caches.

Measure slow queries, examined rows, cardinality, response time, and cache hit
rates before selecting an optimization. Keep common filters selective and add
purpose-built composite indexes only with evidence and a versioned migration.

Repeatable course metadata is replaced with WordPress's native delete-then-add
operations. Incoming values are validated and de-duplicated before deletion,
but the sequence is not transactionally atomic: concurrent writers can
interleave, and an add failure can leave a partial new set. Transactional
relationship or lookup tables become worthwhile only when measured scale,
write concurrency, or consistency requirements justify their migration cost.

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

For future search results, prefer keyset/cursor pagination with a stable,
deterministic order and unique tie-breaker. Large `OFFSET` values force the
database or search engine to scan and discard increasingly many records, so
offset pagination should be limited to shallow result sets where its simplicity
is useful.
