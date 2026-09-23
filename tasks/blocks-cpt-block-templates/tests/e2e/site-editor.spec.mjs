// The plugin's templates / template part in the Site Editor (hidden E2E tests).
import { test, expect } from '@playwright/test';
import { login, trackErrors, getInvalidBlocks, openEditor, wp } from '../wpsb/e2e/helpers.mjs';

const THEME = 'twentytwentyfive';

async function openSiteEditor(page, postType, id) {
	await page.goto(`/wp-admin/site-editor.php?p=${encodeURIComponent(`/${postType}/${id}`)}&canvas=edit`);
	await page.waitForFunction(
		() => {
			const be = window.wp?.data?.select('core/block-editor');
			return be && be.getBlocks().length > 0;
		},
		null,
		{ timeout: 120_000 }
	);
	// Let template parts / controlled inner blocks resolve.
	await page.waitForFunction(
		() => {
			const be = window.wp.data.select('core/block-editor');
			const all = be.getClientIdsWithDescendants();
			return all.length > 3;
		},
		null,
		{ timeout: 60_000 }
	);
	await page.waitForTimeout(3000);
}

/** All block names in the editor store, including controlled inner blocks (template parts). */
async function allBlockNames(page) {
	return page.evaluate(() => {
		const be = window.wp.data.select('core/block-editor');
		return be.getClientIdsWithDescendants().map((id) => be.getBlockName(id));
	});
}

async function invalidEverywhere(page) {
	const invalid = await getInvalidBlocks(page);
	const more = await page.evaluate(() => {
		const be = window.wp.data.select('core/block-editor');
		return be
			.getClientIdsWithDescendants()
			.map((id) => be.getBlock(id))
			.filter((b) => b && (b.isValid === false || b.name === 'core/missing'))
			.map((b) => ({ name: b.name, original: b.attributes?.originalName }));
	});
	return [...invalid, ...more];
}

async function placeholderFreeCanvas(page) {
	const canvas = page.frameLocator('iframe[name="editor-canvas"]');
	await expect(canvas.locator('body')).not.toContainText(/(unexpected or invalid content|Your site doesn’t include support for|doesn't include support for)/i, { timeout: 5_000 });
}

test.describe('Acme Courses in the Site Editor', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('single course template opens with valid, registered blocks and the summary part', async ({ page }) => {
		const errors = trackErrors(page);
		await openSiteEditor(page, 'wp_template', `${THEME}//single-acme_course`);
		expect(await invalidEverywhere(page)).toEqual([]);
		await placeholderFreeCanvas(page);
		const names = await allBlockNames(page);
		expect(names).toContain('core/post-title');
		expect(names).toContain('core/post-content');
		expect(names).toContain('core/template-part');
		// Blocks of the course-summary part (controlled inner blocks of the template part).
		await expect.poll(async () => allBlockNames(page), { timeout: 30_000 }).toContain('acme-courses/course-price');
		const after = await allBlockNames(page);
		expect(after).toContain('acme-courses/course-duration');
		expect(after).toContain('acme-courses/enroll-button');
		const types = await page.evaluate(() =>
			['acme-courses/course-price', 'acme-courses/course-duration', 'acme-courses/enroll-button'].map((n) => !!window.wp.blocks.getBlockType(n))
		);
		expect(types).toEqual([true, true, true]);
		expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
	});

	test('course catalog and topic templates open without block errors', async ({ page }) => {
		const errors = trackErrors(page);
		for (const slug of ['archive-acme_course', 'taxonomy-acme_course_topic']) {
			await openSiteEditor(page, 'wp_template', `${THEME}//${slug}`);
			expect(await invalidEverywhere(page), slug).toEqual([]);
			await placeholderFreeCanvas(page);
			const names = await allBlockNames(page);
			expect(names, slug).toContain('core/query');
			expect(names, slug).toContain('acme-courses/course-price');
			expect(names, slug).toContain('acme-courses/course-duration');
		}
		expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
	});

	test('course summary template part can be edited and saved', async ({ page }) => {
		const errors = trackErrors(page);
		await openSiteEditor(page, 'wp_template_part', `${THEME}//course-summary`);
		expect(await invalidEverywhere(page)).toEqual([]);
		const names = await allBlockNames(page);
		expect(names).toContain('acme-courses/course-price');
		expect(names).toContain('acme-courses/enroll-button');

		// Edit: add a paragraph at the top and save through the editor.
		await page.evaluate(() => {
			const { createBlock } = window.wp.blocks;
			window.wp.data.dispatch('core/block-editor').insertBlocks(createBlock('core/paragraph', { content: 'E2E-SUMMARY-EDIT' }), 0);
		});
		await page.evaluate(async () => {
			const { select, dispatch } = window.wp.data;
			const ed = select('core/editor');
			await dispatch('core').saveEditedEntityRecord('postType', ed.getCurrentPostType(), ed.getCurrentPostId());
		});
		await expect
			.poll(() => wp(['post', 'list', '--post_type=wp_template_part', '--name=course-summary', '--field=post_content']), { timeout: 60_000 })
			.toContain('E2E-SUMMARY-EDIT');

		const res = await page.request.get('/courses/intro-to-php/');
		const html = await res.text();
		expect(html).toContain('E2E-SUMMARY-EDIT');
		expect(html).toContain('wp-block-acme-courses-course-price');
		expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
		wp(['post', 'delete', wp(['post', 'list', '--post_type=wp_template_part', '--name=course-summary', '--field=ID']), '--force']);
	});

	test('course blocks can be inserted in a course and preview its data', async ({ page }) => {
		const id = Number(wp(['post', 'list', '--post_type=acme_course', '--name=wordpress-basics', '--field=ID']));
		await openEditor(page, id);
		await page.evaluate(() => {
			const { createBlock } = window.wp.blocks;
			window.wp.data.dispatch('core/block-editor').insertBlocks(createBlock('acme-courses/course-price'), 0);
		});
		const hasIframe = (await page.locator('iframe[name="editor-canvas"]').count()) > 0;
		const scope = hasIframe ? page.frameLocator('iframe[name="editor-canvas"]').locator('body') : page.locator('.editor-styles-wrapper');
		await expect(scope).toContainText('$29.00', { timeout: 60_000 });
		expect(await getInvalidBlocks(page)).toEqual([]);
	});
});
