// Editorial restrictions in the block editor (hidden E2E tests).
import { test, expect } from '@playwright/test';
import { login, wp, wpEval, openEditor as baseOpenEditor, newPost as baseNewPost, assertBlocksValid, savePost, trackErrors } from '../wpsb/e2e/helpers.mjs';

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


const postId = (slug, type = 'post') =>
	Number(wp(['post', 'list', `--post_type=${type}`, `--name=${slug}`, '--field=ID', '--post_status=any']));

const BLOCKS = ['core/paragraph', 'core/heading', 'core/list', 'core/html', 'core/table', 'core/group', 'core/columns', 'core/embed', 'core/gallery', 'core/image', 'acme/breaking-banner', 'acme/dateline'];

/** canInsertBlockType for a set of blocks in a container ('' = root). */
function canInsert(page, rootClientId = '', names = BLOCKS) {
	return page.evaluate(
		([root, list]) => Object.fromEntries(list.map((n) => [n, window.wp.data.select('core/block-editor').canInsertBlockType(n, root || undefined)])),
		[rootClientId, names]
	);
}

/** Can a list item be added to a (new) list block? */
async function canInsertListItem(page, rootClientId = '') {
	const listId = await page.evaluate((root) => {
		const { createBlock } = window.wp.blocks;
		const list = createBlock('core/list', {}, [createBlock('core/list-item', { content: 'one' })]);
		window.wp.data.dispatch('core/block-editor').insertBlocks(list, undefined, root || undefined);
		return list.clientId;
	}, rootClientId);
	await expect.poll(() => page.evaluate((id) => !!window.wp.data.select('core/block-editor').getBlock(id), listId)).toBe(true);
	return page.evaluate((id) => window.wp.data.select('core/block-editor').canInsertBlockType('core/list-item', id), listId);
}

async function inserterNames(page, rootClientId = '') {
	return page.evaluate((root) => window.wp.data.select('core/block-editor').getInserterItems(root || undefined).map((i) => i.name), rootClientId);
}

const pick = (obj, keys) => Object.fromEntries(keys.map((k) => [k, obj[k]]));

async function bodyGroupId(page) {
	return page.evaluate(() => {
		const b = window.wp.data
			.select('core/block-editor')
			.getBlocks()
			.find((x) => x.name === 'core/group' && ` ${x.attributes.className || ''} `.includes(' press-release__body '));
		return b ? b.clientId : null;
	});
}

