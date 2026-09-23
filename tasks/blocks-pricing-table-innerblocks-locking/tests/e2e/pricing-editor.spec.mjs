// Block editor behaviour of the pricing table / pricing plan blocks (hidden E2E tests).
import { test, expect } from '@playwright/test';
import {
	login,
	wp,
	wpEval,
	openEditor as baseOpenEditor,
	newPost as baseNewPost,
	assertBlocksValid,
	savePost,
	trackErrors,
} from '../wpsb/e2e/helpers.mjs';

/** The welcome guide can still pop up for fresh users; close it. */
async function dismissWelcomeGuide(page) {
	await page.evaluate(() => {
		const prefs = window.wp.data.dispatch('core/preferences');
		prefs.set('core/edit-post', 'welcomeGuide', false);
		prefs.set('core', 'welcomeGuide', false);
	});
	const dialog = page.getByRole('dialog', { name: /welcome/i });
	for (let i = 0; i < 10 && !(await dialog.count()); i++) await page.waitForTimeout(100);
	if (await dialog.count()) {
		await dialog.getByRole('button', { name: 'Close' }).first().click();
		await expect(dialog).toHaveCount(0);
	}
}
async function openEditor(page, id) {
	await baseOpenEditor(page, id);
	await dismissWelcomeGuide(page);
}
async function newPost(page, type) {
	await baseNewPost(page, type);
	await dismissWelcomeGuide(page);
}


const postId = (slug, type = 'page') =>
	Number(wp(['post', 'list', `--post_type=${type}`, `--name=${slug}`, '--field=ID', '--post_status=any']));

/** Block tree with rich-text values flattened to HTML strings. */
async function tree(page) {
	return page.evaluate(() => {
		const flat = (v) => (v && typeof v === 'object' && typeof v.toHTMLString === 'function' ? v.toHTMLString() : v);
		const strip = (blocks) =>
			blocks.map((b) => ({
				clientId: b.clientId,
				name: b.name,
				attributes: Object.fromEntries(Object.entries(b.attributes).map(([k, v]) => [k, flat(v)])),
				innerBlocks: strip(b.innerBlocks || []),
			}));
		return strip(window.wp.data.select('core/block-editor').getBlocks());
	});
}

function findAll(blocks, name, out = []) {
	for (const b of blocks) {
		if (b.name === name) out.push(b);
		findAll(b.innerBlocks, name, out);
	}
	return out;
}

