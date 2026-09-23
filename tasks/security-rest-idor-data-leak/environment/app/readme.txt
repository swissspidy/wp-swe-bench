=== Acme Support ===
Contributors: acmeweb
Tags: helpdesk, support, tickets
Requires at least: 6.5
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later

A lightweight helpdesk for small teams: customers open tickets, agents reply and keep private internal notes.

== Description ==

Acme Support adds a simple ticketing system to your site.

* Customers open support tickets and follow the conversation from their account.
* Agents reply, triage, set priority and keep **internal notes** that customers never see.
* Files can be attached to a ticket.
* A REST API (`acme-support/v1`) backs the customer portal and the agent dashboard app.

Roles:

* **Support Customer** – opens tickets and reads their own.
* **Support Agent** – works every ticket, including the private notes.
* **Support Manager** – like an agent, and can delete tickets.

== Changelog ==

= 1.4.0 =
* Attachments can be added to tickets.
* Internal notes are previewed in the agent list.

= 1.3.0 =
* Added the agent dashboard REST endpoints.

= 1.2.0 =
* Replies and internal notes.

= 1.0.0 =
* Initial release.
