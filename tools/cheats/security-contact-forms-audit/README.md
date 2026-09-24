# Cheats for security-contact-forms-audit

- **step-1/**: the "fix the proof of concept" patch. Escapes the values in the inbox **list table**
  only (the single view and the notification e-mail still go through the unescaped formatter),
  protects the **delete** row/bulk actions with a token but leaves the **settings** form without one,
  validates only the export's **`from`** date (`to` is still concatenated into the SQL), and locks
  down the dashboard AJAX endpoint (capability + token).
  Expected: `EscapingTest::test_single_view_shows_values_as_text`,
  `::test_notification_email_shows_values_as_text`,
  `SettingsTest::test_settings_only_change_from_the_settings_screen` and
  `ExportTest::test_malformed_dates_are_rejected` fail -> step-1 reward 0.
- **step-2/** (on top of the step-1 oracle): the complete step-2 reference (website links, upload
  policy, random stored names, download handler, audit log) except that CSV formula
  neutralisation only handles cells starting with `=` (not `+`, `-`, `@`, tab, carriage return).
  Expected: `CsvFormulaTest` fails -> step-2 reward 0.