test.describe('Allowed blocks per post type and role', () => {
	test('administrators: posts unrestricted, press releases limited to the post type list', async ({ page }) => {
		await login(page);
		await newPost(page);
		const can = await canInsert(page);
		expect(pick(can, ['core/html', 'core/table', 'core/embed', 'core/group', 'core/paragraph', 'acme/breaking-banner'])).toEqual({
			'core/html': true, 'core/table': true, 'core/embed': true, 'core/group': true, 'core/paragraph': true, 'acme/breaking-banner': true,
		});

		await newPost(page, 'press_release');
		const body = await bodyGroupId(page);
		expect(body).not.toBeNull();
		const inBody = await canInsert(page, body);
		expect(pick(inBody, ['core/paragraph', 'core/heading', 'core/image', 'acme/breaking-banner', 'core/html', 'core/table', 'core/columns', 'core/embed'])).toEqual({
			'core/paragraph': true, 'core/heading': true, 'core/image': true, 'acme/breaking-banner': true,
			'core/html': false, 'core/table': false, 'core/columns': false, 'core/embed': false,
		});
		expect(await canInsertListItem(page, body)).toBe(true);
	});

	test('contributors on posts only get their list (plus child blocks)', async ({ page }) => {
		await login(page, 'contributor1', 'password');
		await newPost(page);
		const can = await canInsert(page);
		expect(can).toEqual({
			'core/paragraph': true, 'core/heading': true, 'core/list': true, 'core/html': false, 'core/table': false, 'core/group': false,
			'core/columns': false, 'core/embed': false, 'core/gallery': false, 'core/image': true, 'acme/breaking-banner': true, 'acme/dateline': true,
		});
		expect(await canInsertListItem(page)).toBe(true);
		const items = await inserterNames(page);
		expect(items).toContain('core/paragraph');
		expect(items).not.toContain('core/html');
		expect(items).not.toContain('core/table');

		// Pasting/converting can't sneak in disallowed blocks either.
		const kinds = await page.evaluate(() => {
			const blocks = window.wp.blocks.pasteHandler({ HTML: '<p>Hello</p><table><tbody><tr><td>a</td></tr></tbody></table>', mode: 'BLOCKS' });
			return blocks.map((b) => b.name);
		});
		const allowedAfterPaste = await page.evaluate((names) => names.map((n) => window.wp.data.select('core/block-editor').canInsertBlockType(n)), kinds);
		expect(kinds).toContain('core/table');
		expect(allowedAfterPaste[kinds.indexOf('core/table')]).toBe(false);
	});

	test('authors, multi-role users and editors on posts', async ({ page }) => {
		await login(page, 'author1', 'password');
		await newPost(page);
		expect(pick(await canInsert(page), ['core/embed', 'core/gallery', 'core/html', 'core/table', 'core/paragraph'])).toEqual({
			'core/embed': true, 'core/gallery': true, 'core/html': false, 'core/table': false, 'core/paragraph': true,
		});

		await page.context().clearCookies();
		await login(page, 'stringer1', 'password');
		await newPost(page);
		expect(pick(await canInsert(page), ['core/embed', 'core/gallery', 'core/html', 'core/group', 'core/list', 'acme/breaking-banner'])).toEqual({
			'core/embed': true, 'core/gallery': true, 'core/html': false, 'core/group': false, 'core/list': true, 'acme/breaking-banner': true,
		});

		await page.context().clearCookies();
		await login(page, 'editor1', 'password');
		await newPost(page);
		expect(pick(await canInsert(page), ['core/html', 'core/table', 'core/embed'])).toEqual({ 'core/html': true, 'core/table': true, 'core/embed': true });
	});

	test('press release role lists apply in the body', async ({ page }) => {
		await login(page, 'contributor1', 'password');
		await newPost(page, 'press_release');
		const body = await bodyGroupId(page);
		expect(body).not.toBeNull();
		expect(pick(await canInsert(page, body), ['core/paragraph', 'core/list', 'core/heading', 'core/image', 'core/html', 'acme/breaking-banner'])).toEqual({
			'core/paragraph': true, 'core/list': true, 'core/heading': false, 'core/image': false, 'core/html': false, 'acme/breaking-banner': false,
		});
		expect(await canInsertListItem(page, body)).toBe(true);
	});

	test('post types without rules are unrestricted; rule changes apply the next time the editor opens', async ({ page }) => {
		await login(page, 'editor1', 'password');
		await newPost(page, 'page');
		expect(pick(await canInsert(page), ['core/html', 'core/table', 'core/group'])).toEqual({ 'core/html': true, 'core/table': true, 'core/group': true });

		const original = wpEval(`echo wp_json_encode( get_option( 'acme_newsroom_block_rules' ) );`);
		try {
			wpEval(`$r = get_option( 'acme_newsroom_block_rules' ); $r['post_types']['post']['roles']['contributor'][] = 'core/table'; $r['post_types']['page'] = array( 'allowed' => array( 'core/paragraph', 'core/table', 'acme/*' ), 'roles' => array( 'editor' => array( 'core/paragraph', 'core/*' ) ) ); update_option( 'acme_newsroom_block_rules', $r );`);
			await newPost(page, 'page');
			expect(pick(await canInsert(page), ['core/paragraph', 'core/table', 'core/html', 'acme/breaking-banner'])).toEqual({
				'core/paragraph': true, 'core/table': true, 'core/html': false, 'acme/breaking-banner': false,
			});
			await page.context().clearCookies();
			await login(page, 'contributor1', 'password');
			await newPost(page);
			expect(pick(await canInsert(page), ['core/table', 'core/html'])).toEqual({ 'core/table': true, 'core/html': false });
		} finally {
			wpEval(`update_option( 'acme_newsroom_block_rules', json_decode( ${JSON.stringify(JSON.stringify(JSON.parse(original)))} , true ) );`);
		}
	});
});

