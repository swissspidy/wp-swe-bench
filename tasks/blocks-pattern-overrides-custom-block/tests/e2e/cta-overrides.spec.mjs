// Editor behaviour of CTA blocks in synced patterns (hidden E2E tests).
import { test, expect } from '@playwright/test';
import {
	login,
	wp,
	openEditor,
	newPost,
	assertBlocksValid,
	getBlockTree,
	savePost,
	trackErrors,
	createPost,
} from '../wpsb/e2e/helpers.mjs';

const postId = (slug, type = 'post') =>
	Number(wp(['post', 'list', `--post_type=${type}`, `--name=${slug}`, '--field=ID', '--post_status=any']));

const content = (id) => wp(['post', 'get', String(id), '--field=post_content']);

const parseBlocks = (id) =>
	JSON.parse(wp(['eval', `echo wp_json_encode( parse_blocks( get_post( ${id} )->post_content ) );`]));

const flatten = (blocks) => blocks.flatMap((b) => [b, ...flatten(b.innerBlocks || [])]);

const canvasOf = (page) => page.frameLocator('iframe[name="editor-canvas"]');

test.describe('CTA block and synced pattern overrides', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	for (const [slug, type] of [
		['standalone-ctas', 'post'],
		['legacy-cta', 'post'],
		['pricing', 'page'],
		['webinars', 'page'],
		['newsletter-signup', 'wp_block'],
		['webinar-cta', 'wp_block'],
	]) {
		test(`existing content "${slug}" opens without block errors`, async ({ page }) => {
			const errors = trackErrors(page);
			const id = postId(slug, type);
			expect(id).toBeGreaterThan(0);
			await openEditor(page, id);
			await assertBlocksValid(page);
			expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
		});
	}

	test('the editor shows each instance with its own overrides', async ({ page }) => {
		await openEditor(page, postId('pricing', 'page'));
		await assertBlocksValid(page);
		const headings = canvasOf(page).locator('.wp-block-acme-cta__heading');
		await expect(headings).toHaveCount(4);
		await expect
			.poll(async () => (await headings.allInnerTexts()).map((t) => t.trim()))
			.toEqual(['Get the pricing digest', 'Only the heading changed', 'Join our newsletter', 'Talk to sales']);
		const buttons = canvasOf(page).locator('.wp-block-acme-cta__button');
		await expect
			.poll(async () => (await buttons.allInnerTexts()).map((t) => t.trim()))
			.toEqual(['Send me prices', 'Subscribe', 'Subscribe', 'Contact us']);
	});

	test('editing a CTA inside a pattern instance stores an override on that page only', async ({ page }) => {
		const errors = trackErrors(page);
		const patternId = postId('newsletter-signup', 'wp_block');
		const patternBefore = content(patternId);
		const id = postId('pricing', 'page');
		await openEditor(page, id);
		await assertBlocksValid(page);

		// Third newsletter instance (no overrides yet): replace its heading and button text.
		const canvas = canvasOf(page);
		const heading = canvas.locator('.wp-block-acme-cta__heading').nth(2);
		await expect(heading).toHaveText(/Join our newsletter/);
		// The first click on a synced pattern selects it, the next ones go into its content.
		await canvas.locator('[data-type="core/block"]').nth(2).click();
		await heading.click();
		await expect(heading).toHaveAttribute('contenteditable', 'true');
		await page.keyboard.press('ControlOrMeta+a');
		await page.keyboard.type('Typed on the pricing page');
		const button = canvas.locator('.wp-block-acme-cta__button').nth(2);
		await button.click();
		await page.keyboard.press('ControlOrMeta+a');
		await page.keyboard.type('Count me in');
		await savePost(page);

		expect(content(patternId), 'the synced pattern itself must not change').toBe(patternBefore);

		const refs = flatten(parseBlocks(id)).filter((b) => b.blockName === 'core/block');
		expect(refs.length).toBe(4);
		expect(refs[2].attrs.content?.['Newsletter CTA']?.heading).toBe('Typed on the pricing page');
		expect(refs[2].attrs.content?.['Newsletter CTA']?.buttonText).toBe('Count me in');
		expect(refs[0].attrs.content?.['Newsletter CTA']?.heading).toBe('Get the <em>pricing</em> digest');
		expect(refs[1].attrs.content?.['Newsletter CTA']).toEqual({ heading: 'Only the heading changed' });

		const res = await page.request.get('/pricing/');
		const html = await res.text();
		expect(html).toContain('Typed on the pricing page');
		expect(html).toContain('Count me in');
		const other = await (await page.request.get('/newsletter-everywhere/')).text();
		expect(other).not.toContain('Typed on the pricing page');
		expect(other).toMatch(/Join our <strong>newsletter<\/strong>/);

		// Reload: still valid, still shows the page's values.
		await openEditor(page, id);
		await assertBlocksValid(page);
		await expect
			.poll(async () => (await canvasOf(page).locator('.wp-block-acme-cta__heading').allInnerTexts()).map((t) => t.trim()))
			.toEqual(['Get the pricing digest', 'Only the heading changed', 'Typed on the pricing page', 'Talk to sales']);
		expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
	});

	test('overrides can be enabled on a CTA in the pattern editor', async ({ page }) => {
		const id = createPost({
			type: 'wp_block',
			title: 'Demo request',
			content:
				'<!-- wp:acme/cta {"heading":"See it in action","buttonText":"Book a demo","buttonUrl":"https://demo.example.com/","variant":"dark"} -->\n' +
				'<div class="wp-block-acme-cta is-variant-dark"><h2 class="wp-block-acme-cta__heading">See it in action</h2><a class="wp-block-acme-cta__button wp-element-button" href="https://demo.example.com/">Book a demo</a></div>\n' +
				'<!-- /wp:acme/cta -->',
		});
		await openEditor(page, id);
		await assertBlocksValid(page);
		await page.evaluate(() => {
			const [block] = wp.data.select('core/block-editor').getBlocks();
			wp.data.dispatch('core/block-editor').selectBlock(block.clientId);
			wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-post/block');
		});
		const advanced = page.getByRole('button', { name: 'Advanced', exact: true });
		await expect(advanced).toBeVisible();
		if ((await advanced.getAttribute('aria-expanded')) !== 'true') await advanced.click();
		const enable = page.getByRole('button', { name: 'Enable overrides' });
		await expect(enable).toBeVisible();
		await enable.click();
		const dialog = page.getByRole('dialog', { name: 'Enable overrides' });
		await dialog.getByLabel('Name').fill('Demo CTA');
		await dialog.getByRole('button', { name: 'Enable' }).click();
		await expect(dialog).toBeHidden();
		await savePost(page);

		const [cta] = parseBlocks(id).filter((b) => b.blockName === 'acme/cta');
		expect(cta.attrs.metadata?.name).toBe('Demo CTA');
		expect(cta.attrs.metadata?.bindings?.__default?.source).toBe('core/pattern-overrides');

		// Use it on a page with an override and check the front end.
		const pageId = createPost({
			title: 'Demo landing',
			content: `<!-- wp:block {"ref":${id},"content":{"Demo CTA":{"buttonText":"Try it today","variant":"primary"}}} /-->`,
		});
		const html = await (await page.request.get(`/?p=${pageId}`)).text();
		expect(html).toContain('Try it today');
		expect(html).toContain('See it in action');
		expect(html).toMatch(/is-variant-dark/);
		expect(html).not.toMatch(/class="[^"]*\bis-variant-primary\b/);
	});

	test('new CTAs round-trip and show up in the inventory with their stored values', async ({ page }) => {
		const errors = trackErrors(page);
		await newPost(page);
		await page.evaluate(() => {
			const { createBlock } = wp.blocks;
			wp.data.dispatch('core/editor').editPost({ title: 'Fresh CTA', status: 'publish', slug: 'fresh-cta' });
			wp.data.dispatch('core/block-editor').resetBlocks([
				createBlock('acme/cta', {
					heading: 'Fresh <em>heading</em>',
					headingLevel: 3,
					buttonText: 'Fresh button',
					buttonUrl: 'https://fresh.example.org/start',
					variant: 'secondary',
					campaign: 'fresh',
				}),
			]);
		});
		await savePost(page);
		const id = await page.evaluate(() => wp.data.select('core/editor').getCurrentPostId());

		await openEditor(page, id);
		await assertBlocksValid(page);
		const [cta] = await getBlockTree(page);
		expect(cta.name).toBe('acme/cta');
		expect(cta.attributes.buttonUrl).toBe('https://fresh.example.org/start');
		expect(cta.attributes.headingLevel).toBe(3);

		const rows = JSON.parse(wp(['acme-cta', 'list', '--format=json']));
		const row = rows.find((r) => Number(r.post_id) === id);
		expect(row, 'new CTA missing from the inventory').toBeTruthy();
		expect(row).toMatchObject({
			heading: 'Fresh heading',
			button_text: 'Fresh button',
			url: 'https://fresh.example.org/start',
			variant: 'secondary',
			campaign: 'fresh',
		});

		const html = await (await page.request.get(`/?p=${id}`)).text();
		expect(html).toMatch(/<h3 class="wp-block-acme-cta__heading">Fresh <em>heading<\/em><\/h3>/);
		expect(html).toMatch(/href="https:\/\/fresh\.example\.org\/start\?utm_source=acme-blog[^"]*utm_campaign=fresh"/);
		expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
	});
});
