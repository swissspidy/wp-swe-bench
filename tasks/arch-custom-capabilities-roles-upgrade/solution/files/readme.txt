=== Acme Newsroom ===
Contributors: acmedigital
Tags: newsroom, editorial workflow, approval
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 3.0.0
License: GPL-2.0-or-later

Stories, desks and the editorial approval workflow for the Acme Daily newsroom.

== Description ==

* **Stories** (`story` post type, `/stories/…`) organised in **Desks**.
* **Approval**: the desk approves stories before they go out. Approval is shown in the Stories
  list ("Approval" column, "Approve" row action), in the "Approval" box on the edit screen and in
  the REST API:
  * `approval` field on `/wp/v2/stories` (read only),
  * `GET|POST|DELETE /acme-newsroom/v1/stories/<id>/approval` (read / approve / withdraw).
* **Freelancers** write stories for us but are not staff. They have the **Freelancer** role:
  they can write, submit and delete their own stories (until the desk approves them) and upload
  images, nothing else. Their published stories get a "Freelance contribution by …" credit line.

= Capabilities =

Stories have their own capabilities: `edit_stories`, `edit_others_stories`, `edit_published_stories`,
`edit_private_stories`, `publish_stories`, `delete_stories`, `delete_others_stories`,
`delete_published_stories`, `delete_private_stories`, `read_private_stories` and `approve_stories`
(meta capabilities: `edit_story`, `delete_story`, `read_story`, `approve_story`).

* Nobody can approve their own story.
* An approved story can only be edited or deleted by users who can edit/delete others' stories.

The 3.0 upgrade gives every role the story capabilities matching the post capabilities it had at
that time (and `approve_stories` to roles that can edit others' posts). It runs once; later changes
to the roles are left alone.

= Hooks =

* `acme_newsroom_story_approved( $story_id, $user_id )` / `acme_newsroom_story_unapproved( $story_id, $user_id )`
  – the Slack bot posts to #desk.
* `acme_newsroom_freelance_credit` – filters the credit line HTML.

= Functions (used by the theme and the Slack bot) =

* `Acme\Newsroom\is_approved( $story )`
* `Acme\Newsroom\get_approval( $story )`
* `Acme\Newsroom\is_freelancer( $user_id )`

== Changelog ==

= 3.0.0 =
* New: stories have their own capabilities (`edit_stories`, `approve_stories`, …).
* New: "Freelancer" role. Contributors flagged as freelancers are moved to it automatically; the
  `acme_freelancer` user meta and the profile checkbox are gone.
* Fix: freelancers could create regular posts through the REST API.
* Fix: authors could still edit their stories after approval; editors could approve their own stories.

= 2.3.0 =
* New: "Approval" box on the story edit screen.
* Fix: freelancers could see other freelancers' drafts in the Stories list.

= 2.2.0 =
* New: DELETE /approval withdraws an approval.

= 2.1.0 =
* New: credit line for freelance stories.
* New: freelancers can upload images.

= 2.0.0 =
* Approval stored as `_acme_approved` = "1" (1.x stored "yes"; both are still understood).
* Freelancer flag stored as "1" (1.x stored "yes").
* New: REST endpoint for approvals, `acme_newsroom_story_approved` action.

= 1.0.0 =
* Initial release.
