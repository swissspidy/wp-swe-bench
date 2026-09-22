#!/usr/bin/env bash
# Fast iteration on hidden tests: re-upload tasks/<id>/tests (+ step tests) into a container kept
# by `tools/validate.py <id> --modes <mode> --keep` and re-run test.sh there (no rebuild, no re-solve).
#   tools/retest.sh <task-id> [mode=oracle] [step]
# Results: tools/results/logs/<id>/<mode>/retest/ (summary.txt, checks/, logs).
set -euo pipefail
cd "$(dirname "$0")/.."
id="$1"; mode="${2:-oracle}"; step="${3:-}"
c="wpsb-${id}-${mode}"
docker inspect "$c" >/dev/null 2>&1 || { echo "no container $c (run tools/validate.py $id --modes $mode --keep)"; exit 1; }
tmp=$(mktemp -d)
cp -R "tasks/$id/tests/." "$tmp/" 2>/dev/null || true
if [ -n "$step" ] && [ -d "tasks/$id/steps/$step/tests" ]; then cp -R "tasks/$id/steps/$step/tests/." "$tmp/"; fi
docker exec "$c" rm -rf /tests /logs/verifier
docker exec "$c" mkdir -p /tests /logs/verifier
docker cp "$tmp/." "$c:/tests"
rm -rf "$tmp"
wd=$(docker inspect -f '{{.Config.WorkingDir}}' "$c")
docker exec -w "$wd" "$c" bash /tests/test.sh > /dev/null 2>&1 || true
out="tools/results/logs/$id/$mode/retest"
rm -rf "$out"; mkdir -p "$out"
docker cp "$c:/logs/verifier/." "$out/"
cat "$out/summary.txt"
