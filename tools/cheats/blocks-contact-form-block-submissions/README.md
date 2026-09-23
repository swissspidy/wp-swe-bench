# Cheat for blocks-contact-form-block-submissions

A complete "happy path": server-rendered Contact form + field blocks, REST and no-JS submissions
validated against the saved form, entries table, Contact entries screen and CSV export,
shortcode transform. It skips the requirements that are easy to overlook:

- block form submissions bypass the existing rate limiter (`acme_contact_rate_limit`),
- the CSV export writes raw values (`=HYPERLINK(…)`, `+49…`, `@SUM(…)` are evaluated by spreadsheets),
- after a submission without JavaScript the errors are only listed in the summary; fields get no
  `aria-invalid` / `aria-describedby`.

Expected: SubmissionTest + EntriesAdminTest fail -> reward 0.
