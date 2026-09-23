=== Acme Contact ===
Contributors: acmeweb
Requires at least: 6.6
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPL-2.0-or-later

Contact form for Acme sites.

== Description ==

`[acme_contact subjects="Sales|Support|Press" button="Send message" title="Write to us"]`

* Name, email, optional subject drop-down (`subjects`, separated by `|` or `,`) and message.
* Posts to `wp-admin/admin-post.php` (action `acme_contact_submit`), no JavaScript needed. After
  sending, the visitor is redirected back to the page with a success message, or with the errors
  and their input (kept for 10 minutes).
* Notification email to the address in Settings → Contact form (default: the site admin email),
  with Reply-To set to the sender.
* Spam protection: a hidden honeypot field (`acme_website`; bots that fill it are told the
  message was sent, but nothing happens) and a per-IP rate limit (5 messages per 10 minutes).

= Contact form block =

* "Contact form" block with field blocks (text, email, message, drop-down, checkbox): label,
  field name and "required" per field. Posts only store block comments, the form is rendered on
  the server (the landing-page importer writes the same markup).
* Submits without a page reload (`POST /wp-json/acme-contact/v1/submissions`) and still works
  without JavaScript (normal post to admin-post.php, redirect back).
* Entries are stored in `{prefix}acme_contact_entries` and listed under "Contact entries"
  (Administrators and Editors) with search, paging and a CSV export.
* Classic posts: "Convert to blocks" turns `[acme_contact]` into a Contact form block.

= Hooks =

* `acme_contact_email_template` (filter) – the notification email (to, subject, body, headers).
  Our client sites use it for branding.
* `acme_contact_rate_limit` (filter) – submissions per IP per 10 minutes.
* `acme_contact_client_ip` (filter) – visitor IP (sites behind proxies).
* `acme_contact_submitted` (action) – after a successful submission.

== Changelog ==

= 2.0.0 =
* New: Contact form block with configurable fields, inline validation messages and submission without page reload.
* New: stored entries, "Contact entries" screen with search, paging and CSV export.
* New: `[acme_contact]` shortcodes convert to the block.

= 1.6.2 =
* Fix: subject options are taken from the page's shortcode, not from the submitted form.

= 1.6.0 =
* Message character counter.
* Errors are shown with the entered values after a failed submission.

= 1.5.0 =
* Rate limiting and honeypot.

= 1.0.0 =
* Initial release.
