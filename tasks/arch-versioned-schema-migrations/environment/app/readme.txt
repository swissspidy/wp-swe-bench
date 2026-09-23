=== Acme CRM ===
Contributors: acmeweb
Tags: crm, contacts, leads, sales
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later

Lightweight CRM: contacts, notes, lifecycle stages, a website contact form, CSV export and a REST API for the sales app.

== Description ==

* CRM → Contacts lists, searches and filters contacts; CRM → Add contact.
* `[acme_crm_form]` puts a contact form on any page. Submissions create a lead (or reuse the
  contact with the same email) and store the message as a note.
* CRM → Contacts → Export CSV (administrators). Columns: `id, full_name, email, phone,
  company, stage, created_at`.
* REST API for the sales app (editors and administrators):
  * `GET|POST /wp-json/acme-crm/v1/contacts` (`search`, `stage`, `page`, `per_page`)
  * `GET|PUT|PATCH|DELETE /wp-json/acme-crm/v1/contacts/<id>`
  * `GET|POST /wp-json/acme-crm/v1/contacts/<id>/notes`
* `wp acme-crm stats` shows contacts per stage.

= Data =

Tables `{prefix}acme_crm_contacts` and `{prefix}acme_crm_notes`; options `acme_crm_version`
and `acme_crm_settings`.

= Public API =

* `acme_crm_get_contact( $id )`: array with `id, full_name, email, phone, company, stage,
  owner_id, source, created_at, updated_at`.
* `acme_crm_create_contact( $data )`, `acme_crm_table( $name )`, `acme_crm_stages()`.
* Action `acme_crm_contact_saved( $id, $contact )`, filter `acme_crm_stages`.

== Changelog ==

= 1.4.0 =
* Lifecycle stages (lead, prospect, customer, churned) replace the old status field.
* Contacts track when they were last updated (`updated_at`).
* Indexes on email and stage, and on the notes' contact.

= 1.3.0 =
* REST API for the sales app.

= 1.2.0 =
* Notes. Company field.

= 1.1.0 =
* Website contact form.

= 1.0.0 =
* First release.
