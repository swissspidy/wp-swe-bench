# Cheat: security-ssrf-object-injection-embed

Shortcut: fully fixes the stored XSS (escaping + safe URLs) and the PHP object injection
(JSON storage plus safe legacy reads for the cookie and the import), and blocks the obvious
private IP literals (`127.*`, `10.*`, `192.168.*`, `172.16-31.*`, `169.254.*`, `localhost`, `::1`).

But the URL guard only inspects the literal host string: it does NOT decode alternative IP
encodings and does NOT resolve host names to an IP. So the SSRF is still reachable via
`http://2130706433/` (decimal), `http://0177.0.0.1/` (octal), `http://0x7f.0.0.1/` (hex),
`http://[::ffff:127.0.0.1]/` (IPv4-mapped), a host name that resolves to an internal address,
and a public URL that redirects to any of those.

Expected: `SsrfTest::test_alternative_ip_encodings_are_blocked`,
`::test_hostnames_resolving_to_internal_are_blocked`, and
`::test_redirect_to_internal_host_is_blocked` fail -> reward 0.
