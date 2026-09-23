The obvious "make it batchable" patch: `allow_batch` on the write endpoints, callbacks read
`$request->get_params()` instead of `get_json_params()`/`$_POST`/`$_GET`, DELETE returns a
REST response instead of echo+exit, and `rest_get_max_batch_size` returns 50.

What it misses: validation still lives inside the callbacks, so `require-all-validate`
batches still write the valid requests (and send mails) when one request is invalid; the
per-request static list cache and the legacy position arithmetic are untouched, so several
creates in one batch get the same position, deletes leave gaps, positions past the end create
gaps and 1.2-era gapped lists are never normalized. Expected: BatchTest (require-all-validate,
positions) and PositionsTest fail -> reward 0.
