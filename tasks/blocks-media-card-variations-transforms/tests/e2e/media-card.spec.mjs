// Media card 2.0 in the editor: legacy content, variations, layout, transforms.
import { test, expect } from '@playwright/test';
import { login, wp, wpEval, openEditor, newPost, assertBlocksValid, getBlockTree, savePost, trackErrors, createPost } from '../wpsb/e2e/helpers.mjs';

const NAME = 'acme/media-card';
const postId = (slug, type) => Number(wp(['post', 'list', `--post_type=${type}`, `--name=${slug}`, '--field=ID', '--post_status=any']));
const attachment = (title) => {
	const id = Number(wpEval(`$p = get_posts( array( 'post_type' => 'attachment', 'title' => '${title}', 'numberposts' => 1 ) ); echo $p ? $p[0]->ID : 0;`));
	return { id, url: wpEval(`echo wp_get_attachment_url( ${id} );`) };
};

function cards(tree, out = []) {
	for (const b of tree) {
		if (b.name === NAME) out.push(b);
		cards(b.innerBlocks || [], out);
	}
	return out;
}

/** Wrapper tags of all saved cards in post content. */
const savedWrappers = (content) => content.match(/<div[^>]*class="wp-block-acme-media-card(?: [^"]*)?"[^>]*>/g) || [];

