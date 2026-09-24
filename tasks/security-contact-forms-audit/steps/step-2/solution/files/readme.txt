=== Acme Forms ===
Contributors: acmeweb
Tags: forms, contact form, job applications, csv
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.5.0
License: GPLv2 or later

Contact and application forms with a submissions inbox, CSV export, file uploads and e-mail notifications.

== Description ==

* Build forms (Acme Forms → All Forms) with text, e-mail, website, paragraph, drop-down and file upload fields, and embed them with `[acme_form id="123"]`.
* Every submission lands in the **Submissions** inbox (search, filter by form, new/read views, bulk delete).
* **CSV export** per form, optionally limited to a date range.
* **E-mail notifications** to the site owner (Acme Forms → Settings).
* A **dashboard widget** shows the latest submissions to administrators and editors.

Developers:

* `acme_forms_field_types` filter – add field types.
* `acme_forms_notification_email` filter – change the notification e-mail.
* `acme_forms_submission_created` / `acme_forms_submission_deleted` / `acme_forms_settings_saved` / `acme_forms_settings_updated` / `acme_forms_submissions_exported` actions.
* `acme_forms_admin_menu` action – add screens to the Acme Forms menu.
* Settings are stored in the `acme_forms_settings` option.

== Changelog ==

= 2.5.0 =
* New: Acme Forms → Audit log records who deleted submissions, exported them or changed the settings.
* Security: website values are only linked when they are http(s) addresses.
* Security: CSV cells that a spreadsheet would run as a formula are prefixed with an apostrophe.
* Security: file fields only accept their allowed types (default: pdf, doc, docx, txt, jpg, jpeg, png), never pages or scripts, and the content must match the type.
* Security: uploads are stored under random names and downloaded through the inbox, as attachments.

= 2.4.0 =
* Security: submission values are escaped in the Submissions screen and in notification e-mails.
* Security: deleting submissions and saving the settings only work from the plugin's own screens.
* Security: the export's date range only accepts YYYY-MM-DD dates (anything else is a 400 error).
* Security: the dashboard widget's data is only available to users who can see the widget.

= 2.3.0 =
* Dashboard widget with the latest submissions.
* Settings: submissions per page.

= 2.2.0 =
* CSV export can be limited to a date range.
* Website fields are shown as links.

= 2.1.0 =
* File upload fields (stored in `wp-content/uploads/acme-forms/`).

= 2.0.0 =
* Submissions moved to their own database table; field definitions are stored as JSON.
* Field values are stored exactly as submitted and formatted on display.

= 1.2.0 =
* Drop-down fields.

= 1.0.0 =
* Initial release.
