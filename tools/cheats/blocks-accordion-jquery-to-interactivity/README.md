Plausible shortcut: the full new implementation (dynamic render, script module, deprecations
so old content validates in the editor), but FAQs that were never re-saved keep their old
markup on the front end: 1.2/1.3 items without the new `question` attribute and 1.0 definition
lists are output as saved. Editor tests pass, but the front-end markup/a11y tests for the seeded
legacy pages (and the interactive behaviour on them) fail → reward 0.
