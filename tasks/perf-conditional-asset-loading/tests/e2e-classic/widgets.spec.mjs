// Classic theme: an accordion block in a sidebar widget works.
import { test, expect } from '@playwright/test';
import { trackErrors } from '../wpsb/e2e/helpers.mjs';

test('accordion in a sidebar widget', async ({ page }) => {
	const errors = trackErrors(page);
	await page.goto('/about/');
	const root = page.locator('.site-sidebar .acme-accordion');
	await expect(root).toHaveClass(/is-acme-ready/);
	await root.locator('.acme-accordion__toggle').nth(1).click();
	await expect(root.locator('.acme-accordion__panel').nth(1)).toBeVisible();
	await expect(root.locator('.acme-accordion__panel').nth(1)).toContainText('shop@acme.example');
	expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
});

test('carousel next to a widget accordion', async ({ page }) => {
	const errors = trackErrors(page);
	await page.goto('/gallery/');
	const car = page.locator('.acme-carousel').first();
	await car.locator('.acme-carousel__next').click();
	await expect(car.locator('.acme-carousel__status')).toHaveText('2 / 3');
	expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
});
