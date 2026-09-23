// Editor experience of blocks connected to acme/team-member (hidden E2E tests).
import { test, expect } from '@playwright/test';
import { login, wp, openEditor as openEditorRaw, assertBlocksValid, trackErrors, newPost } from '../wpsb/e2e/helpers.mjs';

/** Open the editor and dismiss any first-use modal (welcome guide etc.). */
async function openEditor(page, id) {
	await openEditorRaw(page, id);
	for (let i = 0; i < 3 && (await page.locator('.components-modal__screen-overlay').count()) > 0; i++) {
		await page.keyboard.press('Escape');
		await page.waitForTimeout(300);
	}
}

const idOf = (slug, type) =>
	Number(wp(['post', 'list', `--post_type=${type}`, `--name=${slug}`, '--field=ID', '--post_status=any']));

const canvasOf = (page) => page.frameLocator('iframe[name="editor-canvas"]');

/** Visible text or placeholder of a canvas element. */
async function shown(locator) {
	return locator.evaluate((el) => (el.innerText.trim() || el.getAttribute('data-rich-text-placeholder') || el.getAttribute('aria-label') || '').trim());
}

const shortcode = (id) => wp(['eval', `echo do_shortcode( '[team_member id="${id}" fields="role,email,phone"]' );`]);

/** Save the post and every changed entity (member records), like clicking Save + confirming. */
async function saveAll(page) {
	await page.getByRole('button', { name: /^(Save|Update)$/ }).first().click();
	const confirm = page.locator('.entities-saved-states__panel').getByRole('button', { name: /^Save$/ });
	await confirm.click({ timeout: 15_000 }).catch(() => {});
	await page.waitForFunction(() => {
		const core = window.wp.data.select('core');
		const editor = window.wp.data.select('core/editor');
		return !editor.isSavingPost() && core.__experimentalGetDirtyEntityRecords().length === 0;
	}, null, { timeout: 120_000 });
}

