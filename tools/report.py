#!/usr/bin/env python3
"""Summarize a Harbor job directory as a Markdown results table.

Usage: tools/report.py <jobs-dir>/<job> [--label "Agent / model"]

Infrastructure failures (agent crashed or could not start, API errors, environment errors) are
reported separately and excluded from the pass rate; timeouts count as failures (the agent had
its full budget). Multi-step tasks score the mean of their steps.
"""
from __future__ import annotations

import argparse
import glob
import json
import os
import tomllib
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
# Exceptions that are the agent's own result, not infrastructure trouble.
AGENT_RESULT_ERRORS = {"AgentTimeoutError"}
IGNORED = {"reward", "wpcs", "required_passed", "required_total"}


def task_meta(task_id: str) -> dict:
    p = ROOT / "tasks" / task_id / "task.toml"
    return tomllib.loads(p.read_text()).get("metadata", {}) if p.exists() else {}


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("job")
    ap.add_argument("--label", default="")
    args = ap.parse_args()

    trials = []
    agent_cfg: dict = {}
    for f in sorted(glob.glob(os.path.join(args.job, "*", "result.json"))):
        d = json.load(open(f))
        tid = d["task_name"].split("/")[-1]
        rewards = (d.get("verifier_result") or {}).get("rewards") or {}
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
        results = [s.get("agent_result") or {} for s in steps] or [d.get("agent_result") or {}]
        total = lambda k: sum(a[k] for a in results if a.get(k) is not None) if any(a.get(k) is not None for a in results) else None  # noqa: E731
        agent_cfg = (d.get("config") or {}).get("agent") or agent_cfg
        trials.append({
            "task": tid,
            "reward": rewards.get("reward"),
            "exc": exc,
            "infra": bool(exc) and exc not in AGENT_RESULT_ERRORS,
            "failed": failed,
            "cost": total("cost_usd"),
            "tokens_in": total("n_input_tokens"),
            "tokens_out": total("n_output_tokens"),
            "meta": task_meta(tid),
        })
    if not trials:
        raise SystemExit(f"no trial results under {args.job}")

    clean = [t for t in trials if not t["infra"]]
    score = lambda ts: sum(t["reward"] or 0 for t in ts)  # noqa: E731
    agent = agent_cfg
    label = args.label or " / ".join(x for x in (agent.get("name"), agent.get("model_name")) if x)

    out = [f"### {label or os.path.basename(os.path.normpath(args.job))}", ""]
    summary = (f"**Pass rate: {score(clean) / max(len(clean), 1):.1%}** "
               f"({score(clean):g} / {len(clean)} tasks; multi-step tasks count the mean of their steps)")
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
    kwargs = {k: v for k, v in (agent.get("kwargs") or {}).items() if v is not None}
    out += ["", f"Run: {len(trials)} trials, job `{os.path.basename(os.path.normpath(args.job))}`"
            + (f", agent options `{json.dumps(kwargs)}`" if kwargs else "") + ".", ""]

    cats: dict[str, list] = {}
    for t in clean:
        cats.setdefault(t["meta"].get("category", "?"), []).append(t)
    out += ["| Category | Tasks | Pass rate |", "|---|---|---|"]
    for c in sorted(cats):
        out.append(f"| `{c}` | {len(cats[c])} | {score(cats[c]) / len(cats[c]):.0%} |")
    out += ["", "| Task | Difficulty | Reward | Failed checks / error |", "|---|---|---|---|"]
    for t in trials:
        r = "–" if t["infra"] or t["reward"] is None else f"{t['reward']:g}"
        note = f"⚠️ {t['exc']} (excluded)" if t["infra"] else "; ".join(filter(None, [t["exc"]] + t["failed"]))
        out.append(f"| `{t['task']}` | {t['meta'].get('difficulty', '')} | {r} | {note} |")
    print("\n".join(out))


if __name__ == "__main__":
    main()
