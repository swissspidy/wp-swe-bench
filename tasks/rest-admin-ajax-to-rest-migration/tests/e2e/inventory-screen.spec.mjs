// The Inventory screen, driven like the warehouse team uses it. It must talk to the REST API
// (no admin-ajax), keep its markup, confirm deletions and show API errors.
import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import { login, wpEval, trackErrors } from '../wpsb/e2e/helpers.mjs';

const item = (sku) =>
	JSON.parse(wpEval(`global $wpdb; echo wp_json_encode( $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}acme_inventory_items WHERE sku = %s", '${sku}' ), ARRAY_A ) );`));
const stockOf = (sku) => {
	const row = item(sku);
	return row ? Number(row.stock) : null;
};
const realErrors = (errors) => errors.filter((e) => !/Failed to load resource/i.test(e));

function trackRequests(page) {
	const urls = [];
	page.on('request', (r) => urls.push(decodeURIComponent(r.url()) + ' ' + (r.postData() || '')));
	return urls;
}

function assertUsesRestOnly(urls) {
	const ajax = urls.filter((u) => /admin-ajax\.php/.test(u) && /acme_inv_/.test(u));
	expect(ajax, 'the screen must not call the admin-ajax actions any more').toEqual([]);
	expect(urls.some((u) => /acme-inventory\/v1\/items/.test(u)), 'the screen must use the REST API').toBe(true);
}

async function openScreen(page) {
	await login(page, 'sam', 'password');
	await page.goto('/wp-admin/admin.php?page=acme-inventory');
	await expect(page.locator('#acme-inv-table tbody tr[data-id]')).toHaveCount(20);
}

async function search(page, term, expected) {
	await page.fill('#acme-inv-search', term);
	await expect(page.locator('#acme-inv-table tbody tr[data-id]')).toHaveCount(expected);
}

const row = (page, sku) => page.locator('#acme-inv-table tbody tr[data-id]').filter({ has: page.locator('.column-sku', { hasText: new RegExp(`^${sku}$`) }) });

test('lists, searches, filters and paginates', async ({ page }) => {
	const errors = trackErrors(page);
	const urls = trackRequests(page);
	await openScreen(page);
	await expect(page.locator('#acme-inv-page-info')).toHaveText('Page 1 of 3');
	await expect(page.locator('#acme-inv-prev')).toBeDisabled();
	await page.click('#acme-inv-next');
	await expect(page.locator('#acme-inv-page-info')).toHaveText('Page 2 of 3');
	await page.click('#acme-inv-next');
	await expect(page.locator('#acme-inv-table tbody tr[data-id]')).toHaveCount(5);
	await expect(page.locator('#acme-inv-next')).toBeDisabled();

	await search(page, 'mug', 4);
	await expect(page.locator('#acme-inv-page-info')).toHaveText('Page 1 of 1');
	await expect(row(page, 'MUG-004')).toHaveClass(/is-low-stock/);
	await expect(row(page, 'MUG-004').locator('.column-name')).toHaveText('Espresso Mug "Tiny"');
	await expect(row(page, 'MUG-001')).not.toHaveClass(/is-low-stock/);
	await search(page, 'pst-', 2);

	await search(page, '', 20);
	await page.check('#acme-inv-low-stock');
	await expect(page.locator('#acme-inv-table tbody tr[data-id]')).toHaveCount(3);
	await expect(page.locator('#acme-inv-table tbody tr[data-id].is-low-stock')).toHaveCount(3);
	await expect(row(page, 'LEG-002').locator('.column-stock')).toHaveText('-3');
	await expect(row(page, 'LEG-002').locator('.column-updated')).toHaveText('—');

	assertUsesRestOnly(urls);
	expect(realErrors(errors)).toEqual([]);
});

test('inline stock edit saves valid values and reports invalid ones', async ({ page }) => {
	const errors = trackErrors(page);
	const urls = trackRequests(page);
	await openScreen(page);
	await search(page, 'mug', 4);

	await row(page, 'MUG-001').locator('.acme-inv-stock').click();
	const input = row(page, 'MUG-001').locator('.acme-inv-stock-input');
	await input.fill('55');
	await input.press('Enter');
	await expect(row(page, 'MUG-001').locator('.acme-inv-stock')).toHaveText('55');
	await expect.poll(() => stockOf('MUG-001')).toBe(55);

	await row(page, 'MUG-002').locator('.acme-inv-stock').click();
	await row(page, 'MUG-002').locator('.acme-inv-stock-input').fill('9');
	await row(page, 'MUG-002').locator('.acme-inv-stock-input').press('Enter');
	await expect(row(page, 'MUG-002').locator('.acme-inv-stock')).toHaveText('9');
	await expect(row(page, 'MUG-002')).toHaveClass(/is-low-stock/);

	await row(page, 'MUG-001').locator('.acme-inv-stock').click();
	await row(page, 'MUG-001').locator('.acme-inv-stock-input').fill('-5');
	await row(page, 'MUG-001').locator('.acme-inv-stock-input').press('Enter');
	await expect(page.locator('#acme-inv-notice')).toBeVisible();
	await expect(page.locator('#acme-inv-notice')).toHaveClass(/notice-error/);
	await expect(row(page, 'MUG-001').locator('.acme-inv-stock')).toHaveText('55');
	await page.waitForTimeout(500);
	expect(stockOf('MUG-001')).toBe(55);

	assertUsesRestOnly(urls);
	expect(realErrors(errors)).toEqual([]);
});