const text = (html) => String(html ?? '').replace(/<[^>]+>/g, '').replace(/&amp;/g, '&').replace(/&nbsp;| /g, ' ').trim();
const amount = (s) => Number(String(s ?? '').trim().replace(/'/g, '').replace(/,(\d{1,2})$/, '.$1'));

/** Describe a table (from the editor tree) in a comparable way. */
function describeTable(table) {
	return {
		currency: table.attributes.currency,
		plans: table.innerBlocks.map((p) => {
			expect(p.name, 'a pricing table may only contain pricing plans').toBe('acme/pricing-plan');
			const lists = findAll(p.innerBlocks, 'core/list');
			expect(lists.length, 'each plan has exactly one feature list').toBe(1);
			return {
				name: text(p.attributes.name),
				price: amount(p.attributes.price),
				period: text(p.attributes.period),
				featured: !!p.attributes.featured,
				buttonText: text(p.attributes.buttonText),
				buttonUrl: p.attributes.buttonUrl || '',
				features: lists[0].innerBlocks.map((li) => text(li.attributes.content)),
			};
		}),
	};
}

const EXPECTED = {
	'hosting-plans': [
		{
			currency: 'USD',
			plans: [
				{ name: 'Starter', price: 9, period: '/month', featured: false, buttonText: 'Start now', buttonUrl: 'https://example.com/signup?plan=starter', features: ['1 website', '10 GB storage', 'Email support'] },
				{ name: 'Business', price: 29.5, period: '/month', featured: true, buttonText: 'Start free trial', buttonUrl: 'https://example.com/signup?plan=business', features: ['10 websites', '100 GB storage', 'Phone & email support'] },
				{ name: 'Agency Plus', price: 1200, period: '/year', featured: false, buttonText: 'Contact sales', buttonUrl: 'https://example.com/contact', features: ['Unlimited websites', 'Dedicated manager'] },
			],
		},
	],
	'swiss-offers': [
		{
			currency: 'CHF',
			plans: [
				{ name: 'Verein', price: 49, period: '/Monat', featured: true, buttonText: 'Bestellen', buttonUrl: 'https://example.ch/verein', features: ['Mitgliederverwaltung', 'Newsletter'] },
				{ name: 'Gemeinde', price: 1290.5, period: '/Jahr', featured: false, buttonText: 'Offerte anfragen', buttonUrl: '', features: ['Alles aus Verein', 'Einwohnerportal', 'Support vor Ort'] },
			],
		},
	],
	'legacy-v1-table': [
		{
			currency: 'USD',
			plans: [
				{ name: 'Hobby', price: 0, period: '/month', featured: false, buttonText: 'Sign up', buttonUrl: 'https://example.com/hobby', features: ['1 project', 'Community forum'] },
				{ name: 'Pro', price: 19, period: '/month', featured: false, buttonText: 'Sign up', buttonUrl: 'https://example.com/pro', features: ['10 projects', 'Priority support', 'Analytics'] },
				{ name: 'Team & Co', price: 49, period: '/month', featured: true, buttonText: 'Sign up', buttonUrl: 'https://example.com/team', features: ['Unlimited projects', 'SLA'] },
			],
		},
	],
	'two-tables': [
		{
			currency: 'EUR',
			plans: [
				{ name: 'Basis', price: 9.9, period: '/Monat', featured: false, buttonText: 'Jetzt buchen', buttonUrl: 'https://example.de/basis', features: ['5 Nutzer'] },
				{ name: 'Team', price: 19, period: '/Monat', featured: false, buttonText: 'Jetzt buchen', buttonUrl: 'https://example.de/team', features: ['25 Nutzer', 'SSO'] },
				{ name: 'Konzern', price: 2500, period: '/Jahr', featured: false, buttonText: 'Kontakt', buttonUrl: 'https://example.de/kontakt', features: ['Unbegrenzte Nutzer'] },
			],
		},
		{
			currency: 'GBP',
			plans: [{ name: 'Annual pass', price: 99, period: '/year', featured: true, buttonText: 'Buy pass', buttonUrl: 'https://example.co.uk/pass', features: ['All workshops', 'Recordings'] }],
		},
	],
};
const TYPES = { 'legacy-v1-table': 'post' };

/** Change something unrelated (like an editor fixing a typo) so that saving writes the new structure. */
async function touchFirstParagraph(page) {
	await page.evaluate(() => {
		const { select, dispatch } = window.wp.data;
		const find = (bs) => bs.flatMap((b) => [b, ...find(b.innerBlocks)]);
		const p = find(select('core/block-editor').getBlocks()).find((b) => b.name === 'core/paragraph');
		dispatch('core/block-editor').updateBlockAttributes(p.clientId, { content: String(p.attributes.content) + ' ' });
	});
}

/** Parse the front end of a URL in the page context: tables, plans and JSON-LD. */
async function frontEnd(page, url) {
	const res = await page.request.get(url, { timeout: 120_000 });
	expect(res.status()).toBe(200);
	const html = await res.text();
	const data = await page.evaluate((h) => {
		const doc = new DOMParser().parseFromString(h, 'text/html');
		const tables = [...doc.querySelectorAll('.wp-block-acme-pricing-table')].map((t) => ({
			classes: [...t.classList],
			nested: !!t.parentElement.closest('.wp-block-acme-pricing-table'),
			plans: [...t.querySelectorAll('.acme-pricing__plan')].map((p) => ({
				classes: [...p.classList],
				tag: p.tagName.toLowerCase(),
				name: p.querySelector('.acme-pricing__name')?.textContent.trim() ?? null,
				nameHtml: p.querySelector('.acme-pricing__name')?.innerHTML.trim() ?? null,
				nameTag: p.querySelector('.acme-pricing__name')?.tagName.toLowerCase() ?? null,
				amount: p.querySelector('.acme-pricing__price .acme-pricing__amount')?.textContent.replace(/\u00a0/g, ' ').replace(/[\u2018\u2019]/g, "'").trim() ?? null,
				period: p.querySelector('.acme-pricing__price .acme-pricing__period')?.textContent.trim() ?? null,
				features: [...p.querySelectorAll('.acme-pricing__features li')].map((li) => li.textContent.trim()),
				button: p.querySelector('a.acme-pricing__button') ? { href: p.querySelector('a.acme-pricing__button').getAttribute('href'), text: p.querySelector('a.acme-pricing__button').textContent.trim() } : null,
			})),
		}));
		const ld = [...doc.querySelectorAll('script[type="application/ld+json"]')]
			.map((s) => { try { return JSON.parse(s.textContent); } catch { return null; } })
			.filter((d) => d && d['@type'] === 'Product');
		return { tables, ld };
	}, html);
	return { html, ...data };
}

function assertNewPlanMarkup(plan, expected) {
	expect(plan.tag).toBe('div');
	expect(plan.classes).toEqual(expect.arrayContaining(['wp-block-acme-pricing-plan', 'acme-pricing__plan']));
	if (expected.featured) expect(plan.classes).toContain('is-featured');
	else expect(plan.classes).not.toContain('is-featured');
	expect(plan.nameTag).toBe('h3');
	expect(plan.name).toBe(expected.name);
	expect(plan.amount).toBe(expected.amount);
	if (expected.period) expect(plan.period).toBe(expected.period);
	else expect(plan.period).toBeNull();
	if (expected.features) expect(plan.features).toEqual(expected.features);
	if (expected.button) expect(plan.button).toEqual(expected.button);
	if (expected.button === null) expect(plan.button).toBeNull();
}

test.describe('Existing pricing tables in the editor', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	for (const slug of Object.keys(EXPECTED)) {
		test(`tables in "${slug}" open without block errors as table + plan blocks`, async ({ page }) => {
			const errors = trackErrors(page);
			const id = postId(slug, TYPES[slug] || 'page');
			expect(id).toBeGreaterThan(0);
			await openEditor(page, id);
			await assertBlocksValid(page);
			const tables = findAll(await tree(page), 'acme/pricing-table');
			expect(tables.map(describeTable)).toEqual(EXPECTED[slug]);
			expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
		});
	}

	test('the formatting of the old plan name is kept', async ({ page }) => {
		await openEditor(page, postId('hosting-plans'));
		const [table] = findAll(await tree(page), 'acme/pricing-table');
		expect(String(table.innerBlocks[2].attributes.name)).toContain('<em>Plus</em>');
	});
});

test.describe('Re-saved tables on the front end', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('a re-saved 1.x page uses the new markup, keeps formatting and stays valid', async ({ page }) => {
		const id = postId('two-tables');
		await openEditor(page, id);
		await assertBlocksValid(page);
		await touchFirstParagraph(page);
		const saved = await savePost(page);
		expect(saved).toContain('wp:acme/pricing-plan');
		expect(saved).not.toContain('Retired plan');

		const fe = await frontEnd(page, '/two-tables/');
		expect(fe.tables.length).toBe(2);
		const [eur, gbp] = fe.tables;
		expect(eur.nested || gbp.nested).toBe(false);
		expect(eur.classes).toEqual(expect.arrayContaining(['acme-pricing', 'acme-pricing--cols-3', 'acme-pricing--currency-eur', 'alignwide']));
		expect(eur.plans.length).toBe(3);
		assertNewPlanMarkup(eur.plans[0], { name: 'Basis', amount: '9,90 €', period: '/Monat', featured: false, features: ['5 Nutzer'], button: { href: 'https://example.de/basis', text: 'Jetzt buchen' } });
		assertNewPlanMarkup(eur.plans[1], { name: 'Team', amount: '19 €', period: '/Monat', featured: false, features: ['25 Nutzer', 'SSO'] });
		assertNewPlanMarkup(eur.plans[2], { name: 'Konzern', amount: '2.500 €', period: '/Jahr', featured: false });
		expect(gbp.classes).toEqual(expect.arrayContaining(['acme-pricing', 'acme-pricing--cols-1', 'acme-pricing--currency-gbp']));
		assertNewPlanMarkup(gbp.plans[0], { name: 'Annual pass', amount: '£99', period: '/year', featured: true, features: ['All workshops', 'Recordings'], button: { href: 'https://example.co.uk/pass', text: 'Buy pass' } });
		expect(fe.html).not.toContain('Retired plan');

		expect(fe.ld.length).toBe(1);
		expect(fe.ld[0].offers).toEqual([
			{ '@type': 'Offer', name: 'Basis', price: '9.90', priceCurrency: 'EUR' },
			{ '@type': 'Offer', name: 'Team', price: '19.00', priceCurrency: 'EUR' },
			{ '@type': 'Offer', name: 'Konzern', price: '2500.00', priceCurrency: 'EUR' },
			{ '@type': 'Offer', name: 'Annual pass', price: '99.00', priceCurrency: 'GBP' },
		]);

		await page.reload();
		await openEditor(page, id);
		await assertBlocksValid(page);
		expect(findAll(await tree(page), 'acme/pricing-table').map(describeTable)).toEqual(EXPECTED['two-tables']);
	});

	test('re-saved CHF table and 1.0-format table render with the same prices as before', async ({ page }) => {
		for (const [slug, type] of [['swiss-offers', 'page'], ['legacy-v1-table', 'post']]) {
			const id = postId(slug, type);
			await openEditor(page, id);
			await assertBlocksValid(page);
			await touchFirstParagraph(page);
			await savePost(page);
		}
		let fe = await frontEnd(page, '/swiss-offers/');
		expect(fe.tables.length).toBe(1);
		expect(fe.tables[0].classes).toEqual(expect.arrayContaining(['acme-pricing--cols-2', 'acme-pricing--currency-chf']));
		assertNewPlanMarkup(fe.tables[0].plans[0], { name: 'Verein', amount: 'CHF 49.–', period: '/Monat', featured: true, button: { href: 'https://example.ch/verein', text: 'Bestellen' } });
		assertNewPlanMarkup(fe.tables[0].plans[1], { name: 'Gemeinde', amount: "CHF 1'290.50", period: '/Jahr', featured: false, features: ['Alles aus Verein', 'Einwohnerportal', 'Support vor Ort'], button: null });

		fe = await frontEnd(page, '/legacy-v1-table/');
		expect(fe.tables.length).toBe(1);
		expect(fe.tables[0].classes).toEqual(expect.arrayContaining(['acme-pricing--cols-3', 'acme-pricing--currency-usd']));
		assertNewPlanMarkup(fe.tables[0].plans[0], { name: 'Hobby', amount: '$0', period: '/month', featured: false });
		assertNewPlanMarkup(fe.tables[0].plans[2], { name: 'Team & Co', amount: '$49', period: '/month', featured: true, features: ['Unlimited projects', 'SLA'], button: { href: 'https://example.com/team', text: 'Sign up' } });
		expect(fe.ld[0].offers.map((o) => [o.name, o.price, o.priceCurrency])).toEqual([
			['Hobby', '0.00', 'USD'],
			['Pro', '19.00', 'USD'],
			['Team & Co', '49.00', 'USD'],
		]);
	});
});

