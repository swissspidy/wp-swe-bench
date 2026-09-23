=== Acme Tasks ===
Contributors: acme
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.7.0
License: GPL-2.0-or-later

Shared to-do lists for the Acme team: a Tasks screen in wp-admin and the REST API behind the Acme mobile app.

== Description ==

Lists are private to their owner and members; editors and administrators see every list.

= REST API (namespace acme-tasks/v1) =

* `GET /lists`, `POST /lists` – lists visible to the current user / create a list (`title`, `color` as `#rrggbb`, `members` as user IDs).
* `GET|POST|PUT|PATCH|DELETE /lists/{id}` – read, change (owner/editors only) or delete a list. `DELETE` moves the list to the trash; `DELETE ?force=true` deletes it and all its tasks permanently.
* `GET /lists/{id}/tasks` – tasks in display order (`?status=open|done|all`).
* `POST /lists/{id}/tasks` – create a task: `title` (required), `notes`, `status` (`open`/`done`), `due_date` (`YYYY-MM-DD`), `assignee` (user ID with access to the list, `0` = nobody), `position` (0-based; omitted = append).
* `GET|POST|PUT|PATCH|DELETE /tasks/{id}` – read, change or delete a task. Sending `position` moves the task within its list. `DELETE` returns `{"deleted":true,"previous":{…task…}}`.
* `POST /lists/{id}/reorder` – `order`: task IDs in the new order.
* `GET /lists/{id}/activity` – latest changes.

The deprecated `completed` flag (boolean) is still accepted instead of `status`.

= Batch requests =

Creating, updating and deleting lists and tasks can be combined in one request to the
core batch endpoint (`POST /wp-json/batch/v1`, up to 50 requests). Every request is
authorized on its own. With `"validation": "require-all-validate"` nothing is written
unless every request is valid.

Positions are always kept as 0..n-1 in display order; a position past the end appends.

= Hooks =

* `acme_tasks_task_created( array $task, WP_REST_Request|null $request )`
* `acme_tasks_task_updated( array $task, array $previous, WP_REST_Request|null $request )`
* `acme_tasks_task_deleted( array $task, WP_REST_Request|null $request )`
* `acme_tasks_list_created`, `acme_tasks_list_updated`, `acme_tasks_list_deleted`
* Filters `acme_tasks_rest_prepare_task`, `acme_tasks_rest_prepare_list`

== Changelog ==

= 1.7.0 =
* Create/update/delete endpoints can be used through the batch endpoint (mobile app sync).
* Input is validated before any change is made.
* Positions stay contiguous after creating, moving and deleting tasks (also fixes lists migrated by 1.3).
* DELETE /tasks/{id} returns a regular REST response.
* DELETE /lists/{id}?force=true works for all clients.

= 1.6.0 =
* Activity feed endpoint.
* Admin screen: "Sort by due date".

= 1.5.0 =
* Assignees must have access to the list.
* Per-request cache for list contents (faster admin screen).

= 1.4.0 =
* `acme_tasks_task_updated` receives the previous task state.
* Due dates are validated strictly.

= 1.3.0 =
* `status` replaces the `completed` flag (still accepted).
* Tasks moved from post meta to the `acme_tasks` table. Positions of migrated lists were copied as-is.

= 1.2.0 =
* REST API for the mobile app (replaces the admin-ajax actions).

= 1.0.0 =
* First release.
