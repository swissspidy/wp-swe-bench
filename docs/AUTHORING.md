# Authoring wp-swe-bench tasks

This is the rubric and the mechanics for writing a task. Read it completely, then
study the exemplar **`tasks/blocks-callout-innerblocks-migration/`**. It is the
reference for structure, tone and test rigor.

wp-swe-bench exists because its predecessor (WordPress/wp-bench) is saturated: its
tasks were "write one PHP function from a spec" (median reference solution ~290
characters, a single assertion, empty WordPress, and prompts that name the API to
use). wp-swe-bench is the opposite: **long-horizon, repository-level WordPress
engineering**. Target: frontier agents solve roughly 20–40% at launch.

---

## 1. The rubric (every task must satisfy all of it)

1. **Repository-level.** The agent starts in a non-trivial existing codebase: a
   realistic custom plugin/theme written for the task (typically **500–3000 LOC**,
   with its own structure, conventions, history and quirks), or a pinned real GPL
   plugin/theme. The agent must read and understand existing code to succeed.
   Existing code should have realistic "gravity": helpers the solution should
   reuse, filters/hooks other code depends on, stored data in legacy formats.
2. **Substantial reference solution.** Typically **150+ changed lines across 3+
   files** (measured by `tools/validate.py`, excluding build output and lockfiles).
   Record `estimated_human_hours` (≥ 1–2 h for a strong WordPress developer who
   doesn't know the codebase). **If the fix is < ~100 lines or a single file, make
   the task harder.** Don't pad it: add real requirements (back-compat, migration,
   security, performance, editor behaviour).
3. **Instruction reads like a real issue.** A bug report from a user, a feature
   request from a product owner, a security report. It states the desired
   behaviour, constraints and acceptance criteria. It **never names the WordPress
   API/hook/function/pattern to use** (no "use `register_block_bindings_source`",
   "add a deprecation", "use `$wpdb->prepare`", "add a nonce"). It **never reveals
   test details** (file names, test names, how grading works). User-facing
   contracts (markup that themes style, REST routes and response shapes other
   clients consume, option names, CLI command names, filter names *of the plugin
   itself*) may and should be specified precisely where the tests depend on them.
   The agent must be able to discover everything else from the codebase.
4. **Thorough hidden tests.** Typically **≥ 10 behavioural assertions** (the exemplar
   has ~25 test cases with many assertions each) covering:
   - **fail-to-pass**: the new behaviour;
   - **pass-to-pass**: existing behaviour that must not regress (run the P2P tests
     against the *starting* code; they must pass there!);
   - edge cases, invalid input, capability boundaries;
   - **back-compat of already-stored data** (seed it in the starting state).
   Prefer behaviour (PHPUnit against the live WordPress, HTTP/REST calls against
   the Playground server, Playwright in the editor/front end) over regex on
   source code. Never grade by grepping the agent's source for an API name.
   Binary `reward` = all required checks pass.
5. **WordPress-specific rigor where relevant**: blocks must validate in the
   editor (`assertBlocksValid`); previously saved content must still
   parse/render (deprecations/migrations); security probed adversarially
   (nonces, capability checks, escaping, sanitization, prepared SQL) with exploit
   attempts that must fail **and** legitimate flows that must keep working; i18n
   where user-facing strings change; WPCS on changed files as a *soft* sub-score.
6. **Oracle = 1, nop = 0, and at least one plausible-but-incomplete "shortcut"
   solution = 0.** The cheat lives in `tools/cheats/<task-id>/` (never inside the
   task, so agents never see it) with a `README.md` saying what shortcut it takes.
   Good cheats: the "obvious" fix that misses legacy data; the fix without
   capability checks; the editor-only fix without server rendering; the cache
   without invalidation; the migration that is not idempotent.
7. **No contamination shortcuts.** Don't copy tasks from public benchmarks or
   public PRs verbatim. If a task is based on a real Trac/Gutenberg/GitHub ticket,
   put it in `[metadata].source` and prefer ones merged after mid-2025. Every
   instruction starts with the canary comment (see exemplar).

