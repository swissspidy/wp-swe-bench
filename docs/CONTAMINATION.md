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

The agent is root inside its container. Mitigations:
- The verifier uses its **own vendored copy** of the grading library (`tests/wpsb/`), not files from
  the image.
- It verifies **WordPress core + the SQLite drop-in** against an md5 manifest shipped with the tests
  (`wpsb_integrity`), so patching core to make tests pass fails the task.
- It **restores the pristine database** (captured at image build) before grading, so agent-side DB
  edits don't carry over. Legacy content must be handled by the code, as it would be in production.
- JS is **rebuilt from source** by the verifier; committed build output is ignored.

Known limitations: the pristine snapshot (`/opt/wpsb/pristine`) and the toolchain in `/opt/wpsb`
(PHPUnit, Playwright, Node) live in the image and could be tampered with by a deliberately malicious
agent. A separate verifier environment (Harbor `[verifier].environment_mode = "separate"`) with
artifacts would close this gap at the cost of shipping the agent's repo as an artifact; this is
a possible future hardening.
