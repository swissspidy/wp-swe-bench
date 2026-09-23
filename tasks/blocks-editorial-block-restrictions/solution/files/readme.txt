=== Acme Newsroom ===
Contributors: acmedigital
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 4.0.0
License: GPLv2 or later

Press releases and newsroom tooling for acme.example.

== Description ==

* Post type "Press releases" (`press_release`, archive at /press/) with an embargo date (`_acme_embargo`).
  New press releases start with the standard structure: dateline, lead paragraph, body, company
  boilerplate and media contact.
* Blocks: Dateline (`acme/dateline`), Company boilerplate (`acme/boilerplate`), Media contact
  (`acme/media-contact`) and Breaking news banner (`acme/breaking-banner`). All server-rendered.
* Settings → Newsroom:
  * Press release details (dateline city, boilerplate, media contact).
  * Editorial rules: allowed blocks per post type, extra restrictions per role, and design tools
    (custom colors, custom font sizes) switched off per role. Stored in the `acme_newsroom_block_rules`
    option (see `includes/class-editorial-rules.php` for the format).
* Filter `acme_newsroom_rule_post_types`: post types that get editorial rules on the settings screen.

== Changelog ==

= 4.0.0 =
* Editorial rules are now real editor restrictions (inserter, slash inserter, paste, transforms)
  instead of CSS/JS workarounds. Child blocks of allowed blocks are allowed automatically.
  Existing content with disallowed blocks keeps working.
* Users with several roles get the blocks of all their roles; administrators are exempt from role rules.
* Press releases have a locked structure; only the body is free-form.
* Custom colors / font sizes can really be switched off per role.

= 3.2.0 =
* Breaking news banner: "Update" level.

= 3.1.0 =
* Editorial rules: namespace wildcards (`acme/*`), design tools per role.

= 3.0.0 =
* Editorial rules per post type and role (settings screen).
* Press release structure is inserted automatically for new press releases.

= 2.0.0 =
* Blocks: dateline, boilerplate, media contact.

= 1.0.0 =
* Press release post type.
