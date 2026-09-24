#!/usr/bin/env python3
"""Validate wp-swe-bench tasks: oracle must score 1, nop 0, cheat 0.

Direct Docker mode (default) replicates Harbor's trial semantics without Harbor:
  build image -> start container (--network none) -> [solution to /solution, run solve.sh]
  -> grade -> read /logs/verifier/reward.json
Grading uses Harbor's separate verifier: a fresh container from the verifier image built from
tests/ (or steps/<s>/tests/) with the task image as base, plus the agent's repository artifact.
Multi-step tasks run every step in one agent container, copying steps/<s>/workdir into the
working directory (+ setup.sh).

Harbor mode (--harbor) shells out to `harbor run -p <task> -a oracle|nop` instead.

Usage:
  tools/validate.py [task-id ...] [--modes oracle,nop,cheat] [-j 2] [--keep]
Results are written to tools/results/<task-id>.json and summarized in tools/results/summary.md.
"""
from __future__ import annotations

import argparse
import concurrent.futures as cf
import contextlib
import fcntl
import json
import os
import shlex
import shutil
import subprocess
import sys
import tempfile
import time
import tomllib
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
TASKS = ROOT / "tasks"
CHEATS = ROOT / "tools" / "cheats"
RESULTS = ROOT / "tools" / "results"
BASE_TAG = (ROOT / "base" / "VERSION").read_text().strip()
BASE_IMAGE = f"ghcr.io/swissspidy/wp-swe-bench-base:{BASE_TAG}"


SLOTS_DIR = Path(os.environ.get("WPSB_SLOTS_DIR", "/tmp/wpsb-validate-slots"))
SLOTS = int(os.environ.get("WPSB_VALIDATE_SLOTS", "2"))


@contextlib.contextmanager
def slot(task_id: str):
    """Machine-wide semaphore: at most $WPSB_VALIDATE_SLOTS heavy trials at once (Playground +
    Chromium are CPU hungry; oversubscription causes editor timeouts)."""
    SLOTS_DIR.mkdir(parents=True, exist_ok=True)
    waited = False
    while True:
        for i in range(SLOTS):
            f = open(SLOTS_DIR / f"slot-{i}.lock", "w", encoding="utf-8")
            try:
                fcntl.flock(f, fcntl.LOCK_EX | fcntl.LOCK_NB)
            except BlockingIOError:
                f.close()
                continue
            try:
                yield
            finally:
                fcntl.flock(f, fcntl.LOCK_UN)
                f.close()
            return
        if not waited:
            log(task_id, f"waiting for a validation slot ({SLOTS} in use)")
            waited = True
        time.sleep(5)


def sh(cmd, check=True, capture=True, timeout=None, input=None):
    r = subprocess.run(cmd, check=False, capture_output=capture, text=True, timeout=timeout, input=input)
    if check and r.returncode != 0:
        raise RuntimeError(f"command failed ({r.returncode}): {' '.join(map(str, cmd))}\n{r.stdout[-4000:] if r.stdout else ''}\n{r.stderr[-4000:] if r.stderr else ''}")
    return r


def log(task, msg):
    print(f"[{time.strftime('%H:%M:%S')}] {task}: {msg}", flush=True)


def load_toml(task_dir: Path) -> dict:
    with open(task_dir / "task.toml", "rb") as f:
        return tomllib.load(f)


def steps_of(cfg: dict) -> list[str]:
    return [s["name"] for s in cfg.get("steps", [])]


def build_image(task_id: str, task_dir: Path) -> str:
    tag = load_toml(task_dir).get("environment", {}).get("docker_image") or f"wpsb-task/{task_id}:latest"
    args = ["docker", "build", "--network", "host", "--build-arg", f"WPSB_BASE_IMAGE={BASE_IMAGE}", "-t", tag, str(task_dir / "environment")]
    for v in ("HTTPS_PROXY", "https_proxy", "NO_PROXY", "no_proxy"):
        if os.environ.get(v):
            args[2:2] = ["--build-arg", f"{v}={os.environ[v]}"]
    r = sh(args, check=False, timeout=3600)
    (RESULTS / "logs").mkdir(parents=True, exist_ok=True)
    (RESULTS / "logs" / f"{task_id}.build.log").write_text((r.stdout or "") + (r.stderr or ""))
    if r.returncode != 0:
        raise RuntimeError(f"image build failed; see tools/results/logs/{task_id}.build.log\n{(r.stderr or '')[-3000:]}")
    return tag


def workdir_of(tag: str) -> str:
    return sh(["docker", "image", "inspect", "-f", "{{.Config.WorkingDir}}", tag]).stdout.strip() or "/"