/** Insert a new pricing table and wait for its plans. */
async function insertTable(page) {
	const clientId = await page.evaluate(() => {
		const block = window.wp.blocks.createBlock('acme/pricing-table');
		window.wp.data.dispatch('core/block-editor').insertBlocks(block);
		return block.clientId;
	});
	await expect.poll(() => page.evaluate((id) => window.wp.data.select('core/block-editor').getBlockCount(id), clientId)).toBe(3);
	return clientId;
}

const planIds = (page, tableId) => page.evaluate((id) => window.wp.data.select('core/block-editor').getBlockOrder(id), tableId);
const planAttr = (page, tableId, key) =>
	page.evaluate(([id, k]) => window.wp.data.select('core/block-editor').getBlocks(id).map((b) => { const v = b.attributes[k]; return v && typeof v === 'object' && v.toHTMLString ? v.toHTMLString() : v; }), [tableId, key]);

async function openSidebarFor(page, clientId) {
	await page.evaluate((id) => {
		window.wp.data.dispatch('core/block-editor').selectBlock(id);
		window.wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-post/block');
	}, clientId);
	await page.waitForTimeout(300);
}

/** Set the "Number of plans" control like a user would (number field, slider or select). */
async function setPlanCount(page, n) {
	const spin = page.getByRole('spinbutton', { name: 'Number of plans' });
	if (await spin.count()) {
		await spin.first().fill(String(n));
		await spin.first().press('Enter');
		return;
	}
	const select = page.getByRole('combobox', { name: 'Number of plans' });
	if (await select.count()) {
		await select.first().selectOption(String(n));
		return;
	}
	const slider = page.getByRole('slider', { name: 'Number of plans' });
	await expect(slider.first()).toBeVisible();
	await slider.first().fill(String(n));
}

