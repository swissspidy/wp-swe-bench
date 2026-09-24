#!/usr/bin/env python3
"""Summarize a Harbor job directory as a Markdown results table.

Usage: tools/report.py <jobs-dir>/<job> [--label "Agent / model / options"] [--partial]

Infrastructure failures (agent crashed or could not start, API errors, environment errors) are
reported separately and excluded from the pass rate; timeouts count as failures (the agent had
its full budget). Multi-step tasks score the mean of their steps. A job with several agents or
models gets one section per agent/model. Unfinished jobs are refused unless --partial is given,
in which case the report is marked as partial.
"""
from __future__ import annotations

import argparse
import glob
import json
import os
import sys
import tomllib
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
# Exceptions that are the agent's own result, not infrastructure trouble.
AGENT_RESULT_ERRORS = {"AgentTimeoutError"}
IGNORED = {"reward", "wpcs", "required_passed", "required_total"}


def task_meta(task_id: str) -> dict:
    """Return the [metadata] table of a task in this repository (empty if unknown)."""
    p = ROOT / "tasks" / task_id / "task.toml"
    return tomllib.loads(p.read_text()).get("metadata", {}) if p.exists() else {}


def load_trial(path: str) -> dict:
    """Read one trial's result.json into the fields the report needs."""
    d = json.load(open(path))
    tid = d["task_name"].split("/")[-1]
    exc = (d.get("exception_info") or {}).get("exception_type") or ""
    steps = d.get("step_results") or []
    # Failed required checks, per step for multi-step tasks (their top-level sub-scores are means).
    failed = []
    for s in steps or [{"step_name": "", "verifier_result": d.get("verifier_result")}]:
        r = (s.get("verifier_result") or {}).get("rewards") or {}
        exc = exc or ((s.get("exception_info") or {}).get("exception_type") or "")
        if r.get("reward", 0) < 1:
            bad = sorted(k for k, v in r.items() if k not in IGNORED and v < 1)
            if bad:
                failed.append((f"{s['step_name']}: " if s["step_name"] else "") + ", ".join(bad))
    usage = [s.get("agent_result") or {} for s in steps] or [d.get("agent_result") or {}]

    def total(key):
        vals = [a[key] for a in usage if a.get(key) is not None]
        return sum(vals) if vals else None

    agent = (d.get("config") or {}).get("agent") or {}
    return {
        "task": tid,
        "reward": ((d.get("verifier_result") or {}).get("rewards") or {}).get("reward"),
        "exc": exc,
        "infra": bool(exc) and exc not in AGENT_RESULT_ERRORS,
        "failed": failed,
        "cost": total("cost_usd"),
        "tokens_in": total("n_input_tokens"),
        "tokens_out": total("n_output_tokens"),
        "agent": agent.get("name") or "?",
        "model": agent.get("model_name") or "",
        # Option names only: values can be anything (including credentials) and reports are published.
        "options": sorted(k for k, v in (agent.get("kwargs") or {}).items() if v is not None),
        "meta": task_meta(tid),
    }


def job_status(job: str, n_found: int) -> str | None:
    """Return a description of why the job is incomplete, or None if it finished."""
    p = os.path.join(job, "result.json")
    if not os.path.exists(p):
        return "the job has no result.json (it never started or was killed early)"
    d = json.load(open(p))
    stats = d.get("stats") or {}
    expected = d.get("n_total_trials")
    unfinished = (stats.get("n_running_trials") or 0) + (stats.get("n_pending_trials") or 0)
    if not d.get("finished_at") or unfinished or (expected is not None and n_found < expected):
        return f"{n_found} of {expected if expected is not None else '?'} trials have results"
    return None


def render(trials: list[dict], label: str, job_name: str, partial: str | None) -> list[str]:
    """Render one agent/model's trials as a Markdown section."""
    clean = [t for t in trials if not t["infra"]]
    score = sum(t["reward"] or 0 for t in clean)
    out = [f"### {label}", ""]
    if partial:
        out += [f"> ⚠️ **Partial results:** {partial}. Not a complete benchmark run.", ""]
    if clean:
        summary = (f"**Pass rate: {score / len(clean):.1%}** "
                   f"({score:g} / {len(clean)} tasks; multi-step tasks count the mean of their steps)")
    else:
        summary = "**Pass rate: N/A** (no trial finished without an infrastructure error)"
    if len(clean) < len(trials):
        summary += f"; {len(trials) - len(clean)} trial(s) excluded for infrastructure errors"
    costs = [t["cost"] for t in trials if t["cost"] is not None]
    if costs:
        summary += f"; agent cost ≈ ${sum(costs):.2f}"
    tin = [t["tokens_in"] for t in trials if t["tokens_in"] is not None]
    tout = [t["tokens_out"] for t in trials if t["tokens_out"] is not None]
    if tin:
        summary += f"; tokens ≈ {sum(tin) / 1e6:.1f}M in / {sum(tout) / 1e6:.2f}M out"
    out.append(summary)
    opts = trials[0]["options"]
    out += ["", f"Run: {len(trials)} trials, job `{job_name}`"
            + (f", agent options set: {', '.join(f'`{o}`' for o in opts)}" if opts else "") + ".", ""]

    cats: dict[str, list] = {}
    for t in clean:
        cats.setdefault(t["meta"].get("category", "?"), []).append(t)
    if cats:
        out += ["| Category | Tasks | Pass rate |", "|---|---|---|"]
        for c in sorted(cats):
            out.append(f"| `{c}` | {len(cats[c])} | {sum(t['reward'] or 0 for t in cats[c]) / len(cats[c]):.0%} |")
        out.append("")
    out += ["| Task | Difficulty | Reward | Failed checks / error |", "|---|---|---|---|"]
    for t in trials:
        r = "–" if t["infra"] or t["reward"] is None else f"{t['reward']:g}"
        note = f"⚠️ {t['exc']} (excluded)" if t["infra"] else "; ".join(filter(None, [t["exc"]] + t["failed"]))
        out.append(f"| `{t['task']}` | {t['meta'].get('difficulty', '')} | {r} | {note} |")
    return out


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("job")
    ap.add_argument("--label", default="", help="section title (only for single-agent/model jobs)")
    ap.add_argument("--partial", action="store_true", help="report an unfinished job, marked as partial")
    args = ap.parse_args()

    trials = [load_trial(f) for f in sorted(glob.glob(os.path.join(args.job, "*", "result.json")))]
    if not trials:
        raise SystemExit(f"no trial results under {args.job}")
    partial = job_status(args.job, len(trials))
    if partial and not args.partial:
        raise SystemExit(f"job is not finished: {partial}. Resume it, or pass --partial to report it anyway.")

    groups: dict[tuple, list] = {}
    for t in trials:
        groups.setdefault((t["agent"], t["model"], tuple(t["options"])), []).append(t)
    if args.label and len(groups) > 1:
        print("note: --label ignored, the job has several agents/models/options", file=sys.stderr)
    job_name = os.path.basename(os.path.normpath(args.job))
    out: list[str] = []
    for (agent, model, _), ts in groups.items():
        label = args.label if len(groups) == 1 and args.label else " / ".join(x for x in (agent, model) if x)
        out += render(ts, label, job_name, partial) + [""]
    print("\n".join(out).rstrip())


if __name__ == "__main__":
    main()
