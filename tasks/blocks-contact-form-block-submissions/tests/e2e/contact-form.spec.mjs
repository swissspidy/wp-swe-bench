// Contact form block in the editor and on the front end (hidden E2E tests).
import { test, expect } from '@playwright/test';
import {
	login,
	wp,
	wpEval,
	openEditor,
	newPost,
	assertBlocksValid,
	assertPostBlocksValid,
	getBlockTree,
	savePost,
	trackErrors,
	BASE_URL,
} from '../wpsb/e2e/helpers.mjs';

const postId = (slug, type = 'page') =>
	Number(wp(['post', 'list', `--post_type=${type}`, `--name=${slug}`, '--field=ID', '--post_status=any']));

const entryCount = (where = '1=1') =>
	Number(wpEval(`global $wpdb; echo (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}acme_contact_entries WHERE ${where}" );`));

const FIELD_BLOCKS = ['acme/field-text', 'acme/field-email', 'acme/field-textarea', 'acme/field-select', 'acme/field-checkbox'];

test.describe('Contact form block', () => {
	test('imported landing pages open without block errors', async ({ page }) => {
		const errors = trackErrors(page);
		await login(page);
		await assertPostBlocksValid(page, postId('get-in-touch'));
		const tree = await getBlockTree(page);
		const form = tree.find((b) => b.name === 'acme/contact-form');
		expect(form, 'contact form block in the tree').toBeTruthy();
		expect(form.attributes.formId).toBe('lp-quote');
		expect(form.attributes.submitLabel).toBe('Request a quote');
		expect(form.innerBlocks.map((b) => [b.name, b.attributes.name])).toEqual([
			['acme/field-text', 'name'],
			['acme/field-email', 'email'],
			['acme/field-text', 'company'],
			['acme/field-select', 'service'],
			['acme/field-textarea', 'message'],
			['acme/field-checkbox', 'newsletter'],
		]);
		expect(form.innerBlocks[3].attributes.options).toEqual(['Design', 'Development', 'Hosting']);
		expect(form.innerBlocks[0].attributes.required).toBe(true);
		expect(form.innerBlocks[2].attributes.required).toBe(false);

		await assertPostBlocksValid(page, postId('two-forms'));
		expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
	});

	test('new forms: default fields, unique ids, nesting rules, round trip', async ({ page }) => {
		const errors = trackErrors(page);
		await login(page);
		await newPost(page, 'page');
		const [first, second] = await page.evaluate(() => {
			const { createBlock } = wp.blocks;
			const a = createBlock('acme/contact-form');
			const b = createBlock('acme/contact-form');
			wp.data.dispatch('core/block-editor').insertBlocks([a, b]);
			return [a.clientId, b.clientId];
		});
		const get = (id) => page.evaluate((cid) => {
			const b = wp.data.select('core/block-editor').getBlock(cid);
			return { formId: b.attributes.formId, fields: b.innerBlocks.map((i) => [i.name, i.attributes.name, i.attributes.required]) };
		}, id);
		await expect.poll(async () => (await get(first)).fields).toEqual([
			['acme/field-text', 'name', true],
			['acme/field-email', 'email', true],
			['acme/field-textarea', 'message', true],
		]);
		await expect.poll(async () => (await get(first)).formId).toBeTruthy();
		await expect.poll(async () => (await get(second)).formId).toBeTruthy();
		expect((await get(first)).formId).not.toBe((await get(second)).formId);

		const can = await page.evaluate((formClientId) => {
			const s = wp.data.select('core/block-editor');
			return {
				fieldAtRoot: s.canInsertBlockType('acme/field-text', ''),
				checkboxAtRoot: s.canInsertBlockType('acme/field-checkbox', ''),
				selectInForm: s.canInsertBlockType('acme/field-select', formClientId),
				checkboxInForm: s.canInsertBlockType('acme/field-checkbox', formClientId),
				paragraphInForm: s.canInsertBlockType('core/paragraph', formClientId),
				formInForm: s.canInsertBlockType('acme/contact-form', formClientId),
			};
		}, first);
		expect(can).toEqual({ fieldAtRoot: false, checkboxAtRoot: false, selectInForm: true, checkboxInForm: true, paragraphInForm: false, formInForm: false });

		// A new field without a name gets a unique one.
		const extra = await page.evaluate((formClientId) => {
			const block = wp.blocks.createBlock('acme/field-text', { label: 'Phone number' });
			wp.data.dispatch('core/block-editor').insertBlocks(block, 3, formClientId);
			return block.clientId;
		}, first);
		await expect.poll(() => page.evaluate((id) => wp.data.select('core/block-editor').getBlockAttributes(id).name, extra)).toMatch(/\S/);
		const names = (await get(first)).fields.map((f) => f[1]);
		expect(new Set(names).size).toBe(names.length);

		await page.evaluate(({ formClientId }) => {
			const { createBlock } = wp.blocks;
			wp.data.dispatch('core/block-editor').insertBlocks(
				[
					createBlock('acme/field-select', { name: 'topic', label: 'Topic', options: ['Billing', 'Other'], required: true }),
					createBlock('acme/field-checkbox', { name: 'terms', label: 'I agree', required: true }),
				],
				4,
				formClientId
			);
			wp.data.dispatch('core/block-editor').updateBlockAttributes(formClientId, { submitLabel: 'Go' });
			wp.data.dispatch('core/editor').editPost({ title: 'E2E form page', status: 'publish', slug: 'e2e-form-page' });
		}, { formClientId: first });
		await savePost(page);
		const id = await page.evaluate(() => wp.data.select('core/editor').getCurrentPostId());

		const content = wp(['post', 'get', String(id), '--field=post_content']);
		expect(content).toContain('<!-- wp:acme/field-select');
		expect(content).not.toMatch(/<form/);

		await openEditor(page, id);
		await assertBlocksValid(page);
		const tree = await getBlockTree(page);
		const forms = tree.filter((b) => b.name === 'acme/contact-form');
		expect(forms.length).toBe(2);
		expect(forms[0].innerBlocks.map((b) => b.name)).toEqual(['acme/field-text', 'acme/field-email', 'acme/field-textarea', 'acme/field-text', 'acme/field-select', 'acme/field-checkbox']);
		expect(forms[0].attributes.submitLabel).toBe('Go');

		const res = await page.request.get(`/?page_id=${id}`);
		const html = await res.text();
		expect((html.match(/<form[^>]*wp-block-acme-contact-form/g) || []).length).toBe(2);
		expect(html).toContain('name="acme_fields[topic]"');
		expect(html).toContain('value="Billing"');
		expect(html).toContain('name="acme_fields[terms]"');
		expect(html).toMatch(/>\s*Go\s*</);
		expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
	});

	test('submits without reloading the page', async ({ page }) => {
		const errors = trackErrors(page);
		const before = entryCount(`email = 'js@example.org'`);
		await page.goto('/get-in-touch/');
		await page.evaluate(() => { window.__acmeNoReload = 'still here'; });
		const form = page.locator('form.wp-block-acme-contact-form');
		await form.locator('[name="acme_fields[name]"]').fill('Jessica Script');
		await form.locator('[name="acme_fields[email]"]').fill('js@example.org');
		await form.locator('[name="acme_fields[service]"]').selectOption('Design');
		await form.locator('[name="acme_fields[message]"]').fill('Sent with fetch');
		await form.locator('[name="acme_fields[newsletter]"]').check();
		await form.locator('button[type="submit"], input[type="submit"]').first().click();

		await expect(form.locator('[role="status"]')).toContainText('Thanks! We will get back to you within one business day.');
		expect(await page.evaluate(() => window.__acmeNoReload)).toBe('still here');
		expect(new URL(page.url()).pathname).toBe('/get-in-touch/');
		await expect.poll(() => entryCount(`email = 'js@example.org'`)).toBe(before + 1);
		expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
	});

	test('shows errors in place with JavaScript', async ({ page }) => {
		const before = entryCount();
		await page.goto('/get-in-touch/');
		await page.evaluate(() => { window.__acmeNoReload = 'still here'; });
		const form = page.locator('form.wp-block-acme-contact-form');
		await form.locator('[name="acme_fields[email]"]').fill('not-an-email');
		await form.locator('[name="acme_fields[service]"]').selectOption('Hosting');
		await form.locator('[name="acme_fields[message]"]').fill('Where is my name?');
		// Bypass the browser's own validation (required/type=email): our messages must show.
		await form.evaluate((f) => { f.noValidate = true; f.querySelectorAll('[required]').forEach((el) => el.removeAttribute('required')); f.querySelectorAll('input[type=email]').forEach((el) => el.setAttribute('type', 'text')); });
		await form.locator('button[type="submit"], input[type="submit"]').first().click();

		const email = form.locator('[name="acme_fields[email]"]');
		await expect(email).toHaveAttribute('aria-invalid', 'true');
		const describedBy = await email.getAttribute('aria-describedby');
		expect(describedBy).toBeTruthy();
		const texts = await Promise.all(describedBy.split(/\s+/).map((id) => page.locator(`[id="${id}"]`).innerText().catch(() => '')));
		expect(texts.join(' ')).toContain('Please enter a valid email address.');
		const name = form.locator('[name="acme_fields[name]"]');
		await expect(name).toHaveAttribute('aria-invalid', 'true');
		await expect(form.locator('[role="alert"]').filter({ hasText: /\S/ }).first()).toBeVisible();
		await expect(form).toContainText('This field is required.');
		expect(await page.evaluate(() => window.__acmeNoReload)).toBe('still here');
		expect(entryCount()).toBe(before);

		// Fixing the input clears the errors and sends.
		await name.fill('Now With Name');
		await email.fill('fixed@example.org');
		await form.locator('button[type="submit"], input[type="submit"]').first().click();
		await expect(form.locator('[role="status"]')).toContainText('Thanks!');
		await expect(email).not.toHaveAttribute('aria-invalid', 'true');
		await expect.poll(() => entryCount(`email = 'fixed@example.org'`)).toBe(1);
	});

	test('works without JavaScript', async ({ browser }) => {
		const context = await browser.newContext({ baseURL: BASE_URL, javaScriptEnabled: false });
		const page = await context.newPage();
		await page.goto('/two-forms/');
		const form = page.locator('form.wp-block-acme-contact-form').first();
		await form.locator('[name="acme_fields[name]"]').fill('Nora NoScript');
		await form.locator('[name="acme_fields[email]"]').fill('nojs@example.org');
		await form.locator('[name="acme_fields[message]"]').fill('Plain old form post');
		await Promise.all([page.waitForNavigation(), form.locator('button[type="submit"], input[type="submit"]').first().click()]);
		expect(new URL(page.url()).pathname).toBe('/two-forms/');
		await expect(page.locator('form.wp-block-acme-contact-form').first().locator('[role="status"]')).toContainText('Support request received.');
		expect(entryCount(`email = 'nojs@example.org' AND form_id = 'support'`)).toBe(1);
		await context.close();
	});

	test('classic [acme_contact] shortcodes convert to the block', async ({ page }) => {
		const errors = trackErrors(page);
		await login(page);
		const id = postId('classic-contact', 'post');
		await openEditor(page, id);
		const converted = await page.evaluate(() => {
			const strip = (blocks) => blocks.map((b) => ({ name: b.name, attributes: b.attributes, innerBlocks: strip(b.innerBlocks || []) }));
			const content = wp.data.select('core/editor').getEditedPostContent();
			return {
				full: strip(wp.blocks.rawHandler({ HTML: content })),
				bare: strip(wp.blocks.rawHandler({ HTML: '<p>Write to us:</p>\n\n<p>[acme_contact]</p>' })),
			};
		});
		const form = converted.full.find((b) => b.name === 'acme/contact-form');
		expect(form, 'the shortcode became a Contact form block').toBeTruthy();
		expect(form.attributes.submitLabel).toBe('Send it');
		expect(form.innerBlocks.map((b) => [b.name, b.attributes.name, b.attributes.label, b.attributes.required])).toEqual([
			['acme/field-text', 'name', 'Your name', true],
			['acme/field-email', 'email', 'Your email', true],
			['acme/field-select', 'subject', 'Subject', true],
			['acme/field-textarea', 'message', 'Message', true],
		]);
		expect(form.innerBlocks[2].attributes.options).toEqual(['Sales', 'Support']);
		expect(converted.full.some((b) => b.name === 'core/paragraph')).toBe(true);

		const bare = converted.bare.find((b) => b.name === 'acme/contact-form');
		expect(bare).toBeTruthy();
		expect(bare.attributes.submitLabel).toBe('Send message');
		expect(bare.innerBlocks.map((b) => b.attributes.name)).toEqual(['name', 'email', 'message']);

		// Convert, save, and the converted form works on the front end.
		await page.evaluate(() => {
			const content = wp.data.select('core/editor').getEditedPostContent();
			wp.data.dispatch('core/block-editor').resetBlocks(wp.blocks.rawHandler({ HTML: content }));
		});
		await expect.poll(() => page.evaluate(() => {
			const f = wp.data.select('core/block-editor').getBlocks().find((b) => b.name === 'acme/contact-form');
			return f ? f.attributes.formId : '';
		})).toBeTruthy();
		await savePost(page);
		await openEditor(page, id);
		await assertBlocksValid(page);
		const res = await page.request.get(`/?p=${id}`);
		const html = await res.text();
		expect(html).toMatch(/<form[^>]*wp-block-acme-contact-form/);
		expect(html).toContain('name="acme_fields[subject]"');
		expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
	});

	test('the classic contact page is unchanged', async ({ page }) => {
		const res = await page.request.get('/contact/');
		const html = await res.text();
		expect(html).toContain('id="acme-contact"');
		expect(html).toContain('name="acme_subject"');
	});
});
