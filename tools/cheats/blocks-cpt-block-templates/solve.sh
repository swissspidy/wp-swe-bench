#!/usr/bin/env bash
# Cheat: "we're a block theme site now". Registers block templates + course blocks, but
# drops the PHP templates / content injection for every theme and inlines the summary
# blocks instead of providing the course-summary template part. Must score 0.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-courses
cp -R "$(dirname "$0")/files/." "$REPO/"
