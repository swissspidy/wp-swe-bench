// "Glossary term" format in the block editor (hidden E2E tests).
import { test, expect } from '@playwright/test';
import { login, wp, openEditor as baseOpenEditor, newPost as baseNewPost, assertBlocksValid, savePost, trackErrors } from '../wpsb/e2e/helpers.mjs';

/** The welcome guide can still pop up for fresh users; close it. */
async function dismissWelcomeGuide(page) {
	await page.evaluate(() => {
		const prefs = window.wp.data.dispatch('core/preferences');
		prefs.set('core/edit-post', 'welcomeGuide', false);
		prefs.set('core', 'welcomeGuide', false);
	});
	const dialog = page.getByRole('dialog', { name: /welcome/i });
	for (let i = 0; i < 10 && !(await dialog.count()); i++) await page.waitForTimeout(100);
	if (await dialog.count()) {
		await dialog.getByRole('button', { name: 'Close' }).first().click();
		await expect(dialog).toHaveCount(0);
	}
}
async function openEditor(page, id) {
	await baseOpenEditor(page, id);
	await dismissWelcomeGuide(page);
}
async function newPost(page, type) {
	await baseNewPost(page, type);
	await dismissWelcomeGuide(page);
}


const termId = (slug) =>
	Number(wp(['post', 'list', '--post_type=glossary_term', `--name=${slug}`, '--field=ID', '--post_status=any']));
const postId = (slug) => Number(wp(['post', 'list', '--post_type=post', `--name=${slug}`, '--field=ID', '--post_status=any']));

/** Marks (span.acme-glossary-term) in a piece of HTML, parsed in the browser. */
async function marksIn(page, html) {
	return page.evaluate((h) => {
		const doc = new DOMParser().parseFromString(`<body>${h}</body>`, 'text/html');
		return [...doc.querySelectorAll('.acme-glossary-term')].map((el) => {
			const clone = el.cloneNode(true);
			clone.querySelectorAll('[role="tooltip"]').forEach((t) => t.remove());
			const tipId = el.getAttribute('aria-describedby');
			const tip = tipId ? doc.getElementById(tipId) : null;
			return {
				tag: el.tagName.toLowerCase(),
				termId: el.getAttribute('data-term-id'),
				text: clone.textContent.trim(),
				html: clone.innerHTML,
				attrs: el.getAttributeNames().sort(),
				tooltip: tip ? { role: tip.getAttribute('role'), text: tip.textContent.trim() } : null,
			};
		});
	}, html);
}

const blockContent = (page, clientId) =>
	page.evaluate((id) => String(window.wp.data.select('core/block-editor').getBlockAttributes(id).content), clientId);

async function insertParagraph(page, text) {
	return page.evaluate((t) => {
		const block = window.wp.blocks.createBlock('core/paragraph', { content: t });
		window.wp.data.dispatch('core/block-editor').insertBlocks(block);
		return block.clientId;
	}, text);
}

/** Select characters [start, end) of a paragraph with the keyboard. */
async function selectText(page, clientId, start, end) {
	const canvas = page.frameLocator('iframe[name="editor-canvas"]');
	await canvas.locator(`[data-block="${clientId}"]`).click();
	await page.keyboard.press('Home');
	for (let i = 0; i < start; i++) await page.keyboard.press('ArrowRight');
	for (let i = start; i < end; i++) await page.keyboard.press('Shift+ArrowRight');
	// Moving the mouse reveals the block toolbar again (hidden while using the keyboard).
	await page.mouse.move(10, 10);
	await page.mouse.move(400, 300);
	await page.waitForTimeout(300);
}

/** Click "Glossary term" in the formatting toolbar (directly or in the "More" dropdown). */
async function clickGlossaryButton(page) {
	const toolbar = page.getByRole('toolbar', { name: 'Block tools' });
	await expect(toolbar).toBeVisible();
	const direct = toolbar.getByRole('button', { name: 'Glossary term', exact: true });
	if (await direct.count()) {
		await direct.first().click();
		return;
	}
	await toolbar.getByRole('button', { name: 'More', exact: true }).click();
	const item = page.getByRole('menuitem', { name: 'Glossary term' }).or(page.getByRole('menuitemcheckbox', { name: 'Glossary term' }));
	await item.first().click();
}

