#!/usr/bin/env bash
# Reference fix: slash every copied value (plain strings, arrays, objects, post data on republish)
# and resolve the copyable taxonomies when copying instead of on init.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/duplicate-post
cp -R /solution/files/. "$REPO/"