test.describe('Existing content with disallowed blocks', () => {
	test('a contributor can open and save a draft that contains blocks they may not add', async ({ page }) => {
		const errors = trackErrors(page);
		await login(page, 'contributor1', 'password');
		const id = Number(wp(['post', 'list', '--post_type=post', '--post_status=draft', '--title=Widget pros and cons', '--field=ID']));
		expect(id).toBeGreaterThan(0);
		await openEditor(page, id);
		await assertBlocksValid(page);
		const names = await page.evaluate(() => window.wp.data.select('core/block-editor').getBlocks().map((b) => b.name));
		expect(names).toEqual(['core/paragraph', 'core/html', 'core/columns', 'core/preformatted']);
		await page.evaluate(() => {
			const [p] = window.wp.data.select('core/block-editor').getBlocks();
			window.wp.data.dispatch('core/block-editor').updateBlockAttributes(p.clientId, { content: 'Draft, revised by the contributor.' });
		});
		const saved = await savePost(page);
		expect(saved).toContain('Draft, revised by the contributor.');
		expect(saved).toContain('<!-- wp:html -->');
		expect(saved).toContain('poll-widget');
		expect(saved).toContain('<!-- wp:columns -->');
		expect(saved).toContain('Contra: higher prices.');
		expect(saved).toContain('<!-- wp:preformatted -->');
		const stored = wp(['post', 'get', String(id), '--field=post_content']);
		expect(stored).toContain('poll-widget');
		expect(stored).toContain('Pro: faster widgets.');

		await openEditor(page, id);
		await assertBlocksValid(page);
		expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
	});

	test('a very old press release keeps its content', async ({ page }) => {
		await login(page);
		const id = postId('new-headquarters', 'press_release');
		await openEditor(page, id);
		await assertBlocksValid(page);
		const names = await page.evaluate(() => window.wp.data.select('core/block-editor').getBlocks().map((b) => b.name));
		expect(names).toEqual(['core/paragraph', 'core/html', 'core/paragraph']);
		await page.evaluate(() => window.wp.data.dispatch('core/editor').editPost({ excerpt: 'HQ opening' }));
		await savePost(page);
		const stored = wp(['post', 'get', String(id), '--field=post_content']);
		expect(stored).toContain('<iframe title="Headquarters tour"');
		expect(stored).not.toContain('wp:acme/dateline');
	});
});

