=== Acme Newsletter ===
Contributors: acmedigital
Tags: newsletter, signup, subscribe, block
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.4.0
License: GPLv2 or later

Newsletter signup form that stores subscribers in the site database.

== Description ==

Ways to show the signup form:

* **Automatically** after the content of single posts and at the end of the site footer
  (Settings → Newsletter → Placement). In block themes the form is added to the Single
  templates and the footer template part, where it can be moved or removed in the Site
  Editor. Classic themes get it appended to the content of single posts.
* The **Newsletter signup** block (`acme/newsletter-signup`).
* The `[acme_newsletter heading="…" button="…"]` shortcode.
* The **Newsletter signup** widget (classic themes).

Every subscriber is stored with the *source* of the form they used (`content`,
`footer`, `block`, `shortcode`, `widget`), which the marketing team uses in their reports
(Users → Subscribers, CSV export).

= Markup =

The Acme themes style the form and the CRM team scrapes it, so treat the markup as
a public contract: `.acme-newsletter` wrapper, `form.acme-newsletter__form`, fields
`acme_email`, `acme_name`, `acme_consent`, `acme_source`, honeypot `acme_website`.

= Hooks =

* `acme_newsletter_form_args` (filter): form arguments before rendering.
* `acme_newsletter_auto_insert` (filter): whether to add the form after a single post's content.
* `acme_newsletter_sources` (filter): known subscription sources.
* `acme_newsletter_before_subscribe` (filter): return a WP_Error to reject a subscription.
* `acme_newsletter_subscribed` (action): after a subscriber was stored.

== Changelog ==

= 2.4.0 =
* New: block themes get the signup block in the Single templates (after the post content) and at the end of the footer template part. Site editors can move or remove it.
* New: "Placement" settings replace the "Single posts" checkbox (`placements` setting; the old `auto_insert` value is still honoured until the settings are saved).
* New: `footer` subscription source.
* Fix: element IDs are unique when several forms are on one page.

= 2.3.1 =
* Fix: don't add the automatic form when the post already contains the block or the shortcode.

= 2.3.0 =
* New: Newsletter signup block.
* New: subscribers screen shows counts per source.

= 2.2.0 =
* New: honeypot field; consent checkbox is required.

= 2.1.0 =
* New: subscription source is stored (DB version 2).
* New: CSV export.

= 2.0.0 =
* Subscribers are stored in a custom table instead of an option.

= 1.0.0 =
* Initial release (shortcode + widget).
