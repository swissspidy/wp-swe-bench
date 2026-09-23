// Editor behaviour of the notice box and statistic after the move to block supports (hidden E2E tests).
import { test, expect } from '@playwright/test';
import {
	login,
	wp,
	openEditor,
	newPost,
	assertBlocksValid,
	getBlockTree,
	savePost,
	trackErrors,
} from '../wpsb/e2e/helpers.mjs';

const postId = (slug, type = 'post') =>
	Number(wp(['post', 'list', `--post_type=${type}`, `--name=${slug}`, '--field=ID', '--post_status=any']));

function find(tree, name, out = []) {
	for (const b of tree) {
		if (b.name === name) out.push(b);
		find(b.innerBlocks || [], name, out);
	}
	return out;
}

const PAD = (v) => ({ top: v, right: v, bottom: v, left: v });
const LEGACY = ['bgColor', 'padding', 'bordered', 'boxed', 'color'];

// Only the design-related attributes, with undefined values dropped.
function design(attrs) {
	const out = {};
	for (const k of ['backgroundColor', 'textColor', 'fontSize', 'style', 'className']) {
		if (attrs[k] !== undefined && attrs[k] !== '' && !(k === 'style' && Object.keys(attrs[k] || {}).length === 0)) out[k] = attrs[k];
	}
	return out;
}

const EXPECTED = {
	'notices-v1': [
		{ tone: 'warning', design: {} },
		{ tone: 'info', design: { backgroundColor: 'sand', textColor: 'umber', fontSize: 'large', style: { spacing: { padding: PAD('var:preset|spacing|30') } } } },
		{ tone: 'error', design: { textColor: 'white', style: { color: { background: '#abcdef' }, spacing: { padding: PAD('18px') }, typography: { fontSize: '15px' } } } },
	],
	'notices-v2': [
		{ tone: 'success', design: {} },
		{ tone: 'warning', design: { textColor: 'ink', fontSize: 'huge', className: 'is-style-outlined', style: { color: { background: '#cf2e2e' }, spacing: { padding: PAD('var:preset|spacing|50') } } } },
		{ tone: 'info', design: { textColor: 'teal', fontSize: 'small', className: 'custom-note is-style-outlined' } },
		{ tone: 'info', design: { backgroundColor: 'blush' } },
	],
};

const normClass = (d) => (d.className ? { ...d, className: d.className.split(/\s+/).filter(Boolean).sort().join(' ') } : d);

