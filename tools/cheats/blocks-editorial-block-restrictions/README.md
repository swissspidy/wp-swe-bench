The "obvious" port of the workarounds to real restrictions:

- allowed blocks = all registered blocks minus the list the old CSS workaround hid
  (`Editorial_Rules::hidden_blocks()`): only the user's first role counts, child
  blocks like list items are not allowed automatically (lists become unusable),
  administrators are restricted by role lists of roles they don't have… (first role only);
- design tools: sets the legacy `disableCustomColors` / `disableCustomFontSizes`
  editor settings, which block themes (theme.json) ignore;
- press releases get `template` + `template_lock => all`, but the body of existing
  press releases stays locked because their group has no `templateLock` attribute.

Expected: child-block, multi-role, design tools and existing press release tests fail → reward 0.
