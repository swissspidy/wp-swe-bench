// Block editor behaviour of the callout block (hidden E2E tests).
import { test, expect } from '@playwright/test';
import {
	login,
	wp,
	openEditor,
	assertBlocksValid,
	getBlockTree,
	savePost,
	trackErrors,
	createPost,
	newPost,
} from '../wpsb/e2e/helpers.mjs';

const postId = (slug, type = 'post') =>
	Number(wp(['post', 'list', `--post_type=${type}`, `--name=${slug}`, '--field=ID', '--post_status=any']));

function findCallouts(tree, out = []) {
	for (const b of tree) {
		if (b.name === 'acme/callout') out.push(b);
		findCallouts(b.innerBlocks || [], out);
	}
	return out;
}

const SEEDED = [
	['legacy-v0-callout', 'post', [['Heads up', 'read the docs']]],
	['v1-callout', 'post', [['Do', 'production'], [null, 'An info callout without a title.']]],
	['anchored-callout', 'post', [['Solved', 'See the FAQ entry']]],
	['nested-callouts', 'page', [['Column one', 'Nested inside columns.'], ['Old one in a group', 'Still from version 1.0.']]],
	['custom-type-callout', 'post', [['Pro tip', 'Use keyboard shortcuts.']]],
	['mixed-callouts', 'post', [['Legacy danger', 'From 1.0.'], ['Newer warning', 'From 1.3.']]],
	['maintenance-callout', 'wp_block', [['Maintenance window', 'Saturdays']]],
];

