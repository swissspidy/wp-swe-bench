Everything the issue lists, done the "obvious" way: new 4.0 save, fixed 1.x deprecation with
the author split, 2.x/3.x deprecations with numeric/half-star migration, importer and JSON-LD
updated. What it misses is the tricky eligibility case: pages created by the 3.2 CSV importer
have 3.x markup but a *string* rating in the block comment. The 3.x deprecation (numeric
`rating`) drops the string and no longer matches the markup, and the 2.x deprecation (string
rating) doesn't match 3.x markup, so those testimonials stay invalid → the customer-reviews
editor tests fail → reward 0.
