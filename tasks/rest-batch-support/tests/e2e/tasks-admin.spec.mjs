// The wp-admin Tasks screen (a legacy client of the REST API) must keep working.
import { test, expect } from '@playwright/test';
import { login, wpEval, trackErrors } from '../wpsb/e2e/helpers.mjs';

function listId(slug) {
	return Number(wpEval(`$p = get_posts( array( 'post_type' => 'acme_task_list', 'name' => '${slug}', 'post_status' => 'any', 'fields' => 'ids' ) ); echo (int) ( $p[0] ?? 0 );`));
}

function apiTasks(id) {
	return JSON.parse(
		wpEval(`wp_set_current_user( 1 ); $r = rest_do_request( new WP_REST_Request( 'GET', '/acme-tasks/v1/lists/${id}/tasks' ) ); echo wp_json_encode( $r->get_data() );`)
	);
}

async function openScreen(page) {
	await page.goto('/wp-admin/admin.php?page=acme-tasks');
	await expect(page.locator('.acme-tasks-list-select')).toBeVisible({ timeout: 60_000 });
	await settle(page);
}

async function settle(page) {
	await expect(page.locator('#acme-tasks-app')).not.toHaveClass(/is-busy/, { timeout: 60_000 });
}

async function titles(page) {
	return page.locator('.acme-task .acme-task-title').allTextContents();
}

async function chooseList(page, label) {
	await page.selectOption('.acme-tasks-list-select', { label });
	await settle(page);
}

const RELAUNCH = ['Write homepage copy', 'Pick hero photos', 'Set up redirects', 'Test contact forms', 'Launch'];

test.describe('Tasks admin screen', () => {
	test('admin can add, complete, move and delete tasks', async ({ page }) => {
		const errors = trackErrors(page);
		page.on('dialog', (d) => d.accept());
		await login(page);
		await openScreen(page);
		await chooseList(page, 'Website relaunch');
		await expect(page.locator('.acme-task')).toHaveCount(5);
		expect(await titles(page)).toEqual(RELAUNCH);
		await expect(page.locator('.acme-task').nth(1)).toHaveClass(/is-done/);

		// Add a task.
		await page.fill('#acme-task-new-title', 'Order cake');
		await page.fill('#acme-task-new-due', '2026-10-20');
		await page.click('.acme-tasks-add button[type=submit]');
		await settle(page);
		await expect(page.locator('.acme-task')).toHaveCount(6);
		expect(await titles(page)).toEqual([...RELAUNCH, 'Order cake']);

		const id = listId('website-relaunch');
		let tasks = apiTasks(id);
		const cake = tasks.find((t) => t.title === 'Order cake');
		expect(cake.position).toBe(5);
		expect(cake.due_date).toBe('2026-10-20');

		// Tick it off (the screen sends the legacy `completed` flag).
		const row = page.locator('.acme-task', { hasText: 'Order cake' });
		await row.locator('.acme-task-toggle').check();
		await settle(page);
		await expect(page.locator('.acme-task', { hasText: 'Order cake' })).toHaveClass(/is-done/);
		expect(apiTasks(id).find((t) => t.title === 'Order cake').status).toBe('done');

		// Move it up twice.
		await page.locator('.acme-task', { hasText: 'Order cake' }).locator('.acme-task-up').click();
		await settle(page);
		await page.locator('.acme-task', { hasText: 'Order cake' }).locator('.acme-task-up').click();
		await settle(page);
		expect(await titles(page)).toEqual(['Write homepage copy', 'Pick hero photos', 'Set up redirects', 'Order cake', 'Test contact forms', 'Launch']);
		tasks = apiTasks(id);
		expect(tasks.map((t) => [t.title, t.position])).toEqual([
			['Write homepage copy', 0],
			['Pick hero photos', 1],
			['Set up redirects', 2],
			['Order cake', 3],
			['Test contact forms', 4],
			['Launch', 5],
		]);

		// Delete it.
		await page.locator('.acme-task', { hasText: 'Order cake' }).locator('.acme-task-delete').click();
		await settle(page);
		await expect(page.locator('.acme-task')).toHaveCount(5);
		await expect(page.locator('.acme-tasks-notice:visible')).toHaveCount(0);

		await page.reload();
		await expect(page.locator('.acme-tasks-list-select')).toBeVisible({ timeout: 60_000 });
		await settle(page);
		await chooseList(page, 'Website relaunch');
		expect(await titles(page)).toEqual(RELAUNCH);
		expect(apiTasks(id).map((t) => t.title)).toEqual(RELAUNCH);

		// Sort by due date (reorder endpoint).
		await page.click('.acme-tasks-sort');
		await settle(page);
		expect(await titles(page)).toEqual(['Set up redirects', 'Launch', 'Write homepage copy', 'Pick hero photos', 'Test contact forms']);

		expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
	});

	test('a member only sees their lists and can create a new one', async ({ page }) => {
		page.on('dialog', (d) => d.accept());
		await login(page, 'bob', 'password');
		await openScreen(page);
		const options = await page.locator('.acme-tasks-list-select option').allTextContents();
		expect(options).toEqual(['Groceries', 'Office move', 'Onboarding checklist', 'Website relaunch']);

		await page.fill('.acme-tasks-new-list [name=list_title]', 'Errands for Bob');
		await page.click('.acme-tasks-new-list button[type=submit]');
		await settle(page);
		await expect(page.locator('.acme-tasks-list-select option:checked')).toHaveText('Errands for Bob');
		await expect(page.locator('.acme-task')).toHaveCount(0);

		await page.fill('#acme-task-new-title', 'Post office');
		await page.click('.acme-tasks-add button[type=submit]');
		await settle(page);
		await page.fill('#acme-task-new-title', 'Dry cleaning');
		await page.click('.acme-tasks-add button[type=submit]');
		await settle(page);
		expect(await titles(page)).toEqual(['Post office', 'Dry cleaning']);

		const id = listId('errands-for-bob');
		expect(id).toBeGreaterThan(0);
		expect(apiTasks(id).map((t) => [t.title, t.position])).toEqual([
			['Post office', 0],
			['Dry cleaning', 1],
		]);
	});
});
