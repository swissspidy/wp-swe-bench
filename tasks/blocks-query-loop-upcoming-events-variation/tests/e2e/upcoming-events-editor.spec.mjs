// "Upcoming events" Query Loop variation in the block editor: registration, active
// variation detection, and editor preview == front end.
import { test, expect } from '@playwright/test';
import {
	login,
	wp,
	wpEval,
	openEditor,
	newPost,
	assertBlocksValid,
	savePost,
	trackErrors,
	createPost,
} from '../wpsb/e2e/helpers.mjs';

const TZ_NOW = (local) =>
	Number(wpEval(`echo ( new DateTimeImmutable( '${local}', new DateTimeZone( 'Pacific/Auckland' ) ) )->getTimestamp();`));

function setNow(local) {
	wp(['option', 'update', 'wpsb_events_now', String(TZ_NOW(local))]);
}

const UPCOMING_AT_1030 = [
	'Winter festival',
	'Sunday market',
	'Afternoon talk',
	'Late show',
	'Board games night',
	'Winter meetup',
	'Harbour cleanup',
	'Kayak trip',
	'Spring conference',
];

const norm = (s) => s.replace(/[‘’]/g, "'").replace(/\s+/g, ' ').trim();

/** Insert the variation the way the inserter does. */
async function insertVariation(page) {
	return page.evaluate(() => {
		const { blocks, data } = window.wp;
		const variation = blocks.getBlockVariations('core/query').find((v) => v.name === 'acme/upcoming-events');
		if (!variation) return null;
		const block = blocks.createBlock(
			'core/query',
			variation.attributes,
			blocks.createBlocksFromInnerBlocksTemplate(variation.innerBlocks || [])
		);
		data.dispatch('core/block-editor').insertBlocks(block);
		return block.clientId;
	});
}

async function canvasTexts(page, selector) {
	// One entry per post in the post template (the active item can render a live copy
	// next to its preview), taken from the first visible match inside each item.
	const frame = page.frame({ name: 'editor-canvas' });
	const texts = await frame.evaluate((sel) => {
		const [loopSel, itemSel] = sel.split(' ');
		const out = [];
		document.querySelectorAll(`${loopSel} .wp-block-post-template > li, ${loopSel} .wp-block-post-template > *`).forEach((li) => {
			if (out.some((o) => o.el === li)) return;
			const candidates = [...li.querySelectorAll(itemSel)];
			const visible = candidates.find((c) => c.offsetParent !== null || c.getClientRects().length) || null;
			if (visible) out.push({ el: li, text: visible.textContent });
		});
		return out.map((o) => o.text);
	}, selector);
	return texts.map(norm);
}

async function frontEnd(page, id) {
	const link = wp(['post', 'list', '--post_type=page', `--post__in=${id}`, '--field=url']);
	const res = await page.request.get(link);
	expect(res.status()).toBe(200);
	const html = await res.text();
	// Only the loops: from the first Query Loop to the footer (the page title is also a post title).
	const start = html.indexOf('<div class="wp-block-query');
	expect(start, 'no Query Loop on the page').toBeGreaterThan(-1);
	const end = html.indexOf('<footer', start);
	return html.slice(start, end > -1 ? end : undefined);
}

