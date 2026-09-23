# Cheat: rest-meta-registration-revisions

Shortcut: implements everything the obvious way and relies on WordPress' built-in revisioned meta
support for revisions, autosaves and previews without checking how it behaves in this plugin:

- REST exposure, schemas, per-field permissions, date validation, the 1.x migration and the WP-CLI
  command are all done (same as the reference).
- Previews: specs are read per key, but core's preview meta filter returns `$value[0]` for array
  meta, and the specs table is still served from (and written to) the transient cache, so previews
  show the published (or broken) specs.
- Restoring a revision saved before 3.0 (no specs recorded) deletes the product's specs, because
  core clears every revisioned key before copying the revision's values.

Expected: `SpecsHttpTest::test_preview_shows_autosaved_specs` and
`SpecsRevisionsTest::test_restoring_a_revision_from_before_the_update_keeps_specs` fail → reward 0.
