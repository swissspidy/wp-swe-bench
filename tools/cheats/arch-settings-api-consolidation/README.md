Plausible shortcut: the complete 2.0 rebuild (one option, standard settings screens with
validation, legacy get_option() reads, uninstall), but

* the upgrade routine is hooked to `admin_init` ("upgrades run in wp-admin"), so the first
  front-end/WP-CLI request after the deploy is served with defaults and the migration only
  happens once someone opens wp-admin;
* saving through options.php ignores the `acme_social_admin_capability` filter (the menu honours
  it, the save flow falls back to `manage_options`), so editors allowed by a site can open the
  screens but not save them.

Expected: MigrationTest (front-end first request, fresh WP-CLI requests) and
SecurityTest::test_capability_filter_controls_who_can_save fail → reward 0.
