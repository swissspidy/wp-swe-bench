// Front-end behaviour of the product grid (step 1 contract).
import { test, expect } from '@playwright/test';
import { login, wp, openEditor, assertBlocksValid, trackErrors } from '../wpsb/e2e/helpers.mjs';
import { recordServerState, snapshot, load, expectNoFlash, expectNoReload, grid, filter, relevantErrors } from './_grid.mjs';

const MUGS = ['Blue Mug', 'Café Mug', 'Mug & Poster Bundle', 'Red Mug', 'Travel Mug'];

test.describe('Product grid front end', () => {
	for (const path of ['/shop/', '/featured/', '/two-grids/', '/classic-shop/']) {
		test(`no flash: ${path} looks the same before and after the scripts run`, async ({ page }) => {
			const errors = trackErrors(page);
			await recordServerState(page);
			await load(page, path);
			const state = await expectNoFlash(page);
			expect(state.length).toBeGreaterThan(0);
			for (const g of state) {
				expect(g.count).toMatch(/^Showing \d+ of \d+ products?$/);
				expect(g.pressed.filter((p) => p.endsWith('=true')).length).toBe(1);
			}
			expect(await page.evaluate(() => typeof window.jQuery)).toBe('undefined');
			expect(relevantErrors(errors)).toEqual([]);
		});
	}

	test('filters and search update the grid without reloading', async ({ page }) => {
		const errors = trackErrors(page);
		await load(page, '/shop/');
		await filter(page, 0, 'Mugs').click();
		await expect(grid(page).locator('.acme-grid__count')).toHaveText('Showing 5 of 12 products');
		let s = (await snapshot(page))[0];
		expect(s.pressed).toEqual(['=false', 'mugs=true', 'posters=false', 'stickers=false', 'shirts=false']);
		expect(s.active).toEqual(['mugs']);
		expect([...s.visible].sort()).toEqual([...MUGS].sort());
		expect(s.empty).toBe(false);

		const search = grid(page).locator('.acme-grid__search');
		await search.fill('blue');
		await expect(grid(page).locator('.acme-grid__count')).toHaveText('Showing 1 of 12 products');
		expect((await snapshot(page))[0].visible).toEqual(['Blue Mug']);

		await filter(page, 0, 'All').click();
		await expect(grid(page).locator('.acme-grid__count')).toHaveText('Showing 2 of 12 products');
		expect((await snapshot(page))[0].visible).toEqual(['Blue Mug', 'Blue T-Shirt']);

		// All words must match, in the name or the SKU, case-insensitive.
		await search.fill('  tee   BLU ');
		await expect(grid(page).locator('.acme-grid__count')).toHaveText('Showing 1 of 12 products');
		expect((await snapshot(page))[0].visible).toEqual(['Blue T-Shirt']);
		await search.fill('CAFÉ');
		await expect(grid(page).locator('.acme-grid__count')).toHaveText('Showing 1 of 12 products');
		expect((await snapshot(page))[0].visible).toEqual(['Café Mug']);

		await search.fill('zzz');
		await expect(grid(page).locator('.acme-grid__count')).toHaveText('Showing 0 of 12 products');
		s = (await snapshot(page))[0];
		expect(s.visible).toEqual([]);
		expect(s.empty).toBe(true);
		await expect(grid(page).locator('.acme-grid__empty')).toBeVisible();

		await search.fill('');
		await expect(grid(page).locator('.acme-grid__count')).toHaveText('Showing 12 of 12 products');
		await expect(grid(page).locator('.acme-grid__empty')).toBeHidden();
		await expectNoReload(page);
		expect(relevantErrors(errors)).toEqual([]);
	});

	test('grids on the same page are independent', async ({ page }) => {
		await load(page, '/two-grids/');
		const before = await snapshot(page);
		await filter(page, 0, 'Posters').click();
		await expect(grid(page, 0).locator('.acme-grid__count')).toHaveText('Showing 3 of 7 products');
		let now = await snapshot(page);
		expect(now[1]).toEqual(before[1]);
		expect(now[0].pressed).toEqual(['=false', 'mugs=false', 'posters=true']);

		await filter(page, 1, 'All').click();
		await expect(grid(page, 1).locator('.acme-grid__count')).toHaveText('Showing 12 of 12 products');
		now = await snapshot(page);
		expect(now[0].pressed).toEqual(['=false', 'mugs=false', 'posters=true']);
		expect(now[0].count).toBe('Showing 3 of 7 products');

		await grid(page, 0).locator('.acme-grid__search').fill('ocean');
		await expect(grid(page, 0).locator('.acme-grid__count')).toHaveText('Showing 1 of 7 products');
		expect((await snapshot(page))[1].count).toBe('Showing 12 of 12 products');
		await expectNoReload(page);
	});

	test('the initially selected category can be changed by visitors', async ({ page }) => {
		await load(page, '/featured/');
		await filter(page, 0, 'All').click();
		await expect(grid(page).locator('.acme-grid__count')).toHaveText('Showing 12 of 12 products');
		await filter(page, 0, 'Stickers').click();
		await expect(grid(page).locator('.acme-grid__count')).toHaveText('Showing 1 of 12 products');
		expect((await snapshot(page))[0].visible).toEqual(['Sticker Pack']);
	});

	test('shortcode grids are interactive too', async ({ page }) => {
		const errors = trackErrors(page);
		await load(page, '/classic-shop/');
		await grid(page, 0).locator('.acme-grid__search').fill('travel');
		await expect(grid(page, 0).locator('.acme-grid__count')).toHaveText('Showing 1 of 5 products');
		expect((await snapshot(page))[0].visible).toEqual(['Travel Mug']);
		expect((await snapshot(page))[1].count).toBe('Showing 1 of 1 product');
		expect(relevantErrors(errors)).toEqual([]);
	});

	test('pages with grids still open in the editor', async ({ page }) => {
		await login(page);
		const id = Number(wp(['post', 'list', '--post_type=page', '--name=two-grids', '--field=ID']));
		await openEditor(page, id);
		await assertBlocksValid(page);
		const names = await page.evaluate(() => wp.data.select('core/block-editor').getBlocks().map((b) => b.name));
		expect(names.filter((n) => n === 'acme/product-grid').length).toBe(2);
	});
});
