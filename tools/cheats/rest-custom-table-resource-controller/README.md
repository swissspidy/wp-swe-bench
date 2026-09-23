# Cheat: rest-custom-table-resource-controller

Shortcut: everything the reference does (v1 whitelist, v2 routes, keyset pagination, ETag/304,
owner scoping, single-query bulk update), but with two popular simplifications:

- The cursor is unsigned base64 JSON: it is keyset-based and stable under inserts, but a client can
  modify it (the instruction requires modified cursors to be rejected).
- `_fields` is only applied by the REST server when the response is sent, so the queries still read
  every column, including the large `notes` column.

Expected: `LeadsV2Test::test_invalid_cursors_are_rejected` and
`LeadsV2Test::test_fields_only_reads_requested_columns` fail → reward 0.
