#!/usr/bin/env bash
# Native seeding (runs after the blueprint, at image build time).
set -euo pipefail
cd "$(dirname "$0")"

mk() { # slug type title file
  wp post create "seed/posts/$4" --post_type="$2" --post_status=publish --post_name="$1" --post_title="$3" --porcelain
}
mk stale-toc post "Getting started with Acme Widgets" stale-toc.html >/dev/null
mk legacy-contents page "Legacy contents page" legacy-contents.html >/dev/null
mk duplicate-headings post "Duplicate headings" duplicate-headings.html >/dev/null
mk no-title-toc post "TOC without a title" no-title-toc.html >/dev/null
mk paged-guide post "The paged guide" paged-guide.html >/dev/null
mk special-chars post "Special characters" special-chars.html >/dev/null
mk no-headings post "No headings" no-headings.html >/dev/null
