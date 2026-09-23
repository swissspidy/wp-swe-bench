# Cheat: synchronous get_sites() loops

Fixes the stale table names and handles new/deleted sites like the reference, but:

- network activation sets every site up in the activation request (no batching, no
  background runs, no first-request set-up), using `get_sites()` with its default limit
  of 100 sites;
- network deactivation removes the daily cleanup from *every* site, including sites where
  the plugin is still activated individually;
- uninstall also iterates `get_sites()` (first 100 sites only).

Expected to fail the large-network tests (more than 25 sites set up during activation,
sites beyond #100 never handled) and the network-deactivation tests.