function htmlTexts(html, className) {
	// Text of every element carrying the class, in document order (simple, markup-agnostic).
	const out = [];
	const re = new RegExp(`<(\\w+)[^>]*class="[^"]*\\b${className}\\b[^"]*"[^>]*>([\\s\\S]*?)</\\1>`, 'g');
	let m;
	while ((m = re.exec(html))) {
		out.push(
			norm(
				m[2]
					.replace(/<[^>]+>/g, '')
					.replace(/&#8217;|&#039;|&#39;|&rsquo;/g, "'")
					.replace(/&amp;/g, '&')
			)
		);
	}
	return out;
}

test.describe('Upcoming events variation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test.afterEach(() => {
		wp(['option', 'delete', 'wpsb_events_now']);
	});

	test('is registered and detected as the active variation', async ({ page }) => {
		const errors = trackErrors(page);
		await newPost(page, 'page');
		const info = await page.evaluate(() => {
			const { blocks } = window.wp;
			const v = blocks.getBlockVariations('core/query').find((x) => x.name === 'acme/upcoming-events');
			const active = (attrs) =>
				window.wp.data.select('core/blocks').getActiveBlockVariation('core/query', attrs)?.name ?? null;
			return {
				title: v?.title ?? null,
				inserter: !!v && (!v.scope || v.scope.includes('inserter')),
				variationAttrs: active(v?.attributes ?? {}),
				changedPerPage: active({ ...(v?.attributes ?? {}), query: { ...(v?.attributes?.query ?? {}), perPage: 7 } }),
				importer: active({ namespace: 'acme/upcoming-events', query: { perPage: 4 } }),
				plainEvents: active({ query: { postType: 'acme_event', perPage: 5, inherit: false } }),
				eventDate: blocks.getBlockType('acme/event-date')?.title ?? null,
			};
		});
		expect(info.title).toBe('Upcoming events');
		expect(info.inserter).toBe(true);
		expect(info.variationAttrs).toBe('acme/upcoming-events');
		expect(info.changedPerPage).toBe('acme/upcoming-events');
		expect(info.importer).toBe('acme/upcoming-events');
		expect(info.plainEvents).not.toBe('acme/upcoming-events');
		expect(info.eventDate).toBe('Event date');

		// The inserted variation contains the Event date block inside the post template.
		const clientId = await insertVariation(page);
		expect(clientId).not.toBeNull();
		const inner = await page.evaluate((id) => {
			const all = (b) => [b.name, ...b.innerBlocks.flatMap(all)];
			return all(window.wp.data.select('core/block-editor').getBlock(id));
		}, clientId);
		expect(inner).toContain('core/post-template');
		expect(inner).toContain('acme/event-date');
		expect(inner).toContain('core/post-title');
		await assertBlocksValid(page);
		expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
	});

	test('editor preview shows exactly what the front end shows', async ({ page }) => {
		test.setTimeout(300_000);
		setNow('2031-06-15 23:45');
		const expected = ['Winter festival', 'Sunday market', 'Afternoon talk', 'Board games night'];

		await newPost(page, 'page');
		await page.evaluate(() => window.wp.data.dispatch('core/editor').editPost({ title: 'E2E upcoming', status: 'publish' }));
		const clientId = await insertVariation(page);
		expect(clientId, 'variation not registered').not.toBeNull();
		await page.evaluate((id) => {
			const { select, dispatch } = window.wp.data;
			const b = select('core/block-editor').getBlock(id);
			dispatch('core/block-editor').updateBlockAttributes(id, { query: { ...b.attributes.query, perPage: 4 } });
		}, clientId);

		await expect
			.poll(async () => canvasTexts(page, '.wp-block-query .wp-block-post-title'), { timeout: 90_000 })
			.toEqual(expected);
		await expect
			.poll(async () => (await canvasTexts(page, '.wp-block-query .wp-block-acme-event-date')).length, { timeout: 60_000 })
			.toBe(4);
		const editorDates = await canvasTexts(page, '.wp-block-query .wp-block-acme-event-date');
		expect(editorDates[1]).toBe('15 June 2031 09:00');
		await assertBlocksValid(page);

		await savePost(page);
		const id = await page.evaluate(() => window.wp.data.select('core/editor').getCurrentPostId());
		const html = await frontEnd(page, id);
		expect(htmlTexts(html, 'wp-block-post-title')).toEqual(expected);
		expect(htmlTexts(html, 'wp-block-acme-event-date')).toEqual(editorDates);

		// Time moves on: both sides agree again.
		setNow('2031-06-16 00:15');
		await openEditor(page, id);
		await assertBlocksValid(page);
		const later = ['Winter festival', 'Board games night', 'Winter meetup', 'Harbour cleanup'];
		await expect
			.poll(async () => canvasTexts(page, '.wp-block-query .wp-block-post-title'), { timeout: 90_000 })
			.toEqual(later);
		expect(htmlTexts(await frontEnd(page, id), 'wp-block-post-title')).toEqual(later);
		wp(['post', 'delete', String(id), '--force']);
	});

	test('saved loops from the importer open as the variation', async ({ page }) => {
		setNow('2031-06-15 10:30');
		const id = createPost({
			type: 'page',
			title: 'Imported loop',
			content:
				'<!-- wp:query {"queryId":9,"query":{"perPage":4},"namespace":"acme/upcoming-events","className":"imported-loop"} -->\n<div class="wp-block-query imported-loop"><!-- wp:post-template -->\n<!-- wp:post-title /-->\n\n<!-- wp:acme/event-date /-->\n<!-- /wp:post-template --></div>\n<!-- /wp:query -->\n\n<!-- wp:query {"queryId":8,"query":{"perPage":50,"postType":"acme_event","order":"asc","orderBy":"title","inherit":false},"className":"plain-loop"} -->\n<div class="wp-block-query plain-loop"><!-- wp:post-template -->\n<!-- wp:post-title /-->\n<!-- /wp:post-template --></div>\n<!-- /wp:query -->',
		});
		await openEditor(page, id);
		await assertBlocksValid(page);
		const active = await page.evaluate(() => {
			const { select } = window.wp.data;
			const { getActiveBlockVariation } = select('core/blocks');
			return select('core/block-editor')
				.getBlocks()
				.filter((b) => b.name === 'core/query')
				.map((b) => getActiveBlockVariation('core/query', b.attributes)?.name ?? null);
		});
		expect(active[0]).toBe('acme/upcoming-events');
		expect(active[1]).not.toBe('acme/upcoming-events');

		const html = await frontEnd(page, id);
		const titles = htmlTexts(html, 'wp-block-post-title');
		expect(titles.slice(0, 4)).toEqual(UPCOMING_AT_1030.slice(0, 4));
		expect(titles.length).toBe(4 + 13);
		wp(['post', 'delete', String(id), '--force']);
	});
});
