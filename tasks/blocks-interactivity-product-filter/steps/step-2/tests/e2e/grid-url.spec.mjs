// Step 2: shareable URLs, history and pagination.
import { test, expect } from '@playwright/test';
import { login, createPost, newPost, trackErrors } from '../wpsb/e2e/helpers.mjs';
import { recordServerState, snapshot, load, expectNoFlash, expectNoReload, grid, filter, relevantErrors } from './_grid.mjs';

const params = (page) => Object.fromEntries(new URL(page.url()).searchParams);

test.describe('Product grid URLs', () => {
	test('filtering updates the URL and a reload shows the same grid', async ({ page }) => {
		const errors = trackErrors(page);
		await recordServerState(page);
		await load(page, '/shop/?utm_source=newsletter');
		await filter(page, 0, 'Mugs').click();
		await expect(page).toHaveURL(/acme_cat=mugs/);
		await grid(page).locator('.acme-grid__search').fill('blue');
		await expect(page).toHaveURL(/acme_q=blue/);
		await expect(grid(page).locator('.acme-grid__count')).toHaveText('Showing 1 of 12 products');
		expect(params(page)).toEqual({ utm_source: 'newsletter', acme_cat: 'mugs', acme_q: 'blue' });
		await expectNoReload(page);
		const before = await snapshot(page);

		await page.reload();
		await page.waitForLoadState('load');
		await page.waitForTimeout(1500);
		expect(await snapshot(page)).toEqual(before);
		await expectNoFlash(page);
		expect(relevantErrors(errors)).toEqual([]);
	});

	test('back and forward restore earlier states without reloading', async ({ page }) => {
		await load(page, '/shop/');
		const initial = await snapshot(page);
		await filter(page, 0, 'Mugs').click();
		await expect(page).toHaveURL(/acme_cat=mugs/);
		const mugs = await snapshot(page);
		await filter(page, 0, 'Posters').click();
		await expect(page).toHaveURL(/acme_cat=posters/);
		await expect(grid(page).locator('.acme-grid__count')).toHaveText('Showing 3 of 12 products');

		await page.goBack();
		await expect(page).toHaveURL(/acme_cat=mugs/);
		await expect(grid(page).locator('.acme-grid__count')).toHaveText('Showing 5 of 12 products');
		expect(await snapshot(page)).toEqual(mugs);

		await page.goBack();
		await expect(page).not.toHaveURL(/acme_cat=/);
		await expect(grid(page).locator('.acme-grid__count')).toHaveText('Showing 12 of 12 products');
		expect(await snapshot(page)).toEqual(initial);

		await page.goForward();
		await expect(grid(page).locator('.acme-grid__count')).toHaveText('Showing 5 of 12 products');
		expect(await snapshot(page)).toEqual(mugs);
		await expectNoReload(page);
	});

	test('a shared URL opens the grid in that state', async ({ page }) => {
		await recordServerState(page);
		await load(page, '/two-grids/?acme_cat=posters&acme_q=ocean');
		const state = await expectNoFlash(page);
		expect(state[0].pressed).toEqual(['=false', 'mugs=false', 'posters=true']);
		expect(state[0].visible).toEqual(['Ocean Poster']);
		expect(state[0].search).toBe('ocean');
		expect(state[1].pressed).toContain('shirts=true');
	});

	test('only the first grid writes to the URL', async ({ page }) => {
		await load(page, '/two-grids/');
		const url = page.url();
		await filter(page, 1, 'Mugs').click();
		await expect(grid(page, 1).locator('.acme-grid__count')).toHaveText('Showing 5 of 12 products');
		expect(page.url()).toBe(url);
		await filter(page, 0, 'Mugs').click();
		await expect(page).toHaveURL(/acme_cat=mugs/);
		expect((await snapshot(page))[1].pressed).toContain('mugs=true');
	});

	test('choosing "All" on a grid with a default category is shareable', async ({ page }) => {
		await load(page, '/featured/');
		await filter(page, 0, 'All').click();
		await expect(page).toHaveURL(/acme_cat=all/);
		const before = await snapshot(page);
		await page.reload();
		await page.waitForLoadState('load');
		await page.waitForTimeout(1500);
		expect(await snapshot(page)).toEqual(before);
		await filter(page, 0, 'Posters').click();
		await expect(page).not.toHaveURL(/acme_cat=/);
	});

	test('pagination: next/previous, URL, reset on filter change, history', async ({ page }) => {
		const errors = trackErrors(page);
		createPost({ title: 'Paged grid', type: 'page', content: '<!-- wp:acme/product-grid {"perPage":4} /-->' });
		const slug = 'paged-grid';
		await recordServerState(page);
		await load(page, `/${slug}/`);
		await expectNoFlash(page);
		const nav = grid(page).locator('.acme-grid__pagination');
		await expect(nav).toBeVisible();
		await expect(nav.locator('.acme-grid__page')).toHaveText('Page 1 of 3');
		await expect(nav.locator('.acme-grid__prev')).toBeDisabled();
		const page1 = (await snapshot(page))[0].visible;
		expect(page1.length).toBe(4);

		await nav.locator('.acme-grid__next').click();
		await expect(nav.locator('.acme-grid__page')).toHaveText('Page 2 of 3');
		await expect(page).toHaveURL(/acme_page=2/);
		const page2 = (await snapshot(page))[0].visible;
		expect(page2.length).toBe(4);
		expect(page2.filter((t) => page1.includes(t))).toEqual([]);

		await nav.locator('.acme-grid__next').click();
		await expect(nav.locator('.acme-grid__page')).toHaveText('Page 3 of 3');
		await expect(nav.locator('.acme-grid__next')).toBeDisabled();
		await page.reload();
		await page.waitForLoadState('load');
		await page.waitForTimeout(1500);
		await expect(nav.locator('.acme-grid__page')).toHaveText('Page 3 of 3');
		await page.evaluate(() => { window.__wpsbNoReload = true; });

		await nav.locator('.acme-grid__prev').click();
		await expect(nav.locator('.acme-grid__page')).toHaveText('Page 2 of 3');
		expect((await snapshot(page))[0].visible).toEqual(page2);

		await filter(page, 0, 'Mugs').click();
		await expect(nav.locator('.acme-grid__page')).toHaveText('Page 1 of 2');
		await expect(page).not.toHaveURL(/acme_page=/);
		await filter(page, 0, 'Stickers').click();
		await expect(nav).toBeHidden();

		await page.goBack();
		await expect(nav.locator('.acme-grid__page')).toHaveText('Page 1 of 2');
		await page.goBack();
		await expect(nav.locator('.acme-grid__page')).toHaveText('Page 2 of 3');
		expect((await snapshot(page))[0].visible).toEqual(page2);
		await expectNoReload(page);
		expect(relevantErrors(errors)).toEqual([]);
	});

	test('the page size is a block setting', async ({ page }) => {
		await login(page);
		await newPost(page, 'page');
		const attrs = await page.evaluate(() => wp.blocks.getBlockType('acme/product-grid')?.attributes);
		expect(attrs?.perPage?.type).toBe('number');
		expect(attrs?.perPage?.default ?? 0).toBe(0);
	});
});
