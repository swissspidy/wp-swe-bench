=== Acme Members ===
Contributors: acmeweb
Requires at least: 6.6
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.1.0
License: GPL-2.0-or-later

Member directory for the Acme Makers community.

== Description ==

* "Member" role (`acme_member`). Members and administrators have the `read_acme_members` capability.
* Profile fields (filter `acme_members_fields`): job title, company, city, phone, website, about me.
* Visibility per profile and per field: Everyone (`public`), Members only (`members`), Only me (`private`).
  Only the member and people who can edit users see private data.
* Profile pages at `/members/{nicename}/`.
* Directory: `[acme_member_directory city="" per_page="12"]` shortcode and the "Member directory" block.
* Profile data on `/wp/v2/users` (`acme_profile`), member results in `/wp/v2/search?type=acme-member`,
  author details in the RSS feed, member profiles in the XML sitemap.
* Template tag `acme_members_author_card( $user_id )` for author archives.

== Changelog ==

= 2.1.0 =
* Directory output is cached for 10 minutes (the directory page was our slowest page).
* Member search in the site search overlay (`/wp/v2/search?type=acme-member`).

= 2.0.0 =
* Per-field visibility. Profile visibility now has three levels (was: hidden yes/no).
* One user meta per field (`acme_member_{field}`). 1.x profiles (`acme_member_profile`) are read
  until the profile is saved again.
* Renamed fields: `title` → `job_title`, `about` → `bio`.

= 1.3.0 =
* Option to hide the phone number.

= 1.0.0 =
* Initial release: profile pages, directory shortcode, hide profile option.
