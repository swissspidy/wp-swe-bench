Plausible shortcut: the complete 3.0 capability model (own capability type, approve_story mapping,
approval lock, Freelancer role, freelancer migration, desk assignment), but the upgrade grants the
story capabilities from the usual hard-coded list for the four default roles (administrator, editor,
author incl. publish_stories, contributor) instead of deriving them from each role's current post
capabilities, and it re-grants them whenever the version option is older.

Missed: the customized "Section Editor" role gets nothing (can't approve/edit stories), authors on
this site get publish_stories although they can't publish posts, and changes the site owner makes
after the upgrade are reverted when the upgrade runs again. Expected: CapabilityModelTest,
UpgradeTest and parts of WorkflowTest/AdminTest fail → reward 0.
