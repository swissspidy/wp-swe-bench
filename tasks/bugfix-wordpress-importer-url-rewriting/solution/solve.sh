#!/usr/bin/env bash
# Reference fix: the upstream fixes backported onto 0.9.3.
#  - 0.9.4 (WordPress/wordpress-importer, 2025-10): keep the "/" of self-closing block comments when
#    their attributes are re-serialized during URL rewriting (BlockMarkupProcessor), plus the WXR regex
#    parser notices fixed in the same release.
#  - 0.9.5 (2025-11): rewrite CSS url() references in style attributes (CSSProcessor/CSSURLProcessor,
#    BlockMarkupUrlProcessor), with the toolkit changes it depends on (UTF-8 scanning, WPURL returning a
#    ConvertedUrl) and the matching WPURL::replace_base_url() call sites in class-wp-import.php.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/wordpress-importer
cp -R /solution/files/. "$REPO/"
rm -f "$REPO/php-toolkit/Encoding/utf8-decoder.php"
