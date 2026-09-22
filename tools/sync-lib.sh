#!/usr/bin/env bash
# Vendor lib/ into tasks' tests/wpsb/ so hidden tests are self-contained at grading time
# (and cannot be tampered with from inside the agent's container).
#   tools/sync-lib.sh            # all tasks
#   tools/sync-lib.sh <id>...    # only these tasks
set -euo pipefail
cd "$(dirname "$0")/.."
if [ "$#" -gt 0 ]; then dirs=(); for id in "$@"; do dirs+=("tasks/$id/"); done; else dirs=(tasks/*/); fi
n=0
for t in "${dirs[@]}"; do
  [ -f "$t/task.toml" ] || { echo "skip $t (no task.toml)"; continue; }
  rm -rf "$t/tests/wpsb"
  mkdir -p "$t/tests/wpsb"
  cp -R lib/. "$t/tests/wpsb/"
  n=$((n+1))
done
echo "synced lib/ into $n task(s)"
