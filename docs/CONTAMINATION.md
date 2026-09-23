# Contamination and cheating

## Training-data contamination

- Every `instruction.md` starts with a canary comment containing a per-task GUID
  (`wp-swe-bench canary GUID …`). Harbor strips leading comments before showing the instruction
  to the agent. Please don't include this repository in training corpora.
- Tasks are **original**: the codebases (plugins/themes) are written for the benchmark. Tasks based
  on real plugins or real tickets record the origin in `[metadata].source` (plugin + pinned version,
  Trac/GitHub reference). For historical real bugs we prefer fixes merged after mid-2025. Injected
  bugs are marked as such in `source`.
- Nothing is copied verbatim from public benchmarks (SWE-bench, wp-bench, …).

## Hidden tests and reference solutions

- Tests (`tests/`) are uploaded into the container only **after** the agent finishes (Harbor
  semantics). Reference solutions (`solution/`) are only uploaded for the oracle agent.
- Cheat solutions (plausible shortcuts used to validate test strength) live in `tools/cheats/`,
  outside the task directories, so they're never copied into an environment.
- This repository itself is public. An agent with internet access could in principle look up the
  hidden tests. Run evaluations with network restrictions for the agent where your sandbox allows
  (Harbor `[agent].network_mode` / allowlists; the tasks need no network at runtime).

## In-container tampering

The agent is root inside its container, so grading never trusts that container:

- **Separate verifier.** Every task sets `[verifier] environment_mode = "separate"`. Harbor grades in a
  **fresh container from the pristine task image** (plus the hidden tests, baked into the verifier
  image by the generated `tests/Dockerfile`, so they never exist in the agent's container). Only the agent's repository (`$WPSB_REPO`,
  declared as the task's `artifacts`, minus the `node_modules` symlink) is transferred, and Harbor
  empties the target directory before uploading it, so deleted files stay deleted. Everything else
  the agent could touch (WordPress core, the database and its pristine snapshot, `/opt/wpsb` with
  PHPUnit/Playwright/Node, mu-plugins, `wp-config.php`) comes from the untouched image.
- The verifier also uses its **own vendored copy** of the grading library (`tests/wpsb/`), verifies
  **WordPress core + the SQLite drop-in** against an md5 manifest (`wpsb_integrity`), **restores the
  pristine database** before grading, and **rebuilds JS from source**.

What remains possible: code inside the agent's own plugin/theme runs during grading, so a
deliberately adversarial solution could try to detect the test harness. Reviewing trajectories of
high-scoring runs is the mitigation.
