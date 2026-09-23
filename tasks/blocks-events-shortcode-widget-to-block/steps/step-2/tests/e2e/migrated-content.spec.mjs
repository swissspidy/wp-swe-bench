// Step 2: migrated content and converted widgets in the editors.
import { test, expect } from '@playwright/test';
import { login, wp, wpEval, openEditor, assertBlocksValid, getBlockTree, getInvalidBlocks, savePost, trackErrors } from '../wpsb/e2e/helpers.mjs';

const BLOCK = 'acme/upcoming-events';
const DEFAULTS = { limit: 5, category: '', showPast: false, layout: 'list', title: '', showVenue: true };

function findBlocks(tree, name, out = []) {
	for (const b of tree) {
		if (b.name === name) out.push(b);
		findBlocks(b.innerBlocks || [], name, out);
	}
	return out;
}

const postId = (slug, type = 'post') =>
	Number(wpEval(`global $wpdb; echo (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = %s", '${slug}', '${type}' ) );`));

test.describe('Acme Events 3.0 migration', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('migrated posts open without block errors and keep their settings', async ({ page }) => {
		const out = wp(['acme-events', 'migrate-shortcodes']);
		expect(out).toContain('Success: Migrated 6 shortcode(s) in 4 post(s).');

		const cases = [
			['whats-on', 'page', [{ limit: 3, layout: 'grid', title: 'Next up' }]],
			['nested-shortcodes', 'post', [{ showVenue: false, category: expect.stringMatching(/^workshops, ?meetups$/) }, { limit: 2 }, { showPast: true }]],
			['draft-events', 'post', [{ limit: 4, showPast: true, title: 'The "Big" recap' }]],
			['sidebar-events', 'wp_block', [{ limit: 2, title: 'Soon', category: 'conferences' }]],
		];
		for (const [slug, type, expected] of cases) {
			const errors = trackErrors(page);
			await openEditor(page, postId(slug, type));
			await assertBlocksValid(page);
			const tree = await getBlockTree(page);
			const blocks = findBlocks(tree, BLOCK);
			expect(blocks.length, `${slug}: number of Upcoming Events blocks`).toBe(expected.length);
			blocks.forEach((b, i) => {
				expect({ ...DEFAULTS, ...b.attributes }, `${slug} block ${i}`).toMatchObject(expected[i]);
				expect(typeof b.attributes.limit).toBe('number');
				expect(typeof b.attributes.showPast).toBe('boolean');
				expect(typeof b.attributes.showVenue).toBe('boolean');
			});
			const leftovers = findBlocks(tree, 'core/shortcode').filter((b) => /^\s*\[acme_events[^\]]*\]\s*$/.test(b.attributes.text || ''));
			expect(leftovers.length, `${slug}: convertible Shortcode blocks left`).toBe(0);
			expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
		}

		// Title with "--", "&" and markup survives the round trip through the editor.
		const nested = postId('nested-shortcodes');
		await openEditor(page, nested);
		const [first] = findBlocks(await getBlockTree(page), BLOCK);
		expect(first.attributes.title).toContain('Talks -- Q&');
		expect(first.attributes.title).toContain('live');
		await page.evaluate(() => wp.data.dispatch('core/editor').editPost({ title: 'Talks and workshops (edited)' }));
		await savePost(page);
		await openEditor(page, nested);
		await assertBlocksValid(page);
		expect(findBlocks(await getBlockTree(page), BLOCK).length).toBe(3);
	});

	test('converted widgets are plain block widgets in the widget editor', async ({ page }) => {
		const errors = trackErrors(page);
		await page.goto('/wp-admin/widgets.php');
		await page.waitForFunction(() => window.wp?.data?.select('core/block-editor'), null, { timeout: 120_000 });
		await page.evaluate(() => {
			window.wp.data.dispatch('core/preferences')?.set('core/edit-widgets', 'welcomeGuide', false);
		});
		// Expand every widget area (collapsed areas don't load their widgets).
		await page.waitForSelector('.block-editor-block-list__layout', { timeout: 120_000 });
		for (const name of ['Footer', 'Inactive widgets']) {
			const toggle = page.getByRole('button', { name, exact: true });
			await expect(toggle.first()).toBeVisible({ timeout: 60_000 });
			if ((await toggle.first().getAttribute('aria-expanded')) === 'false') {
				await toggle.first().click();
			}
		}
		// Widget areas load asynchronously.
		await expect
			.poll(
				() =>
					page.evaluate((name) => {
						const s = window.wp.data.select('core/block-editor');
						return s.getClientIdsWithDescendants().filter((id) => s.getBlockName(id) === name).length;
					}, BLOCK),
				{ timeout: 90_000 }
			)
			.toBeGreaterThanOrEqual(2);

		const state = await page.evaluate(() => {
			const s = window.wp.data.select('core/block-editor');
			const blocks = s.getClientIdsWithDescendants().map((id) => s.getBlock(id));
			return {
				invalid: blocks.filter((b) => b.isValid === false || b.name === 'core/missing').map((b) => b.name),
				legacy: blocks.filter((b) => b.name === 'core/legacy-widget').map((b) => b.attributes),
				events: blocks.filter((b) => b.name === 'acme/upcoming-events').map((b) => b.attributes),
			};
		});
		expect(state.legacy).toEqual([]);
		const titles = state.events.map((a) => a.title);
		expect(titles).toEqual(expect.arrayContaining(['Meetups & "Talks"', 'Workshops']));
		expect(titles.length).toBeLessThanOrEqual(3);
		expect(state.invalid).toEqual([]);
		expect(await getInvalidBlocks(page)).toEqual([]);

		// The classic widget can't be added any more.
		const legacyTypes = await page.evaluate(async () => {
			const types = await window.wp.apiFetch({ path: '/wp/v2/widget-types?per_page=100' });
			return types.map((t) => t.id);
		});
		expect(legacyTypes).not.toContain('acme_upcoming_events');
		expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
	});
});
