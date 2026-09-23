// Site Editor / block editor behaviour of the automatically placed signup form.
import { test, expect } from '@playwright/test';
import {
	login,
	wp,
	wpEval,
	waitForEditor,
	openEditor,
	newPost,
	assertBlocksValid,
	getBlockTree,
	insertBlock,
	savePost,
	trackErrors,
} from '../wpsb/e2e/helpers.mjs';

const TEMPLATE_ID = 'twentytwentyfive//single';

function flatten(tree, out = []) {
	for (const b of tree) {
		out.push(b);
		flatten(b.innerBlocks || [], out);
	}
	return out;
}

/** Sibling list containing core/post-content, and its index. */
function postContentSiblings(tree) {
	for (let i = 0; i < tree.length; i++) {
		if (tree[i].name === 'core/post-content') return { siblings: tree, index: i };
		const found = postContentSiblings(tree[i].innerBlocks || []);
		if (found) return found;
	}
	return null;
}

async function openTemplate(page) {
	await page.goto(`/wp-admin/site-editor.php?p=${encodeURIComponent('/wp_template/' + TEMPLATE_ID)}&canvas=edit`);
	await waitForEditor(page);
	await page.waitForFunction(
		() => window.wp.data.select('core/block-editor').getBlocks().length > 0,
		null,
		{ timeout: 120_000 }
	);
	await page.waitForTimeout(1000);
}

function countForms(html) {
	return (html.match(/<form[^>]*class="[^"]*\bacme-newsletter__form\b/g) || []).length;
}

test.describe('Newsletter form in the Site Editor', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test.afterEach(() => {
		// Restore the seeded (customized) Single template and drop saved template parts.
		wpEval(`
			$t = get_posts( array( 'post_type' => 'wp_template', 'name' => 'single', 'post_status' => 'any', 'numberposts' => 1 ) );
			if ( $t ) {
				$orig = get_option( 'wpsb_e2e_single_backup' );
				if ( $orig ) {
					global $wpdb;
					$wpdb->update( $wpdb->posts, array( 'post_content' => $orig ), array( 'ID' => $t[0]->ID ) );
					clean_post_cache( $t[0]->ID );
				}
			}
			delete_option( 'wpsb_e2e_single_backup' );
		`);
	});

	test('the Single template shows the signup block after the post content', async ({ page }) => {
		wpEval(`$t = get_posts( array( 'post_type' => 'wp_template', 'name' => 'single', 'post_status' => 'any', 'numberposts' => 1 ) ); update_option( 'wpsb_e2e_single_backup', $t[0]->post_content, false );`);
		const errors = trackErrors(page);
		await openTemplate(page);
		await assertBlocksValid(page);
		const tree = await getBlockTree(page);
		const pc = postContentSiblings(tree);
		expect(pc, 'core/post-content not found in the template').not.toBeNull();
		expect(pc.siblings[pc.index + 1]?.name, 'block after the post content').toBe('acme/newsletter-signup');
		expect(flatten(tree).filter((b) => b.name === 'acme/newsletter-signup').length).toBe(1);

		// The editor canvas renders the block (not a "missing block" / error placeholder).
		const canvas = page.frameLocator('iframe[name="editor-canvas"]');
		await expect(canvas.locator('.wp-block-acme-newsletter-signup').first()).toBeVisible({ timeout: 60_000 });
		expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
	});

	test('removing it in the Site Editor and saving is respected on the front end', async ({ page }) => {
		test.setTimeout(300_000);
		wpEval(`$t = get_posts( array( 'post_type' => 'wp_template', 'name' => 'single', 'post_status' => 'any', 'numberposts' => 1 ) ); update_option( 'wpsb_e2e_single_backup', $t[0]->post_content, false );`);
		await openTemplate(page);
		const clientId = await page.evaluate(() => {
			const { select } = window.wp.data;
			const all = (blocks) => blocks.flatMap((b) => [b, ...all(b.innerBlocks)]);
			const b = all(select('core/block-editor').getBlocks()).find((x) => x.name === 'acme/newsletter-signup');
			return b ? b.clientId : null;
		});
		expect(clientId, 'signup block not in the template').not.toBeNull();
		await page.evaluate((id) => window.wp.data.dispatch('core/block-editor').removeBlock(id), clientId);
		await page.evaluate(async (tid) => {
			await window.wp.data.dispatch('core').saveEditedEntityRecord('postType', 'wp_template', tid);
		}, TEMPLATE_ID);
		await page.waitForFunction(
			(tid) => !window.wp.data.select('core').isSavingEntityRecord('postType', 'wp_template', tid),
			TEMPLATE_ID,
			{ timeout: 60_000 }
		);

		const res = await page.request.get('/welcome-to-the-new-blog/');
		expect(res.status()).toBe(200);
		const html = await res.text();
		expect(html).toContain('Thanks for reading the Acme blog.');
		const footerAt = html.indexOf('<footer');
		expect(footerAt).toBeGreaterThan(-1);
		expect(countForms(html.slice(0, footerAt)), 'removed form must not come back').toBe(0);
		expect(countForms(html.slice(footerAt)), 'footer form stays').toBe(1);

		// The Site Editor does not get it re-inserted either.
		const raw = await page.evaluate(async (tid) => {
			const t = await window.wp.apiFetch({ path: `/wp/v2/templates/${tid}?context=edit` });
			return t.content.raw;
		}, TEMPLATE_ID);
		expect(raw).not.toContain('wp:acme/newsletter-signup');
	});

	test('a signup block placed in a post is the only form after its content', async ({ page }) => {
		test.setTimeout(300_000);
		const errors = trackErrors(page);
		await newPost(page);
		await page.evaluate(() => window.wp.data.dispatch('core/editor').editPost({ title: 'E2E manual signup', status: 'publish' }));
		await insertBlock(page, 'core/paragraph', { content: 'Hello from the editor.' });
		await insertBlock(page, 'acme/newsletter-signup', { heading: 'Manual form' });
		await assertBlocksValid(page);
		await savePost(page);
		const id = await page.evaluate(() => window.wp.data.select('core/editor').getCurrentPostId());
		await openEditor(page, id);
		await assertBlocksValid(page);
		const link = wp(['post', 'list', '--post_type=post', `--post__in=${id}`, '--field=url']);
		const res = await page.request.get(link);
		const html = await res.text();
		const main = html.slice(0, html.indexOf('<footer'));
		expect(countForms(main), 'exactly one form outside the footer').toBe(1);
		expect(main).toContain('Manual form');
		expect(countForms(html)).toBe(2);
		expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
		wp(['post', 'delete', String(id), '--force']);
	});
});
