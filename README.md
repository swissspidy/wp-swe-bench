# wp-swe-bench

A long-horizon, **repository-level WordPress engineering benchmark** for coding agents, packaged
as a [Harbor](https://docs.harborframework.com) dataset.

The predecessor, [WordPress/wp-bench](https://github.com/WordPress/wp-bench), is saturated (frontier
models score > 95%): its tasks were "write one PHP function from a spec", with a median reference
solution of ~290 characters, a median of one runtime assertion, a single LLM call, no tools, an
empty WordPress install and prompts hinting at the API to use. wp-swe-bench is the opposite, in the
spirit of SWE-bench Pro / FrontierCode / DeepSWE:

- The agent starts in a **non-trivial existing codebase** (a realistic plugin/theme, typically
  500–3000 LOC, or a pinned real GPL plugin), inside a WordPress site full of **legacy data**.
- Instructions read like **real issues** (bug reports, feature requests, security reports,
  requirement changes) and never name the WordPress API to use.
- Reference solutions are **substantial** (typically 150+ changed lines across 3+ files,
  ≥ 1–2 hours of expert work).
- **Hidden tests** check behaviour thoroughly: PHPUnit against the live site, HTTP/REST requests,
  Playwright in the block editor and on the front end (including a block-validity checker), exploit
  probes, query counts, legacy-data back-compat (fail-to-pass **and** pass-to-pass).
- Several tasks are **multi-step**: requirements change midway and later steps build on earlier work.

Target: frontier agents solve roughly **20–40%** at launch.

## How it works

Each task is a single container (works on any Harbor sandbox; no Docker-in-Docker, no MySQL
sidecar). WordPress 7.1.2 lives on disk at `/wordpress` on **SQLite**; **WordPress Playground CLI**
(PHP-WASM in Node) serves it on `http://127.0.0.1:9400`, while **native PHP 8.3** runs WP-CLI,
PHPUnit and PHPCS against the very same site. The starting state of every task is built from a
Playground **blueprint** plus a seed script at image build time. See [docs/PLAYGROUND.md](docs/PLAYGROUND.md).

```
base/            shared base image (Node 24, Playground CLI, PHP 8.3, Composer, WP-CLI, PHPUnit,
                 PHPCS/WPCS, @wordpress/scripts, Playwright + Chromium, WordPress 7.1.2 + SQLite)
lib/             grading helpers (vendored into each task's tests/wpsb/ by tools/sync-lib.sh)
tasks/<id>/      Harbor tasks (instruction.md, task.toml, environment/, solution/, tests/ | steps/)
tools/           validate.py (oracle=1 / nop=0 / cheat=0), lint.py, sync-lib.sh, retest.sh,
                 build-base.sh, cheats/<id>/ (shortcut solutions that must score 0)
docs/            AUTHORING.md (rubric), DIFFICULTY.md, CONTAMINATION.md, PLAYGROUND.md
```

## Running

```bash
uv tool install harbor

# 1. Build the base image (tasks are `FROM ghcr.io/swissspidy/wp-swe-bench-base:<base/VERSION>`;
#    skip this if you can pull the published image).
tools/build-base.sh

# 2. Run an agent on the whole dataset
harbor run -p tasks -a claude-code -m <model> -n 4

# One task / the oracle / the nop agent
harbor run -p tasks/blocks-callout-innerblocks-migration -a oracle
harbor run -p tasks/blocks-callout-innerblocks-migration -a nop
```

Tasks need no network at runtime. Recommended resources per trial: 4 CPUs, 8 GB RAM (declared in
each `task.toml`). Verification takes ~3–10 minutes per task (Playground + Chromium).

`reward.json` contains the binary `reward` (1 iff every required check passed) plus diagnostic
sub-scores per check (e.g. `phpunit`, `e2e`, `build`, `integrity`, `no_fatals` and the soft `wpcs`
score) and `required_passed` / `required_total`.

### Validating tasks (maintainers)

```bash
tools/sync-lib.sh                       # vendor lib/ into every task's tests/wpsb/
python3 tools/lint.py                   # static checks
python3 tools/validate.py -j 2          # oracle must be 1, nop 0, cheat 0 (offline, --network none)
python3 tools/validate.py <id> --modes oracle --keep && tools/retest.sh <id> oracle
```

## Tasks

_Status table generated from `tools/results/` (see below)._

## Contributing tasks

Read [docs/AUTHORING.md](docs/AUTHORING.md) and study the exemplar
`tasks/blocks-callout-innerblocks-migration`.

## License

GPL-2.0-or-later (task codebases are GPL, like WordPress).
