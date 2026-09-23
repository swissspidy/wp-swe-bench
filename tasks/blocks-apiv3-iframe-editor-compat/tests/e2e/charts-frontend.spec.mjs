// Acme Charts on the front end (must keep working exactly as before).
import { test, expect } from '@playwright/test';
import { geometry, waitForBars, assertChart, watchConsole, SERIES } from './_charts.mjs';

test.describe('Acme Charts on the front end', () => {
	test('charts are drawn and legends toggle bars', async ({ page }) => {
		const con = watchConsole(page);
		await page.goto('/quarterly-visitors/');
		const chart = page.locator('#visitors-2025');
		await waitForBars(chart, 4);
		assertChart(await geometry(chart), SERIES.visitors);
		const items = page.locator('.acme-legend[data-chart="visitors-2025"] .acme-legend__item');
		await expect(items).toHaveCount(4);
		expect((await items.allTextContents()).map((t) => t.trim())).toEqual(['Q1', 'Q2', 'Q3', 'Q4']);
		await items.nth(2).click();
		await expect(items.nth(2)).toHaveAttribute('aria-pressed', 'false');
		const g = await geometry(chart);
		expect(g.bars.map((b) => b.hidden)).toEqual([false, false, true, false]);
		expect(g.bars[2].opacity).toBe('0.15');
		await items.nth(2).click();
		await expect(items.nth(2)).toHaveAttribute('aria-pressed', 'true');
		expect((await geometry(chart)).bars.every((b) => !b.hidden)).toBe(true);
		expect(con.errors).toEqual([]);
	});

	test('1.0 charts (never re-saved) are still drawn', async ({ page }) => {
		await page.goto('/legacy-chart/');
		const chart = page.locator('#survey-2019');
		await waitForBars(chart, 4);
		assertChart(await geometry(chart), SERIES.survey);
		const items = page.locator('.acme-legend__item');
		await expect(items).toHaveCount(4);
		await items.first().click();
		expect((await geometry(chart)).bars.map((b) => b.hidden)).toEqual([true, false, false, false]);
	});

	test('wide chart with values and the minimal style', async ({ page }) => {
		await page.goto('/wide-chart/');
		const chart = page.locator('.wp-block-acme-chart');
		await expect(chart).toHaveClass(/\balignwide\b/);
		await expect(chart).toHaveClass(/\bis-style-minimal\b/);
		await expect(chart).toHaveClass(/\breport-chart\b/);
		await waitForBars(chart, 6);
		const g = await geometry(chart);
		assertChart(g, SERIES.downloads);
		expect(g.values).toEqual(['5400', '3600', '900', '2700', '4500', '1800']);
		expect(await chart.locator('text.acme-chart__label').first().evaluate((el) => getComputedStyle(el).display)).toBe('none');
	});

	test('charts in columns and in synced patterns, legends in other columns', async ({ page }) => {
		await page.goto('/budget-overview/');
		let chart = page.locator('#budget');
		await waitForBars(chart, 3);
		assertChart(await geometry(chart), SERIES.budget);
		const items = page.locator('.acme-legend.is-vertical .acme-legend__item');
		await expect(items).toHaveCount(3);
		await items.nth(1).click();
		expect((await geometry(chart)).bars.map((b) => b.hidden)).toEqual([false, true, false]);

		await page.goto('/uses-synced-chart/');
		chart = page.locator('#revenue');
		await waitForBars(chart, 4);
		assertChart(await geometry(chart), SERIES.revenue);
		await expect(page.locator('.acme-legend__item')).toHaveCount(4);
	});

	test('charts are redrawn when the window is resized', async ({ page }) => {
		await page.goto('/quarterly-visitors/');
		const chart = page.locator('#visitors-2025');
		await waitForBars(chart, 4);
		const before = await geometry(chart);
		await page.setViewportSize({ width: 420, height: 900 });
		await expect
			.poll(async () => {
				const g = await geometry(chart);
				return g.canvasWidth < before.canvasWidth && Math.abs(g.svgWidth - g.canvasWidth) <= 1;
			}, { timeout: 15_000 })
			.toBe(true);
		assertChart(await geometry(chart), SERIES.visitors);
	});

	test('the table fallback and the saved markup are unchanged', async ({ page }) => {
		const res = await page.request.get('/quarterly-visitors/');
		const html = await res.text();
		expect(html).toMatch(/<figure[^>]*class="[^"]*\bwp-block-acme-chart\b[^"]*\bacme-chart\b[^"]*"[^>]*data-chart="/);
		expect(html).toContain('<div class="acme-chart__canvas" style="height:240px" aria-hidden="true"></div>');
		expect(html).toContain('<table class="acme-chart__table"><tbody><tr><th scope="row">Q1</th><td>120</td></tr>');
		expect(html).toMatch(/<ul class="wp-block-acme-chart-legend acme-legend" data-chart="visitors-2025"><\/ul>/);
	});
});
