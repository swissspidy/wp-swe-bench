// Shared helpers for the Acme Charts hidden tests.
import { expect } from '@playwright/test';
import { wp } from '../wpsb/e2e/helpers.mjs';

// Settings → Charts palette, with the site's mu-plugin brand colours in front.
export const PALETTE = ['#0b3d91', '#fc3d21', '#3858e9', '#e26f56', '#1a8f5c', '#dba617'];

// Renderer geometry (see assets/js/chart-renderer.js): label area 20px, top padding 8px.
export const LABEL_AREA = 20;
export const TOP_PADDING = 8;

export const postId = (slug, type = 'post') =>
	Number(wp(['post', 'list', `--post_type=${type}`, `--name=${slug}`, '--field=ID', '--post_status=any']));

export const postContent = (id) => wp(['post', 'get', String(id), '--field=post_content']);

export const canvas = (page) => page.frameLocator('iframe[name="editor-canvas"]');

/** Collect console errors, page errors and warnings that concern the plugin. */
export function watchConsole(page) {
	const out = { errors: [], warnings: [] };
	page.on('pageerror', (e) => out.errors.push(`pageerror: ${e.message}`));
	page.on('console', (m) => {
		const text = m.text();
		if (m.type() === 'error') {
			// The site is offline: external resources (fonts, avatars…) can't load. Everything else counts.
			if (/^Failed to load resource: net::ERR_/.test(text)) return;
			out.errors.push(`console.error: ${text.slice(0, 400)}`);
		}
		if (m.type() === 'warning' && /acme/i.test(text)) out.warnings.push(`console.warn: ${text.slice(0, 400)}`);
	});
	return out;
}

/** Geometry of a rendered chart (editor or front end). `chart` is a Locator of the chart element. */
export async function geometry(chart) {
	return chart.evaluate((el) => {
		const c = el.querySelector('.acme-chart__canvas');
		const svg = c && c.querySelector('svg.acme-chart__svg');
		return {
			canvasWidth: c ? c.clientWidth : null,
			svgWidth: svg ? Number(svg.getAttribute('width')) : null,
			svgHeight: svg ? Number(svg.getAttribute('height')) : null,
			bars: svg
				? [...svg.querySelectorAll('rect.acme-chart__bar')].map((r) => ({
						index: Number(r.getAttribute('data-index')),
						y: Number(r.getAttribute('y')),
						h: Number(r.getAttribute('height')),
						w: Number(r.getAttribute('width')),
						fill: (r.getAttribute('fill') || '').toLowerCase(),
						hidden: r.classList.contains('is-hidden'),
						opacity: getComputedStyle(r).opacity,
				  }))
				: [],
			labels: svg ? [...svg.querySelectorAll('text.acme-chart__label')].map((t) => t.textContent) : [],
			values: svg ? [...svg.querySelectorAll('text.acme-chart__value')].map((t) => t.textContent) : [],
		};
	});
}

/** Wait until a chart has drawn `n` bars. */
export async function waitForBars(chart, n) {
	await expect(chart.locator('svg.acme-chart__svg rect.acme-chart__bar')).toHaveCount(n, { timeout: 30_000 });
}

/**
 * Assert that a chart was drawn like the shared renderer draws it: one bar per point, bar
 * heights proportional to the values, bars standing on the same baseline, the SVG as wide
 * as its canvas, and the expected colours.
 */
export function assertChart(g, { series, height, colors }) {
	expect(g.svgWidth, 'SVG width').toBeGreaterThan(100);
	expect(Math.abs(g.svgWidth - g.canvasWidth), `SVG width ${g.svgWidth} should match the canvas width ${g.canvasWidth}`).toBeLessThanOrEqual(1);
	expect(g.svgHeight).toBe(height);
	expect(g.bars.length).toBe(series.length);
	expect(g.labels).toEqual(series.map((p) => p.label));
	const max = Math.max(...series.map((p) => p.value));
	const plot = height - LABEL_AREA - TOP_PADDING;
	g.bars.forEach((bar, i) => {
		expect(bar.index).toBe(i);
		expect(Math.abs(bar.h - (series[i].value / max) * plot), `height of bar ${i}`).toBeLessThanOrEqual(0.02);
		expect(Math.abs(bar.y + bar.h - (height - LABEL_AREA)), `baseline of bar ${i}`).toBeLessThanOrEqual(0.02);
		expect(bar.w).toBeGreaterThan(0);
		expect(bar.fill, `colour of bar ${i}`).toBe(colors[i].toLowerCase());
	});
}

export const SERIES = {
	visitors: {
		series: [{ label: 'Q1', value: 120 }, { label: 'Q2', value: 180 }, { label: 'Q3', value: 150 }, { label: 'Q4', value: 240 }],
		height: 240,
		colors: PALETTE.slice(0, 4),
	},
	survey: {
		series: [{ label: 'Daily', value: 18 }, { label: 'Weekly', value: 46 }, { label: 'Monthly', value: 23 }, { label: 'Rarely', value: 13 }],
		height: 260,
		colors: ['#e26f56', PALETTE[1], PALETTE[2], PALETTE[3]],
	},
	downloads: {
		series: [
			{ label: 'Windows', value: 5400 }, { label: 'macOS', value: 3600 }, { label: 'Linux', value: 900 },
			{ label: 'iOS', value: 2700 }, { label: 'Android', value: 4500 }, { label: 'Web', value: 1800 },
		],
		height: 320,
		colors: [PALETTE[0], PALETTE[1], PALETTE[2], PALETTE[3], '#1a8f5c', PALETTE[5]],
	},
	budget: {
		series: [{ label: 'R&D', value: 42 }, { label: 'Sales', value: 28 }, { label: 'Support', value: 14 }],
		height: 200,
		colors: [PALETTE[0], '#8a4fb4', PALETTE[2]],
	},
	revenue: {
		series: [{ label: '2022', value: 3.2 }, { label: '2023', value: 4.8 }, { label: '2024', value: 4.1 }, { label: '2025', value: 6.4 }],
		height: 240,
		colors: PALETTE.slice(0, 4),
	},
};
