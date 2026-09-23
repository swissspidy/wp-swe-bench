// Editor + front-end behaviour of the FAQ accordion (hidden E2E tests).
import { test, expect } from '@playwright/test';
import {
	login,
	wp,
	openEditor,
	assertBlocksValid,
	getBlockTree,
	savePost,
	trackErrors,
	newPost,
} from '../wpsb/e2e/helpers.mjs';

const postId = (slug, type = 'post') =>
	Number(wp(['post', 'list', `--post_type=${type}`, `--name=${slug}`, '--field=ID', '--post_status=any']));
const permalink = (id) => new URL(wp(['eval', `echo get_permalink( ${Number(id)} );`])).pathname;

const SEEDED = [
	['help-legacy-v1', 'page', [['How do I reset my password?', 'lost password'], ['username', 'permanent'], ['Where are my invoices?', 'Billing']]],
	['help-center', 'page', [['What is Acme?', 'everything'], ['How long does shipping take?', '2–3'], ['How do I pay?', 'invoice'], ['REST', 'developer docs']]],
	['two-faqs', 'post', [['What is Acme?', 'A company.'], ['Do you ship abroad?', 'Yes.'], ['Can I return an item?', '30 days'], ['What is Acme?', 'Still a company.'], ['Who founded Acme?', 'Wile E.']]],
	['faq-in-group', 'post', [['Is this nested?', 'inside a group'], ['Does it still work?', 'It should.']]],
];

function findAll(tree, name, out = []) {
	for (const b of tree) {
		if (b.name === name) out.push(b);
		findAll(b.innerBlocks || [], name, out);
	}
	return out;
}

/** State of every FAQ on the page as the user sees it. */
async function faqState(page) {
	return page.evaluate(() =>
		[...document.querySelectorAll('.wp-block-acme-faq')].map((faq) =>
			[...faq.querySelectorAll('.wp-block-acme-faq-item')].map((item) => {
				const button = item.querySelector('.acme-faq__toggle');
				const panel = item.querySelector('.acme-faq__panel');
				return {
					id: item.id,
					expanded: button?.getAttribute('aria-expanded'),
					visible: !!panel && !panel.hidden && panel.getClientRects().length > 0,
				};
			})
		)
	);
}

test.describe('FAQ block in the editor', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	for (const [slug, type, expected] of SEEDED) {
		test(`existing FAQ in "${slug}" opens without block errors`, async ({ page }) => {
			const errors = trackErrors(page);
			const id = postId(slug, type);
			expect(id).toBeGreaterThan(0);
			await openEditor(page, id);
			await assertBlocksValid(page);
			const items = findAll(await getBlockTree(page), 'acme/faq-item');
			expect(items.length).toBe(expected.length);
			items.forEach((item, i) => {
				expect(JSON.stringify(item.attributes)).toContain(expected[i][0]);
				expect(JSON.stringify(item.innerBlocks)).toContain(expected[i][1]);
			});
			expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
		});
	}

	test('upgraded FAQs save, reload valid and keep their settings', async ({ page }) => {
		const id = postId('help-center', 'page');
		await openEditor(page, id);
		await assertBlocksValid(page);
		await page.evaluate(() => {
			const { select, dispatch } = wp.data;
			const para = select('core/block-editor').getBlocks().find((b) => b.name === 'core/paragraph');
			dispatch('core/block-editor').updateBlockAttributes(para.clientId, { content: 'Everything about your Acme account (edited).' });
		});
		await savePost(page);
		await openEditor(page, id);
		await assertBlocksValid(page);
		const faqs = findAll(await getBlockTree(page), 'acme/faq');
		expect(faqs.length).toBe(1);
		expect(faqs[0].attributes.openFirst).toBe(true);
		expect(faqs[0].innerBlocks.length).toBe(4);
		expect(faqs[0].innerBlocks[2].attributes.anchor).toBe('billing');

		await page.goto(permalink(id));
		expect((await faqState(page))[0].map((i) => [i.id, i.expanded])).toEqual([
			['faq-what-is-acme', 'true'],
			['faq-how-long-does-shipping-take', 'false'],
			['billing', 'false'],
			['faq-is-there-a-rest-api', 'false'],
		]);
	});

	test('a new FAQ with "allow several open questions" round-trips to the front end', async ({ page }) => {
		const errors = trackErrors(page);
		await newPost(page);
		await page.evaluate(() => {
			const { createBlock } = wp.blocks;
			wp.data.dispatch('core/editor').editPost({ title: 'Fresh FAQ', status: 'publish' });
			wp.data.dispatch('core/block-editor').resetBlocks([
				createBlock('acme/faq', {}, [
					createBlock('acme/faq-item', { question: 'Fresh question one?' }, [createBlock('core/paragraph', { content: 'Fresh answer one.' })]),
					createBlock('acme/faq-item', { question: 'Fresh question two?' }, [createBlock('core/paragraph', { content: 'Fresh answer two.' })]),
				]),
			]);
			const faq = wp.data.select('core/block-editor').getBlocks()[0];
			wp.data.dispatch('core/block-editor').selectBlock(faq.clientId);
			wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-post/block');
		});
		const toggle = page.getByRole('checkbox', { name: 'Allow several open questions' });
		await expect(toggle).toBeVisible();
		await toggle.check();
		await savePost(page);
		const id = await page.evaluate(() => wp.data.select('core/editor').getCurrentPostId());
		await openEditor(page, id);
		await assertBlocksValid(page);
		const faqs = findAll(await getBlockTree(page), 'acme/faq');
		expect(faqs[0].attributes.allowMultiple).toBe(true);
		expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);

		await page.goto(permalink(id));
		await page.waitForLoadState('load');
		await page.waitForTimeout(500);
		const buttons = page.locator('.acme-faq__toggle');
		await expect(buttons).toHaveText(['Fresh question one?', 'Fresh question two?']);
		await buttons.nth(0).click();
		await buttons.nth(1).click();
		await expect(buttons.nth(0)).toHaveAttribute('aria-expanded', 'true');
		await expect(buttons.nth(1)).toHaveAttribute('aria-expanded', 'true');
		await expect(page.locator('.acme-faq__panel').nth(0)).toBeVisible();
		await expect(page.locator('.acme-faq__panel').nth(1)).toBeVisible();
		const ld = await page.locator('script[type="application/ld+json"]').allTextContents();
		expect(ld.join('')).toContain('Fresh question two?');
	});
});

