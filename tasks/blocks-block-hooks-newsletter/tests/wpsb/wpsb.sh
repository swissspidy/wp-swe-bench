#!/usr/bin/env bash
# wp-swe-bench grading helpers. Source from a task's tests/test.sh:
#
#   source /tests/wpsb/wpsb.sh
#   wpsb_init
#   wpsb_integrity                                   # required: WP core untouched
#   wpsb_reset_site                                  # restore pristine DB/uploads
#   wpsb_build  build "$REPO"                        # required: npm run build succeeds
#   wpsb_server_start
#   wpsb_phpunit  phpunit /tests/phpunit             # required
#   wpsb_playwright e2e /tests/e2e                   # required
#   wpsb_wpcs wpcs "$REPO" --changed                 # soft
#   wpsb_finish                                      # writes /logs/verifier/reward.json
#
# Every check writes /logs/verifier/checks/<name>.json:
#   {"name":..., "required":true|false, "passed":N, "total":N, "ok":true|false, "failures":[...]}
# wpsb_finish aggregates them: reward = 1 iff every required check is ok.
# If the script dies early, the EXIT trap still writes reward.json (reward 0 when a
# required check is missing or wpsb_finish was never reached).

WPSB_LIB="${WPSB_LIB:-$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)}"
WPSB_LOGS="${WPSB_LOGS:-/logs/verifier}"
WPSB_CHECKS="$WPSB_LOGS/checks"
WPSB_REQUIRED_CHECKS=()
export WPSB_LIB WPSB_LOGS WPSB_CHECKS

_wpsb_log() { echo "[wpsb $(date +%H:%M:%S)] $*" >&2; }

# Write a check result. Usage: _wpsb_record name required(0|1) passed total [failure...]
_wpsb_record() {
  local name="$1" required="$2" passed="$3" total="$4"; shift 4
  local failures='[]'
  if [ "$#" -gt 0 ]; then failures=$(printf '%s\n' "$@" | jq -R . | jq -s .); fi
  local ok=false
  if [ "$total" -gt 0 ] && [ "$passed" -eq "$total" ]; then ok=true; fi
  jq -n --arg name "$name" --argjson required "$([ "$required" = 1 ] && echo true || echo false)" \
    --argjson passed "$passed" --argjson total "$total" --argjson ok "$ok" --argjson failures "$failures" \
    '{name:$name, required:$required, passed:$passed, total:$total, ok:$ok, failures:$failures}' \
    > "$WPSB_CHECKS/$name.json"
  _wpsb_log "check $name: $passed/$total $( [ "$ok" = true ] && echo PASS || echo FAIL )$( [ "$required" = 1 ] || echo ' (soft)')"
}