test('bulk adjustment applies to the selection, all or nothing', async ({ page }) => {
	const errors = trackErrors(page);
	const urls = trackRequests(page);
	await openScreen(page);
	await search(page, 'mug', 4);

	const before = Object.fromEntries(['MUG-001', 'MUG-002', 'MUG-003', 'MUG-004'].map((sku) => [sku, stockOf(sku)]));
	await row(page, 'MUG-001').locator('.acme-inv-select').check();
	await row(page, 'MUG-003').locator('.acme-inv-select').check();
	await page.fill('#acme-inv-bulk-delta', '5');
	await page.fill('#acme-inv-bulk-reason', 'Delivery');
	await page.click('#acme-inv-bulk-apply');
	await expect.poll(() => [stockOf('MUG-001'), stockOf('MUG-003')]).toEqual([before['MUG-001'] + 5, before['MUG-003'] + 5]);
	await expect(row(page, 'MUG-001').locator('.acme-inv-stock')).toHaveText(String(before['MUG-001'] + 5));
	await expect(page.locator('#acme-inv-notice')).not.toHaveClass(/notice-error/);
	expect(stockOf('MUG-002')).toBe(before['MUG-002']);

	// MUG-004 has 3 in stock: -4 must fail for both selected items.
	expect(before['MUG-004']).toBe(3);
	await row(page, 'MUG-002').locator('.acme-inv-select').check();
	await row(page, 'MUG-004').locator('.acme-inv-select').check();
	await page.fill('#acme-inv-bulk-delta', '-4');
	await page.click('#acme-inv-bulk-apply');
	await expect(page.locator('#acme-inv-notice')).toHaveClass(/notice-error/);
	await page.waitForTimeout(500);
	expect([stockOf('MUG-002'), stockOf('MUG-004')]).toEqual([before['MUG-002'], 3]);

	assertUsesRestOnly(urls);
	expect(realErrors(errors)).toEqual([]);
});

test('delete asks for confirmation', async ({ page }) => {
	const errors = trackErrors(page);
	const urls = trackRequests(page);
	await openScreen(page);
	await search(page, 'GEN-00', 9);

	let asked = 0;
	page.once('dialog', (d) => {
		asked++;
		expect(d.type()).toBe('confirm');
		d.dismiss();
	});
	await row(page, 'GEN-003').locator('.acme-inv-delete').click();
	await page.waitForTimeout(1000);
	expect(asked).toBe(1);
	expect(item('GEN-003')).not.toBeNull();
	await expect(row(page, 'GEN-003')).toHaveCount(1);

	page.once('dialog', (d) => {
		asked++;
		d.accept();
	});
	await row(page, 'GEN-003').locator('.acme-inv-delete').click();
	await expect.poll(() => item('GEN-003')).toBeNull();
	await expect(row(page, 'GEN-003')).toHaveCount(0);
	expect(asked).toBe(2);

	assertUsesRestOnly(urls);
	expect(realErrors(errors)).toEqual([]);
});

test('export link downloads the CSV from the API', async ({ page }) => {
	await openScreen(page);
	const href = await page.getAttribute('#acme-inv-export', 'href');
	expect(decodeURIComponent(href)).toMatch(/acme-inventory\/v1\/items\/export/);
	expect(href).not.toMatch(/admin-ajax/);
	const [download] = await Promise.all([page.waitForEvent('download'), page.click('#acme-inv-export')]);
	expect(download.suggestedFilename()).toMatch(/^inventory-\d{4}-\d{2}-\d{2}\.csv$/);
	const csv = fs.readFileSync(await download.path(), 'utf8');
	expect(csv.startsWith('SKU,Name,Stock,Low stock threshold,Location,Updated')).toBe(true);
	expect(csv).toContain('"Mug, large"');
	expect(csv).toContain(`'=HYPERLINK`);
});

test('users without the capability have no Inventory screen', async ({ page }) => {
	await login(page, 'eddie', 'password');
	const res = await page.goto('/wp-admin/admin.php?page=acme-inventory');
	expect(res.status()).toBe(403);
	await expect(page.locator('#acme-inv-table')).toHaveCount(0);
});
