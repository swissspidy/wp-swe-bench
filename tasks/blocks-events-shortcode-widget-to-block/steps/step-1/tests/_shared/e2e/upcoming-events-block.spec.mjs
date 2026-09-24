// The Upcoming Events block in the editor (hidden E2E tests, run in every step).
import { test, expect } from '@playwright/test';
import {
	login,
	wp,
	wpEval,
	openEditor,
	newPost,
	assertBlocksValid,
	getBlockTree,
	savePost,
	trackErrors,
	createPost,
	phpStr,
} from '../wpsb/e2e/helpers.mjs';

const BLOCK = 'acme/upcoming-events';

function findBlocks(tree, name, out = []) {
	for (const b of tree) {
		if (b.name === name) out.push(b);
		findBlocks(b.innerBlocks || [], name, out);
	}
	return out;
}

/** Normalized inner HTML of the first div.acme-events in some HTML (whitespace-insensitive). */
function listingInner(html) {
	const start = html.search(/<div class="[^"]*\bacme-events\b[^"]*"/);
	if (start < 0) return null;
	// Find the matching closing div.
	const re = /<\/?div\b[^>]*>/g;
	re.lastIndex = start;
	let depth = 0;
	let m;
	let openEnd = -1;
	while ((m = re.exec(html))) {
		if (m[0].startsWith('</')) {
			depth--;
			if (depth === 0) {
				return html.slice(openEnd, m.index).replace(/>\s+</g, '><').replace(/\s+/g, ' ').trim();
			}
		} else {
			if (depth === 0) openEnd = m.index + m[0].length;
			depth++;
		}
	}
	return null;
}

