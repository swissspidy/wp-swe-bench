#!/usr/bin/env bash
# Native seeding (runs after the blueprint, at image build time).
set -euo pipefail
cd "$(dirname "$0")"

mk() { # slug type title file
  wp post create "seed/posts/$4" --post_type="$2" --post_status=publish --post_name="$1" --post_title="$3" --porcelain
}
mk help-legacy-v1 page "Account questions (FAQ 1.0)" help-legacy-v1.html >/dev/null
mk help-center page "Help center" help-center.html >/dev/null
mk two-faqs post "Two FAQs" two-faqs.html >/dev/null
mk faq-in-group post "FAQ in a group" faq-in-group.html >/dev/null
