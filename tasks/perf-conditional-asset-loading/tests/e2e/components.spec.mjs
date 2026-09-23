// The components keep working wherever they are used (block theme).
import { test, expect } from '@playwright/test';
import { trackErrors } from '../wpsb/e2e/helpers.mjs';

async function visit(page, path) {
	const errors = trackErrors(page);
	const res = await page.goto(path);
	expect(res.status()).toBe(200);
	await page.waitForLoadState('load');
	return errors;
}

function pageErrors(errors) {
	return errors.filter((e) => e.startsWith('pageerror') || /AcmeUI|AcmeMotion|is not defined/.test(e));
}

async function checkTabs(page, root) {
	const tabs = root.locator('.acme-tabs__tab');
	const panels = root.locator('.acme-tabs__panel');
	await expect(root).toHaveClass(/is-acme-ready/);
	await expect(panels.nth(0)).toBeVisible();
	await expect(panels.nth(1)).toBeHidden();
	await tabs.nth(1).click();
	await expect(tabs.nth(1)).toHaveAttribute('aria-selected', 'true');
	await expect(tabs.nth(0)).toHaveAttribute('aria-selected', 'false');
	await expect(panels.nth(1)).toBeVisible();
	await expect(panels.nth(0)).toBeHidden();
	// Styles are there: the selected tab is underlined.
	const border = await tabs.nth(1).evaluate((el) => getComputedStyle(el).borderBottomStyle + ' ' + getComputedStyle(el).borderBottomWidth);
	expect(border).toBe('solid 3px');
}

async function checkAccordion(page, root) {
	const toggles = root.locator('.acme-accordion__toggle');
	await expect(root).toHaveClass(/is-acme-ready/);
	const first = root.locator('.acme-accordion__panel').nth(0);
	await expect(first).toBeHidden();
	await toggles.nth(0).click();
	await expect(toggles.nth(0)).toHaveAttribute('aria-expanded', 'true');
	await expect(first).toBeVisible();
	// The icon set is loaded (icons are CSS masks).
	const mask = await root.locator('.acme-icon').first().evaluate((el) => getComputedStyle(el).webkitMaskImage || getComputedStyle(el).maskImage);
	expect(mask).toContain('url(');
}

test.describe('UI Kit components on the front end', () => {
	test('tabs in post content', async ({ page }) => {
		const errors = await visit(page, '/gear-guide/');
		await checkTabs(page, page.locator('.acme-tabs').first());
		expect(pageErrors(errors)).toEqual([]);
	});

	test('accordion in post content (one open at a time)', async ({ page }) => {
		const errors = await visit(page, '/tent-faq/');
		const root = page.locator('.acme-accordion').first();
		await checkAccordion(page, root);
		await root.locator('.acme-accordion__toggle').nth(1).click();
		await expect(root.locator('.acme-accordion__panel').nth(1)).toBeVisible();
		await expect(root.locator('.acme-accordion__panel').nth(0)).toBeHidden();
		expect(pageErrors(errors)).toEqual([]);
	});

	test('carousel animates with the motion library', async ({ page }) => {
		const errors = await visit(page, '/gallery/');
		const root = page.locator('.acme-carousel').first();
		await expect(root).toHaveClass(/is-acme-ready/);
		expect(await page.evaluate(() => typeof window.AcmeMotion?.tween)).toBe('function');
		await root.locator('.acme-carousel__next').click();
		await expect(root.locator('.acme-carousel__status')).toHaveText('2 / 3');
		await expect(root).toHaveAttribute('data-current', '1');
		const transform = await root.locator('.acme-carousel__track').evaluate((el) => el.style.transform);
		expect(transform).toBe('translateX(-100%)');
		await root.locator('.acme-carousel__prev').click();
		await expect(root.locator('.acme-carousel__status')).toHaveText('1 / 3');
		expect(pageErrors(errors)).toEqual([]);
	});

	test('all components on one page', async ({ page }) => {
		const errors = await visit(page, '/everything/');
		await checkTabs(page, page.locator('.acme-tabs').first());
		await checkAccordion(page, page.locator('.acme-accordion').first());
		const car = page.locator('.acme-carousel').first();
		await car.locator('.acme-carousel__next').click();
		await expect(car.locator('.acme-carousel__status')).toHaveText('2 / 3');
		expect(pageErrors(errors)).toEqual([]);
	});

	test('accordion in a synced pattern', async ({ page }) => {
		const errors = await visit(page, '/shipping/');
		await checkAccordion(page, page.locator('.acme-accordion').first());
		expect(pageErrors(errors)).toEqual([]);
	});

	test('tabs in a template part', async ({ page }) => {
		const errors = await visit(page, '/summer/');
		await checkTabs(page, page.locator('.acme-tabs.is-promo').first());
		expect(pageErrors(errors)).toEqual([]);
	});

	test('legacy shortcode', async ({ page }) => {
		const errors = await visit(page, '/packing-list/');
		await checkTabs(page, page.locator('.acme-tabs--shortcode').first());
		expect(pageErrors(errors)).toEqual([]);
	});

	test('newsletter plugin: own component + inline script + kit tabs', async ({ page }) => {
		const errors = await visit(page, '/newsletter/');
		const box = page.locator('.acme-newsletter');
		await expect(box).toHaveClass(/is-ready/);
		await expect(box.locator('.acme-newsletter__status')).toHaveText('Ready to subscribe');
		await checkTabs(page, box.locator('.acme-tabs'));
		await box.locator('button[type=submit]').click();
		await expect(box.locator('.acme-newsletter__status')).toHaveText('Thanks, hiker@example.org');
		expect(pageErrors(errors)).toEqual([]);
	});

	test('FAQ teaser requested while rendering', async ({ page }) => {
		const errors = await visit(page, '/help/');
		await checkAccordion(page, page.locator('.acme-faq-teaser'));
		expect(pageErrors(errors)).toEqual([]);
	});

	test('pages without components still work', async ({ page }) => {
		const errors = await visit(page, '/about/');
		expect(await page.evaluate(() => typeof window.AcmeUI)).toBe('undefined');
		expect(pageErrors(errors)).toEqual([]);
	});
});
