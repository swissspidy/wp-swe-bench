// The imported (URL-rewritten) content in the block editor and on the front end.
import { test, expect } from '@playwright/test';
import { login, wp, openEditor, assertBlocksValid, getBlockTree, trackErrors } from '../wpsb/e2e/helpers.mjs';

const SITE = process.env.WPSB_URL || 'http://127.0.0.1:9400';
const postId = (slug) => Number(wp(['post', 'list', '--post_type=post', `--name=${slug}`, '--field=ID']));

const find = (tree, name, out = []) => {
	for (const b of tree) {
		if (b.name === name) out.push(b);
		find(b.innerBlocks || [], name, out);
	}
	return out;
};

test.describe('Imported content', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	for (const slug of ['summer-in-the-alps', 'granite-pattern', 'follow-us', 'old-landing-page', 'packing-tips']) {
		test(`"${slug}" opens in the editor without block errors`, async ({ page }) => {
			const errors = trackErrors(page);
			const id = postId(slug);
			expect(id).toBeGreaterThan(0);
			await openEditor(page, id);
			await assertBlocksValid(page);
			expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
		});
	}

	test('cover blocks show the image from this site', async ({ page }) => {
		const id = postId('summer-in-the-alps');
		await openEditor(page, id);
		const covers = find(await getBlockTree(page), 'core/cover');
		expect(covers).toHaveLength(1);
		expect(covers[0].attributes.url).toBe(`${SITE}/wp-content/uploads/2024/05/alps-hero.jpg`);

		const html = await (await page.request.get(`/?p=${id}`)).text();
		expect(html).not.toContain('oldblog.example');
		expect(html).toMatch(/background-image:\s*url\(\s*(?:"|'|&quot;)?http:\/\/127\.0\.0\.1:9400\/wp-content\/uploads\/2024\/05\/alps-hero\.jpg/);
	});

	test('social icons are separate blocks in the editor', async ({ page }) => {
		await openEditor(page, postId('follow-us'));
		const tree = await getBlockTree(page);
		expect(tree.map((b) => b.name)).toEqual(['core/paragraph', 'core/social-links', 'core/latest-posts', 'core/paragraph']);
		const links = find(tree, 'core/social-link');
		expect(links.map((b) => b.attributes.url)).toEqual([`${SITE}/feed/`, 'https://mastodon.example/@alpinetrails', `${SITE}/newsletter/`]);
		expect(links.every((b) => b.innerBlocks.length === 0)).toBe(true);
	});
});