test.describe('Press release structure', () => {
	const STRUCTURE = ['acme/dateline', 'core/paragraph', 'core/group', 'acme/boilerplate', 'acme/media-contact'];

	async function structureState(page) {
		return page.evaluate(() => {
			const s = window.wp.data.select('core/block-editor');
			const root = s.getBlocks();
			return {
				names: root.map((b) => b.name),
				lead: root[1] ? String(root[1].attributes.className || '') : '',
				body: root[2] ? String(root[2].attributes.className || '') : '',
				bodyChildren: root[2] ? root[2].innerBlocks.map((b) => b.name) : [],
				removable: root.map((b) => s.canRemoveBlock(b.clientId)),
				movable: root.map((b) => s.canMoveBlock(b.clientId)),
				insertAtRoot: ['core/paragraph', 'core/heading', 'acme/dateline'].map((n) => s.canInsertBlockType(n)),
			};
		});
	}

	test('new press releases start with the locked structure and a free-form body', async ({ page }) => {
		const errors = trackErrors(page);
		await login(page, 'author1', 'password');
		await newPost(page, 'press_release');
		const st = await structureState(page);
		expect(st.names).toEqual(STRUCTURE);
		expect(st.lead.split(/\s+/)).toContain('press-release__lead');
		expect(st.body.split(/\s+/)).toContain('press-release__body');
		expect(st.bodyChildren).toEqual(['core/paragraph']);
		expect(st.removable).toEqual([false, false, false, false, false]);
		expect(st.movable).toEqual([false, false, false, false, false]);
		expect(st.insertAtRoot).toEqual([false, false, false]);

		const body = await bodyGroupId(page);
		// Body: add, move and remove blocks freely.
		const ids = await page.evaluate((b) => {
			const { createBlock } = window.wp.blocks;
			const a = createBlock('core/paragraph', { content: 'Second body paragraph.' });
			const h = createBlock('core/heading', { content: 'Background', level: 2 });
			window.wp.data.dispatch('core/block-editor').insertBlocks([a, h], undefined, b);
			return [a.clientId, h.clientId];
		}, body);
		await expect.poll(() => page.evaluate((b) => window.wp.data.select('core/block-editor').getBlockCount(b), body)).toBe(3);
		const bodyCan = await page.evaluate(([a, h]) => {
			const s = window.wp.data.select('core/block-editor');
			return { removeA: s.canRemoveBlock(a), moveH: s.canMoveBlock(h) };
		}, ids);
		expect(bodyCan).toEqual({ removeA: true, moveH: true });
		await page.evaluate((h) => window.wp.data.dispatch('core/block-editor').moveBlocksUp([h], window.wp.data.select('core/block-editor').getBlockRootClientId(h)), ids[1]);
		await expect.poll(() => page.evaluate((b) => window.wp.data.select('core/block-editor').getBlocks(b).map((x) => x.name), body)).toEqual(['core/paragraph', 'core/heading', 'core/paragraph']);

		// Texts stay editable: type the lead paragraph.
		const canvas = page.frameLocator('iframe[name="editor-canvas"]');
		const lead = await page.evaluate(() => window.wp.data.select('core/block-editor').getBlocks()[1].clientId);
		await canvas.locator(`[data-block="${lead}"]`).click();
		await page.keyboard.type('Acme launches the widget 3000.');
		await expect.poll(() => page.evaluate((id) => String(window.wp.data.select('core/block-editor').getBlockAttributes(id).content), lead)).toBe('Acme launches the widget 3000.');

		await page.evaluate(() => window.wp.data.dispatch('core/editor').editPost({ title: 'Widget 3000', status: 'pending' }));
		const saved = await savePost(page);
		expect(saved).toContain('<!-- wp:acme/dateline');
		expect(saved).toContain('Acme launches the widget 3000.');
		expect(saved).toContain('Background');
		expect((saved.match(/<!-- wp:acme\/boilerplate/g) || []).length).toBe(1);

		const id = await page.evaluate(() => window.wp.data.select('core/editor').getCurrentPostId());
		await openEditor(page, id);
		await assertBlocksValid(page);
		expect((await structureState(page)).names).toEqual(STRUCTURE);
		expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
	});

	test('existing 3.x press releases get the same rules', async ({ page }) => {
		await login(page, 'editor1', 'password');
		await openEditor(page, postId('q3-results', 'press_release'));
		await assertBlocksValid(page);
		const st = await structureState(page);
		expect(st.names).toEqual(STRUCTURE);
		expect(st.bodyChildren).toEqual(['core/paragraph', 'core/html', 'core/paragraph']);
		expect(st.removable).toEqual([false, false, false, false, false]);
		expect(st.movable).toEqual([false, false, false, false, false]);
		expect(st.insertAtRoot).toEqual([false, false, false]);

		const body = await bodyGroupId(page);
		const children = await page.evaluate((b) => window.wp.data.select('core/block-editor').getBlockOrder(b), body);
		const can = await page.evaluate(([b, kids]) => {
			const s = window.wp.data.select('core/block-editor');
			return { insertParagraph: s.canInsertBlockType('core/paragraph', b), insertHtml: s.canInsertBlockType('core/html', b), removeChild: s.canRemoveBlock(kids[2]), moveChild: s.canMoveBlock(kids[0]) };
		}, [body, children]);
		expect(can).toEqual({ insertParagraph: true, insertHtml: false, removeChild: true, moveChild: true });

		// The existing HTML block stays and survives a save.
		await page.evaluate((b) => {
			const p = window.wp.blocks.createBlock('core/paragraph', { content: 'Added by the editor.' });
			window.wp.data.dispatch('core/block-editor').insertBlocks(p, undefined, b);
		}, body);
		const saved = await savePost(page);
		expect(saved).toContain('data-symbol="ACME"');
		expect(saved).toContain('Added by the editor.');
		const res = await page.request.get('/press/q3-results/', { timeout: 120_000 });
		const html = await res.text();
		expect(html).toContain('Added by the editor.');
		expect(html).toContain('ACME +2.4%');
	});
});

