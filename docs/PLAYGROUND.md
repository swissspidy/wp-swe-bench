# WordPress Playground + native PHP: architecture and caveats

wp-swe-bench runs WordPress in a **single container** without MySQL or Docker-in-Docker,
so tasks work on every Harbor sandbox (local Docker, Daytona, Modal, E2B, …).

## How it fits together

```
/wordpress                          WordPress 7.1.2 (pinned), on disk
├── wp-config.php                   shared by both runtimes (base/wordpress/wp-config.php)
└── wp-content/
    ├── db.php                      sqlite-database-integration 3.0.2 drop-in
    ├── database/.ht.sqlite         the database (SQLite, rollback journal)
    ├── mu-plugins/wpsb-environment.php   mail capture → wpsb-mail.log
    └── plugins|themes/<task repo>  $WPSB_REPO (git) – the agent's working directory

Playground CLI (Node 24, PHP 8.3 → WASM)  ──serves──►  http://127.0.0.1:9400   (wpsb-server)
Native PHP 8.3                             ──loads──►  WP-CLI (`wp`), PHPUnit, PHPCS
```

Both runtimes read the same files and the same SQLite database. Playground is started with
`--mount-dir-before-install /wordpress /wordpress --wordpress-install-mode=do-not-attempt-installing`
so it serves the on-disk site as-is. WordPress is installed at image build time by
`wpsb-provision` (native `wp core install`), then the task's **Playground blueprint** is applied
with `wp-playground-cli run-blueprint` against the same directory, then an optional native
`seed.sh` runs, and finally the DB + uploads are snapshotted to `/opt/wpsb/pristine`.

## Verified behaviour (validated while building the exemplar)

| Question | Answer |
|---|---|
| Can native PHP and Playground share one SQLite DB? | **Yes**, with `SQLITE_JOURNAL_MODE=DELETE` (set in wp-config). |
| Does WP-CLI work? | **Yes, natively** (`wp` wrapper → wp-cli.phar 2.12.0 on PHP 8.3). Not inside Playground (the blueprint `wp-cli` step downloads a phar at runtime, so avoid it offline). |
| PHPUnit? | **Yes, natively** (PHPUnit 11.5), loading `/wordpress/wp-load.php` via `lib/phpunit/bootstrap.php`. Transactions (`START TRANSACTION`/`ROLLBACK`) work through the SQLite driver. |
| PHPCS/WPCS? | **Yes, natively** (PHPCS 3.13, WPCS 3.2). |
| Composer? | Available natively (Composer 2.10). Tasks must not need network at runtime, so vendor any PHP dependencies into the starting codebase. |
| Playwright + Chromium against Playground? | **Yes.** Block editor loads take ~8–10 s; each E2E test ~8–15 s. |
| `@wordpress/scripts` builds? | **Yes**, from the shared toolchain in `/opt/wpsb/node/node_modules` (symlinked into the repo). |
| Offline at runtime? | **Yes.** Oracle/nop/cheat validation runs with `docker run --network none`. `WP_HTTP_BLOCK_EXTERNAL` is on. |
| DOM / libxml in Playground PHP? | Yes (`DOMDocument` works in both runtimes). |
| Pretty permalinks in Playground? | Yes. |
| Multisite? | **Yes** (subdirectory; `wp core multisite-convert` in seed.sh). Sub-site front ends, REST, admin and Network Admin work through Playground; `/<site>/wp-login.php` is not rewritten (build auth cookies directly). ~1,100 tables → ~2 s per sub-site request. |
| Schema introspection via the SQLite driver? | `SHOW COLUMNS`, `DESCRIBE`, `SHOW INDEX`, `information_schema`, `ALTER TABLE` ADD/DROP/CHANGE COLUMN and ADD/DROP INDEX, `CONCAT`, `CASE` work. `SUBSTRING_INDEX` is unsupported, `GET_LOCK` is not meaningful, dropping a nonexistent column reports success. |
| Reset the DB inside a PHPUnit run? | `sqlite3 <db> ".restore /opt/wpsb/pristine/.ht.sqlite"` restores in place; both native PHP and Playground see it without a restart. |

## Caveats

1. **SQLite, not MySQL.** The `sqlite-database-integration` driver translates MySQL queries
   (it parses MySQL and emulates `information_schema`, `SHOW`, `DESCRIBE`, most functions).
   Avoid tasks whose correctness depends on MySQL-only behaviour: FULLTEXT indexes/`MATCH … AGAINST`,
   collation/charset subtleties, strict-mode errors, `ON DUPLICATE KEY` corner cases, exact
   `EXPLAIN` output, performance characteristics of indexes. Performance tasks measure **query
   counts**, not timings.
2. **Journal mode.** WAL does not work reliably across PHP-WASM and native PHP, and the driver's
   default of switching to WAL on each new connection caused `database is locked` → "Cannot escape
   data without an active database connection" fatals under concurrent Playground workers. The base
   pins `SQLITE_JOURNAL_MODE=DELETE`; don't override it.
3. **Workers.** Playground runs 6 request workers (`WPSB_WORKERS`). Fewer than 6 triggered
   Playground's deadlock warning; with 3 workers and WAL we saw lock timeouts.
4. **PHPUnit transactions hold the write lock.** A test inside a transaction that makes an HTTP
   request to the server (which needs to write, e.g. session tokens) would deadlock. `WPSB\TestCase::http()`
   refuses to run inside a transaction; set `$use_transactions = false` for such test classes.
5. **Cron is disabled** (`DISABLE_WP_CRON`) for determinism; run events explicitly.
6. **No persistent object cache.** Redis/Memcached exist in Playground only as experimental flags
   and not natively. Tests that need a persistent cache must ship their own object-cache drop-in.
7. **Speed.** PHP-WASM is several times slower than php-fpm. Budget verifier timeouts accordingly
   (exemplar: ~6–9 min per verification, including building JS, PHPUnit and 12 editor tests).
8. **Multisite** works in Playground (blueprint `enableMultisite`) and natively. Check that a
   multisite task behaves the same in both runtimes before relying on it.
9. **Blueprint steps that need the network at runtime** (e.g. `wp-cli`, `installPlugin` from a URL)
   only run at image build time, which is fine; the verifier never re-runs the blueprint.
10. **The agent runs as root** in the container (Harbor default). The verifier restores the DB from
    the pristine snapshot, verifies WordPress core + the SQLite drop-in against checksums shipped
    with the tests, and uses the tests' own vendored copy of the grading library. The pristine
    snapshot and the toolchain in `/opt/wpsb` could still be tampered with by a malicious agent; this
    is a known, accepted limitation (see docs/CONTAMINATION.md).
