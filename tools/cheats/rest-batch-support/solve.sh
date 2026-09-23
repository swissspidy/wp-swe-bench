#!/usr/bin/env bash
# Cheat: "just make it batchable" (allow batching, read params from the request object,
# return a response from DELETE, raise the batch size). Must score 0.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-tasks
cp -R "$(dirname "$0")/files/." "$REPO/"