test.describe('Callout block in the editor', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	for (const [slug, type, expected] of SEEDED) {
		test(`existing callouts in "${slug}" open without block errors and are upgraded`, async ({ page }) => {
			const errors = trackErrors(page);
			const id = postId(slug, type);
			expect(id).toBeGreaterThan(0);
			await openEditor(page, id);
			await assertBlocksValid(page);

			const callouts = findCallouts(await getBlockTree(page));
			expect(callouts.length).toBe(expected.length);
			callouts.forEach((c, i) => {
				const [title, text] = expected[i];
				// The text now lives in a paragraph block inside the callout.
				expect(c.innerBlocks.length, 'callout body should contain blocks').toBeGreaterThan(0);
				expect(c.innerBlocks[0].name).toBe('core/paragraph');
				expect(JSON.stringify(c.innerBlocks[0].attributes)).toContain(text);
				if (title) expect(JSON.stringify(c.attributes)).toContain(title);
			});
			expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
		});
	}

	test('upgraded content saves in the new format and stays valid after reload', async ({ page }) => {
		const id = postId('mixed-callouts');
		await openEditor(page, id);
		await assertBlocksValid(page);
		// Edit the content (like a user fixing a typo), then save.
		await page.evaluate(() => {
			const { select, dispatch } = wp.data;
			const find = (blocks) => blocks.flatMap((b) => [b, ...find(b.innerBlocks)]);
			const para = find(select('core/block-editor').getBlocks()).find(
				(b) => b.name === 'core/paragraph' && String(b.attributes.content).includes('From 1.0.')
			);
			dispatch('core/block-editor').updateBlockAttributes(para.clientId, { content: 'From 1.0 (edited).' });
		});
		await savePost(page);

		const saved = wp(['post', 'get', String(id), '--field=post_content']);
		expect(saved).not.toContain('acme-callout__content');
		expect(saved).not.toMatch(/class="[^"]*\bcallout-(danger|warning)\b/);
		expect(saved).toContain('<!-- wp:paragraph');

		await page.reload();
		await openEditor(page, id);
		await assertBlocksValid(page);
		const callouts = findCallouts(await getBlockTree(page));
		expect(callouts.length).toBe(2);

		// Front end still renders both callouts plus the shortcode one.
		const res = await page.request.get('/mixed-callouts/');
		const html = await res.text();
		expect((html.match(/<aside[^>]*wp-block-acme-callout/g) || []).length).toBe(3);
	});

	test('a new callout holds paragraphs, headings and lists but not other callouts', async ({ page }) => {
		await newPost(page);
		const clientId = await page.evaluate(() => {
			const block = wp.blocks.createBlock('acme/callout', { type: 'warning' });
			wp.data.dispatch('core/block-editor').insertBlocks(block);
			return block.clientId;
		});
		// A freshly inserted callout starts with an empty paragraph.
		await expect
			.poll(() => page.evaluate((id) => wp.data.select('core/block-editor').getBlock(id).innerBlocks.map((b) => b.name), clientId))
			.toEqual(['core/paragraph']);

		const can = await page.evaluate((id) => {
			const s = wp.data.select('core/block-editor');
			return {
				paragraph: s.canInsertBlockType('core/paragraph', id),
				heading: s.canInsertBlockType('core/heading', id),
				list: s.canInsertBlockType('core/list', id),
				callout: s.canInsertBlockType('acme/callout', id),
			};
		}, clientId);
		expect(can).toEqual({ paragraph: true, heading: true, list: true, callout: false });
	});

	test('new callouts round-trip through save, reload and the front end', async ({ page }) => {
		const errors = trackErrors(page);
		await newPost(page);
		await page.evaluate(() => {
			const { createBlock } = wp.blocks;
			wp.data.dispatch('core/editor').editPost({ title: 'Fresh callout', status: 'publish', slug: 'fresh-callout' });
			const block = createBlock('acme/callout', { type: 'danger' }, [
				createBlock('core/paragraph', { content: 'First <strong>paragraph</strong>.' }),
				createBlock('core/list', {}, [
					createBlock('core/list-item', { content: 'Item one' }),
					createBlock('core/list-item', { content: 'Item two' }),
				]),
			]);
			wp.data.dispatch('core/block-editor').resetBlocks([block]);
		});
		// Title is edited like a user would: type into the title field of the block.
		const canvas = page.frameLocator('iframe[name="editor-canvas"]');
		const title = canvas.locator('.wp-block-acme-callout [contenteditable="true"]').first();
		await title.click();
		await page.keyboard.type('Fresh title');
		await savePost(page);
		const id = await page.evaluate(() => wp.data.select('core/editor').getCurrentPostId());

		await openEditor(page, id);
		await assertBlocksValid(page);
		const callouts = findCallouts(await getBlockTree(page));
		expect(callouts.length).toBe(1);
		expect(JSON.stringify(callouts[0].attributes)).toContain('Fresh title');
		expect(callouts[0].innerBlocks.map((b) => b.name)).toEqual(['core/paragraph', 'core/list']);

		const res = await page.request.get(`/?p=${id}`);
		const html = await res.text();
		const m = html.match(/<aside[^>]*class="[^"]*wp-block-acme-callout[^"]*"[^>]*>([\s\S]*?)<\/aside>/);
		expect(m, 'front end should render an <aside> callout').not.toBeNull();
		expect(m[0]).toMatch(/is-type-danger/);
		expect(m[0]).toMatch(/role="note"/);
		expect(m[0]).toMatch(/aria-label="Danger"/);
		expect(m[1]).toMatch(/<p class="wp-block-acme-callout__title">\s*Fresh title\s*<\/p>/);
		expect(m[1]).toMatch(/<div class="wp-block-acme-callout__body">[\s\S]*<strong>paragraph<\/strong>[\s\S]*<li>Item one<\/li>/);
		expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
	});

	test('converting classic content turns [callout] shortcodes into callout blocks', async ({ page }) => {
		await newPost(page);
		const blocks = await page.evaluate(() => {
			const strip = (bs) => bs.map((b) => ({ name: b.name, attributes: b.attributes, innerBlocks: strip(b.innerBlocks) }));
			return strip(
				wp.blocks.rawHandler({
					HTML: '<p>Intro</p>\n\n[callout type="warning" title="Old school"]Shortcode body text.[/callout]\n\n<p>Middle</p>\n\n[callout]No type here[/callout]\n\n<p>Outro</p>',
				})
			);
		});
		const callouts = findCallouts(blocks);
		expect(callouts.length).toBe(2);
		expect(callouts[0].attributes.type).toBe('warning');
		expect(JSON.stringify(callouts[0].attributes)).toContain('Old school');
		expect(callouts[0].innerBlocks[0].name).toBe('core/paragraph');
		expect(JSON.stringify(callouts[0].innerBlocks[0].attributes)).toContain('Shortcode body text.');
		// No type: the site's default type (Settings → Callouts, "success" here).
		expect(callouts[1].attributes.type).toBe('success');
		expect(JSON.stringify(callouts[1].innerBlocks)).toContain('No type here');
	});

	test('the type picker still lists types registered by themes and plugins', async ({ page }) => {
		const id = createPost({
			title: 'Type picker',
			content:
				'<!-- wp:acme/callout {"type":"tip"} -->\n<div class="wp-block-acme-callout acme-callout acme-callout--tip"><p class="acme-callout__title">T</p><div class="acme-callout__content">Body</div></div>\n<!-- /wp:acme/callout -->',
		});
		await openEditor(page, id);
		await assertBlocksValid(page);
		await page.evaluate(() => {
			const [block] = wp.data.select('core/block-editor').getBlocks();
			wp.data.dispatch('core/block-editor').selectBlock(block.clientId);
			wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-post/block');
		});
		const select = page.getByRole('combobox', { name: 'Type' });
		await expect(select).toBeVisible();
		await expect(select).toHaveValue('tip');
		const options = await select.locator('option').allTextContents();
		expect(options).toEqual(expect.arrayContaining(['Info', 'Success', 'Warning', 'Danger', 'Tip']));
	});
});
