# Planned security tasks

The plan called for ~5 dedicated `security` tasks. Two exist
(`security-rest-idor-data-leak`, `security-ssrf-object-injection-embed`) from the start. Three more below were
designed; §1 has since been authored as `tasks/security-contact-forms-audit` (on a retry), §2 and §3
are still **not authored**. The first two automated authoring attempts were stopped by a model safety
check while the subagent was designing the deliberately flawed starting plugins, so they are
written down here for a human author (or a later attempt). Their slots were filled with feature
tasks that grade security requirements as part of the feature
(`blocks-contact-form-block-submissions`, `arch-privacy-export-erase-integration`,
`rest-member-directory-visibility`).

All three follow the normal rubric in [AUTHORING.md](AUTHORING.md): a realistic plugin
(~1,000–2,000 LOC), an instruction written as a responsible-disclosure report from the reviewer's
point of view (who can do what they shouldn't, and the impact, never the fix), hidden tests that
assert the secure behaviour **and** that every legitimate flow keeps working, and a cheat that fixes
only the proof of concept named in the report.

Testing style that works well (see `tasks/rest-admin-ajax-to-rest-migration` and
`tasks/security-rest-idor-data-leak`): ordinary PHPUnit regression tests that send requests as
different roles via `$this->http_login()` / `$this->http()` and assert 401/403/nonce failures,
escaped output (`&lt;script&gt;` present, no raw tag), unchanged DB state, refused paths.

---

## 1. `security-contact-forms-audit` (multi-step, 2 steps, very-hard, ~8 h) — authored

**Codebase.** "Acme Forms": forms as a CPT (field definitions in post meta), submissions in a custom
table, a `WP_List_Table` admin screen with search/filters, CSV export with a date-range filter, file
upload fields (stored in uploads), email notifications to the site owner, a settings page, and an
admin-ajax action used by a dashboard widget to fetch submissions.

**Step 1 — the audit report.** Symptoms/impact the reviewer lists:
- Submission field values are shown unescaped in the admin list table and in the notification email
  (a visitor can plant markup that runs in an administrator's browser).
- "Delete submission" (a GET link) and the settings form don't verify the request came from the
  site's own admin screen (an admin visiting a third-party page can be made to delete data).
- The export's date filter is concatenated into SQL (a crafted filter value changes the query).
- Any logged-in user, including subscribers, can fetch any submission through the dashboard
  widget's ajax action.

Legitimate flows that must keep working: public submission incl. uploads and validation, the admin
list (search, pagination, filters), delete from the list, CSV export with and without dates, the
settings page, the dashboard widget for editors/admins, notification emails (via `mails()`).

Tests (≥ 15): per-role probes for the ajax action (subscriber/author/editor/admin/anonymous);
requests without/with forged/with valid nonce for delete and settings; stored values rendered
escaped in list table HTML and email body; malformed date filter values → same result set as no
filter or a 400, no DB error, and a sentinel row untouched; P2P for all legitimate flows.

Cheat: escapes the list table only (not the email), checks the nonce on delete but not settings,
casts only the `from` date.

**Step 2 — the reviewer's retest + a feature request.**
- The same output problem in another field type (e.g. `select` option labels rendered into an
  attribute context, or a "website" field rendered as a link: `javascript:` URLs).
- CSV exports: values starting with `=`, `+`, `-`, `@` (and tab/CR) are interpreted as formulas by
  spreadsheet apps; export must neutralise them (specify the exact convention, e.g. prefix `'`).
- Uploaded files are served with a content type the browser renders as HTML (e.g. `.html`, `.svg`
  uploads in a "CV" field): restrict types per field and serve downloads through a handler with
  `Content-Disposition: attachment` and `X-Content-Type-Options: nosniff`.
- Feature: an audit log of admin actions (delete, export, settings change) with a specified row
  shape and a read-only admin screen.

Tests: the new variants, CSV bytes, upload type matrix, download headers, audit log rows; all step-1
tests re-run. Cheat: formula neutralisation only for `=`.

---

## 2. `security-downloads-path-traversal-upload` (hard, ~6 h)

**Codebase.** "Acme Downloads": member-only file downloads (a `download` CPT pointing at stored
files), admin uploads, per-file download counters, "signed" share links for non-members, a
shortcode listing files for the current membership level.

**Report.**
- The download handler takes a relative file path parameter; `..` sequences reach files outside
  the downloads folder (e.g. `wp-config.php`).
- Uploads accept any file type (`.php`, `.phtml`, `.phar`, `.svg` with script).
- Private files are reachable by incrementing the numeric id in the URL.
- Share links never expire and the "signature" is a predictable hash of the id.

**Expected behaviour** (instruction contracts): downloads resolved by id, never by client-supplied
path; per-file access rules unchanged for members; upload allow-list (specify it) with
content sniffing; share links with an expiry and a keyed signature (specify the query parameter
format, e.g. `?download=ID&expires=TS&sig=HEX`, HMAC-SHA256 over `ID|TS` with a site-specific key);
counters still increment exactly once per successful download; correct headers
(`Content-Disposition`, `X-Content-Type-Options: nosniff`, no caching of private files).

**Environment finding (important).** WordPress Playground serves *every* file under `/wordpress`
directly (including dot-files, `.ht*`, and `.php` files in uploads, which execute), and PHP in
Playground cannot read files outside `/wordpress` (a symlink to an outside folder returns a 500).
So "store files outside the web root" is **not testable** here. Define "not directly reachable" as:
unguessable stored filenames in a dedicated uploads subfolder **plus** delivery only through the
handler, and test that the old predictable URLs 404.

Tests: traversal variants (`../`, encoded `%2e%2e%2f`, absolute paths, null bytes) → refused and no
file contents in the body; upload matrix (allowed vs refused, spoofed extensions/MIME); id
enumeration as non-member → 403/404; share links (valid, expired, tampered id, tampered expiry,
wrong key) ; counters; member downloads P2P. Multipart uploads: `TestCase::http()` URL-encodes
array bodies, so build `multipart/form-data` bodies by hand in `_helpers.php`.

Cheat: blocks the literal `../` string only and validates the extension but not the content.

---

## 3. `security-membership-privesc-reset` (hard, ~6 h)

**Codebase.** "Acme Membership": front-end registration and profile forms, membership levels mapped
to roles, a custom "forgot password" flow with its own tokens and emails, a login form with a
`redirect_to` parameter, and a members directory.

**Report.**
- Extra POST fields on registration/profile update can change a user's role or capabilities
  (the handler passes the whole `$_POST` to user/meta update functions).
- Reset tokens are predictable (derived from the user id and time) and remain valid after use and
  after a password change.
- The post-login redirect accepts external URLs.
- Login and reset forms return different messages for existing vs unknown accounts.

**Expected behaviour**: only whitelisted profile fields are writable; role changes only through
the level mapping; reset tokens random, single-use, expiring, invalidated on password change;
redirects limited to the site (fallback to the account page); identical responses for existing and
unknown accounts. Registration, profile edit, level changes by admins, and the full reset flow keep
working (use `mails()` to fetch the reset email).

Tests: extra-field probes on both forms (role, capabilities meta key, `user_level`, other users'
ids); token reuse, expiry, predictability (two tokens for the same user in the same second differ),
invalidation on password change; redirect matrix (absolute external, protocol-relative `//evil`,
backslash tricks, encoded) vs allowed internal paths; message equality; P2P flows.

Cheat: strips only the `role` key and checks `redirect_to` with a simple prefix match.

---

## Suggested retry prompt framing

If you try automated authoring again: present it as a secure-coding training benchmark (like a
course or CTF), keep the starting-code mistakes simple and typical (missing checks, missing escaping,
concatenated SQL, trusting paths), avoid elaborate payloads, and describe tests as regression tests
of the fixed behaviour. Author one task per session and start with task 1, step 1.