**Difficulty.** Aim for `hard` (strong agent sometimes solves it) or `very-hard`.
Things that make tasks hard *for the right reasons*: several interacting
requirements; stored legacy data the obvious approach breaks; needing to run and
observe WordPress (editor validation, REST responses) rather than just read code;
security requirements that are easy to half-do; performance requirements checked by
query counts; requirements that change midway (multi-step). Things that make tasks
hard for the *wrong* reasons (avoid them): ambiguity the tests resolve in one
arbitrary way, undocumented magic strings, flaky timing, tests that pin one
implementation choice when several are valid.

---

## 2. Environment architecture

Everything runs in **one container** (works on every Harbor sandbox, no
Docker-in-Docker, no MySQL sidecar):

- **WordPress 7.1.2** is installed on disk at `/wordpress`, backed by **SQLite**
  (`sqlite-database-integration` 3.0.2 drop-in, DB at
  `/wordpress/wp-content/database/.ht.sqlite`).
- **WordPress Playground CLI** (`@wp-playground/cli`, PHP 8.3 compiled to WASM,
  running in Node 24) serves that site over HTTP at **http://127.0.0.1:9400**
  (`wpsb-server start|stop|status|logs`). Login `admin` / `password`.
- **Native PHP 8.3** loads *the same site* for WP-CLI (`wp …`), PHPUnit and PHPCS.
  Both runtimes share files and the SQLite database.
- Base image `ghcr.io/swissspidy/wp-swe-bench-base:<version>` (see `base/`) also
  has Composer, PHPUnit 11, PHPCS + WPCS 3, `@wordpress/scripts` 35, Playwright
  1.63 + Chromium, git, jq, sqlite3.

`wp-config.php` sets `WP_HOME`/`WP_SITEURL` to the server URL, `WP_DEBUG` +
`WP_DEBUG_LOG` (`/wordpress/wp-content/debug.log`), `DISABLE_WP_CRON`,
`WP_HTTP_BLOCK_EXTERNAL` (only 127.0.0.1/localhost allowed; the site is offline),
`WP_ENVIRONMENT_TYPE=local`. An environment mu-plugin captures all mail to
`/wordpress/wp-content/wpsb-mail.log` (JSON lines).

### Helper commands in the image

| Command | What it does |
|---|---|
| `wp …` | WP-CLI for `/wordpress` (native PHP, `--allow-root` implied) |
| `wpsb-server start\|stop\|restart\|status\|logs` | Playground HTTP server on :9400 |
| `wpsb-playground <cmd> …` | Playground CLI bound to the on-disk site (e.g. `run-blueprint`) |
| `wpsb-provision` | **Build time only**: install WP, apply blueprint + seed, build JS, snapshot, `git init` the repo |
| `wpsb-snapshot` / `wpsb-reset` | Save / restore the pristine DB + uploads (`/opt/wpsb/pristine`) |

### Starting state = blueprint + seed

`wpsb-provision` (run from the task Dockerfile) does, in order:
1. `wp core install` (pretty permalinks, UTC).
2. If `$WPSB_REPO/package.json` has a `build` script: symlink the shared
   `node_modules` and `npm run build` (so the starting state is built).
3. Applies `/opt/wpsb/task/blueprint.json` with **Playground** (`run-blueprint`).
   Use it for plugin/theme activation, options, and installing pinned
   wordpress.org plugins/themes (`installPlugin` with a versioned zip URL:
   `https://downloads.wordpress.org/plugin/<slug>.<version>.zip`).
4. Runs `/opt/wpsb/task/seed.sh` (native WP-CLI) if present. Use it for content
   fixtures (posts with legacy markup, users, terms, meta, custom tables…).
5. Snapshots DB + uploads to `/opt/wpsb/pristine` (the verifier restores this).
6. `git init` + "Initial import" commit in `$WPSB_REPO` so the agent (and
   `wpsb_wpcs --changed`) can diff.

The **agent's working directory is `$WPSB_REPO`**, a git repository inside
`/wordpress/wp-content/…` (typically a plugin or theme directory, or
`/wordpress/wp-content` itself when a task spans a plugin *and* a theme).

**Caveats you must design around** (see `docs/PLAYGROUND.md`):
- **SQLite, not MySQL.** Don't write tasks whose correctness depends on MySQL-only
  SQL (FULLTEXT, `ON DUPLICATE KEY` edge semantics, MySQL-specific functions,
  collations, `SHOW` statements, strict-mode errors). Standard `$wpdb`/`dbDelta`
  usage works through the translation layer.