test.describe('Team member bindings in the editor', () => {
	// Tests run in order against one site; the editing tests come last.
	test('connected blocks show member values and field labels', async ({ page }) => {
		const errors = trackErrors(page);
		await login(page);
		await openEditor(page, idOf('meet-ada', 'page'));
		await assertBlocksValid(page);
		const canvas = canvasOf(page);
		await expect(canvas.locator('h2.wp-block-heading').first()).toHaveText('Ada Lovelace');
		const paragraphs = canvas.locator('p.wp-block-paragraph, .wp-block-paragraph');
		await expect.poll(async () => shown(paragraphs.nth(0))).toBe('Head of Engineering');
		// Grace has no phone: the field label is shown.
		await expect.poll(async () => shown(paragraphs.nth(1))).toMatch(/Phone/);
		await expect(canvas.locator('.wp-block-button__link').first()).toHaveText('ada@acme.test');
		const img = canvas.locator('figure.wp-block-image img').first();
		await expect(img).toHaveAttribute('src', /ada.*\.png/);
		await expect(img).toHaveAttribute('alt', 'Portrait of Ada Lovelace');
		expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
	});

	test('query loop: values per member, read only, fields listed with labels', async ({ page }) => {
		await login(page);
		await openEditor(page, idOf('our-team', 'page'));
		await assertBlocksValid(page);
		const canvas = canvasOf(page);
		const firstRole = canvas.locator('.team-card .wp-block-paragraph').first();
		await expect.poll(async () => shown(firstRole)).toBe('Head of Engineering');
		await expect(canvas.locator('.team-card h3').first()).toHaveText('Ada Lovelace');
		await firstRole.click();
		await expect(firstRole).not.toHaveAttribute('contenteditable', 'true');

		await page.evaluate(() => {
			const { select, dispatch } = window.wp.data;
			const all = (bs) => bs.flatMap((b) => [b, ...all(b.innerBlocks)]);
			const p = all(select('core/block-editor').getBlocks()).find(
				(b) => b.name === 'core/paragraph' && b.attributes.metadata?.bindings?.content?.args?.key === 'role'
			);
			dispatch('core/block-editor').selectBlock(p.clientId);
			dispatch('core/edit-post').openGeneralSidebar('edit-post/block');
		});
		const panel = page.locator('.block-editor-bindings__panel');
		await expect(panel).toBeVisible();
		await expect(panel.locator('.block-editor-bindings__item').first()).toContainText('Role');
		await panel.locator('.block-editor-bindings__item').first().click();
		await page.getByRole('menuitem', { name: 'Team member' }).click();
		for (const label of ['Name', 'Email', 'Phone', 'Pronouns']) {
			await expect(page.getByRole('menuitemcheckbox', { name: new RegExp(label) }).first()).toBeVisible();
		}
	});

	test('the member card pattern is offered and valid', async ({ page }) => {
		await login(page);
		await newPost(page, 'page');
		await expect
			.poll(() => page.evaluate(() => (window.wp.data.select('core').getBlockPatterns() || []).some((p) => p.name === 'acme-team/member-card')), { timeout: 60_000 })
			.toBe(true);
		const res = await page.evaluate(() => {
			const patterns = window.wp.data.select('core').getBlockPatterns() || [];
			const found = patterns.find((p) => p.name === 'acme-team/member-card');
			if (!found) return { found: false, names: patterns.map((p) => p.name) };
			const blocks = window.wp.blocks.parse(found.content);
			const all = (bs) => bs.flatMap((b) => [b, ...all(b.innerBlocks)]);
			return {
				found: true,
				title: found.title,
				inserter: found.inserter !== false,
				invalid: all(blocks).filter((b) => !b.isValid).map((b) => b.name),
				bound: all(blocks).filter((b) => b.attributes.metadata?.bindings).map((b) => b.name),
			};
		});
		expect(res.found, JSON.stringify(res.names)).toBe(true);
		expect(res.title).toBe('Team member card');
		expect(res.inserter).toBe(true);
		expect(res.invalid).toEqual([]);
		expect(res.bound).toEqual(expect.arrayContaining(['core/image', 'core/heading', 'core/paragraph', 'core/button']));
	});

	test('authors can only edit members they may edit, and never see private ones', async ({ page }) => {
		await login(page, 'alex', 'password');
		const postId = idOf('alex-team-post', 'post');
		await openEditor(page, postId);
		await assertBlocksValid(page);
		const paras = canvasOf(page).locator('.wp-block-paragraph');
		await expect(paras).toHaveCount(4);

		// Ada's member belongs to the admin: visible, read only.
		await expect.poll(async () => shown(paras.nth(1))).toBe('Head of Engineering');
		await expect(paras.nth(1)).not.toHaveAttribute('contenteditable', 'true');

		// Grace's member belongs to Alex: editable.
		await expect.poll(async () => shown(paras.nth(2))).toBe('Compiler Lead');
		await paras.nth(2).click();
		await expect(paras.nth(2)).toHaveAttribute('contenteditable', 'true');

		// Pete is private and not Alex's: only the label.
		await expect.poll(async () => shown(paras.nth(3))).toMatch(/Phone/);
		const canvasText = await canvasOf(page).locator('body').innerText();
		expect(canvasText).not.toContain('010 9999');
		expect(canvasText).not.toContain('Acquisition');

		// Edit Grace's role in place and save.
		await page.keyboard.press('ControlOrMeta+a');
		await page.keyboard.type('Compiler Pioneer');
		await saveAll(page);
		expect(shortcode(idOf('grace-hopper', 'acme_member'))).toContain('Compiler Pioneer');
		expect(shortcode(idOf('ada-lovelace', 'acme_member'))).toContain('Head of Engineering');
	});

	test('an editor can change a member field in place', async ({ page }) => {
		const ada = idOf('ada-lovelace', 'acme_member');
		const pageId = idOf('meet-ada', 'page');
		const pageBefore = wp(['post', 'get', String(pageId), '--field=post_content']);
		// Another test may have left the page locked by the admin.
		wp(['post', 'meta', 'delete', String(pageId), '_edit_lock']);
		await login(page, 'eddie', 'password');
		await openEditor(page, pageId);
		const para = canvasOf(page).locator('.wp-block-paragraph').first();
		await expect.poll(async () => shown(para)).toBe('Head of Engineering');
		await para.click();
		await expect(para).toHaveAttribute('contenteditable', 'true');
		await page.keyboard.press('ControlOrMeta+a');
		await page.keyboard.type('Chief Engineer & Mentor');
		await saveAll(page);

		expect(shortcode(ada)).toContain('Chief Engineer &amp; Mentor');
		expect(wp(['post', 'get', String(pageId), '--field=post_content'])).toBe(pageBefore);
		const html = await (await page.request.get('/meet-ada/')).text();
		expect(html).toContain('Chief Engineer &amp; Mentor');
		expect(html).not.toContain('Head of Engineering');
	});

});
