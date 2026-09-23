Near-complete implementation (signatures, tolerance, idempotency, queue with retries,
status endpoint, rate limit, redaction of *new* log entries and stored payloads) with two
plausible shortcuts:

- the unsigned "manual re-send" path checks `is_user_logged_in()` + `manage_options`
  instead of requiring application-password authentication, so an admin's browser session
  (cookie + REST nonce, e.g. a CSRF-able admin page or XSS) can inject orders;
- redaction only applies to entries written from now on; the 1.3.2 `sync.log` still
  contains the webhook secrets and card numbers.

Expected: HttpAuthTest::test_cookie_authentication_does_not_replace_the_signature and
RedactionTest::test_existing_log_is_scrubbed_but_keeps_its_entries fail -> reward 0.
