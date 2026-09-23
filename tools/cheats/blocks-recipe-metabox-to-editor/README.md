Shortcut: the reference editor panel, Recipe card block and card de-duplication, but the meta is registered
the "obvious" way: `show_in_rest => true` with `auth_callback => '__return_true'`, a loose ingredient schema
(no required/non-empty item, extra properties allowed), plain integer/string types without reading the 1.x
formats (legacy ingredients/times come back as null/garbage in REST and in the panel), kitchen notes public,
anyone who can edit the recipe can set the staff pick.

Expected: REST PHPUnit tests (legacy normalization, private notes, staff pick, validation) and the legacy
recipe E2E tests fail → reward 0.
