Shortcut: bumps theme.json and the style variations to version 3, sets the
default-preset flags and the fluid font size presets (so every preset/variable check
and the mobile type scale pass), removes the hand-rolled heading clamps, but keeps the
Card/Inverted/button/quote/link-hover/kicker styles as hardcoded CSS in style.css.

Expected: the front end looks right, but Styles-UI customizations of those styles are
overridden by style.css, the editor canvas doesn't show the section styles, and the
global styles data has no Card/Inverted styles → reward 0.