test.describe('Notice box and statistic with block supports', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	for (const [slug, expected] of Object.entries(EXPECTED)) {
		test(`notices in "${slug}" open valid and are converted`, async ({ page }) => {
			const errors = trackErrors(page);
			await openEditor(page, postId(slug));
			await assertBlocksValid(page);
			const notices = find(await getBlockTree(page), 'acme/notice-box');
			expect(notices.length).toBe(expected.length);
			notices.forEach((n, i) => {
				for (const legacy of LEGACY) expect(n.attributes[legacy], `notice ${i}: ${legacy} must be converted`).toBeUndefined();
				expect(n.attributes.tone).toBe(expected[i].tone);
				expect(normClass(design(n.attributes)), `notice ${i}`).toEqual(normClass(expected[i].design));
				expect(n.innerBlocks.length).toBeGreaterThan(0);
			});
			expect(notices[0].attributes.showIcon !== false || slug === 'notices-v2').toBe(true);
			if (slug === 'notices-v2') expect(notices[0].attributes.showIcon).toBe(false);
			expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
		});
	}

	test('statistics open valid and are converted', async ({ page }) => {
		await openEditor(page, postId('acme-in-numbers', 'page'));
		await assertBlocksValid(page);
		const stats = find(await getBlockTree(page), 'acme/stat');
		expect(stats.length).toBe(3);
		for (const s of stats) for (const legacy of LEGACY) expect(s.attributes[legacy]).toBeUndefined();
		expect(design(stats[0].attributes)).toEqual({});
		expect(stats[0].attributes.value).toBe('99.9%');
		expect(design(stats[1].attributes)).toEqual({ textColor: 'navy', fontSize: 'x-large', className: 'is-style-card' });
		expect(stats[1].attributes.alignment).toBe('center');
		expect(design(stats[2].attributes)).toEqual({ style: { color: { text: '#123456' }, typography: { fontSize: '60px' } } });
		expect(stats[2].attributes.label).toBe('<em>monthly</em> readers');
	});

	test('both blocks use the standard design tools and block styles', async ({ page }) => {
		await newPost(page);
		const info = await page.evaluate(() => {
			const t = (n) => wp.blocks.getBlockType(n);
			const styles = (n) => (wp.data.select('core/blocks').getBlockStyles(n) || []).map((s) => s.name);
			const has = (n, f) => wp.blocks.hasBlockSupport(n, f);
			return {
				notice: {
					background: !!t('acme/notice-box').supports?.color?.background,
					text: t('acme/notice-box').supports?.color?.text !== false && has('acme/notice-box', 'color'),
					padding: !!t('acme/notice-box').supports?.spacing?.padding,
					fontSize: has('acme/notice-box', 'typography.fontSize') || !!t('acme/notice-box').supports?.typography?.fontSize,
					styles: styles('acme/notice-box'),
				},
				stat: {
					text: t('acme/stat').supports?.color?.text !== false && has('acme/stat', 'color'),
					fontSize: has('acme/stat', 'typography.fontSize') || !!t('acme/stat').supports?.typography?.fontSize,
					styles: styles('acme/stat'),
				},
			};
		});
		expect(info.notice).toMatchObject({ background: true, text: true, padding: true, fontSize: true });
		expect(info.notice.styles).toEqual(expect.arrayContaining(['outlined']));
		expect(info.stat).toMatchObject({ text: true, fontSize: true });
		expect(info.stat.styles).toEqual(expect.arrayContaining(['card']));

		// The sidebar shows the theme palette for a selected notice.
		await page.evaluate(() => {
			const block = wp.blocks.createBlock('acme/notice-box', {}, [wp.blocks.createBlock('core/paragraph', { content: 'Hi' })]);
			wp.data.dispatch('core/block-editor').insertBlocks(block);
			wp.data.dispatch('core/block-editor').selectBlock(block.clientId);
			wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-post/block');
		});
		await page.getByRole('tab', { name: 'Styles' }).click().catch(() => {});
		await expect(page.getByRole('button', { name: /^Background/ }).first()).toBeVisible();
		await expect(page.getByText('Padding (px)')).toHaveCount(0);
	});

	test('converted content round-trips through save, reload and the front end', async ({ page }) => {
		const errors = trackErrors(page);
		const id = postId('notices-v2');
		await openEditor(page, id);
		await assertBlocksValid(page);
		// Edit the content like a user fixing a typo, then save.
		await page.evaluate(() => {
			const { select, dispatch } = wp.data;
			const all = (blocks) => blocks.flatMap((b) => [b, ...all(b.innerBlocks)]);
			const para = all(select('core/block-editor').getBlocks()).find(
				(b) => b.name === 'core/paragraph' && String(b.attributes.content).includes('Nested in columns.')
			);
			dispatch('core/block-editor').updateBlockAttributes(para.clientId, { content: 'Nested in columns (edited).' });
		});
		await savePost(page);
		const saved = wp(['post', 'get', String(id), '--field=post_content']);
		expect(saved).not.toMatch(/"bgColor"|"bordered"|"padding":\d|is-bordered/);
		expect(saved).toContain('"backgroundColor":"blush"');
		expect(saved).toContain('has-blush-background-color');

		await openEditor(page, id);
		await assertBlocksValid(page);
		const notices = find(await getBlockTree(page), 'acme/notice-box');
		expect(normClass(design(notices[1].attributes))).toEqual(normClass(EXPECTED['notices-v2'][1].design));

		const html = await (await page.request.get('/notices-v2/')).text();
		expect(html).toMatch(/class="[^"]*has-blush-background-color[^"]*"/);
		expect(html).toMatch(/background-color:\s*#cf2e2e/i);
		expect(html).not.toContain('is-bordered');
		expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
	});

	test('new blocks with presets and custom values save valid markup', async ({ page }) => {
		await newPost(page);
		await page.evaluate(() => {
			const { createBlock } = wp.blocks;
			wp.data.dispatch('core/editor').editPost({ title: 'Fresh blocks', status: 'publish' });
			wp.data.dispatch('core/block-editor').resetBlocks([
				createBlock('acme/notice-box', { tone: 'warning', backgroundColor: 'wine', textColor: 'white', fontSize: 'medium', style: { spacing: { padding: { top: '10px', right: '10px', bottom: '10px', left: '10px' } } }, className: 'is-style-outlined' }, [
					createBlock('core/paragraph', { content: 'Fresh notice' }),
				]),
				createBlock('acme/stat', { value: '7', label: 'days', textColor: 'teal', style: { typography: { fontSize: '40px' } }, alignment: 'right' }),
			]);
		});
		await savePost(page);
		const id = await page.evaluate(() => wp.data.select('core/editor').getCurrentPostId());
		await openEditor(page, id);
		await assertBlocksValid(page);
		const html = await (await page.request.get(`/?p=${id}`)).text();
		const notice = html.match(/<div[^>]*wp-block-acme-notice-box[^>]*>/)?.[0] || '';
		for (const c of ['has-wine-background-color', 'has-white-color', 'has-medium-font-size', 'is-style-outlined', 'is-tone-warning']) expect(notice).toContain(c);
		expect(notice).toMatch(/padding-top:\s*10px/);
		const stat = html.match(/<div[^>]*wp-block-acme-stat[^>]*>/)?.[0] || '';
		for (const c of ['has-teal-color', 'has-text-align-right']) expect(stat).toContain(c);
		expect(stat).toMatch(/font-size:\s*40px/);
	});
});
