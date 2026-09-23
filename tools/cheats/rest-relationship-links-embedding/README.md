The "textbook" implementation: `authors`/`books` fields via `register_rest_field`, links
added in `rest_prepare_{post_type}`, CURIE, visibility filtering, pre-save validation,
`book_author` collection param. Two shortcuts:

- relations are looked up per item (one relation query + related-post lookups per book/
  author), so `/wp/v2/books?per_page=100&_embed` runs well over 100 queries;
- the existing `/acme-library/v1` routes are not touched and still expose draft/private
  books and authors.

Expected: PerformanceTest and LegacyRoutesTest::test_relation_routes_do_not_leak_unpublished_posts
fail -> reward 0.
