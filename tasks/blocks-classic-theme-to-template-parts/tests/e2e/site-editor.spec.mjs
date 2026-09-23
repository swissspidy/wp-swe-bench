// Header / footer template parts in the Site Editor (hidden E2E tests).
import { test, expect } from '@playwright/test';
import { login, trackErrors, getInvalidBlocks } from '../wpsb/e2e/helpers.mjs';

async function openPart(page, slug) {
	await page.goto(`/wp-admin/site-editor.php?p=${encodeURIComponent(`/wp_template_part/acme-corporate//${slug}`)}&canvas=edit`);
	await page.waitForFunction(
		() => {
			const be = window.wp?.data?.select('core/block-editor');
			return be && be.getBlocks().length > 0;
		},
		null,
		{ timeout: 120_000 }
	);
	// Patterns inside the part are resolved asynchronously; let the canvas settle.
	await page.waitForFunction(
		() => !window.wp.data.select('core/block-editor').getClientIdsWithDescendants().some((id) => window.wp.data.select('core/block-editor').getBlockName(id) === 'core/pattern'),
		null,
		{ timeout: 60_000 }
	).catch(() => {});
	await page.waitForTimeout(4000);
}

async function problems(page) {
	const invalid = await getInvalidBlocks(page);
	const more = await page.evaluate(() => {
		const be = window.wp.data.select('core/block-editor');
		return be
			.getClientIdsWithDescendants()
			.map((id) => be.getBlock(id))
			.filter((b) => b && (b.isValid === false || b.name === 'core/missing' || !window.wp.blocks.getBlockType(b.name)))
			.map((b) => ({ name: b.name, original: b.attributes?.originalName }));
	});
	return [...invalid, ...more];
}

function canvas(page) {
	return page.frameLocator('iframe[name="editor-canvas"]').locator('body');
}

test.describe('Acme Corporate template parts in the Site Editor', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('header part opens without block errors and shows the site header', async ({ page }) => {
		const errors = trackErrors(page);
		await openPart(page, 'header');
		expect(await problems(page)).toEqual([]);
		const body = canvas(page);
		await expect(body).not.toContainText(/(unexpected or invalid content|doesn.t include support for)/i, { timeout: 5_000 });
		await expect(body).toContainText('Acme Corporation', { timeout: 30_000 });
		await expect(body).toContainText('Request a quote', { timeout: 30_000 });
		expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
	});

	test('footer part opens without block errors and shows the footer content', async ({ page }) => {
		const errors = trackErrors(page);
		await openPart(page, 'footer');
		expect(await problems(page)).toEqual([]);
		const body = canvas(page);
		await expect(body).not.toContainText(/(unexpected or invalid content|doesn.t include support for)/i, { timeout: 5_000 });
		await expect(body).toContainText('hello@acme-corp.example', { timeout: 30_000 });
		await expect(body).toContainText('All rights reserved', { timeout: 30_000 });
		expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
	});
});
