// Editor + front-end behaviour of the TOC block (hidden E2E tests).
import { test, expect } from '@playwright/test';
import {
	login,
	wp,
	openEditor,
	assertBlocksValid,
	getBlockTree,
	savePost,
	trackErrors,
	newPost,
} from '../wpsb/e2e/helpers.mjs';

const postId = (slug, type = 'post') =>
	Number(wp(['post', 'list', `--post_type=${type}`, `--name=${slug}`, '--field=ID', '--post_status=any']));

const SEEDED = [
	['stale-toc', 'post'],
	['legacy-contents', 'page'],
	['duplicate-headings', 'post'],
	['no-title-toc', 'post'],
	['paged-guide', 'post'],
	['special-chars', 'post'],
	['no-headings', 'post'],
];

const permalink = (id) => new URL(wp(['eval', `echo get_permalink( ${Number(id)} );`])).pathname;

async function frontEndToc(page, url) {
	const res = await page.request.get(url, { timeout: 120_000 });
	expect(res.status()).toBe(200);
	const html = await res.text();
	return page.evaluate((h) => {
		const doc = new DOMParser().parseFromString(h, 'text/html');
		const nav = doc.querySelector('.wp-block-acme-toc');
		if (!nav) return null;
		return {
			tag: nav.tagName.toLowerCase(),
			title: nav.querySelector('.acme-toc__title')?.textContent.trim() ?? null,
			items: [...nav.querySelectorAll('li a')].map((a) => ({ text: a.textContent.trim(), href: a.getAttribute('href') })),
			ids: [...doc.querySelectorAll('[id]')].map((e) => e.id),
		};
	}, html);
}

test.describe('TOC block', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	for (const [slug, type] of SEEDED) {
		test(`existing TOC in "${slug}" opens without block errors`, async ({ page }) => {
			const errors = trackErrors(page);
			const id = postId(slug, type);
			expect(id).toBeGreaterThan(0);
			await openEditor(page, id);
			await assertBlocksValid(page);
			const tree = await getBlockTree(page);
			expect(tree.filter((b) => b.name === 'acme/toc').length).toBe(1);
			expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
		});
	}

	test('re-saving upgraded TOCs keeps them valid and keeps custom titles', async ({ page }) => {
		for (const [slug, type, title] of [['duplicate-headings', 'post', 'In this guide'], ['legacy-contents', 'page', 'Table of contents']]) {
			const id = postId(slug, type);
			await openEditor(page, id);
			await assertBlocksValid(page);
			// Make an unrelated edit so the post is saved.
			await page.evaluate(() => {
				const { createBlock } = wp.blocks;
				wp.data.dispatch('core/block-editor').insertBlocks(createBlock('core/paragraph', { content: 'Edited after the update.' }));
			});
			await savePost(page);
			await openEditor(page, id);
			await assertBlocksValid(page);

			const toc = await frontEndToc(page, permalink(id));
			expect(toc, `${slug}: TOC must still render`).not.toBeNull();
			expect(toc.title).toBe(title);
			expect(toc.items.length).toBeGreaterThan(0);
			for (const item of toc.items) {
				expect(toc.ids.filter((x) => x === item.href.split('#')[1]).length, `#${item.href} must resolve`).toBe(1);
			}
		}
	});

	test('a new TOC follows heading changes made after saving', async ({ page }) => {
		const errors = trackErrors(page);
		await newPost(page);
		await page.evaluate(() => {
			const { createBlock } = wp.blocks;
			wp.data.dispatch('core/editor').editPost({ title: 'Fresh TOC', status: 'publish' });
			wp.data.dispatch('core/block-editor').resetBlocks([
				createBlock('acme/toc', { maxLevel: 3 }),
				createBlock('core/heading', { content: 'First part', level: 2 }),
				createBlock('core/paragraph', { content: 'Text.' }),
				createBlock('core/heading', { content: 'Details', level: 3 }),
				createBlock('core/heading', { content: 'First part', level: 2 }),
			]);
		});
		await savePost(page);
		const id = await page.evaluate(() => wp.data.select('core/editor').getCurrentPostId());
		await openEditor(page, id);
		await assertBlocksValid(page);
		expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);

		let toc = await frontEndToc(page, permalink(id));
		expect(toc).not.toBeNull();
		expect(toc.title).toBe('Table of contents');
		expect(toc.items.map((i) => i.text)).toEqual(['First part', 'Details', 'First part']);
		const frags = toc.items.map((i) => i.href.split('#')[1]);
		expect(new Set(frags).size).toBe(3);
		for (const f of frags) expect(toc.ids.filter((x) => x === f).length).toBe(1);

		// Change the headings outside the editor (e.g. search & replace, WP-CLI).
		const content = wp(['post', 'get', String(id), '--field=post_content']);
		const updated = content.replace('>Details<', '>Implementation details<') +
			'\n\n<!-- wp:heading -->\n<h2 class="wp-block-heading">Added later</h2>\n<!-- /wp:heading -->';
		wp(['post', 'update', String(id), '-'], { input: updated });
		toc = await frontEndToc(page, permalink(id));
		expect(toc.items.map((i) => i.text)).toEqual(['First part', 'Implementation details', 'First part', 'Added later']);
		for (const item of toc.items) expect(toc.ids.filter((x) => x === item.href.split('#')[1]).length).toBe(1);
	});

	test('a custom title typed in the editor is shown on the front end', async ({ page }) => {
		await newPost(page);
		await page.evaluate(() => {
			const { createBlock } = wp.blocks;
			wp.data.dispatch('core/editor').editPost({ title: 'Custom title TOC', status: 'publish' });
			wp.data.dispatch('core/block-editor').resetBlocks([
				createBlock('acme/toc'),
				createBlock('core/heading', { content: 'Only heading', level: 2 }),
			]);
		});
		const canvas = page.frameLocator('iframe[name="editor-canvas"]');
		const title = canvas.locator('.wp-block-acme-toc [contenteditable="true"]').first();
		await title.click();
		await page.keyboard.press('ControlOrMeta+a');
		await page.keyboard.type('On this page');
		await savePost(page);
		const id = await page.evaluate(() => wp.data.select('core/editor').getCurrentPostId());
		await openEditor(page, id);
		await assertBlocksValid(page);
		const toc = await frontEndToc(page, permalink(id));
		expect(toc.title).toBe('On this page');
		expect(toc.items.map((i) => i.text)).toEqual(['Only heading']);
	});

	test('clicking TOC links on the front end reaches the headings, also on other pages', async ({ page }) => {
		await page.goto('/duplicate-headings/');
		const links = page.locator('.wp-block-acme-toc a');
		await expect(links).toHaveCount(5);
		await links.nth(1).click();
		await expect(page).toHaveURL(/#setup-3$/);
		const target = page.locator('#setup-3');
		await expect(target).toHaveText('Setup');
		await expect(target).toBeInViewport();

		await page.goto('/paged-guide/');
		await page.locator('.wp-block-acme-toc a', { hasText: 'Verifying' }).click();
		await expect(page).toHaveURL(/\/paged-guide\/2\/#verifying$/);
		await expect(page.locator('#verifying')).toHaveText('Verifying');
		await expect(page.locator('#verifying')).toBeInViewport();
	});
});
