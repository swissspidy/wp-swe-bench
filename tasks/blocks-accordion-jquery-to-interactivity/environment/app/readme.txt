=== Acme FAQ ===
Contributors: acmeweb
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.3.1
License: GPLv2 or later

Collapsible FAQ (accordion) block with FAQPage structured data.

== Description ==

* Block "FAQ" (`acme/faq`) holding "Question" blocks (`acme/faq-item`): a question and a rich answer (paragraphs, lists, images).
* "Open the first question by default" setting.
* FAQPage structured data (JSON-LD) for posts with FAQ blocks; every question links to `{permalink}#faq-{question-slug}`. Filter: `acme_faq_schema_data`. Can be turned off in Settings → FAQ.

== Changelog ==

= 1.3.1 =
* Fix: structured data for FAQs nested in groups.

= 1.3.0 =
* Answers can contain lists and images.

= 1.2.0 =
* Every question is its own block ("Question") with a rich answer. FAQs from 1.0/1.1 are converted when they are edited.
* New: "Open the first question by default".

= 1.1.0 =
* New: FAQPage structured data, `acme_faq_schema_data` filter.

= 1.0.0 =
* Initial release: questions and answers in a definition list.