- **Offline.** No outbound HTTP at runtime (both agent and verifier must work
  offline). Mock remote APIs inside the plugin/tests (e.g. `pre_http_request`).
- **No persistent object cache** by default (no Redis/Memcached). Performance
  tasks count queries (`WPSB\TestCase::count_queries`) and can install a test
  object-cache drop-in from the tests themselves.
- **Cron is disabled**; run events explicitly (`wp cron event run --due-now`, or
  `do_action( $hook )` in PHPUnit).
- `wpsb-provision` flushes rewrite rules **before** the blueprint activates plugins; if your
  plugin registers post types/taxonomies/rewrites, run `wp rewrite flush` at the end of `seed.sh`.
- Migrated-but-unedited blocks are not re-serialized by `savePost()`: change something first.
- Fresh installs have a **draft `privacy-policy` page**; delete it before seeding a page with
  that slug (otherwise you get `privacy-policy-2`).
- Seeding **user global styles** from WP-CLI: `WP_Theme_JSON_Resolver::get_user_global_styles_post_id()`
  without a logged-in user creates the `wp_global_styles` post without its `wp_theme` term, and
  every later call creates a duplicate. Set the term explicitly when seeding.
- Playground's HTTP server is slower than php-fpm (PHP-WASM): budget ~1–5 s per
  admin page and 10–30 s for a block-editor load.

---

## 3. Task layout

```
tasks/<task-id>/                      # kebab-case: <category>-<short-slug>
├── instruction.md                    # the issue (starts with the canary comment)
├── task.toml                         # config + metadata (see below)
├── environment/
│   ├── Dockerfile                    # FROM the base image; COPY app; RUN wpsb-provision
│   ├── app/                          # the starting codebase ($WPSB_REPO contents)
│   ├── blueprint.json                # Playground blueprint (activation, options, pinned installs)
│   ├── seed.sh                       # optional native seeding (content fixtures)
│   └── seed/…                        # fixture files used by seed.sh
├── solution/
│   ├── solve.sh                      # reference solution (oracle); runs in the workdir
│   └── files/…                       # usually: full files overlaid onto the repo (+ deletions in solve.sh)
└── tests/                            # HIDDEN: uploaded to /tests only after the agent finishes
    ├── test.sh                       # verifier entrypoint (see below)
    ├── wpsb/                         # vendored copy of lib/ (tools/sync-lib.sh) – do not edit here
    ├── phpunit/*Test.php (+ _helpers.php, fixtures/)
    └── e2e/*.spec.mjs
tools/cheats/<task-id>/solve.sh (+ files/, README.md)   # plausible shortcut that must score 0
```

