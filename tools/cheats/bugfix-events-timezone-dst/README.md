# Cheat: DST-correct math, no per-event timezone

The "obvious" fix: convert local strings with the site's timezone at the event's date (instead of the
current `gmt_offset`), format all-day dates without shifting them, fix the iCal all-day dates, and
re-save every event once on upgrade so the 1.6 timestamps get recomputed (1.0 events get converted too).

It skips the actual requirement of storing UTC start/end plus the event's own timezone: the documented
`_acme_event_timezone` / `_acme_event_start_utc` / `_acme_event_end_utc` fields are missing, and events
jump when the site timezone setting changes afterwards (display, feed, editor). Storage, migration and
"site timezone changed" tests fail → reward 0.
