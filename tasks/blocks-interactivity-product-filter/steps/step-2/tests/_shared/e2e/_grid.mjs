// Shared helpers for the product grid E2E tests.
import { expect } from '@playwright/test';

/** Snapshot of every grid's visible state (as a string, so it can run in init scripts). */
export const SNAPSHOT_SRC = `(() => [...document.querySelectorAll('.wp-block-acme-product-grid')].map((g) => {
	const nav = g.querySelector('.acme-grid__pagination');
	const empty = g.querySelector('.acme-grid__empty');
	return {
		pressed: [...g.querySelectorAll('.acme-grid__filter')].map((b) => (b.dataset.category || '') + '=' + b.getAttribute('aria-pressed')),
		active: [...g.querySelectorAll('.acme-grid__filter.is-active')].map((b) => b.dataset.category || ''),
		search: g.querySelector('.acme-grid__search') ? g.querySelector('.acme-grid__search').value : null,
		count: (g.querySelector('.acme-grid__count')?.textContent || '').trim(),
		visible: [...g.querySelectorAll('.acme-grid__item')].filter((li) => !li.hidden && getComputedStyle(li).display !== 'none').map((li) => (li.querySelector('.acme-grid__title')?.textContent || '').trim()),
		empty: !!empty && !empty.hidden && getComputedStyle(empty).display !== 'none',
		page: nav && !nav.hidden ? (g.querySelector('.acme-grid__page')?.textContent || '').trim() : null,
	};
}))()`;

/** Record the grid state as delivered by the server (before any script runs), and a reload marker. */
export async function recordServerState(page) {
	await page.addInitScript((src) => {
		document.addEventListener('readystatechange', () => {
			if (document.readyState === 'interactive' && !window.__wpsbServerState) {
				window.__wpsbServerState = eval(src); // eslint-disable-line no-eval
			}
		});
	}, SNAPSHOT_SRC);
}

export async function snapshot(page) {
	return page.evaluate(SNAPSHOT_SRC);
}

/** Load a page, wait for scripts to settle. */
export async function load(page, url) {
	await page.goto(url);
	await page.waitForLoadState('load');
	await page.waitForTimeout(1500);
	await page.evaluate(() => { window.__wpsbNoReload = true; });
}

/** The state after scripts ran must be exactly the server-rendered state. */
export async function expectNoFlash(page) {
	const server = await page.evaluate(() => window.__wpsbServerState);
	expect(server, 'server state snapshot').toBeTruthy();
	expect(await snapshot(page)).toEqual(server);
	return server;
}

export async function expectNoReload(page) {
	expect(await page.evaluate(() => window.__wpsbNoReload === true), 'the page must not reload').toBe(true);
}

export const grid = (page, i = 0) => page.locator('.wp-block-acme-product-grid').nth(i);
export const filter = (page, i, name) => grid(page, i).locator('.acme-grid__filter', { hasText: new RegExp(`^\\s*${name}\\s*$`) });

export function relevantErrors(errors) {
	return errors.filter((e) => !e.includes('Failed to load resource'));
}