**Multi-step tasks** (Harbor `steps/`): the root has `task.toml`, `environment/`,
shared `tests/` (at least `tests/wpsb/` + shared helpers) and
`steps/step-1..N/{instruction.md, solution/solve.sh, tests/test.sh, workdir/…}`.
All steps share one container; later steps build on the agent's earlier work
(e.g. build feature → product owner changes requirements → migrate data created
with step 1's format). Each step's tests must re-verify what later steps must not
break. Declare the steps in `task.toml` with `min_reward = 1.0` on each step but
the last (a failed step stops the trial) and `multi_step_reward_strategy = "mean"`.
Cheats for multi-step tasks live in `tools/cheats/<id>/<step-name>/solve.sh`
(the validator runs the oracle for the other steps). **Important:** step N's
starting state is whatever the agent left after step N−1, and the verifier does
not reset code between steps. If step N needs seeded data in the old format,
seed it in `steps/step-N/workdir/setup.sh` (runs before the agent, at step
start) or in the base provisioning.

### Dockerfile (copy this)

```dockerfile
ARG WPSB_BASE_IMAGE=ghcr.io/swissspidy/wp-swe-bench-base:0.1.0
FROM ${WPSB_BASE_IMAGE}

ENV WPSB_REPO=/wordpress/wp-content/plugins/acme-callouts

COPY app/ /wordpress/wp-content/plugins/acme-callouts/
COPY blueprint.json seed.sh /opt/wpsb/task/
COPY seed/ /opt/wpsb/task/seed/

RUN wpsb-provision

WORKDIR /wordpress/wp-content/plugins/acme-callouts
```

- Anything else the task needs (pinned wordpress.org plugin/theme, a second
  plugin) goes into the blueprint or `COPY`. Pin versions; don't fetch "latest".
- **Don't commit `node_modules/`, `vendor/`, `build/` or WordPress.** JS projects
  use the shared toolchain: `wpsb-provision` symlinks `/opt/wpsb/node/node_modules`
  into `$WPSB_REPO` (keep `@wordpress/scripts` in `devDependencies` for realism).
  If PHP dependencies are needed, write them yourself as part of the codebase.
- The agent runs as root in the container. The verifier restores the DB from the
  pristine snapshot and re-checks core integrity, so tampering doesn't help.

### task.toml (copy + adapt)

```toml
schema_version = "1.3"

[task]
name = "wp-swe-bench/<task-id>"
description = "<one sentence>"
authors = [{ name = "wp-swe-bench" }]
keywords = ["wordpress", "..."]

[metadata]
category = "blocks"            # blocks | rest-api | security | performance | plugin-architecture | bugfix
tags = ["block-deprecation", "..."]
difficulty = "hard"            # hard | very-hard
estimated_human_hours = 4
source = "original"            # or "Trac #NNNNN (merged 2025-09)" / "gutenberg#NNNNN" / "<plugin> <version> historical bug"
repo = "<what the codebase is>"
starting_state = "<what is seeded>"
multi_step = false

[verifier]
timeout_sec = 1500.0

[agent]
timeout_sec = 5400.0

[environment]
build_timeout_sec = 1800.0
cpus = 4
memory_mb = 8192
storage_mb = 20480
```

---

## 4. The verifier (`tests/test.sh`)

Always source the vendored helpers and use the check functions: every check
writes `/logs/verifier/checks/<name>.json`, and `wpsb_finish` writes
`/logs/verifier/reward.json` with `reward` (1 iff every **required** check
passed) plus one diagnostic sub-score per check (pass fraction), `required_passed`
and `required_total`. **Don't use `set -e`** (a failing check must not abort
grading); the EXIT trap forces reward 0 if the script dies before `wpsb_finish`.

```bash
#!/usr/bin/env bash
source /tests/wpsb/wpsb.sh
REPO=/wordpress/wp-content/plugins/acme-callouts

wpsb_init
wpsb_expect integrity build phpunit e2e no_fatals   # must-run required checks
wpsb_integrity                       # WP core + SQLite drop-in unmodified
wpsb_php_lint php_lint "$REPO"
wpsb_build build "$REPO"             # npm run build from source (block tasks)
wpsb_reset_site                      # pristine DB/uploads, as if the new code was just deployed
wpsb_server_start
wpsb_phpunit phpunit /tests/phpunit
wpsb_reset_site
wpsb_playwright e2e /tests/e2e
wpsb_no_fatals                       # no PHP fatals in debug.log
wpsb_wpcs wpcs "$REPO" --changed     # soft
wpsb_finish
```

Other helpers: `wpsb_check <name> required|soft <cmd…>` (any command as a
check), `wpsb_server_stop`. Use several PHPUnit invocations (e.g. `phpunit_f2p`,
`phpunit_p2p`) if that makes the diagnostics clearer; all required checks count.

### PHPUnit (`WPSB\TestCase`)

Tests run in **native PHP with WordPress loaded once** (front-end context,
`wp-admin/includes/admin.php` loaded, `SAVEQUERIES` on) against the live site,
with the task's plugins active. Test files are `*Test.php`; optional
`/tests/phpunit/_helpers.php` is auto-loaded.

- Each test runs in a **DB transaction rolled back afterwards** (default). Set
  `protected bool $use_transactions = false;` for tests that call the HTTP server
  (`http()`), spawn `wp_cli()`, or need committed data, and clean up yourself.
- Fixtures: `create_user( $role )`, `create_post( $args )`, `login_as( $user|$role )`.
- REST in-process: `$this->rest( 'GET', '/acme/v1/items', $query, $json_body, $headers )`
  returns a `WP_REST_Response`; `rest_data( $response, $embed )` gives the
  envelope-free data with `_links`/`_embedded` like the real server.
