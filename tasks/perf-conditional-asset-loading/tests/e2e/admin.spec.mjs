// wp-admin: settings preview and the block editor.
import { test, expect } from '@playwright/test';
import { login, trackErrors, newPost } from '../wpsb/e2e/helpers.mjs';

test.describe('UI Kit in wp-admin', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('settings screen preview works', async ({ page }) => {
		const errors = trackErrors(page);
		await page.goto('/wp-admin/options-general.php?page=acme-ui');
		const preview = page.locator('.acme-ui-preview .acme-tabs');
		await expect(preview).toHaveClass(/is-acme-ready/);
		await preview.locator('.acme-tabs__tab').nth(1).click();
		await expect(preview.locator('.acme-tabs__panel').nth(1)).toBeVisible();
		await expect(page.locator('.wp-picker-container')).toHaveCount(1);
		await expect(page.locator('.acme-ui-speed-value')).toHaveText('150 ms');
		expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
	});

	test('blocks can be inserted and are styled in the editor', async ({ page }) => {
		const errors = trackErrors(page);
		await newPost(page, 'page');
		const types = await page.evaluate(() => ['acme/tabs', 'acme/accordion', 'acme/carousel'].map((n) => !!window.wp.blocks.getBlockType(n)));
		expect(types).toEqual([true, true, true]);
		await page.evaluate(() => {
			const { createBlock } = window.wp.blocks;
			window.wp.data.dispatch('core/block-editor').insertBlocks([
				createBlock('acme/tabs', { tabs: [{ title: 'One', content: 'First' }, { title: 'Two', content: 'Second' }] }),
				createBlock('acme/accordion', { items: [{ title: 'Q', content: 'A' }] }),
			]);
		});
		const iframe = page.frameLocator('iframe[name="editor-canvas"]');
		const inIframe = (await page.locator('iframe[name="editor-canvas"]').count()) > 0;
		const canvas = inIframe ? iframe : page;
		const tab = canvas.locator('.acme-tabs__tab[aria-selected="true"]').first();
		await expect(tab).toBeVisible({ timeout: 60_000 });
		const border = await tab.evaluate((el) => getComputedStyle(el).borderBottomStyle + ' ' + getComputedStyle(el).borderBottomWidth);
		expect(border).toBe('solid 3px');
		const mask = await canvas.locator('.acme-accordion .acme-icon').first().evaluate((el) => getComputedStyle(el).webkitMaskImage || getComputedStyle(el).maskImage);
		expect(mask).toContain('url(');
		expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
	});
});
