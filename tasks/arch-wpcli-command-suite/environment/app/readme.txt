=== Acme Redirects ===
Contributors: acmeweb
Tags: redirects, 301, 410, seo
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.3.1
License: GPL-2.0-or-later

Manage redirects with exact, prefix and regular expression rules, priorities and hit counters.

== Description ==

Rules live in their own database table (`{prefix}acme_redirects`) and are managed
under **Tools → Redirects**. Every rule has:

* a **source**: a path (`/old-page/`) for exact and prefix rules, or a regular
  expression without delimiters (`^/blog/(\d+)/?$`) for regex rules,
* a **target**: a path on this site or a full URL (empty for 410 Gone),
* a **status** code: 301, 302, 307, 308 or 410,
* a **priority** (0-100, lower numbers are checked first, default 10),
* an enabled flag, a note, and a hit counter with the time of the last hit.

= How rules are matched =

1. Enabled rules are checked by priority (lowest first), then by ID. The first match wins.
2. Paths are compared percent-decoded, case-insensitively, with duplicate slashes
   collapsed and ignoring a trailing slash.
3. Exact rules match the path. If the source contains a query string
   (`/search?cat=5`), the request must have exactly those query arguments.
4. Prefix rules match the source and everything below it (`/shop` matches `/shop/shoes`,
   not `/shopping`). A target ending in `*` receives the rest of the path.
5. Regex rules are matched case-insensitively against the decoded path, including
   its trailing slash. `$1`…`$9` in the target are replaced by the captured groups.
6. The query string of the request is passed on, unless the target has its own
   query string or the rule matched on a query string.
7. `acme_redirects_match` (bool $match, Rule $rule, string $path, array $query) can
   veto a match; `acme_redirects_target` (string $url, Rule $rule, string $path)
   filters the final URL.

= Hooks =

* `acme_redirects_rules_changed` (string $action, Rule $rule): after a rule was created,
  updated or deleted. Our CDN integration purges on it.
* `acme_redirects_validate_rule` (WP_Error $errors, array $input, array $args)
* `acme_redirects_loaded` (Plugin $plugin)

== Changelog ==

= 2.3.1 =
* Fix: the export dropped rules with an empty note on some hosts.

= 2.3.0 =
* New: bulk actions to enable/disable rules and reset hit counters.
* New: CSV export (Tools → Redirects → Export CSV).

= 2.2.0 =
* New: `acme_redirects_validate_rule` filter.
* Tweak: regex rules are validated before saving.

= 2.1.0 =
* New: the matched rule set is cached, so the front end runs one query per day
  instead of one per request. Always write rules through `Rule_Repository`.

= 2.0.0 =
* New: priorities, notes, 307/308 status codes, prefix rules with `*` targets.
* Changed: rules are checked by priority instead of by ID.

= 1.2.0 =
* New: rules can be disabled; regex rules; last hit time.

= 1.0.0 =
* First release: exact 301 redirects.
