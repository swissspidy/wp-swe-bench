Complete plumbing (variation, namespace handling, REST parity, Event date block,
webpack config) but it reuses the plugin's old notion of "upcoming": start >= today
computed with `strtotime( 'today' )` (UTC day, not the site timezone), ignoring the
end date, and excluding only `cancelled` (not the 1.0 `canceled`).
Misses: running multi-day events, events that ended earlier today, timezone edge
around local midnight, legacy `canceled`. Expected: PHPUnit fails -> reward 0.