/** Open the design tools of a new paragraph and report whether custom values are offered. */
async function designTools(page) {
	await newPost(page);
	const id = await page.evaluate(() => {
		const b = window.wp.blocks.createBlock('core/paragraph', { content: 'Styled text' });
		window.wp.data.dispatch('core/block-editor').insertBlocks(b);
		return b.clientId;
	});
	await page.evaluate((cid) => {
		window.wp.data.dispatch('core/preferences').set('core/edit-post', 'welcomeGuide', false);
		window.wp.data.dispatch('core/block-editor').selectBlock(cid);
		window.wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-post/block');
	}, id);
	const guide = page.getByRole('dialog', { name: /welcome/i });
	if (await guide.count()) await guide.getByRole('button', { name: 'Close' }).click();
	const inspector = page.locator('.block-editor-block-inspector');
	await expect(inspector).toBeVisible();
	const stylesTab = page.getByRole('tab', { name: 'Styles' });
	if (await stylesTab.count()) await stylesTab.first().click();

	// Text color dropdown ("Text" in the color panel, or "Color" in the typography panel).
	const textColor = inspector.getByRole('button', { name: /^(Text|Color)$/ }).first();
	await expect(textColor).toBeVisible();
	await textColor.click();
	const popover = page.locator('.components-popover').filter({ has: page.locator('.components-circular-option-picker, .components-color-palette') }).last();
	await expect(popover).toBeVisible();
	const palette = await popover.locator('.components-circular-option-picker__option').count();
	const customColor = await popover.getByRole('button', { name: /custom color/i }).count();
	await page.keyboard.press('Escape');

	// Font size.
	const fontSize = inspector.locator('.components-font-size-picker').first();
	await expect(fontSize).toBeVisible();
	const customSize = await fontSize.getByRole('button', { name: /custom size/i }).count();
	const customInput = await fontSize.locator('input[type="number"], .components-unit-control input').count();
	return { palette: palette > 0, customColor: customColor > 0, customSize: customSize > 0 || customInput > 0 };
}

test.describe('Design tools per role', () => {
	test('administrators keep custom colors and sizes', async ({ page }) => {
		await login(page);
		expect(await designTools(page)).toEqual({ palette: true, customColor: true, customSize: true });
	});

	test('contributors only get the palette and the theme font sizes', async ({ page }) => {
		await login(page, 'contributor1', 'password');
		expect(await designTools(page)).toEqual({ palette: true, customColor: false, customSize: false });
	});

	test('authors lose custom colors only; multi-role users lose both', async ({ page }) => {
		await login(page, 'author1', 'password');
		expect(await designTools(page)).toEqual({ palette: true, customColor: false, customSize: true });
		await page.context().clearCookies();
		await login(page, 'stringer1', 'password');
		expect(await designTools(page)).toEqual({ palette: true, customColor: false, customSize: false });
	});
});
