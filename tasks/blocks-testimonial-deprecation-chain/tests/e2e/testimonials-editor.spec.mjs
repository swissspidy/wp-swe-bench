// Acme Testimonials 4.0: every saved format opens, migrates and saves as 4.x.
import { test, expect } from '@playwright/test';
import { login, openEditor, newPost, assertBlocksValid, savePost, trackErrors } from '../wpsb/e2e/helpers.mjs';
import { postId, postContent, SEEDED, describeInPage, assertV4, editorTestimonials, serializeBlocks, reviewsOf } from './_testimonials.mjs';

const canvas = (page) => page.frameLocator('iframe[name="editor-canvas"]');

test.describe('Testimonials in the editor', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	for (const [slug, { type, items }] of Object.entries(SEEDED)) {
		test(`"${slug}" opens without block errors and is upgraded to the 4.x format`, async ({ page }) => {
			const errors = trackErrors(page);
			await openEditor(page, postId(slug, type));
			await assertBlocksValid(page);
			const blocks = await editorTestimonials(page);
			expect(blocks.length).toBe(items.length);
			for (let i = 0; i < items.length; i++) {
				const exp = items[i];
				expect(blocks[i].attributes.rating ?? 0, `${slug}[${i}] rating attribute`).toBe(exp.rating);
				expect(typeof (blocks[i].attributes.rating ?? 0), `${slug}[${i}] rating must be a number`).toBe('number');
				const [d] = await describeInPage(page, blocks[i].html);
				assertV4(d, exp, `${slug}[${i}]`);
			}
			expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
		});
	}

	for (const slug of ['v1-testimonials', 'v2-testimonials', 'v3-testimonials', 'customer-reviews']) {
		test(`saving "${slug}" writes 4.x markup that stays stable and renders on the front end`, async ({ page }) => {
			const { type, items } = SEEDED[slug];
			const id = postId(slug, type);
			await openEditor(page, id);
			await assertBlocksValid(page);
			// The editor writes what the blocks serialize to.
			await page.evaluate(() => {
				const blocks = wp.data.select('core/block-editor').getBlocks();
				wp.data.dispatch('core/editor').editPost({ content: wp.blocks.serialize(blocks) });
			});
			await savePost(page);
			const saved = postContent(id);
			expect(saved).not.toContain('acme-testimonial__author');
			expect(saved).not.toContain('data-rating');
			expect(saved).not.toMatch(/[★☆]/);
			expect(saved).not.toMatch(/"rating":"/);

			// Reopening: valid, and nothing left to migrate.
			await openEditor(page, id);
			await assertBlocksValid(page);
			expect((await serializeBlocks(page)).trim()).toBe(saved.trim());

			// Front end: 4.x markup + structured data.
			const { html, reviews } = await reviewsOf(page, `/?p=${id}`);
			const found = await describeInPage(page, html);
			expect(found.length).toBe(items.length);
			found.forEach((d, i) => assertV4(d, items[i], `${slug} front end [${i}]`));
			expect(reviews.map((r) => r.author?.name)).toEqual(items.map((it) => it.name));
			expect(reviews.map((r) => r.reviewRating?.ratingValue ?? 0)).toEqual(items.map((it) => it.rating));
			expect(reviews.every((r) => r.itemReviewed?.name === 'Acme Suite')).toBe(true);
		});
	}

	test('new testimonial: half-star rating from the sidebar, 4.x markup, front end', async ({ page }) => {
		await newPost(page);
		await page.evaluate(() => {
			wp.data.dispatch('core/editor').editPost({ title: 'Fresh testimonial', status: 'publish' });
			const block = wp.blocks.createBlock('acme/testimonial', {
				quote: 'Best <strong>support</strong> ever.',
				authorName: 'Nina Berg',
				authorRole: 'CEO, Berg AB',
			});
			wp.data.dispatch('core/block-editor').insertBlocks(block);
			wp.data.dispatch('core/block-editor').selectBlock(block.clientId);
			wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-post/block');
		});
		const input = page.getByRole('spinbutton', { name: 'Rating' });
		await expect(input).toBeVisible();
		await input.fill('3.5');
		await input.press('Tab');
		await expect.poll(() => page.evaluate(() => wp.data.select('core/block-editor').getBlocks()[0].attributes.rating)).toBe(3.5);

		// The editor preview shows the stars too.
		const preview = canvas(page).locator('.wp-block-acme-testimonial .acme-testimonial__rating');
		await expect(preview).toHaveAttribute('aria-label', 'Rated 3.5 out of 5');
		await expect(preview.locator('.acme-testimonial__star')).toHaveCount(5);

		const saved = await savePost(page);
		const id = await page.evaluate(() => wp.data.select('core/editor').getCurrentPostId());
		expect(saved).toMatch(/<!-- wp:acme\/testimonial \{[^}]*"rating":3\.5/);
		const exp = { quote: 'Best <strong>support</strong> ever.', name: 'Nina Berg', role: 'CEO, Berg AB', rating: 3.5, avatar: null, classes: [] };
		const [d] = await describeInPage(page, saved);
		assertV4(d, exp, 'new');

		await openEditor(page, id);
		await assertBlocksValid(page);
		const { html, reviews } = await reviewsOf(page, `/?p=${id}`);
		assertV4((await describeInPage(page, html))[0], exp, 'new front end');
		expect(reviews[0].reviewRating.ratingValue).toBe(3.5);
		expect(reviews[0].author.name).toBe('Nina Berg');

		// Rating 0 = not rated; out-of-range values are clamped.
		for (const [value, expected] of [[0, 0], [7, 5], [4.25, 4.5]]) {
			await page.evaluate((v) => {
				const [b] = wp.data.select('core/block-editor').getBlocks();
				wp.data.dispatch('core/block-editor').updateBlockAttributes(b.clientId, { rating: v });
			}, value);
			const [b] = await editorTestimonials(page);
			const [desc] = await describeInPage(page, b.html);
			assertV4(desc, { ...exp, rating: expected }, `rating ${value}`);
		}
	});

	test('avatar from the media library: thumbnail in the byline, removable', async ({ page }) => {
		await newPost(page);
		await page.evaluate(() => {
			const block = wp.blocks.createBlock('acme/testimonial', { quote: 'Lovely.', authorName: 'Jane Doe' });
			wp.data.dispatch('core/block-editor').insertBlocks(block);
			wp.data.dispatch('core/block-editor').selectBlock(block.clientId);
			wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-post/block');
		});
		await page.getByRole('button', { name: 'Choose avatar' }).click();
		const modal = page.locator('.media-modal');
		await expect(modal).toBeVisible();
		const mediaTab = modal.getByRole('tab', { name: 'Media Library' });
		if (await mediaTab.count()) await mediaTab.click();
		await modal.locator('li.attachment[aria-label="Jane Doe"]').click();
		await modal.locator('.media-button-select').click();
		await expect(modal).toBeHidden();

		const img = canvas(page).locator('.wp-block-acme-testimonial .acme-testimonial__byline img.acme-testimonial__avatar');
		await expect(img).toHaveAttribute('src', /jane-doe-150x150\.jpg$/);
		let [b] = await editorTestimonials(page);
		let [d] = await describeInPage(page, b.html);
		assertV4(d, { quote: 'Lovely.', name: 'Jane Doe', role: null, rating: 0, avatar: 'jane-doe-150x150.jpg', classes: [] }, 'avatar');

		await page.getByRole('button', { name: 'Remove avatar' }).click();
		await expect(img).toHaveCount(0);
		[b] = await editorTestimonials(page);
		[d] = await describeInPage(page, b.html);
		assertV4(d, { quote: 'Lovely.', name: 'Jane Doe', role: null, rating: 0, avatar: null, classes: [] }, 'avatar removed');
	});

	test('old testimonials that were never re-saved still render on the front end', async ({ page }) => {
		for (const [slug, { items }] of Object.entries(SEEDED)) {
			const res = await page.request.get(`/${slug}/`);
			expect(res.status()).toBe(200);
			const text = (await res.text()).replace(/<[^>]+>/g, '').replace(/\u2019/g, "'");
			for (const item of items) {
				expect(text, `${slug}: ${item.name}`).toContain(item.name);
				expect(text).toContain(item.quote.replace(/<[^>]+>/g, '').slice(0, 12));
			}
		}
	});
});
