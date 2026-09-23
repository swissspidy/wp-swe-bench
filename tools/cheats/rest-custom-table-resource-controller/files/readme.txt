=== Acme Leads ===
Contributors: acmeweb
Tags: leads, crm, sales, forms
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.9.0
License: GPLv2 or later

Collects sales leads from the website ("Talk to sales" form) and lets the sales team work through them.

== Description ==

* `[acme_lead_form]` shortcode: the "Talk to sales" form. Submissions go to `POST /wp-json/acme-leads/v1/leads`.
* Leads screen in wp-admin. Sales reps (`acme_view_leads`) see the leads assigned to them, sales managers
  (`acme_manage_leads`) see and edit all leads.
* `GET /wp-json/acme-leads/v1/leads` is used by the nightly CRM sync (`page`, `per_page`, `orderby`,
  `order`, `status`).
* `acme-leads/v2` REST API for the sales dashboard: `GET /leads`, `GET|PATCH /leads/<id>`, `POST /leads/bulk`.
* Leads are stored in the `{prefix}acme_leads` table (dates in UTC).

Hooks:

* `acme_leads_created` (action): `$id, $data`.
* `acme_leads_status_changed` (action): `$id, $new_status, $old_status`. Won-lead mails and the CRM sync
  rely on it.
* `acme_leads_initial_score` (filter).

== Changelog ==

= 1.9.0 =
* Security: `GET /acme-leads/v1/leads` no longer passes `orderby`/`order` to the database; unsupported values are rejected.
* New `acme-leads/v2` REST API: `/leads` (filters, search, cursor pagination), `/leads/<id>` (ETag / If-None-Match,
  updates), `/leads/bulk` (bulk status changes). `_fields` only reads the requested columns.

= 1.8.0 =
* Dashboard widget with the sales pipeline.

= 1.6.0 =
* Lead scores, `updated_at`.

= 1.4.0 =
* Leads can be assigned to sales reps; reps only see their own leads in wp-admin.

= 1.2.0 =
* `GET /acme-leads/v1/leads` for the CRM sync.
* `acme_leads_status_changed` action.

= 1.0.0 =
* Initial release.
