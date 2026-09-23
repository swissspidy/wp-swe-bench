=== Acme Newsletter ===
Contributors: acmedigital
Tags: newsletter, signup, subscribe, block
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.3.1
License: GPLv2 or later

Newsletter signup form that stores subscribers in the site database.

== Description ==

Ways to show the signup form:

* **Automatically** after the content of single posts (Settings → Newsletter → Placement).
* The **Newsletter signup** block (`acme/newsletter-signup`).
* The `[acme_newsletter heading="…" button="…"]` shortcode.
* The **Newsletter signup** widget (classic themes).

Every subscriber is stored with the *source* of the form they used (`content`,
`block`, `shortcode`, `widget`), which the marketing team uses in their reports
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