async function isGlossaryButtonActive(page) {
	const toolbar = page.getByRole('toolbar', { name: 'Block tools' });
	const direct = toolbar.getByRole('button', { name: 'Glossary term', exact: true });
	if (await direct.count()) {
		const pressed = await direct.first().getAttribute('aria-pressed');
		const cls = (await direct.first().getAttribute('class')) || '';
		return pressed === 'true' || /is-pressed/.test(cls);
	}
	await toolbar.getByRole('button', { name: 'More', exact: true }).click();
	const item = page.getByRole('menuitem', { name: 'Glossary term' }).or(page.getByRole('menuitemcheckbox', { name: 'Glossary term' })).first();
	const checked = await item.getAttribute('aria-checked');
	const pressed = await item.getAttribute('aria-pressed');
	const cls = (await item.getAttribute('class')) || '';
	await page.keyboard.press('Escape');
	return checked === 'true' || pressed === 'true' || /is-active|is-pressed/.test(cls);
}

/** Visible result elements for a term title in the open picker. */
function results(page) {
	return page.locator('.components-popover [role="option"], .components-popover button, .components-popover li, .components-popover a');
}

async function pickTerm(page, search, title) {
	const input = page.getByLabel('Search glossary terms');
	await expect(input.first()).toBeVisible();
	await input.first().fill(search);
	const exact = page.getByRole('option', { name: title, exact: true }).or(page.getByRole('button', { name: title, exact: true }));
	await expect(exact.first()).toBeVisible({ timeout: 30_000 });
	await exact.first().click();
}

