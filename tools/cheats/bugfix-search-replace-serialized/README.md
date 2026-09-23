# Cheat: "safe unserialize" that skips objects

The obvious hardening of the existing replacer: unserialize with classes disabled (so the legacy cache
objects are no longer woken up), recurse into nested serialized strings, replace the JSON-escaped and
URL-encoded forms too (sequential `str_replace()` per form), widen the LIKE pre-filter to all forms,
drop the double escaping, skip GUIDs by default, and make dry runs compute but not write.

What it misses: with classes disabled every stored object other than `stdClass` becomes
`__PHP_Incomplete_Class`, which can't be modified, so the cheat leaves those objects untouched. The
old slider slides (`_old_slider_slides`) and the `Acme_Legacy_Cache_Item` objects in meta and options
keep the old URL, and the counts are off. It also re-serializes values after an unserialize round trip
instead of rewriting them token by token.

Expected: `phpunit_api` (unloaded-class / stored-object cases) and `phpunit_cli` / `phpunit_admin`
(stored values of those fixtures, report counts) fail → reward 0.