class Container:
    def __init__(self, tag: str, name: str, network: str):
        self.name = name
        sh(["docker", "rm", "-f", name], check=False)
        sh(["docker", "run", "-d", "--name", name, "--network", network, "--shm-size", "1g",
            "--memory", os.environ.get("WPSB_TRIAL_MEMORY", "3g"), "--cpus", os.environ.get("WPSB_TRIAL_CPUS", "2"),
            tag, "sleep", "infinity"])

    def exec(self, cmd: str, timeout: int, workdir: str | None = None, env: dict | None = None):
        args = ["docker", "exec"]
        if workdir:
            args += ["-w", workdir]
        for k, v in (env or {}).items():
            args += ["-e", f"{k}={v}"]
        args += [self.name, "bash", "-lc", cmd]
        try:
            return sh(args, check=False, timeout=timeout)
        except subprocess.TimeoutExpired:
            return subprocess.CompletedProcess(args, 124, "", f"timeout after {timeout}s")

    def put(self, src: Path, dst: str):
        self.exec(f"rm -rf {dst} && mkdir -p {dst}", 60)
        sh(["docker", "cp", f"{src}/.", f"{self.name}:{dst}"])

    def read(self, path: str) -> str | None:
        r = sh(["docker", "exec", self.name, "cat", path], check=False)
        return r.stdout if r.returncode == 0 else None

    def fetch_dir(self, src: str, dst: Path):
        dst.mkdir(parents=True, exist_ok=True)
        sh(["docker", "cp", f"{self.name}:{src}/.", str(dst)], check=False)

    def remove(self):
        sh(["docker", "rm", "-f", self.name], check=False)


def build_verifier_image(task_id: str, task_dir: Path, step: str | None, tag: str) -> str:
    """Build the verifier image the way Harbor does: from the step's tests/ (else the task's tests/),
    whose generated Dockerfile (tools/sync-lib.sh) bakes the tests into the task image."""
    ctx = task_dir / "steps" / step / "tests" if step and (task_dir / "steps" / step / "tests").exists() else task_dir / "tests"
    vtag = f"wpsb-verifier/{task_id}:{step or 'task'}"
    r = sh(["docker", "build", "--build-arg", f"WPSB_TASK_IMAGE={tag}", "-t", vtag, str(ctx)], check=False, timeout=1800)
    if r.returncode != 0:
        raise RuntimeError(f"verifier image build failed ({ctx}):\n{(r.stderr or '')[-3000:]}")
    return vtag


def separate_verifier(cfg: dict) -> bool:
    ver = cfg.get("verifier", {})
    return ver.get("environment_mode") == "separate" or "environment" in ver


def artifact_dirs(cfg: dict) -> list[dict]:
    out = []
    for a in cfg.get("artifacts", []):
        out.append({"source": a, "exclude": []} if isinstance(a, str) else {"source": a["source"], "exclude": a.get("exclude", [])})
    return out


def merged_tests(task_dir: Path, step: str | None) -> Path:
    tmp = Path(tempfile.mkdtemp(prefix="wpsb-tests-"))
    base = task_dir / "tests"
    if base.exists():
        shutil.copytree(base, tmp, dirs_exist_ok=True)
    if step:
        st = task_dir / "steps" / step / "tests"
        if st.exists():
            shutil.copytree(st, tmp, dirs_exist_ok=True)
    return tmp


