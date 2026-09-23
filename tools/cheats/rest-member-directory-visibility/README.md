# Cheats for rest-member-directory-visibility

## step-2 (validated)

The complete connections feature (API, `connections` level on every surface, form option), but the
one-time import of the forum buddy lists treats every entry as an accepted connection. One-sided
lists (Carol → Bob, Erin → Dave) must only become pending requests, which grant nothing; the
cheat hands Bob Carol's connections-only data.
Expected: ConnectionsTest fails -> step-2 reward 0.

## step-1 (historical, validated at 0 before the task became multi-step)

"Apply the visibility helper for the current user everywhere": REST API, profile form, core users
field, search handler, author card and member sitemap fixed, but feeds use the requesting user's
view, the directory's shared transient cache still leaks across visitors, and the core users
sitemap still lists hidden members. Not kept as a file cheat so that cheat validation exercises
step 2 (a failing step 1 stops the trial).