test.describe('Upcoming Events block', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('is available in the editor with the documented attributes', async ({ page }) => {
		await newPost(page);
		const type = await page.evaluate((name) => {
			const t = wp.blocks.getBlockType(name);
			return t ? { title: t.title, attributes: JSON.parse(JSON.stringify(t.attributes)) } : null;
		}, BLOCK);
		expect(type, 'acme/upcoming-events must be registered in the editor').not.toBeNull();
		expect(type.title).toBe('Upcoming Events');
		const defaults = Object.fromEntries(Object.entries(type.attributes).map(([k, v]) => [k, v.default]));
		expect(defaults).toMatchObject({ limit: 5, category: '', showPast: false, layout: 'list', title: '', showVenue: true });
		const inserter = await page.evaluate((name) => wp.data.select('core/block-editor').canInsertBlockType(name), BLOCK);
		expect(inserter).toBe(true);
	});

	test('shows a live preview that follows the settings', async ({ page }) => {
		const errors = trackErrors(page);
		await newPost(page);
		const clientId = await page.evaluate((name) => {
			const block = wp.blocks.createBlock(name, { layout: 'grid', limit: 2, title: 'Preview heading' });
			wp.data.dispatch('core/block-editor').insertBlocks(block);
			return block.clientId;
		}, BLOCK);
		const canvas = page.frameLocator('iframe[name="editor-canvas"]');
		const preview = canvas.locator('.acme-events');
		await expect(preview.locator('.acme-event__link')).toHaveCount(2, { timeout: 60_000 });
		await expect(preview.locator('.acme-event__link').first()).toHaveText('Intro to Gutenberg');
		await expect(canvas.locator('.acme-events.acme-events--grid')).toHaveCount(1);
		await expect(preview.locator('.acme-events__title')).toHaveText('Preview heading');
		await expect(preview.locator('.acme-event__cta').first()).toBeVisible();

		await page.evaluate((id) => wp.data.dispatch('core/block-editor').updateBlockAttributes(id, { layout: 'list', showVenue: false, limit: 3, category: 'meetups' }), clientId);
		await expect(canvas.locator('.acme-events.acme-events--list')).toHaveCount(1, { timeout: 60_000 });
		await expect(canvas.locator('.acme-events .acme-event__link')).toHaveText(['WordPress Meetup Zürich', 'Performance Clinic', 'Holiday Meetup']);
		await expect(canvas.locator('.acme-events .acme-event__venue')).toHaveCount(0);

		// All options are editable in the block settings sidebar.
		await page.evaluate((id) => {
			wp.data.dispatch('core/block-editor').selectBlock(id);
			wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-post/block');
		}, clientId);
		const inspector = page.locator('.block-editor-block-inspector');
		await expect(inspector).toBeVisible();
		const controls = await inspector.locator('input:not([type="hidden"]), select, textarea').count();
		expect(controls, 'expected controls for limit, category, past events, layout, title and venue').toBeGreaterThanOrEqual(5);
		expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
	});

	test('round-trips through save, reload and the front end', async ({ page }) => {
		await newPost(page);
		await page.evaluate((name) => {
			const { createBlock } = wp.blocks;
			wp.data.dispatch('core/editor').editPost({ title: 'Events block round trip', status: 'publish' });
			wp.data.dispatch('core/block-editor').resetBlocks([
				createBlock('core/paragraph', { content: 'Before the listing.' }),
				createBlock(name, { limit: 3, category: 'workshops,meetups', showPast: true, title: 'All sorts', showVenue: false }),
			]);
		}, BLOCK);
		await savePost(page);
		const id = await page.evaluate(() => wp.data.select('core/editor').getCurrentPostId());

		await openEditor(page, id);
		await assertBlocksValid(page);
		const blocks = findBlocks(await getBlockTree(page), BLOCK);
		expect(blocks.length).toBe(1);
		expect(blocks[0].attributes).toMatchObject({ limit: 3, category: 'workshops,meetups', showPast: true, title: 'All sorts', showVenue: false });

		const res = await page.request.get(`/?p=${id}`);
		expect(res.status()).toBe(200);
		const html = await res.text();
		const main = html.slice(html.indexOf('<main'), html.indexOf('</main>'));
		const expected = wpEval(`echo do_shortcode( ${phpStr('[acme_events limit="3" category="workshops,meetups" show_past="yes" title="All sorts" show_venue="no"]')} );`);
		expect(listingInner(main)).not.toBeNull();
		expect(listingInner(main)).toBe(listingInner(expected));
	});

	test('a Shortcode block with [acme_events] transforms into the block', async ({ page }) => {
		const id = createPost({
			title: 'Shortcode transform',
			content: '<!-- wp:shortcode -->\n[acme_events limit="3" show_past="yes" show_venue="no" layout="grid" category="meetups" title="Hi there"]\n<!-- /wp:shortcode -->',
		});
		await openEditor(page, id);
		await assertBlocksValid(page);
		const result = await page.evaluate((name) => {
			const [shortcode] = wp.data.select('core/block-editor').getBlocks();
			const possible = wp.blocks.getPossibleBlockTransformations([shortcode]).map((t) => t.name);
			const converted = wp.blocks.switchToBlockType(shortcode, name);
			if (converted) {
				wp.data.dispatch('core/block-editor').replaceBlocks(shortcode.clientId, converted);
			}
			return {
				possible,
				converted: converted ? converted.map((b) => ({ name: b.name, attributes: b.attributes })) : null,
			};
		}, BLOCK);
		expect(result.possible).toContain(BLOCK);
		expect(result.converted).not.toBeNull();
		expect(result.converted.length).toBe(1);
		expect(result.converted[0].name).toBe(BLOCK);
		expect(result.converted[0].attributes).toMatchObject({ limit: 3, showPast: true, showVenue: false, layout: 'grid', category: 'meetups', title: 'Hi there' });

		await savePost(page);
		await openEditor(page, id);
		await assertBlocksValid(page);
		const html = await (await page.request.get(`/?p=${id}`)).text();
		const main = html.slice(html.indexOf('<main'), html.indexOf('</main>'));
		const expected = wpEval(`echo do_shortcode( ${phpStr('[acme_events limit="3" show_past="yes" show_venue="no" layout="grid" category="meetups" title="Hi there"]')} );`);
		expect(listingInner(main)).toBe(listingInner(expected));
	});

	test('converting classic content turns [acme_events] into the block', async ({ page }) => {
		await newPost(page);
		const blocks = await page.evaluate(() => {
			const strip = (bs) => bs.map((b) => ({ name: b.name, attributes: b.attributes, innerBlocks: strip(b.innerBlocks) }));
			return strip(
				wp.blocks.rawHandler({
					HTML: '<p>Intro</p>\n\n[acme_events limit="2" show_past="1" category="workshops"]\n\n<p>Middle</p>\n\n[acme_events show_venue="false" layout="grid"]\n\n<p>Outro</p>',
				})
			);
		});
		const found = findBlocks(blocks, BLOCK);
		expect(found.length).toBe(2);
		expect(found[0].attributes).toMatchObject({ limit: 2, showPast: true, category: 'workshops', showVenue: true, layout: 'list' });
		expect(found[1].attributes).toMatchObject({ limit: 5, showPast: false, showVenue: false, layout: 'grid' });
		expect(findBlocks(blocks, 'core/shortcode').length).toBe(0);
	});

	test('the seeded pages with shortcodes still open without block errors', async ({ page }) => {
		for (const [slug, type] of [['whats-on', 'page'], ['nested-shortcodes', 'post'], ['inline-shortcode', 'post']]) {
			const id = Number(wpEval(`global $wpdb; echo (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = %s", '${slug}', '${type}' ) );`));
			await openEditor(page, id);
			await assertBlocksValid(page);
		}
	});
});
