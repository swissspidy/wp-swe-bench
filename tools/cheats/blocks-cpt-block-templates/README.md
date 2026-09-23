Shortcut: registers the three block templates with the core registry and adds the
course price / duration / enroll button blocks (so the block-theme front end looks
right), but

- unconditionally disables the PHP template loader and the summary box/listing meta
  injection ("we're a block theme site now"), which breaks classic themes, and
- inlines the summary blocks in the single template instead of providing the
  customizable `course-summary` template part (no core registry for parts, so it
  skips the hard bit).

Expected: ClassicThemeTest, the template-part REST/precedence tests and the
template-part E2E test fail → reward 0.