async function readPlanCount(page) {
	for (const role of ['spinbutton', 'slider', 'combobox']) {
		const el = page.getByRole(role, { name: 'Number of plans' });
		if (await el.count()) return Number(await el.first().inputValue());
	}
	throw new Error('"Number of plans" control not found');
}

/** Close the welcome guide if it shows up anyway (new users). */
async function dismissGuide(page) {
	await page.evaluate(() => {
		const prefs = window.wp.data.dispatch('core/preferences');
		prefs.set('core/edit-post', 'welcomeGuide', false);
		prefs.set('core', 'welcomeGuide', false);
	});
	const dialog = page.getByRole('dialog', { name: /welcome/i });
	if (await dialog.count()) {
		await dialog.getByRole('button', { name: 'Close' }).click();
		await expect(dialog).toHaveCount(0);
	}
}

/** Is there an enabled control with this accessible name in the editor UI? */
async function hasEnabledControl(page, name) {
	const loc = page.getByLabel(name, { exact: true });
	const n = await loc.count();
	for (let i = 0; i < n; i++) {
		const el = loc.nth(i);
		if ((await el.isVisible()) && (await el.isEnabled())) return true;
	}
	return false;
}

test.describe('New pricing tables', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('a new table has three plans in the default currency and only accepts plans', async ({ page }) => {
		wpEval(`$o = get_option( 'acme_pricing_options' ); $o['default_currency'] = 'EUR'; update_option( 'acme_pricing_options', $o );`);
		try {
			await newPost(page);
			const tableId = await insertTable(page);
			const featured = (await planAttr(page, tableId, 'featured')).map((f) => !!f);
			expect(featured).toEqual([false, false, false]);
			expect(await page.evaluate((id) => window.wp.data.select('core/block-editor').getBlockAttributes(id).currency, tableId)).toBe('EUR');
			const groupId = await page.evaluate(() => {
				const g = window.wp.blocks.createBlock('core/group');
				window.wp.data.dispatch('core/block-editor').insertBlocks(g);
				return g.clientId;
			});
			const [firstPlan] = await planIds(page, tableId);
			const can = await page.evaluate(([t, g, p]) => {
				const s = window.wp.data.select('core/block-editor');
				return {
					planInTable: s.canInsertBlockType('acme/pricing-plan', t),
					planAtRoot: s.canInsertBlockType('acme/pricing-plan'),
					planInGroup: s.canInsertBlockType('acme/pricing-plan', g),
					paragraphInTable: s.canInsertBlockType('core/paragraph', t),
					tableInTable: s.canInsertBlockType('acme/pricing-table', t),
					paragraphInPlan: s.canInsertBlockType('core/paragraph', p),
					tableAtRoot: s.canInsertBlockType('acme/pricing-table'),
				};
			}, [tableId, groupId, firstPlan]);
			expect(can).toEqual({ planInTable: true, planAtRoot: false, planInGroup: false, paragraphInTable: false, tableInTable: false, paragraphInPlan: false, tableAtRoot: true });
			await assertBlocksValid(page);
		} finally {
			wpEval(`$o = get_option( 'acme_pricing_options' ); $o['default_currency'] = 'USD'; update_option( 'acme_pricing_options', $o );`);
		}
	});

	test('"Number of plans" adds and removes plans at the end and follows the actual plans', async ({ page }) => {
		await newPost(page);
		const tableId = await insertTable(page);
		const [first] = await planIds(page, tableId);
		await page.evaluate((id) => window.wp.data.dispatch('core/block-editor').updateBlockAttributes(id, { name: 'Keep me', price: '12' }), first);
		await openSidebarFor(page, tableId);
		expect(await readPlanCount(page)).toBe(3);

		await setPlanCount(page, 4);
		await expect.poll(() => planIds(page, tableId).then((ids) => ids.length)).toBe(4);
		let ids = await planIds(page, tableId);
		expect(ids[0]).toBe(first);
		expect((await planAttr(page, tableId, 'name'))[0]).toBe('Keep me');
		expect(await page.evaluate((id) => window.wp.data.select('core/block-editor').getBlocks(id).map((b) => b.name), tableId)).toEqual(Array(4).fill('acme/pricing-plan'));

		await setPlanCount(page, 2);
		await expect.poll(() => planIds(page, tableId).then((x) => x.length)).toBe(2);
		ids = await planIds(page, tableId);
		expect(ids[0]).toBe(first);
		expect((await planAttr(page, tableId, 'name'))[0]).toBe('Keep me');
		expect((await planAttr(page, tableId, 'price'))[0]).toBe('12');

		// Removing a plan directly: the control follows.
		await page.evaluate((id) => window.wp.data.dispatch('core/block-editor').removeBlock(id, false), ids[1]);
		await openSidebarFor(page, tableId);
		await expect.poll(() => readPlanCount(page)).toBe(1);

		await page.evaluate(() => window.wp.data.dispatch('core/editor').editPost({ title: 'Count sync', status: 'publish' }));
		await savePost(page);
		const id = await page.evaluate(() => window.wp.data.select('core/editor').getCurrentPostId());
		const fe = await frontEnd(page, `/?p=${id}`);
		expect(fe.tables.length).toBe(1);
		expect(fe.tables[0].classes).toContain('acme-pricing--cols-1');
		expect(fe.tables[0].plans.length).toBe(1);
		expect(fe.tables[0].plans[0].name).toBe('Keep me');
		expect(fe.tables[0].plans[0].amount).toBe('$12');

		await openEditor(page, id);
		await assertBlocksValid(page);
	});

	test('only one plan per table is featured', async ({ page }) => {
		await newPost(page);
		const tableId = await insertTable(page);
		const ids = await planIds(page, tableId);
		const featured = async () => (await planAttr(page, tableId, 'featured')).map((f) => !!f);

		await openSidebarFor(page, ids[1]);
		await page.getByLabel('Featured plan', { exact: true }).click();
		await expect.poll(featured).toEqual([false, true, false]);

		await openSidebarFor(page, ids[2]);
		await page.getByLabel('Featured plan', { exact: true }).click();
		await expect.poll(featured).toEqual([false, false, true]);

		// Duplicating the featured plan: the first featured plan keeps the flag.
		await page.evaluate((id) => window.wp.data.dispatch('core/block-editor').duplicateBlocks([id]), ids[2]);
		await expect.poll(() => planIds(page, tableId).then((x) => x.length)).toBe(4);
		await expect.poll(featured).toEqual([false, false, true, false]);

		// Pasting/inserting a featured plan in front of it: now that one is first.
		await page.evaluate((t) => {
			const { createBlock } = window.wp.blocks;
			const plan = createBlock('acme/pricing-plan', { name: 'Pasted', price: '5', featured: true }, [
				createBlock('core/list', {}, [createBlock('core/list-item', { content: 'x' })]),
			]);
			window.wp.data.dispatch('core/block-editor').insertBlocks(plan, 0, t);
		}, tableId);
		await expect.poll(() => planIds(page, tableId).then((x) => x.length)).toBe(5);
		await expect.poll(featured).toEqual([true, false, false, false, false]);

		await page.evaluate(() => window.wp.data.dispatch('core/editor').editPost({ title: 'Featured', status: 'publish' }));
		await savePost(page);
		const id = await page.evaluate(() => window.wp.data.select('core/editor').getCurrentPostId());
		const fe = await frontEnd(page, `/?p=${id}`);
		expect(fe.tables[0].plans.map((p) => p.classes.includes('is-featured'))).toEqual([true, false, false, false, false]);
		expect(fe.tables[0].plans[0].name).toBe('Pasted');
	});

	test('currency changes re-format all plans; new tables round-trip and appear in the structured data', async ({ page }) => {
		const errors = trackErrors(page);
		await newPost(page);
		const tableId = await insertTable(page);
		const ids = await planIds(page, tableId);
		await page.evaluate(([a, b, c]) => {
			const { updateBlockAttributes } = window.wp.data.dispatch('core/block-editor');
			updateBlockAttributes(a, { name: 'Small', price: '19', period: '/month', buttonText: 'Buy small', buttonUrl: 'https://example.com/small' });
			updateBlockAttributes(b, { name: '<strong>Large</strong>', price: '1290.5', period: '', buttonText: 'Buy large', buttonUrl: '' });
			updateBlockAttributes(c, { name: 'Huge', price: '2500', period: '/year', buttonText: 'Call', buttonUrl: 'https://example.com/call' });
		}, ids);
		// Features: type into the first plan's list like an editor.
		await page.evaluate((planId) => {
			const s = window.wp.data.select('core/block-editor');
			const find = (bs) => bs.flatMap((b) => [b, ...find(b.innerBlocks)]);
			const items = find(s.getBlocks(planId)).filter((b) => b.name === 'core/list-item');
			const list = find(s.getBlocks(planId)).find((b) => b.name === 'core/list');
			const { createBlock } = window.wp.blocks;
			window.wp.data.dispatch('core/block-editor').replaceInnerBlocks(list.clientId, [
				createBlock('core/list-item', { content: 'One user' }),
				createBlock('core/list-item', { content: '<em>Fast</em> support' }),
			]);
			return items.length;
		}, ids[0]);

		await openSidebarFor(page, tableId);
		await page.getByRole('combobox', { name: 'Currency' }).selectOption('CHF');
		await expect.poll(() => page.evaluate((id) => window.wp.data.select('core/block-editor').getBlockAttributes(id).currency, tableId)).toBe('CHF');

		await page.evaluate(() => window.wp.data.dispatch('core/editor').editPost({ title: 'Swiss prices', status: 'publish' }));
		await savePost(page);
		const id = await page.evaluate(() => window.wp.data.select('core/editor').getCurrentPostId());

		const fe = await frontEnd(page, `/?p=${id}`);
		expect(fe.tables.length).toBe(1);
		expect(fe.tables[0].classes).toEqual(expect.arrayContaining(['wp-block-acme-pricing-table', 'acme-pricing', 'acme-pricing--cols-3', 'acme-pricing--currency-chf']));
		const [small, large, huge] = fe.tables[0].plans;
		assertNewPlanMarkup(small, { name: 'Small', amount: 'CHF 19.–', period: '/month', featured: false, features: ['One user', 'Fast support'], button: { href: 'https://example.com/small', text: 'Buy small' } });
		assertNewPlanMarkup(large, { name: 'Large', amount: "CHF 1'290.50", period: null, featured: false, button: null });
		expect(large.nameHtml).toContain('<strong>Large</strong>');
		assertNewPlanMarkup(huge, { name: 'Huge', amount: "CHF 2'500.–", period: '/year', featured: false, button: { href: 'https://example.com/call', text: 'Call' } });
		expect(fe.html).toMatch(/<li>\s*<em>Fast<\/em> support\s*<\/li>/);

		expect(fe.ld.length).toBe(1);
		expect(fe.ld[0].name).toBe('Swiss prices');
		expect(fe.ld[0].offers).toEqual([
			{ '@type': 'Offer', name: 'Small', price: '19.00', priceCurrency: 'CHF' },
			{ '@type': 'Offer', name: 'Large', price: '1290.50', priceCurrency: 'CHF' },
			{ '@type': 'Offer', name: 'Huge', price: '2500.00', priceCurrency: 'CHF' },
		]);

		await openEditor(page, id);
		await assertBlocksValid(page);
		const [table] = findAll(await tree(page), 'acme/pricing-table');
		expect(table.attributes.currency).toBe('CHF');
		expect(table.innerBlocks.map((p) => text(p.attributes.name))).toEqual(['Small', 'Large', 'Huge']);
		expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
	});
});