- HTTP against Playground (real request lifecycle, cookies, nonces, redirects):
  `$login = $this->http_login( $user_id )` gives valid auth cookies and a REST
  nonce; `$this->http( 'POST', '/wp-admin/admin-ajax.php', [ 'login' => $login, 'body' => [...] ] )`
  returns `status`, `headers`, `body`, `json`. `nonce_for( $user_id, $action, $login['logged_in'] )`
  mints nonces exactly as that logged-in user would see them.
- `count_queries( fn )` → `count`, `queries`, `result` (performance tasks).
- `mails()` / `clear_mails()` read captured outgoing mail.
- `assertNoDoingItWrong()` fails on `_doing_it_wrong()`/deprecation notices.
- `wp_cli( 'my-plugin sync --dry-run' )` runs WP-CLI in a subprocess.

Skipped/incomplete tests count as **failures**. Don't write tests that can only
pass with one specific internal design (private class names, option keys the
instruction didn't specify). Test through public behaviour.

### Playwright (`tests/e2e/*.spec.mjs`)

Specs import `@playwright/test` and `../wpsb/e2e/helpers.mjs`: `login`,
`openEditor(page, postId)`, `newPost`, **`assertBlocksValid(page)`** (fails on any
invalid block, i.e. "This block contains unexpected or invalid content", or an
unregistered block), `assertPostBlocksValid(page, id)`, `getBlockTree`,
`insertBlock`, `savePost`, `getEditedContent`, `getBlockType`, `trackErrors`,
`wp(args)` / `wpEval(php)` / `createPost({...})` (native WP-CLI from the spec).
One worker, no retries, 180 s per test. The server runs at `WPSB_URL`. Use
`page.request.get()` for front-end HTML checks.

### What test.sh must do for blocks

- Rebuild from source (`wpsb_build`). Never trust committed build output.
- `wpsb_reset_site` before front-end/editor checks so that stored content is the
  **pristine legacy content**, i.e. "the new plugin version was just deployed".
- Check seeded legacy content in the editor (`assertPostBlocksValid`) **and** on
  the front end, plus newly created content round-tripping (insert → save →
  reload → valid → front end).

---

## 5. Authoring workflow

1. **Design** the scenario: product context, existing codebase, stored legacy
   data, the change, what can go wrong. Write the cheat idea down first: if you
   can't think of a plausible shortcut your tests catch, the tests are too weak.
2. **Write the starting codebase** in `environment/app/`. Make it realistic
   (docblocks, i18n, sanitization in the old code where it existed, a readme with
   a changelog, some tech debt). It must work: build it, activate it, click through it.
3. **Seed** legacy data (`blueprint.json`, `seed.sh`). Generate block markup with
   the *actual* old code whenever possible (e.g. in the editor via Playwright,
   then copy `getEditedContent()`), because hand-written block markup is easy to get
   subtly wrong. P2P tests on the starting code catch mistakes.
4. **Write the reference solution** (`solution/solve.sh`, usually overlaying
   `solution/files/` and rebuilding). Idempotent; runs in the workdir; offline.
5. **Write the hidden tests** (`tests/`) and the **cheat** (`tools/cheats/<id>/`).
6. `tools/sync-lib.sh && tools/lint.py <id>`
7. `tools/validate.py <id>` builds the image and runs **oracle** (must be 1),
   **nop** (must be 0) and **cheat** (must be 0) offline (`--network none`),
   writing logs to `tools/results/logs/<id>/`. Iterate until all three are
   as expected. Also run the P2P tests against the starting state and
   confirm they pass (nop's `checks/*.json` shows which checks passed).
8. Re-read the instruction as the agent would: is every tested behaviour stated
   or discoverable from the codebase? Is any API named that shouldn't be?

### Checklist before you hand in

- [ ] Starting codebase ≥ ~500 LOC, realistic, builds, works.
- [ ] Reference diff ≥ ~150 lines across ≥ 3 files (check `solution_stats` in `tools/results/<id>.json`).
- [ ] Instruction: issue-style, canary line, no API hints, no test leaks, precise user-facing contracts.
- [ ] ≥ 10 behavioural assertions; F2P + P2P + edge cases + legacy data + security where relevant.
- [ ] oracle = 1, nop = 0, cheat = 0 (validated with `tools/validate.py`).
- [ ] No `node_modules/`, `vendor/`, `build/`, WordPress, or files > 2 MB committed.
- [ ] `task.toml` metadata complete; `source` recorded.
