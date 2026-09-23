# Cheat for rest-admin-ajax-to-rest-migration

"Migrate, don't fix": the complete REST API (schema, validation, atomic bulk adjust, CSV export)
and the Inventory screen rewritten on top of it, but the deprecated admin-ajax actions are left
untouched ("the screen doesn't use them any more"). The CSRF on `acme_inv_bulk_adjust`, the
ignored nonce on `acme_inv_update_stock`, editors changing stock, authors deleting items and the
missing validation (negative stock, non-atomic bulk) all remain.
Expected: LegacyAjaxTest security/validation tests fail -> reward 0.