test.describe('Glossary term format', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('marks selected text with a searched term; front end shows the tooltip', async ({ page }) => {
		const errors = trackErrors(page);
		await newPost(page);
		await page.evaluate(() => window.wp.data.dispatch('core/editor').editPost({ title: 'Marked in the editor', status: 'publish' }));
		const id = await insertParagraph(page, 'We rely on caching for speed.');
		await selectText(page, id, 11, 18);
		await clickGlossaryButton(page);
		await pickTerm(page, 'cach', 'Cache');

		await expect.poll(async () => (await marksIn(page, await blockContent(page, id))).length).toBe(1);
		const [stored] = await marksIn(page, await blockContent(page, id));
		expect(stored.tag).toBe('span');
		expect(stored.termId).toBe(String(termId('cache')));
		expect(stored.text).toBe('caching');
		expect(stored.attrs).toEqual(['class', 'data-term-id']);

		const saved = await savePost(page);
		const postIdNew = await page.evaluate(() => window.wp.data.select('core/editor').getCurrentPostId());
		const inContent = await marksIn(page, saved);
		expect(inContent.map((m) => [m.termId, m.text])).toEqual([[String(termId('cache')), 'caching']]);

		await openEditor(page, postIdNew);
		await assertBlocksValid(page);

		const res = await page.request.get(`/?p=${postIdNew}`, { timeout: 120_000 });
		const fe = await marksIn(page, await res.text());
		expect(fe.length).toBe(1);
		expect(fe[0].text).toBe('caching');
		expect(fe[0].tooltip).toEqual({ role: 'tooltip', text: 'A store of copies of data, so that future requests are served faster.' });
		expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
	});

	test('search only offers published terms', async ({ page }) => {
		await newPost(page);
		const id = await insertParagraph(page, 'Our internal CDN caches pages.');
		await selectText(page, id, 4, 16);
		await clickGlossaryButton(page);
		const input = page.getByLabel('Search glossary terms').first();
		await input.fill('cache');
		await expect(page.getByRole('option', { name: 'Cache invalidation', exact: true }).or(page.getByRole('button', { name: 'Cache invalidation', exact: true })).first()).toBeVisible({ timeout: 30_000 });
		const texts = (await results(page).allTextContents()).join(' | ');
		expect(texts).toContain('Cache');
		expect(texts).not.toContain('Cache stampede');

		await input.fill('cdn');
		await expect(page.getByRole('option', { name: 'CDN', exact: true }).or(page.getByRole('button', { name: 'CDN', exact: true })).first()).toBeVisible({ timeout: 30_000 });
		expect((await results(page).allTextContents()).join(' | ')).not.toContain('Internal CDN');
	});

	test('the button is active inside marked text and removes the marking', async ({ page }) => {
		await newPost(page);
		const cdn = termId('cdn');
		const id = await insertParagraph(page, `Put a <span class="acme-glossary-term" data-term-id="${cdn}">CDN</span> in front.`);
		await assertBlocksValid(page);
		expect((await marksIn(page, await blockContent(page, id))).length).toBe(1);
		// Cursor inside "CDN".
		await selectText(page, id, 7, 7);
		expect(await isGlossaryButtonActive(page)).toBe(true);
		await clickGlossaryButton(page);
		await expect.poll(async () => (await marksIn(page, await blockContent(page, id))).length).toBe(0);
		expect(await blockContent(page, id)).toBe('Put a CDN in front.');
	});

	test('converting a classic post turns [glossary] shortcodes into marked text', async ({ page }) => {
		const errors = trackErrors(page);
		const id = postId('cdn-setup');
		await openEditor(page, id);
		const [classic] = await page.evaluate(() => window.wp.data.select('core/block-editor').getBlocks().map((b) => ({ name: b.name, clientId: b.clientId })));
		expect(classic.name).toBe('core/freeform');
		await page.evaluate((cid) => window.wp.data.dispatch('core/block-editor').selectBlock(cid), classic.clientId);
		const convert = page.getByRole('button', { name: 'Convert to blocks' });
		if (await convert.count()) {
			await convert.first().click();
		} else {
			await page.evaluate((cid) => {
				const { select, dispatch } = window.wp.data;
				const block = select('core/block-editor').getBlock(cid);
				dispatch('core/block-editor').replaceBlocks(cid, window.wp.blocks.rawHandler({ HTML: window.wp.blocks.serialize(block) }));
			}, classic.clientId);
		}
		await expect.poll(() => page.evaluate(() => window.wp.data.select('core/block-editor').getBlocks().map((b) => b.name))).toEqual(['core/paragraph', 'core/paragraph']);
		await assertBlocksValid(page);

		const contents = await page.evaluate(() => window.wp.data.select('core/block-editor').getBlocks().map((b) => String(b.attributes.content)));
		const all = contents.join('\n');
		expect(all).not.toContain('[glossary');
		expect(all).not.toContain('[/glossary]');
		const m1 = await marksIn(page, contents[0]);
		expect(m1.map((m) => [m.termId, m.text])).toEqual([
			[String(termId('cdn')), 'CDN'],
			[String(termId('origin-server')), 'origin'],
		]);
		const m2 = await marksIn(page, contents[1]);
		expect(m2.map((m) => [m.termId, m.text])).toEqual([
			[String(termId('cache-invalidation')), 'cache invalidation'],
			[String(termId('ttfb')), 'time to first byte'],
		]);
		expect(contents[1]).toContain('ignore this one');
		expect(contents[1]).toContain('<strong>Tip:</strong>');

		const saved = await savePost(page);
		expect(saved).toContain('<!-- wp:paragraph');
		const res = await page.request.get('/cdn-setup/', { timeout: 120_000 });
		const fe = await marksIn(page, await res.text());
		expect(fe.map((m) => m.text)).toEqual(['CDN', 'origin', 'cache invalidation', 'time to first byte']);
		expect(fe.every((m) => m.tooltip && m.tooltip.role === 'tooltip' && m.tooltip.text.length > 10)).toBe(true);
		expect(fe[2].tooltip.text).toBe('Removing or refreshing cached data when the original changes. (Deprecated term.)');
		expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
	});
});
