#!/usr/bin/env bash
# Build task images locally and tag them with the name each task.toml references
# ([environment].docker_image), so `harbor run` and tools/validate.py use them.
#   tools/build-task-images.sh            # all tasks
#   tools/build-task-images.sh <id>...    # only these
# Requires the base image (tools/build-base.sh). Proxy variables are forwarded; the build uses the
# host network.
set -euo pipefail
cd "$(dirname "$0")/.."
BASE="ghcr.io/swissspidy/wp-swe-bench-base:$(cat base/VERSION)"
if [ "$#" -gt 0 ]; then ids=("$@"); else ids=(); for d in tasks/*/task.toml; do ids+=("$(basename "$(dirname "$d")")"); done; fi
args=(--network host --build-arg "WPSB_BASE_IMAGE=$BASE")
for v in HTTPS_PROXY https_proxy NO_PROXY no_proxy; do
  if [ -n "${!v:-}" ]; then args+=(--build-arg "$v=${!v}"); fi
done
for id in "${ids[@]}"; do
  image=$(python3 -c 'import sys,tomllib; print(tomllib.load(open(sys.argv[1],"rb"))["environment"]["docker_image"])' "tasks/$id/task.toml")
  echo "building $image"
  docker build "${args[@]}" -t "$image" "tasks/$id/environment"
done
