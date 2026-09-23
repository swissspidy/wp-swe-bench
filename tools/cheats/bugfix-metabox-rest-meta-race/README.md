# Cheat: patch the meta box save handler only

Fixes the two most visible reports in `Metabox::save()`:

- skips the sidebar fields when the request is the block editor's meta box request (`meta-box-loader`),
  so sidebar changes are no longer reverted;
- `wp_unslash()`es the submitted values (no more backslashes before quotes);
- saves a checkbox that was not submitted as unchecked.

It does not stop the save handler from running for Quick Edit (whose box prints the same nonce): Quick
Edit now also clears "Featured product" and still wipes SKU, badge, notes and supplier. Bulk Edit
"— No Change —" still sets "No", and products imported from 1.x still come out of the REST API with
`null` stock/featured values (the sidebar shows them unchecked and saving them fails).

Expected: `phpunit` (Quick Edit, Bulk Edit, imported products) and `e2e` (imported product) fail → reward 0.
