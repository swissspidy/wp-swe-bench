#!/usr/bin/env bash
# Native seeding: events as saved by Acme Events 1.0 and 1.6 over the last years.
set -euo pipefail
cd "$(dirname "$0")"
wp option update timezone_string 'America/New_York' >/dev/null
wp option update gmt_offset 0 >/dev/null
wp eval-file seed/events.php
wp rewrite flush >/dev/null
