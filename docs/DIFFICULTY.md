# Difficulty calibration

**Target:** frontier agents solve roughly **20–40%** of tasks at launch (binary `reward`).

## What makes a wp-swe-bench task hard (on purpose)

| Lever | Example |
|---|---|
| Existing codebase to understand | 500–3000 LOC custom plugin/theme with its own abstractions, hooks other code relies on |
| Legacy data | Posts saved by 2–3 older block versions, serialized options, old shortcodes, flat meta keys |
| Interacting requirements | Editor validity **and** server rendering **and** back-compat **and** security |
| Runtime verification needed | Block validation only shows up in the editor; REST shapes only via requests |
| Security done completely | Every access path (collection, single, embed, search, revisions) not just the obvious one |
| Performance with invariants | Bounded query counts *and* exact output parity *and* correct invalidation |
| Changing requirements | Multi-step tasks: step 2 changes the contract and must migrate step 1's data |

## What must NOT make a task hard

- Ambiguous instructions resolved by the tests in one arbitrary way.
- Hidden magic strings, file names or internal APIs the instruction doesn't specify.
- Flaky timing, network, or environment problems.
- Tests that only accept one of several valid implementations.

## Metadata

- `difficulty`: `hard` (a strong agent sometimes solves it) or `very-hard` (requires sustained,
  careful multi-surface work; likely < 20% for frontier agents).
- `estimated_human_hours`: time for a strong WordPress developer who doesn't know the codebase,
  including reading the code and testing their change. The rubric requires ≥ 1–2 hours.

## Calibration plan

1. Pilot with 2–3 frontier agents × 3 attempts (`harbor run -p tasks -a claude-code -m … -k 3`).
2. Per task: solve rate, and per-check sub-scores from `reward.json` (which requirement agents miss).
3. Tasks solved by every agent every time → add requirements (legacy formats, security paths,
   editor behaviour) or merge with a follow-up step. Tasks never solved → check instruction/test
   alignment first (read the trajectories); unsolvable-by-design tasks are bugs, not difficulty.
4. Re-run until the overall rate lands in the target band; record per-task stats in the README.
