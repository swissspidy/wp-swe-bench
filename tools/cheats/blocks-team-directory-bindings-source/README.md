step-1: a binding source that looks the member up and returns raw field values: no status check
(drafts/private/password-protected members leak), no escaping (relies on core's `wp_kses_post`,
so stored markup like `<b>` is interpreted instead of shown as text), no protocol check on URLs,
and `memberId` pointing to non-members / junk args are only partly handled.
step-2: the reference editor integration with the privacy checks removed from the REST field
(fields of non-public members readable by anyone who can list them) and `canUserEditValue`
always true. Both must score 0 on their step.
