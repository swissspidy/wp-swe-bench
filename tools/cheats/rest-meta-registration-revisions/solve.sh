#!/usr/bin/env bash
# Cheat: the "obvious" 3.0 relying on WordPress' built-in revisioned-meta handling alone:
# REST meta with full schemas, per-field auth, date validation, migration + CLI, but
# - specs are read per key and trusted to core's preview handling (which only works for scalar meta),
# - the cached front-end table is not bypassed for previews,
# - restoring a pre-3.0 revision (no specs recorded) wipes the product's specs.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-specs
cp -R "$(dirname "$0")/files/." "$REPO/"