def run_trial(task_id: str, task_dir: Path, tag: str, mode: str, keep: bool, network: str) -> dict:
    cfg = load_toml(task_dir)
    steps = steps_of(cfg)
    wd = cfg.get("environment", {}).get("workdir") or workdir_of(tag)
    agent_timeout = int(cfg.get("agent", {}).get("timeout_sec", 3600))
    verifier_timeout = int(cfg.get("verifier", {}).get("timeout_sec", 1800))
    name = f"wpsb-{task_id}-{mode}".replace("/", "-")[:120]
    c = Container(tag, name, network)
    logs_root = RESULTS / "logs" / task_id / mode
    shutil.rmtree(logs_root, ignore_errors=True)
    out = {"mode": mode, "steps": []}
    cheat_dir = CHEATS / task_id
    try:
        for step in (steps or [None]):
            label = step or "task"
            t0 = time.time()
            c.exec("rm -rf /logs/verifier /tests /solution && mkdir -p /logs/verifier /logs/agent", 60)
            if step:
                wdir = task_dir / "steps" / step / "workdir"
                if wdir.exists():
                    sh(["docker", "cp", f"{wdir}/.", f"{name}:{wd}"])
                    if (wdir / "setup.sh").exists():
                        r = c.exec(f"bash {wd}/setup.sh", 900, workdir=wd)
                        if r.returncode != 0:
                            raise RuntimeError(f"{label}: setup.sh failed: {r.stderr[-2000:]}")
            sol = None
            if mode == "oracle":
                sol = task_dir / "steps" / step / "solution" if step else task_dir / "solution"
            elif mode == "cheat":
                cand = cheat_dir / step if step else cheat_dir
                if step and not (cand / "solve.sh").exists():
                    sol = task_dir / "steps" / step / "solution"  # oracle for steps the cheat doesn't target
                else:
                    sol = cand
            solve_rc = None
            if sol is not None:
                if not (sol / "solve.sh").exists():
                    raise RuntimeError(f"{label}: missing {sol}/solve.sh")
                c.put(sol, "/solution")
                r = c.exec("bash /solution/solve.sh", agent_timeout, workdir=wd, env=cfg.get("solution", {}).get("env"))
                solve_rc = r.returncode
                logs_root.mkdir(parents=True, exist_ok=True)
                (logs_root / f"{label}.solve.log").write_text((r.stdout or "") + "\n--- stderr ---\n" + (r.stderr or ""))
                if mode == "oracle":
                    st = c.exec('cd "${WPSB_REPO:-.}" && git add -A -N . >/dev/null 2>&1; '
                                "git diff --numstat -- . ':(exclude)build/**' ':(exclude)**/build/**' ':(exclude)*.lock' ':(exclude)package-lock.json' ':(exclude)*.min.*'", 120, workdir=wd)
                    files, added, deleted = 0, 0, 0
                    for line in (st.stdout or "").splitlines():
                        parts = line.split("\t")
                        if len(parts) == 3 and parts[0].isdigit():
                            files += 1; added += int(parts[0]); deleted += int(parts[1])
                    out.setdefault("solution_stats", {})[label] = {"files": files, "added": added, "deleted": deleted}
            # Separate verifier (Harbor [verifier].environment_mode = "separate"): grade in a fresh
            # container from the verifier image (task image + baked-in tests, never uploaded); only
            # the declared artifacts (the agent's repository) are transferred, replacing the pristine
            # copy (Harbor empties the target directory before uploading a directory artifact).
            v = c
            if separate_verifier(cfg):
                v = Container(build_verifier_image(task_id, task_dir, step, tag), f"{name}-verifier", network)
                v.exec("mkdir -p /logs/verifier", 60)
                for art in artifact_dirs(cfg):
                    excl = " ".join(f"--exclude={shlex.quote(e)}" for e in art.get("exclude", []))
                    pack = subprocess.run(["docker", "exec", name, "bash", "-c", f"tar -C {shlex.quote(art['source'])} {excl} -cf - ."],
                                          capture_output=True, check=False)
                    if pack.returncode != 0:
                        raise RuntimeError(f"{label}: collecting artifact {art['source']} failed: {pack.stderr[-2000:]!r}")
                    src = shlex.quote(art["source"])
                    unpack = subprocess.run(["docker", "exec", "-i", v.name, "bash", "-c",
                                             f"rm -rf {src} && mkdir -p {src} && tar -C {src} -xf -"],
                                            input=pack.stdout, capture_output=True, check=False)
                    if unpack.returncode != 0:
                        raise RuntimeError(f"{label}: uploading artifact {art['source']} failed: {unpack.stderr[-2000:]!r}")
            else:
                tests = merged_tests(task_dir, step)
                v.put(tests, "/tests")
                shutil.rmtree(tests, ignore_errors=True)
            r = v.exec("bash /tests/test.sh", verifier_timeout + 120, workdir=wd, env=cfg.get("verifier", {}).get("env"))
            reward_raw = v.read("/logs/verifier/reward.json")
            v.fetch_dir("/logs/verifier", logs_root / label)
            if v is not c and not keep:
                v.remove()
            (logs_root / label).mkdir(parents=True, exist_ok=True)
            (logs_root / label / "test-stdout.txt").write_text((r.stdout or "") + "\n--- stderr ---\n" + (r.stderr or ""))
            try:
                reward = json.loads(reward_raw) if reward_raw else {"reward": 0, "error": "no reward.json"}
            except json.JSONDecodeError:
                reward = {"reward": 0, "error": "invalid reward.json"}
            out["steps"].append({"step": label, "reward": reward, "solve_rc": solve_rc, "test_rc": r.returncode, "seconds": round(time.time() - t0)})
            log(task_id, f"{mode} {label}: reward={reward.get('reward')} ({round(time.time() - t0)}s)")
    finally:
        if not keep:
            c.remove()
    rewards = [s["reward"].get("reward", 0) for s in out["steps"]]
    strategy = cfg.get("multi_step_reward_strategy") or "mean"
    out["reward"] = (rewards[-1] if strategy == "final" else sum(rewards) / len(rewards)) if rewards else 0
    return out