test.describe('FAQ accordion on the front end', () => {
	test('server state is used as is; clicking opens one question at a time', async ({ page }) => {
		const errors = trackErrors(page);
		await page.goto('/help-center/');
		await page.waitForLoadState('load');
		await page.waitForTimeout(500);
		expect(await page.evaluate(() => typeof window.jQuery)).toBe('undefined');
		expect((await faqState(page))[0]).toEqual([
			{ id: 'faq-what-is-acme', expanded: 'true', visible: true },
			{ id: 'faq-how-long-does-shipping-take', expanded: 'false', visible: false },
			{ id: 'billing', expanded: 'false', visible: false },
			{ id: 'faq-is-there-a-rest-api', expanded: 'false', visible: false },
		]);
		const buttons = page.locator('.acme-faq__toggle');
		await buttons.nth(1).click();
		await expect(buttons.nth(1)).toHaveAttribute('aria-expanded', 'true');
		await expect(page.locator('#faq-how-long-does-shipping-take .acme-faq__panel')).toBeVisible();
		await expect(buttons.nth(0)).toHaveAttribute('aria-expanded', 'false');
		await expect(page.locator('#faq-what-is-acme .acme-faq__panel')).toBeHidden();
		await buttons.nth(1).click();
		await expect(buttons.nth(1)).toHaveAttribute('aria-expanded', 'false');
		await expect(page.locator('#faq-how-long-does-shipping-take .acme-faq__panel')).toBeHidden();
		expect(errors.filter((e) => !e.includes('Failed to load resource'))).toEqual([]);
	});

	test('legacy 1.0 FAQ is interactive without re-saving', async ({ page }) => {
		await page.goto('/help-legacy-v1/');
		await page.waitForLoadState('load');
		await page.waitForTimeout(500);
		const button = page.getByRole('button', { name: 'Where are my invoices?' });
		await expect(button).toHaveAttribute('aria-expanded', 'false');
		await button.click();
		await expect(button).toHaveAttribute('aria-expanded', 'true');
		const panel = page.getByRole('region', { name: 'Where are my invoices?' });
		await expect(panel).toBeVisible();
		await expect(panel).toContainText('Account → Billing');
	});

	test('keyboard: arrows, Home and End move between questions of the same FAQ', async ({ page }) => {
		await page.goto('/two-faqs/');
		await page.waitForLoadState('load');
		await page.waitForTimeout(500);
		const first = page.locator('.wp-block-acme-faq').nth(0).locator('.acme-faq__toggle');
		const focusedId = () => page.evaluate(() => document.activeElement?.closest('.wp-block-acme-faq-item')?.id || null);
		await first.nth(0).focus();
		await page.keyboard.press('ArrowDown');
		expect(await focusedId()).toBe('faq-do-you-ship-abroad');
		await page.keyboard.press('ArrowDown');
		expect(await focusedId()).toBe('faq-can-i-return-an-item');
		await page.keyboard.press('ArrowDown');
		expect(await focusedId(), 'ArrowDown wraps within the same FAQ').toBe('faq-what-is-acme');
		await page.keyboard.press('ArrowUp');
		expect(await focusedId()).toBe('faq-can-i-return-an-item');
		await page.keyboard.press('Home');
		expect(await focusedId()).toBe('faq-what-is-acme');
		await page.keyboard.press('End');
		expect(await focusedId()).toBe('faq-can-i-return-an-item');
		// Enter and Space toggle the focused question.
		await page.keyboard.press('Enter');
		await expect(first.nth(2)).toHaveAttribute('aria-expanded', 'true');
		await page.keyboard.press('Space');
		await expect(first.nth(2)).toHaveAttribute('aria-expanded', 'false');
		// The second FAQ is independent.
		const second = page.locator('.wp-block-acme-faq').nth(1).locator('.acme-faq__toggle');
		await expect(second.nth(0)).toHaveAttribute('aria-expanded', 'true');
		await second.nth(1).focus();
		await page.keyboard.press('ArrowDown');
		expect(await focusedId()).toBe('faq-what-is-acme-2');
	});

	test('deep links open the linked question', async ({ page }) => {
		await page.goto('/help-center/#billing');
		await page.waitForLoadState('load');
		await expect(page.locator('#billing .acme-faq__toggle')).toHaveAttribute('aria-expanded', 'true');
		await expect(page.locator('#billing .acme-faq__panel')).toBeVisible();
		await expect(page.locator('#faq-what-is-acme .acme-faq__toggle')).toHaveAttribute('aria-expanded', 'false');

		await page.goto('/two-faqs/#faq-who-founded-acme');
		await page.waitForLoadState('load');
		await expect(page.locator('#faq-who-founded-acme .acme-faq__toggle')).toHaveAttribute('aria-expanded', 'true');
		await expect(page.locator('#faq-who-founded-acme .acme-faq__panel')).toBeVisible();
		await expect(page.locator('#faq-who-founded-acme')).toBeInViewport();
		// The other FAQ on the page is not affected.
		expect((await faqState(page))[0].map((i) => i.expanded)).toEqual(['false', 'false', 'false']);

		// In-page links to a question open it too.
		await page.getByRole('link', { name: 'returns' }).click();
		await expect(page).toHaveURL(/#faq-can-i-return-an-item$/);
		await expect(page.locator('#faq-can-i-return-an-item .acme-faq__toggle')).toHaveAttribute('aria-expanded', 'true');
		await expect(page.locator('#faq-can-i-return-an-item .acme-faq__panel')).toBeVisible();
	});

	test('without JavaScript the server-rendered state is complete', async ({ browser }) => {
		const context = await browser.newContext({ javaScriptEnabled: false });
		const page = await context.newPage();
		await page.goto('/two-faqs/');
		const state = await faqState(page).catch(() => null);
		// page.evaluate needs JS in the page context only for the helper; fall back to locators.
		if (state === null) {
			await expect(page.locator('#faq-what-is-acme-2 .acme-faq__toggle')).toHaveAttribute('aria-expanded', 'true');
		} else {
			expect(state[1][0]).toEqual({ id: 'faq-what-is-acme-2', expanded: 'true', visible: true });
			expect(state[1][1]).toEqual({ id: 'faq-who-founded-acme', expanded: 'false', visible: false });
		}
		await expect(page.locator('#faq-what-is-acme-2 .acme-faq__panel')).toBeVisible();
		await expect(page.locator('#faq-who-founded-acme .acme-faq__panel')).toBeHidden();
		await expect(page.locator('#faq-who-founded-acme .acme-faq__toggle')).toHaveAttribute('aria-expanded', 'false');
		await context.close();
	});
});
