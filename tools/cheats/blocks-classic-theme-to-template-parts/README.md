Shortcut: converts the header/footer into block template parts by copying what the
3.4.1 site shows today into static block markup (navigation-link blocks, a static
button, paragraphs with the phone/email/footer text, static social links), moves the
patterns into /patterns and enables template-part editing. It looks identical on
acme-corp.example on day one, but:

- menu changes made in Appearance → Menus no longer show up, and the "no menu"
  fallback is gone;
- the regional sites (other Customizer settings / widgets) show acme-corp.example's
  data and the year is frozen;
- the synced "Contact card" pattern is never created.

Expected: the menu-change, no-menu, other-sites, widget and Contact card tests fail → reward 0.