def run_harbor(task_id: str, task_dir: Path, mode: str) -> dict:
    jobs = RESULTS / "harbor-jobs"
    job = f"{task_id}-{mode}-{int(time.time())}"
    r = sh(["harbor", "run", "-p", str(task_dir), "-a", mode, "-y", "-q", "-o", str(jobs), "--job-name", job], check=False, timeout=7200)
    rewards = []
    for p in (jobs / job).rglob("reward.json"):
        try:
            rewards.append(json.loads(p.read_text()).get("reward", 0))
        except Exception:
            pass
    return {"mode": mode, "harbor_rc": r.returncode, "reward": (sum(rewards) / len(rewards)) if rewards else None, "stdout": (r.stdout or "")[-2000:]}


def expected_ok(mode: str, res: dict) -> bool:
    if mode == "oracle":
        return res.get("reward") == 1 and all(s["reward"].get("reward") == 1 for s in res.get("steps", []))
    if mode == "nop":
        return res.get("reward") == 0
    if mode == "cheat":
        # A cheat must fail at least the step(s) it targets.
        return any(s["reward"].get("reward") == 0 for s in res.get("steps", [])) if res.get("steps") else res.get("reward") == 0
    return False


def validate(task_id: str, modes: list[str], keep: bool, harbor: bool, network: str) -> dict:
    task_dir = TASKS / task_id
    result = {"task": task_id, "modes": {}, "ok": True, "time": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime())}
    try:
        cfg = load_toml(task_dir)
        result["metadata"] = cfg.get("metadata", {})
        tag = None
        if not harbor:
            log(task_id, "building image")
            with slot(task_id):
                tag = build_image(task_id, task_dir)
        for mode in modes:
            if mode == "cheat" and not (CHEATS / task_id).exists():
                result["modes"][mode] = {"skipped": "no cheat"}
                result["ok"] = False
                continue
            with slot(task_id):
                res = run_harbor(task_id, task_dir, mode) if harbor else run_trial(task_id, task_dir, tag, mode, keep, network)
            res["as_expected"] = expected_ok(mode, res)
            result["ok"] &= res["as_expected"]
            result["modes"][mode] = res
    except Exception as e:  # noqa: BLE001
        result["ok"] = False
        result["error"] = str(e)[-4000:]
        log(task_id, f"ERROR {str(e)[:500]}")
    RESULTS.mkdir(parents=True, exist_ok=True)
    path = RESULTS / f"{task_id}.json"
    if path.exists():
        # Keep results of modes that were not re-run in this invocation.
        try:
            prev = json.loads(path.read_text())
            for m, r in prev.get("modes", {}).items():
                result["modes"].setdefault(m, r)
            result["ok"] = "error" not in result and all(r.get("as_expected") for r in result["modes"].values())
        except Exception:  # noqa: BLE001
            pass
    path.write_text(json.dumps(result, indent=2))
    return result


def summarize():
    rows = []
    for p in sorted(RESULTS.glob("*.json")):
        r = json.loads(p.read_text())
        md = r.get("metadata", {})
        def cell(m):
            x = r.get("modes", {}).get(m)
            if not x:
                return "–"
            if "skipped" in x:
                return "skipped"
            rew = x.get("reward")
            mark = "✅" if x.get("as_expected") else "❌"
            return f"{mark} {rew if rew is not None else '?'}"
        rows.append(f"| `{r['task']}` | {md.get('category', '')} | {md.get('difficulty', '')} | {md.get('estimated_human_hours', '')} | {'yes' if md.get('multi_step') else ''} | {cell('oracle')} | {cell('nop')} | {cell('cheat')} |")
    table = "| Task | Category | Difficulty | Est. hours | Multi-step | Oracle (=1) | Nop (=0) | Cheat (=0) |\n|---|---|---|---|---|---|---|---|\n" + "\n".join(rows) + "\n"
    (RESULTS / "summary.md").write_text(table)
    return table


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("tasks", nargs="*")
    ap.add_argument("--modes", default="oracle,nop,cheat")
    ap.add_argument("-j", "--jobs", type=int, default=1)
    ap.add_argument("--keep", action="store_true", help="keep containers for debugging")
    ap.add_argument("--harbor", action="store_true", help="use `harbor run` (oracle/nop only)")
    ap.add_argument("--network", default="none", help="docker network for trial containers (default: none = offline)")
    ap.add_argument("--summary-only", action="store_true")
    a = ap.parse_args()
    if a.summary_only:
        print(summarize())
        return
    tasks = a.tasks or sorted(p.name for p in TASKS.iterdir() if (p / "task.toml").exists())
    modes = [m for m in a.modes.split(",") if m]
    if a.harbor:
        modes = [m for m in modes if m != "cheat"]
    with cf.ThreadPoolExecutor(max_workers=a.jobs) as ex:
        results = list(ex.map(lambda t: validate(t, modes, a.keep, a.harbor, a.network), tasks))
    print(summarize())
    bad = [r["task"] for r in results if not r["ok"]]
    if bad:
        print("NOT OK:", " ".join(bad))
        sys.exit(1)


if __name__ == "__main__":
    main()