test.describe('Locked pricing tables', () => {
	const setLock = (on) =>
		wpEval(`$o = get_option( 'acme_pricing_options' ); $o = is_array( $o ) ? $o : array(); $o['lock_tables'] = ${on ? 'true' : 'false'}; update_option( 'acme_pricing_options', $o );`);

	test.afterEach(() => setLock(false));

	async function lockState(page) {
		const [table] = findAll(await tree(page), 'acme/pricing-table');
		const ids = table.innerBlocks.map((p) => p.clientId);
		return page.evaluate(([t, plans]) => {
			const s = window.wp.data.select('core/block-editor');
			return {
				insertPlan: s.canInsertBlockType('acme/pricing-plan', t),
				removePlan: plans.map((p) => s.canRemoveBlock(p)).some(Boolean),
				movePlan: plans.map((p) => s.canMoveBlock(p)).some(Boolean),
			};
		}, [table.clientId, ids]).then((r) => ({ ...r, tableId: table.clientId, planIds: ids }));
	}

	test('editors get content-only editing when the setting is on', async ({ page }) => {
		setLock(true);
		const errors = trackErrors(page);
		await login(page, 'editor1', 'password');
		const id = postId('hosting-plans');
		// Earlier tests opened this page as admin: release the edit lock.
		wpEval(`delete_post_meta( ${id}, '_edit_lock' );`);
		await openEditor(page, id);
		await assertBlocksValid(page);
		const st = await lockState(page);
		expect({ insertPlan: st.insertPlan, removePlan: st.removePlan, movePlan: st.movePlan }).toEqual({ insertPlan: false, removePlan: false, movePlan: false });

		await openSidebarFor(page, st.tableId);
		expect(await hasEnabledControl(page, 'Number of plans'), '"Number of plans" must not be usable').toBe(false);
		expect(await hasEnabledControl(page, 'Currency'), 'currency must not be changeable').toBe(false);
		await openSidebarFor(page, st.planIds[0]);
		expect(await hasEnabledControl(page, 'Featured plan'), 'featured plan must not be changeable').toBe(false);

		// Texts can still be edited: type into the first plan's name in the canvas.
		await dismissGuide(page);
		const canvas = page.frameLocator('iframe[name="editor-canvas"]');
		const name = canvas
			.locator('[data-type="acme/pricing-plan"]')
			.first()
			.locator('[contenteditable="true"]:not(.block-editor-block-list__block)', { hasText: /^\s*Starter\s*$/ })
			.first();
		await name.click();
		await page.keyboard.press('End');
		await page.keyboard.type(' Plus');
		await expect.poll(() => planAttr(page, st.tableId, 'name').then((n) => text(n[0]))).toBe('Starter Plus');

		const saved = await savePost(page);
		expect(saved).toContain('Starter Plus');
		const fe = await frontEnd(page, '/hosting-plans/');
		expect(fe.tables[0].plans.map((p) => p.name)).toEqual(['Starter Plus', 'Business', 'Agency Plus']);
		expect(fe.tables[0].plans[1].classes).toContain('is-featured');
		expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
	});

	test('administrators are never locked', async ({ page }) => {
		setLock(true);
		await login(page);
		await openEditor(page, postId('swiss-offers'));
		await assertBlocksValid(page);
		const st = await lockState(page);
		expect({ insertPlan: st.insertPlan, removePlan: st.removePlan, movePlan: st.movePlan }).toEqual({ insertPlan: true, removePlan: true, movePlan: true });
		await openSidebarFor(page, st.tableId);
		expect(await hasEnabledControl(page, 'Number of plans')).toBe(true);
		await openSidebarFor(page, st.planIds[1]);
		expect(await hasEnabledControl(page, 'Featured plan')).toBe(true);
	});

	test('nobody is locked when the setting is off', async ({ page }) => {
		setLock(false);
		await login(page, 'editor1', 'password');
		wpEval(`delete_post_meta( ${postId('swiss-offers')}, '_edit_lock' );`);
		await openEditor(page, postId('swiss-offers'));
		await assertBlocksValid(page);
		const st = await lockState(page);
		expect({ insertPlan: st.insertPlan, removePlan: st.removePlan, movePlan: st.movePlan }).toEqual({ insertPlan: true, removePlan: true, movePlan: true });
		await openSidebarFor(page, st.tableId);
		expect(await hasEnabledControl(page, 'Number of plans')).toBe(true);
		expect(await hasEnabledControl(page, 'Currency')).toBe(true);
		await openSidebarFor(page, st.planIds[1]);
		expect(await hasEnabledControl(page, 'Featured plan')).toBe(true);
	});
});