test.describe('Media card 2.0', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('1.2 cards open without errors, keep everything and save in the new format', async ({ page }) => {
		const errors = trackErrors(page);
		const sun = attachment('Sunflowers');
		const id = postId('spring-campaign', 'page');
		await openEditor(page, id);
		await assertBlocksValid(page);
		const [a, b, c] = cards(await getBlockTree(page));
		expect(a.attributes).toMatchObject({
			mediaId: sun.id,
			mediaUrl: sun.url,
			mediaAlt: 'Sunflowers in a vase',
			mediaLink: 'https://shop.example.org/spring',
			ctaText: 'Shop now',
			ctaUrl: 'https://shop.example.org/spring',
			align: 'wide',
			anchor: 'spring-sale',
			layout: 'stacked',
		});
		expect(a.attributes.cardType || '').toBe('');
		expect(String(a.attributes.heading)).toBe('Spring <em>sale</em>');
		expect(String(a.attributes.text)).toBe('Everything <strong>30% off</strong> until <a href="/terms">Sunday</a>.');
		expect(b.attributes).toMatchObject({ backgroundColor: 'accent-1', layout: 'stacked' });
		expect(c.attributes).toMatchObject({ className: 'is-style-outlined', ctaUrl: '/team', layout: 'stacked' });

		await page.evaluate(() => {
			const para = wp.data.select('core/block-editor').getBlocks().find((b) => b.name === 'core/paragraph');
			wp.data.dispatch('core/block-editor').updateBlockAttributes(para.clientId, { content: 'Edited: ' + String(para.attributes.content) });
		});
		await savePost(page);
		const content = wp(['post', 'get', String(id), '--field=post_content']);
		const wrappers = savedWrappers(content);
		expect(wrappers.length).toBe(3);
		for (const w of wrappers) {
			expect(w).toMatch(/\bhas-layout-stacked\b/);
			expect(w).not.toMatch(/\bis-[a-z]*-card\b/);
		}
		expect(wrappers[0]).toMatch(/id="spring-sale"/);
		expect(wrappers[0]).toMatch(/\balignwide\b/);
		expect(wrappers[1]).toMatch(/has-accent-1-background-color/);
		expect(wrappers[2]).toMatch(/is-style-outlined/);
		expect(content).toContain(`<a href="https://shop.example.org/spring"><img src="${sun.url}" alt="Sunflowers in a vase" class="wp-image-${sun.id}"/></a>`);
		expect(content).toContain('<h3 class="wp-block-acme-media-card__heading">Spring <em>sale</em></h3>');
		expect(content).not.toContain('wp-block-acme-media-card__meta');

		await openEditor(page, id);
		await assertBlocksValid(page);
		expect(cards(await getBlockTree(page)).length).toBe(3);
		const html = await (await page.request.get('/spring-campaign/')).text();
		expect((html.match(/class="wp-block-acme-media-card [^"]*has-layout-stacked/g) || []).length).toBe(3);
		expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
	});

	test('1.0 cards and the promo pattern are upgraded too', async ({ page }) => {
		const team = attachment('Team');
		const id = postId('about-acme', 'post');
		await openEditor(page, id);
		await assertBlocksValid(page);
		const [card] = cards(await getBlockTree(page));
		expect(card.attributes).toMatchObject({ mediaId: team.id, mediaAlt: 'Old photo', ctaText: 'About us', ctaUrl: '/about', layout: 'stacked' });
		expect(String(card.attributes.heading)).toBe('Since 1999');
		await page.evaluate(() => {
			const para = wp.data.select('core/block-editor').getBlocks().find((b) => b.name === 'core/paragraph');
			wp.data.dispatch('core/block-editor').updateBlockAttributes(para.clientId, { content: 'Edited: ' + String(para.attributes.content) });
		});
		await savePost(page);
		const content = wp(['post', 'get', String(id), '--field=post_content']);
		expect(content).toContain('<h3 class="wp-block-acme-media-card__heading">Since 1999</h3>');
		expect(content).toContain('<a class="wp-block-acme-media-card__cta" href="/about">About us</a>');
		expect(savedWrappers(content)[0]).toMatch(/has-layout-stacked/);
		await openEditor(page, id);
		await assertBlocksValid(page);

		const pattern = postId('why-acme', 'page');
		await openEditor(page, pattern);
		await assertBlocksValid(page);
		const all = cards(await getBlockTree(page));
		expect(all.map((c) => String(c.attributes.heading))).toEqual(['Fast', 'Friendly', 'Fair']);
	});

	test('variations are registered with their defaults', async ({ page }) => {
		await newPost(page);
		const variations = await page.evaluate((name) =>
			JSON.parse(JSON.stringify(wp.blocks.getBlockVariations(name).map((v) => ({ name: v.name, title: v.title, attributes: v.attributes, scope: v.scope })))),
		NAME);
		const byName = Object.fromEntries(variations.map((v) => [v.name, v]));
		expect(byName.product).toMatchObject({ title: 'Product card', attributes: { cardType: 'product', layout: 'stacked', ctaText: 'Buy now' } });
		expect(byName.profile).toMatchObject({ title: 'Profile card', attributes: { cardType: 'profile', layout: 'media-left', ctaText: 'View profile' } });
		expect(byName.event).toMatchObject({ title: 'Event card', attributes: { cardType: 'event', layout: 'media-right', ctaText: 'Register' } });
		for (const n of ['product', 'profile', 'event']) {
			expect(byName[n].scope || ['inserter', 'block']).toContain('inserter');
		}
		const inserterItems = await page.evaluate(() =>
			wp.data.select('core/block-editor').getInserterItems().filter((i) => i.name === 'acme/media-card').map((i) => i.title)
		);
		expect(inserterItems).toEqual(expect.arrayContaining(['Product card', 'Profile card', 'Event card']));
	});

	test('the active variation depends on the card type only', async ({ page }) => {
		await newPost(page);
		const result = await page.evaluate((name) => {
			const { getBlockVariations, createBlock } = wp.blocks;
			const active = (attrs) => wp.data.select('core/blocks').getActiveBlockVariation(name, attrs)?.name || null;
			const out = {};
			for (const v of getBlockVariations(name).filter((x) => ['product', 'profile', 'event'].includes(x.name))) {
				const block = createBlock(name, v.attributes);
				out[v.name] = {
					fresh: active(block.attributes),
					edited: active({ ...block.attributes, layout: v.name === 'event' ? 'stacked' : 'media-right', ctaText: 'Something else', heading: 'Changed', meta: 'x' }),
				};
			}
			out.plain = active(createBlock(name, {}).attributes);
			out.plainWithLayout = active(createBlock(name, { layout: 'media-left', ctaText: 'View profile' }).attributes);
			out.profileStacked = active(createBlock(name, { cardType: 'profile', layout: 'stacked', ctaText: 'Buy now' }).attributes);
			return out;
		}, NAME);
		expect(result.product).toEqual({ fresh: 'product', edited: 'product' });
		expect(result.profile).toEqual({ fresh: 'profile', edited: 'profile' });
		expect(result.event).toEqual({ fresh: 'event', edited: 'event' });
		expect(['product', 'profile', 'event']).not.toContain(result.plain);
		expect(['product', 'profile', 'event']).not.toContain(result.plainWithLayout);
		expect(result.profileStacked).toBe('profile');
	});

	test('variation cards show their type in the editor and save the new markup', async ({ page }) => {
		const errors = trackErrors(page);
		const sun = attachment('Sunflowers');
		await newPost(page);
		const clientId = await page.evaluate(({ name, sun }) => {
			const v = wp.blocks.getBlockVariations(name).find((x) => x.name === 'product');
			const block = wp.blocks.createBlock(name, { ...v.attributes, mediaId: sun.id, mediaUrl: sun.url, mediaAlt: 'Sunflowers', heading: 'Vase', text: 'Hand made.', ctaUrl: 'https://shop.example.org/vase', meta: '€19' }, v.innerBlocks || []);
			wp.data.dispatch('core/editor').editPost({ title: 'Variation cards', status: 'publish' });
			wp.data.dispatch('core/block-editor').insertBlocks(block);
			wp.data.dispatch('core/block-editor').selectBlock(block.clientId);
			wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-post/block');
			return block.clientId;
		}, { name: NAME, sun });
		const title = page.locator('.block-editor-block-inspector .block-editor-block-card__title');
		await expect(title).toContainText('Product card');
		await page.evaluate((id) => wp.data.dispatch('core/block-editor').updateBlockAttributes(id, { layout: 'media-right', ctaText: 'Order' }), clientId);
		await expect(title).toContainText('Product card');

		// A profile card next to it.
		await page.evaluate((name) => {
			const v = wp.blocks.getBlockVariations(name).find((x) => x.name === 'profile');
			wp.data.dispatch('core/block-editor').insertBlocks(wp.blocks.createBlock(name, { ...v.attributes, heading: 'Jane', meta: 'CEO', ctaUrl: '/team/jane' }, v.innerBlocks || []));
		}, NAME);
		await savePost(page);
		const id = await page.evaluate(() => wp.data.select('core/editor').getCurrentPostId());
		const content = wp(['post', 'get', String(id), '--field=post_content']);
		const wrappers = savedWrappers(content);
		expect(wrappers.length).toBe(2);
		expect(wrappers[0]).toMatch(/\bhas-layout-media-right\b/);
		expect(wrappers[0]).toMatch(/\bis-product-card\b/);
		expect(wrappers[1]).toMatch(/\bhas-layout-media-left\b/);
		expect(wrappers[1]).toMatch(/\bis-profile-card\b/);
		expect(content).toMatch(/<div class="wp-block-acme-media-card__content"><p class="wp-block-acme-media-card__meta">€19<\/p><h3 class="wp-block-acme-media-card__heading">Vase<\/h3>/);
		expect(content).toContain('<a class="wp-block-acme-media-card__cta" href="https://shop.example.org/vase">Order</a>');
		expect(content).toContain('<a class="wp-block-acme-media-card__cta" href="/team/jane">View profile</a>');

		await openEditor(page, id);
		await assertBlocksValid(page);
		const [p1, p2] = cards(await getBlockTree(page));
		expect(p1.attributes).toMatchObject({ cardType: 'product', layout: 'media-right', ctaText: 'Order', mediaId: sun.id });
		expect(String(p1.attributes.meta)).toBe('€19');
		expect(p2.attributes).toMatchObject({ cardType: 'profile', layout: 'media-left' });

		const html = await (await page.request.get(`/?p=${id}`)).text();
		expect(html).toMatch(/class="wp-block-acme-media-card [^"]*is-product-card/);
		expect(html).toContain('<p class="wp-block-acme-media-card__meta">CEO</p>');
		expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
	});

	test('image → card → media & text → card keeps the image and texts', async ({ page }) => {
		const sun = attachment('Sunflowers');
		await newPost(page);
		const r = await page.evaluate(({ name, sun }) => {
			const { createBlock, switchToBlockType } = wp.blocks;
			const plain = (b) => JSON.parse(JSON.stringify({ name: b.name, attributes: Object.fromEntries(Object.entries(b.attributes).map(([k, v]) => [k, v && typeof v === 'object' && 'toHTMLString' in v ? v.toHTMLString() : v])), innerBlocks: (b.innerBlocks || []).map(plain) }));
			const image = createBlock('core/image', { id: sun.id, url: sun.url, alt: 'Sunflowers in a vase', href: 'https://shop.example.org/flowers', linkDestination: 'custom', caption: 'Fresh <em>every</em> day' });
			wp.data.dispatch('core/block-editor').insertBlocks(image);
			const toCard = switchToBlockType(image, name);
			if (!toCard) return { error: 'image → card not possible' };
			const card = toCard[0];
			wp.data.dispatch('core/block-editor').replaceBlocks(image.clientId, toCard);
			const withTexts = { ...card, attributes: { ...card.attributes, heading: 'Our <strong>flowers</strong>', ctaText: 'Order', ctaUrl: 'https://shop.example.org/order', layout: 'media-right' } };
			wp.data.dispatch('core/block-editor').updateBlockAttributes(card.clientId, withTexts.attributes);
			const current = wp.data.select('core/block-editor').getBlock(card.clientId);
			const possible = wp.blocks.getPossibleBlockTransformations([current]).map((t) => t.name);
			const toMT = switchToBlockType(current, 'core/media-text');
			if (!toMT) return { error: 'card → media-text not possible', possible };
			wp.data.dispatch('core/block-editor').replaceBlocks(card.clientId, toMT);
			const back = switchToBlockType(toMT[0], name);
			if (!back) return { error: 'media-text → card not possible' };
			wp.data.dispatch('core/block-editor').replaceBlocks(toMT[0].clientId, back);
			return { card: plain(card), mediaText: plain(toMT[0]), back: plain(back[0]), possible };
		}, { name: NAME, sun });
		expect(r.error).toBeUndefined();
		expect(r.card.name).toBe(NAME);
		expect(r.card.attributes).toMatchObject({ mediaId: sun.id, mediaUrl: sun.url, mediaAlt: 'Sunflowers in a vase', mediaLink: 'https://shop.example.org/flowers' });
		expect(String(r.card.attributes.text)).toBe('Fresh <em>every</em> day');
		expect(r.possible).toContain('core/media-text');

		expect(r.mediaText.attributes).toMatchObject({ mediaId: sun.id, mediaUrl: sun.url, mediaAlt: 'Sunflowers in a vase', href: 'https://shop.example.org/flowers', mediaType: 'image', mediaPosition: 'right' });
		const inner = r.mediaText.innerBlocks;
		expect(inner.map((b) => b.name)).toEqual(['core/heading', 'core/paragraph', 'core/buttons']);
		expect(inner[0].attributes).toMatchObject({ level: 3, content: 'Our <strong>flowers</strong>' });
		expect(inner[1].attributes.content).toBe('Fresh <em>every</em> day');
		expect(inner[2].innerBlocks[0]).toMatchObject({ name: 'core/button', attributes: { text: 'Order', url: 'https://shop.example.org/order' } });

		expect(r.back.attributes).toMatchObject({
			mediaId: sun.id,
			mediaUrl: sun.url,
			mediaAlt: 'Sunflowers in a vase',
			mediaLink: 'https://shop.example.org/flowers',
			ctaText: 'Order',
			ctaUrl: 'https://shop.example.org/order',
			layout: 'media-right',
		});
		expect(String(r.back.attributes.heading)).toBe('Our <strong>flowers</strong>');
		expect(String(r.back.attributes.text)).toBe('Fresh <em>every</em> day');

		await page.evaluate(() => wp.data.dispatch('core/editor').editPost({ title: 'Round trip', status: 'publish' }));
		await savePost(page);
		const id = await page.evaluate(() => wp.data.select('core/editor').getCurrentPostId());
		await openEditor(page, id);
		await assertBlocksValid(page);
		expect(cards(await getBlockTree(page)).length).toBe(1);
	});

	test('media & text converts into a card; video media & text does not', async ({ page }) => {
		const sun = attachment('Sunflowers');
		await newPost(page);
		const r = await page.evaluate(({ name, sun }) => {
			const { createBlock, switchToBlockType, getPossibleBlockTransformations } = wp.blocks;
			const mt = createBlock('core/media-text', { mediaId: sun.id, mediaUrl: sun.url, mediaAlt: 'Sunflowers', mediaType: 'image', mediaPosition: 'left', href: 'https://example.org/flowers' }, [
				createBlock('core/heading', { level: 2, content: 'Big <em>news</em>' }),
				createBlock('core/paragraph', { content: 'First paragraph.' }),
				createBlock('core/paragraph', { content: 'Second paragraph.' }),
				createBlock('core/buttons', {}, [createBlock('core/button', { text: 'Read more', url: 'https://example.org/news' }), createBlock('core/button', { text: 'Ignore', url: '/x' })]),
			]);
			const video = createBlock('core/media-text', { mediaId: 999, mediaUrl: 'https://example.org/clip.mp4', mediaType: 'video' }, [createBlock('core/paragraph', { content: 'Clip' })]);
			wp.data.dispatch('core/block-editor').insertBlocks([mt, video]);
			const card = switchToBlockType(mt, name);
			const s = (v) => (v && typeof v === 'object' && 'toHTMLString' in v ? v.toHTMLString() : v);
			return {
				card: card ? Object.fromEntries(Object.entries(card[0].attributes).map(([k, v]) => [k, s(v)])) : null,
				videoTargets: getPossibleBlockTransformations([video]).map((t) => t.name),
			};
		}, { name: NAME, sun });
		expect(r.card).not.toBeNull();
		expect(r.card).toMatchObject({ mediaId: sun.id, mediaUrl: sun.url, mediaAlt: 'Sunflowers', mediaLink: 'https://example.org/flowers', heading: 'Big <em>news</em>', text: 'First paragraph.', ctaText: 'Read more', ctaUrl: 'https://example.org/news', layout: 'media-left' });
		expect(r.videoTargets).not.toContain(NAME);
	});
});
