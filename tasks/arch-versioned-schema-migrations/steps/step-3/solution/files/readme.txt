=== Acme CRM ===
Contributors: acmeweb
Tags: crm, contacts, leads, sales
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.7.0
License: GPLv2 or later

Lightweight CRM: contacts, notes, lifecycle stages, a website contact form, CSV export and a REST API for the sales app.

== Description ==

* CRM → Contacts lists, searches and filters contacts; CRM → Add contact.
* `[acme_crm_form]` puts a contact form on any page. Submissions create a lead (or reuse the
  contact with the same email) and store the message as a note.
* CRM → Contacts → Export CSV (administrators). Columns: `id, full_name, first_name,
  last_name, email, phone, company, stage, created_at`.
* REST API for the sales app (editors and administrators):
  * `GET|POST /wp-json/acme-crm/v1/contacts` (`search`, `stage`, `page`, `per_page`)
  * `GET|PUT|PATCH|DELETE /wp-json/acme-crm/v1/contacts/<id>`
  * `GET|POST /wp-json/acme-crm/v1/contacts/<id>/notes`
* `wp acme-crm stats` shows contacts per stage.

= Data =

Tables `{prefix}acme_crm_contacts` and `{prefix}acme_crm_notes`; options `acme_crm_version`
and `acme_crm_settings`.

= Database updates =

The schema is versioned (option `acme_crm_db_version`). Pending migrations run
automatically on the first web request after an update (and on activation), one run at a
time (lock: option `acme_crm_migration_lock`, holding the start time; a lock older than
10 minutes is considered abandoned). Every result is logged in the option
`acme_crm_migration_log`. A failed migration stops the run; administrators see a notice
with a "Retry now" button. Add-ons can add migrations with the `acme_crm_migrations` filter
(`up`, and optionally `down` to make them reversible).

WP-CLI never migrates automatically; deploy scripts use:

* `wp acme-crm migrate status [--format=json]` (exit code 2 while migrations are pending),
* `wp acme-crm migrate run [--dry-run]` (runs everything, including all batches of the
  name backfill),
* `wp acme-crm migrate rollback [--dry-run]` (undoes the latest migration and pauses
  automatic runs until the next `migrate run`).

= Public API =

* `acme_crm_get_contact( $id )`: array with `id, full_name, first_name, last_name, email,
  phone, company, stage, owner_id, source, created_at, updated_at` (`full_name` is
  "first last").
* `acme_crm_create_contact( $data )`, `acme_crm_table( $name )`, `acme_crm_stages()`.
* Action `acme_crm_contact_saved( $id, $contact )`, filter `acme_crm_stages`.

== Changelog ==

= 1.7.0 =
* `wp acme-crm migrate status|run|rollback`.
* Migrations 2–5 can be rolled back.

= 1.6.0 =
* Contacts have a first and a last name (migrations 3–5). Existing names are split in the
  background, 500 contacts per request. Legacy "full name" input (website form, REST
  `name`, `acme_crm_create_contact()`) is split with the same rules.
* CSV export: new `first_name` and `last_name` columns.

= 1.5.0 =
* Versioned database migrations that run on update, not only on activation. Fixes sites
  updated from 1.2/1.3 that never got the 1.4.0 columns ("Could not save the contact").

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
