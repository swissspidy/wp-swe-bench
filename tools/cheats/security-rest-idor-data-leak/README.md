# Cheat: security-rest-idor-data-leak

Shortcut: fix the plugin's own `acme-support/v1` routes (scope the collection per user,
404 other customers' tickets, hide internal notes from customers, agents-only writes) and
even add a capability guard for ticket attachments on the core media endpoint — but leave
the `acme_ticket` and `acme_reply` custom post types registered `public` + `show_in_rest`
with wide-open capabilities (`class-post-types.php` is left untouched).

This is the classic "secured the app, forgot the framework" mistake: the data still leaks
through `GET /wp/v2/acme_ticket`, `GET /wp/v2/acme_ticket/<id>` (incl. `?author=` and `?_embed`),
`GET /wp/v2/acme_reply` (internal notes!), `GET /wp/v2/search`, and the
`/wp/v2/acme_ticket/<id>/revisions` + `.../autosaves` routes.

Expected: `IdorAccessTest::test_core_ticket_routes_do_not_leak`,
`::test_core_search_does_not_surface_tickets`, `::test_core_revisions_and_autosaves_do_not_leak`,
and `IdorHttpTest::test_core_routes_are_not_a_backdoor_over_http` fail -> reward 0.
