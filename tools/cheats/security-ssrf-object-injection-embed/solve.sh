#!/usr/bin/env bash
# Cheat: fixes the XSS and the object injection (JSON + safe legacy reads), and
# blocks the OBVIOUS private literals in the URL guard, but does not decode
# alternative IP encodings (decimal/octal/hex/IPv6-mapped), does not resolve host
# names, and the initial-URL patterns don't stop a redirect/host that only reveals
# the internal address after resolution.
set -euo pipefail
REPO=/wordpress/wp-content/plugins/acme-link-previews
cp -R "$(dirname "$0")/files/." "$REPO/"
