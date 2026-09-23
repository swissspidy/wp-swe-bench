The "obvious" 2.0 rebuild: table + plan child blocks, count control, featured
exclusivity and locking all work, and 1.3–1.6 tables migrate. But it takes three
shortcuts a hurried developer takes:

- the existing 1.0–1.2 deprecation is left as is (it migrates to the 1.6 attribute
  format, which the new block no longer understands), so 1.0 tables open empty;
- the table is rendered dynamically without special-casing tables that were never
  re-saved, so their old markup gets wrapped in a second table element (`--cols-0`);
- the JSON-LD generator still reads the `plans` attribute, so new tables have no offers.

Expected: editor migration test for the 1.0 table, legacy front-end PHPUnit tests and
the structured-data assertions fail → reward 0.
