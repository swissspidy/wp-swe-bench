Shortcuts that must score 0 (validated per step; the validator runs this cheat for both steps):

- **step-1**: a complete background queue (batches via WP-Cron, per-row progress, claims with
  expiry and a watchdog, cancel, REST API, Import screen), but it keeps the existing
  `Product_Repository::find_by_sku()` (published products only, exact upper-case match). Draft,
  pending and private products and 1.x products with lower-case/space-padded SKUs are not found,
  so imports create duplicates. Expected: QueueTest fails (legacy/draft/private rows, re-import).
- **step-2**: the complete step-2 feature set (retries with backoff, validation, error report,
  admin link, WP-CLI, and the step-1 SKU lookup fix, so only this shortcut differs), but the error report writes cells as-is ("fputcsv quotes what needs
  quoting"): SKUs like `=HYPERLINK(...)`, `-X`, `+X`, `@X` are formula injections in Excel.
  Expected: ValidationTest fails.
