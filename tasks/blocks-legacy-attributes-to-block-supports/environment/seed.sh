#!/usr/bin/env bash
# Native seeding (runs after the blueprint, at image build time).
set -euo pipefail
cd "$(dirname "$0")"
mk() { # slug type status title file
  wp post create "seed/posts/$5" --post_type="$2" --post_status="$3" --post_name="$1" --post_title="$4" --post_author=1 --user=1 --porcelain
}
mk notices-v1 post publish "Office notices (2019)" notices-v1.html >/dev/null
mk notices-v2 post publish "Service notices" notices-v2.html >/dev/null
mk acme-in-numbers page publish "Acme in numbers" stats.html >/dev/null
mk internal-notice post private "Internal notice" internal-notice.html >/dev/null
