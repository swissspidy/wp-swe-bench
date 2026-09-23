// Acme Charts in the (iframed) block editor.
import { test, expect } from '@playwright/test';
import { login, openEditor, newPost, assertBlocksValid, savePost } from '../wpsb/e2e/helpers.mjs';
import { postId, postContent, canvas, watchConsole, geometry, waitForBars, assertChart, SERIES, PALETTE } from './_charts.mjs';

const chartIn = (page, n = 0) => canvas(page).locator('[data-type="acme/chart"]').nth(n);
const legendIn = (page, n = 0) => canvas(page).locator('[data-type="acme/chart-legend"]').nth(n);

// What the editor would save for the current blocks (not the stored content the editor keeps while unmodified).
const serializeBlocks = (page) => page.evaluate(() => wp.blocks.serialize(wp.data.select('core/block-editor').getBlocks()));

test.describe('Acme Charts in the editor', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('the editor canvas is an iframe and the chart is drawn inside it', async ({ page }) => {
		await openEditor(page, postId('quarterly-visitors'));
		await expect(page.locator('iframe[name="editor-canvas"]')).toHaveCount(1);
		await assertBlocksValid(page);
		const chart = chartIn(page);
		await waitForBars(chart, 4);
		assertChart(await geometry(chart), SERIES.visitors);
	});

	test('1.0 charts are valid in the editor and drawn', async ({ page }) => {
		const id = postId('legacy-chart');
		await openEditor(page, id);
		await assertBlocksValid(page);
		const chart = chartIn(page);
		await waitForBars(chart, 4);
		assertChart(await geometry(chart), SERIES.survey);
		// Upgraded to the current markup when opened, and stays valid after editing + saving.
		const edited = await serializeBlocks(page);
		expect(edited).toMatch(/<figure[^>]*class="[^"]*wp-block-acme-chart[^"]*"[^>]*data-chart=/);
		expect(edited).toContain('id="survey-2019"');
		await page.evaluate(() => {
			const block = wp.data.select('core/block-editor').getBlocks().find((b) => b.name === 'acme/chart');
			wp.data.dispatch('core/block-editor').updateBlockAttributes(block.clientId, { title: 'Reading frequency (2019)' });
		});
		const saved = await savePost(page);
		expect(saved).toMatch(/<figure[^>]*data-chart=/);
		expect(saved).not.toContain('data-series=');
		await openEditor(page, id);
		await assertBlocksValid(page);
		await waitForBars(chartIn(page), 4);
		assertChart(await geometry(chartIn(page)), SERIES.survey);
	});

	test('wide/minimal charts with values are drawn with the right styles in the canvas', async ({ page }) => {
		await openEditor(page, postId('wide-chart'));
		await assertBlocksValid(page);
		const chart = chartIn(page);
		await waitForBars(chart, 6);
		const g = await geometry(chart);
		assertChart(g, SERIES.downloads);
		expect(g.values).toEqual(['5400', '3600', '900', '2700', '4500', '1800']);
		// "Minimal" style hides the bar labels (front-end stylesheet, applied in the canvas).
		const labelDisplay = await chart.locator('text.acme-chart__label').first().evaluate((el) => getComputedStyle(el).display);
		expect(labelDisplay).toBe('none');
		const valueWeight = await chart.locator('text.acme-chart__value').first().evaluate((el) => getComputedStyle(el).fontWeight);
		expect(valueWeight).toBe('600');
	});

	test('charts in columns and in synced patterns are drawn', async ({ page }) => {
		await openEditor(page, postId('budget-overview', 'page'));
		await assertBlocksValid(page);
		let chart = chartIn(page);
		await waitForBars(chart, 3);
		assertChart(await geometry(chart), SERIES.budget);

		await openEditor(page, postId('revenue-chart', 'wp_block'));
		await assertBlocksValid(page);
		chart = chartIn(page);
		await waitForBars(chart, 4);
		assertChart(await geometry(chart), SERIES.revenue);
	});

	test('chart and legend styles apply inside the editor canvas', async ({ page }) => {
		await openEditor(page, postId('quarterly-visitors'));
		const chart = chartIn(page);
		await waitForBars(chart, 4);
		const legend = legendIn(page);
		await expect(legend.locator('.acme-legend__item')).toHaveCount(4);
		const styles = await legend.evaluate((el) => {
			const list = el.matches('.acme-legend') ? el : el.querySelector('.acme-legend');
			const swatch = list.querySelector('.acme-legend__swatch');
			const item = list.querySelector('.acme-legend__item');
			return {
				display: getComputedStyle(list).display,
				listStyle: getComputedStyle(list).listStyleType,
				swatchWidth: getComputedStyle(swatch).width,
				swatchHeight: getComputedStyle(swatch).height,
				itemBorder: getComputedStyle(item).borderTopStyle,
			};
		});
		expect(styles).toEqual({ display: 'flex', listStyle: 'none', swatchWidth: '12px', swatchHeight: '12px', itemBorder: 'none' });
		const title = await chart.locator('.acme-chart__title').evaluate((el) => ({
			align: getComputedStyle(el).textAlign,
			weight: getComputedStyle(el).fontWeight,
		}));
		expect(title).toEqual({ align: 'center', weight: '600' });
		const label = await chart.locator('text.acme-chart__label').first().evaluate((el) => getComputedStyle(el).fontSize);
		expect(label).toBe('12px');
	});

	test('legend items toggle their bar in the editor', async ({ page }) => {
		await openEditor(page, postId('quarterly-visitors'));
		const chart = chartIn(page);
		await waitForBars(chart, 4);
		const items = legendIn(page).locator('.acme-legend__item');
		await expect(items).toHaveCount(4);
		expect((await items.allTextContents()).map((t) => t.trim())).toEqual(['Q1', 'Q2', 'Q3', 'Q4']);
		const swatches = await legendIn(page).locator('.acme-legend__swatch').evaluateAll((els) => els.map((e) => getComputedStyle(e).backgroundColor));
		expect(swatches).toEqual(['rgb(11, 61, 145)', 'rgb(252, 61, 33)', 'rgb(56, 88, 233)', 'rgb(226, 111, 86)']);

		await items.nth(1).click();
		await expect(items.nth(1)).toHaveAttribute('aria-pressed', 'false');
		await expect(chart.locator('rect.acme-chart__bar[data-index="1"]')).toHaveClass(/\bis-hidden\b/);
		let g = await geometry(chart);
		expect(g.bars.map((b) => b.hidden)).toEqual([false, true, false, false]);
		expect(g.bars[1].opacity).toBe('0.15');
		expect(g.bars[0].opacity).toBe('1');
		const deco = await items.nth(1).evaluate((el) => getComputedStyle(el).textDecorationLine);
		expect(deco).toContain('line-through');

		await items.nth(3).click();
		await expect(items.nth(3)).toHaveAttribute('aria-pressed', 'false');
		await items.nth(1).click();
		await expect(items.nth(1)).toHaveAttribute('aria-pressed', 'true');
		await expect(chart.locator('rect.acme-chart__bar[data-index="1"]')).not.toHaveClass(/\bis-hidden\b/);
		g = await geometry(chart);
		expect(g.bars.map((b) => b.hidden)).toEqual([false, false, false, true]);
		// Toggling is a preview only: the post is not modified.
		expect(await page.evaluate(() => wp.data.select('core/editor').isEditedPostDirty())).toBe(false);
	});

	test('a legend in another column toggles its chart', async ({ page }) => {
		await openEditor(page, postId('budget-overview', 'page'));
		const chart = chartIn(page);
		await waitForBars(chart, 3);
		const items = legendIn(page).locator('.acme-legend__item');
		await expect(items).toHaveCount(3);
		const direction = await legendIn(page).evaluate((el) => {
			const list = el.matches('.acme-legend') ? el : el.querySelector('.acme-legend');
			return getComputedStyle(list).flexDirection;
		});
		expect(direction).toBe('column');
		await items.nth(2).click();
		await expect(chart.locator('rect.acme-chart__bar[data-index="2"]')).toHaveClass(/\bis-hidden\b/);
		await expect(items.nth(2)).toHaveAttribute('aria-pressed', 'false');
		expect((await geometry(chart)).bars.map((b) => b.hidden)).toEqual([false, false, true]);
	});

	test('the preview follows data changes and canvas resizes', async ({ page }) => {
		await openEditor(page, postId('quarterly-visitors'));
		const chart = chartIn(page);
		await waitForBars(chart, 4);
		const before = await geometry(chart);

		// Editing data or height redraws the preview.
		await page.evaluate(() => {
			const block = wp.data.select('core/block-editor').getBlocks().find((b) => b.name === 'acme/chart');
			wp.data.dispatch('core/block-editor').updateBlockAttributes(block.clientId, {
				height: 300,
				series: [...block.attributes.series, { label: 'Q5', value: 60 }],
			});
		});
		await waitForBars(chart, 5);
		await expect.poll(async () => (await geometry(chart)).svgHeight).toBe(300);
		assertChart(await geometry(chart), {
			series: [...SERIES.visitors.series, { label: 'Q5', value: 60 }],
			height: 300,
			colors: PALETTE.slice(0, 5),
		});

		// Tablet/mobile preview: the canvas gets narrower, the chart is redrawn to fit.
		await page.evaluate(() => wp.data.dispatch('core/editor').setDeviceType('Mobile'));
		await expect
			.poll(async () => {
				const g = await geometry(chartIn(page));
				return g.canvasWidth > 0 && g.canvasWidth < before.canvasWidth - 50 && Math.abs(g.svgWidth - g.canvasWidth) <= 1 && g.bars.length === 5;
			}, { timeout: 20_000 })
			.toBe(true);
		await page.evaluate(() => wp.data.dispatch('core/editor').setDeviceType('Desktop'));
		await expect
			.poll(async () => {
				const g = await geometry(chartIn(page));
				return Math.abs(g.canvasWidth - before.canvasWidth) <= 1 && Math.abs(g.svgWidth - g.canvasWidth) <= 1;
			}, { timeout: 20_000 })
			.toBe(true);
	});

	test('a new chart: placeholder, drawing, save, reload and front end', async ({ page }) => {
		const con = watchConsole(page);
		await newPost(page);
		const clientId = await page.evaluate(() => {
			wp.data.dispatch('core/editor').editPost({ title: 'New chart', status: 'publish' });
			const block = wp.blocks.createBlock('acme/chart', { anchor: 'fresh' });
			wp.data.dispatch('core/block-editor').insertBlocks([block, wp.blocks.createBlock('acme/chart-legend', { chartId: 'fresh' })]);
			return block.clientId;
		});
		// Empty chart: editor placeholder, styled in the canvas.
		const placeholder = chartIn(page).locator('.acme-chart-placeholder');
		await expect(placeholder).toBeVisible();
		const border = await placeholder.evaluate((el) => getComputedStyle(el).borderTopStyle);
		expect(border).toBe('dashed');

		const series = [{ label: 'Apples', value: 30 }, { label: 'Pears', value: 10, color: '#123456' }, { label: 'Plums', value: 20 }];
		await page.evaluate(({ id, series }) => wp.data.dispatch('core/block-editor').updateBlockAttributes(id, { series, title: 'Fruit' }), { id: clientId, series });
		const chart = chartIn(page);
		await waitForBars(chart, 3);
		const expected = { series, height: 240, colors: [PALETTE[0], '#123456', PALETTE[2]] };
		assertChart(await geometry(chart), expected);
		await expect(legendIn(page).locator('.acme-legend__item')).toHaveCount(3);
		await legendIn(page).locator('.acme-legend__item').first().click();
		await expect(chart.locator('rect.acme-chart__bar[data-index="0"]')).toHaveClass(/\bis-hidden\b/);

		const saved = await savePost(page);
		const id = await page.evaluate(() => wp.data.select('core/editor').getCurrentPostId());
		expect(saved).toContain('wp-block-acme-chart');
		expect(saved).not.toContain('is-hidden');

		await openEditor(page, id);
		await assertBlocksValid(page);
		await waitForBars(chartIn(page), 3);
		assertChart(await geometry(chartIn(page)), expected);

		await page.goto(`/?p=${id}`);
		const front = page.locator('.acme-chart').first();
		await waitForBars(front, 3);
		assertChart(await geometry(front), expected);
		await expect(page.locator('.acme-legend__item')).toHaveCount(3);
		expect(con.errors).toEqual([]);
		expect(con.warnings).toEqual([]);
	});

	for (const [slug, type] of [['quarterly-visitors', 'post'], ['legacy-chart', 'post'], ['budget-overview', 'page'], ['revenue-chart', 'wp_block']]) {
		test(`no console errors or warnings about the blocks while editing "${slug}"`, async ({ page }) => {
			const con = watchConsole(page);
			await openEditor(page, postId(slug, type));
			await expect(chartIn(page).locator('rect.acme-chart__bar').first()).toBeAttached({ timeout: 30_000 });
			const items = legendIn(page).locator('.acme-legend__item');
			await expect(items.first()).toBeVisible();
			await items.first().click();
			await expect(items.first()).toHaveAttribute('aria-pressed', 'false');
			expect(con.errors).toEqual([]);
			expect(con.warnings).toEqual([]);
		});
	}

	for (const [slug, type] of [['quarterly-visitors', 'post'], ['wide-chart', 'post'], ['budget-overview', 'page'], ['revenue-chart', 'wp_block']]) {
		test(`unchanged "${slug}" serializes to exactly the stored markup`, async ({ page }) => {
			const id = postId(slug, type);
			await openEditor(page, id);
			await assertBlocksValid(page);
			const edited = await serializeBlocks(page);
			expect(edited.trim(), `${slug} would change when re-saved`).toBe(postContent(id).trim());
		});
	}

	test('the data editor in the block settings keeps its styles', async ({ page }) => {
		await openEditor(page, postId('quarterly-visitors'));
		await page.evaluate(() => {
			const block = wp.data.select('core/block-editor').getBlocks().find((b) => b.name === 'acme/chart');
			wp.data.dispatch('core/block-editor').selectBlock(block.clientId);
			wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-post/block');
		});
		const grid = page.locator('.acme-charts-series');
		await expect(grid).toBeVisible();
		expect(await grid.evaluate((el) => getComputedStyle(el).display)).toBe('grid');
		await expect(page.getByRole('textbox', { name: 'Label of bar 2' })).toHaveValue('Q2');
	});
});