wpsb_init() {
  mkdir -p "$WPSB_LOGS" "$WPSB_CHECKS"
  rm -f "$WPSB_CHECKS"/*.json "$WPSB_LOGS/reward.json" "$WPSB_LOGS/reward.txt" "$WPSB_LOGS/debug-accumulated.log"
  WPSB_FINISHED=0
  trap '_wpsb_on_exit' EXIT
  _wpsb_log "grading started (lib $WPSB_LIB)"
}

_wpsb_on_exit() {
  if [ "${WPSB_FINISHED:-0}" != 1 ]; then
    _wpsb_log "test.sh exited before wpsb_finish; forcing reward 0"
    _wpsb_record aborted 1 0 1 "test.sh exited before wpsb_finish (see test-stdout)"
    wpsb_finish || true
  fi
  wpsb-server stop >/dev/null 2>&1 || true
}

# Declare checks that MUST appear (a missing required check => reward 0).
wpsb_expect() { WPSB_REQUIRED_CHECKS+=("$@"); }

# Generic check: wpsb_check <name> <required|soft> <command...>
wpsb_check() {
  local name="$1" kind="$2"; shift 2
  local req=1; [ "$kind" = soft ] && req=0
  local out="$WPSB_LOGS/$name.log"
  if "$@" > "$out" 2>&1; then _wpsb_record "$name" "$req" 1 1
  else _wpsb_record "$name" "$req" 0 1 "command failed: $* (see $name.log): $(tail -c 1500 "$out")"; fi
}

# WordPress core files (wp-admin, wp-includes) must be unmodified.
wpsb_integrity() {
  local name="${1:-integrity}"
  local out="$WPSB_LOGS/$name.log"
  if (cd /wordpress && md5sum -c --quiet "$WPSB_LIB/core.md5") > "$out" 2>&1 \
     && [ -f /wordpress/wp-content/db.php ] && cmp -s /wordpress/wp-content/db.php /wordpress/wp-content/plugins/sqlite-database-integration/db.copy; then
    _wpsb_record "$name" 1 1 1
  else
    _wpsb_record "$name" 1 0 1 "WordPress core or the SQLite drop-in was modified: $(head -c 1500 "$out")"
  fi
}

# Restore pristine DB + uploads (code is kept). Stops the server if it is running.
wpsb_reset_site() {
  wpsb-server stop >/dev/null 2>&1 || true
  # wpsb-reset clears debug.log; keep what was logged so far for wpsb_no_fatals.
  if [ -f /wordpress/wp-content/debug.log ]; then cat /wordpress/wp-content/debug.log >> "$WPSB_LOGS/debug-accumulated.log"; fi
  wpsb-reset > "$WPSB_LOGS/reset.log" 2>&1 || { _wpsb_record reset 1 0 1 "wpsb-reset failed: $(tail -c 1000 "$WPSB_LOGS/reset.log")"; return 1; }
}

# npm run build in a JS project, using the shared toolchain node_modules.
# Usage: wpsb_build <name> <dir> [script]
wpsb_build() {
  local name="$1" dir="$2" script="${3:-build}"
  if [ ! -e "$dir/node_modules" ]; then ln -s /opt/wpsb/node/node_modules "$dir/node_modules"; fi
  local out="$WPSB_LOGS/$name.log"
  if (cd "$dir" && npm run "$script") > "$out" 2>&1; then _wpsb_record "$name" 1 1 1
  else _wpsb_record "$name" 1 0 1 "npm run $script failed in $dir: $(tail -c 2000 "$out")"; return 1; fi
}

wpsb_server_start() {
  if ! wpsb-server start > "$WPSB_LOGS/server-start.log" 2>&1; then
    _wpsb_record server 1 0 1 "Playground server failed to start: $(tail -c 2000 "$WPSB_LOGS/server-start.log")"
    return 1
  fi
}

wpsb_server_stop() { wpsb-server stop >/dev/null 2>&1 || true; }

# PHPUnit suite against the live /wordpress site (native PHP).
# Usage: wpsb_phpunit <name> <dir-or-file> [required|soft] [extra phpunit args...]
wpsb_phpunit() {
  local name="$1" target="$2" kind="${3:-required}"; shift 3 2>/dev/null || shift $#
  local req=1; [ "$kind" = soft ] && req=0
  local junit="$WPSB_LOGS/$name.junit.xml"
  rm -f "$junit"
  (cd /wordpress && phpunit --bootstrap "$WPSB_LIB/phpunit/bootstrap.php" --no-configuration \
      --do-not-cache-result --colors=never --log-junit "$junit" "$@" "$target") \
      > "$WPSB_LOGS/$name.log" 2>&1 || true
  php "$WPSB_LIB/phpunit/junit2check.php" "$name" "$req" "$junit" "$WPSB_LOGS/$name.log" > "$WPSB_CHECKS/$name.json"
  _wpsb_log "check $name: $(jq -r '"\(.passed)/\(.total) " + (if .ok then "PASS" else "FAIL" end)' "$WPSB_CHECKS/$name.json")"
}

# Playwright suite (Chromium) against the Playground server; starts it if needed.
# Usage: wpsb_playwright <name> <spec-dir> [required|soft]
wpsb_playwright() {
  local name="$1" dir="$2" kind="${3:-required}"
  local req=1; [ "$kind" = soft ] && req=0
  wpsb-server status >/dev/null 2>&1 || wpsb_server_start || return 1
  local json="$WPSB_LOGS/$name.playwright.json"
  rm -f "$json"
  ln -sfn /opt/wpsb/node/node_modules "$(dirname "$dir")/node_modules" 2>/dev/null || true
  ln -sfn /opt/wpsb/node/node_modules "$(dirname "$WPSB_LIB")/node_modules" 2>/dev/null || true
  (cd /opt/wpsb/node && WPSB_SPEC_DIR="$dir" WPSB_PW_JSON="$json" WPSB_PW_OUT="$WPSB_LOGS/$name-artifacts" \
      npx playwright test --config "$WPSB_LIB/e2e/playwright.config.mjs") > "$WPSB_LOGS/$name.log" 2>&1 || true
  node "$WPSB_LIB/e2e/pw2check.mjs" "$name" "$req" "$json" "$WPSB_LOGS/$name.log" > "$WPSB_CHECKS/$name.json"
  _wpsb_log "check $name: $(jq -r '"\(.passed)/\(.total) " + (if .ok then "PASS" else "FAIL" end)' "$WPSB_CHECKS/$name.json")"
}

# WordPress Coding Standards (soft). Scores the fraction of checked PHP files without errors.
# Usage: wpsb_wpcs <name> <repo-dir> [--changed]   (--changed: only files changed since the initial commit)
wpsb_wpcs() {
  local name="$1" repo="$2" mode="${3:-}"
  local files=()
  if [ "$mode" = "--changed" ] && [ -d "$repo/.git" ]; then
    local root; root=$(git -C "$repo" rev-list --max-parents=0 HEAD 2>/dev/null | tail -1)
    while IFS= read -r f; do
      [ -n "$f" ] && [ -f "$repo/$f" ] && files+=("$repo/$f")
    done < <( { git -C "$repo" diff --name-only "$root" -- '*.php'; git -C "$repo" ls-files --others --exclude-standard -- '*.php'; } | sort -u | grep -v -E '(^|/)(vendor|node_modules|build)/|\.asset\.php$' )
  else
    while IFS= read -r f; do files+=("$f"); done < <(find "$repo" -name '*.php' -not -path '*/vendor/*' -not -path '*/node_modules/*' -not -path '*/build/*' -not -name '*.asset.php')
  fi
  if [ "${#files[@]}" -eq 0 ]; then _wpsb_record "$name" 0 1 1; return 0; fi
  local json="$WPSB_LOGS/$name.phpcs.json"
  phpcs --standard="$WPSB_LIB/phpcs.xml" --report=json --report-file="$json" "${files[@]}" > /dev/null 2>&1 || true
  local total clean
  total=$(jq '.files | length' "$json" 2>/dev/null || echo 0)
  clean=$(jq '[.files[] | select(.errors == 0)] | length' "$json" 2>/dev/null || echo 0)
  if [ "$total" -eq 0 ]; then _wpsb_record "$name" 0 0 1 "phpcs produced no report"; return 0; fi
  mapfile -t fails < <(jq -r '.files | to_entries[] | select(.value.errors > 0) | "\(.key): \(.value.errors) errors"' "$json")
  _wpsb_record "$name" 0 "$clean" "$total" "${fails[@]}"
}

# PHP syntax check for all PHP files in a directory (required).
wpsb_php_lint() {
  local name="$1" dir="$2" bad=()
  while IFS= read -r f; do
    php -l "$f" >/dev/null 2>&1 || bad+=("$f")
  done < <(find "$dir" -name '*.php' -not -path '*/vendor/*' -not -path '*/node_modules/*')
  if [ "${#bad[@]}" -eq 0 ]; then _wpsb_record "$name" 1 1 1; else _wpsb_record "$name" 1 0 1 "syntax errors: ${bad[*]}"; fi
}

# Fail if PHP fatal errors / uncaught exceptions landed in debug.log during grading.
wpsb_no_fatals() {
  local name="${1:-no_fatals}" log=/wordpress/wp-content/debug.log
  if cat "$WPSB_LOGS/debug-accumulated.log" "$log" 2>/dev/null | grep -E 'PHP (Fatal error|Parse error)|Uncaught ' > "$WPSB_LOGS/$name.log"; then
    _wpsb_record "$name" 1 0 1 "PHP fatals in debug.log: $(head -c 1500 "$WPSB_LOGS/$name.log")"
  else
    _wpsb_record "$name" 1 1 1
  fi
}

wpsb_finish() {
  [ -f /wordpress/wp-content/debug.log ] && cp /wordpress/wp-content/debug.log "$WPSB_LOGS/debug.log" 2>/dev/null
  node "$WPSB_LIB/reward.mjs" "$WPSB_CHECKS" "$WPSB_LOGS/reward.json" "${WPSB_REQUIRED_CHECKS[@]}"
  WPSB_FINISHED=1
  cat "$WPSB_LOGS/reward.json"
}
