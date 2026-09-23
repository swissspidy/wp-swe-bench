#!/usr/bin/env bash
# Native seeding (runs after the blueprint, at image build time).
set -euo pipefail
cd "$(dirname "$0")"
wp eval-file seed/seed.php
wp eval 'update_option( "acme_activity_settings", array( "tracked" => array( "user_login", "user_registered", "post_published", "post_trashed", "plugin_activated" ), "log_ip" => true ) );'
wp acme-activity count
wp rewrite flush >/dev/null
