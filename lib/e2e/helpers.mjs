// Shared Playwright helpers for wp-swe-bench hidden E2E tests.
//
//   import { test, expect } from '@playwright/test';
//   import { login, wp, openEditor, assertBlocksValid, ... } from '../wpsb/e2e/helpers.mjs';
//
// The Playground server runs on WPSB_URL (default http://127.0.0.1:9400), admin/password.
import { expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';

export const BASE_URL = process.env.WPSB_URL || 'http://127.0.0.1:9400';

/** Run native WP-CLI synchronously against /wordpress; returns trimmed stdout. */
export function wp(args, { input } = {}) {
	const argv = Array.isArray(args) ? args : shellSplit(args);
	return execFileSync('wp', argv, { encoding: 'utf8', input, stdio: ['pipe', 'pipe', 'pipe'], maxBuffer: 64 * 1024 * 1024 }).trim();
}

/** Evaluate PHP inside WordPress (native) and return stdout. */
export function wpEval(php) {
	return wp(['eval', php]);
}

/** Create a post via WP-CLI and return its ID. content is raw post_content (block markup). */
export function createPost({ title = 'E2E post', content = '', status = 'publish', type = 'post', meta = {} } = {}) {
	const id = Number(
		wp(['eval', `echo wp_insert_post( array( 'post_title' => ${phpStr(title)}, 'post_content' => ${phpStr(content)}, 'post_status' => ${phpStr(status)}, 'post_type' => ${phpStr(type)}, 'post_author' => 1 ), true );`])
	);
	for (const [k, v] of Object.entries(meta)) {
		wp(['eval', `update_post_meta( ${id}, ${phpStr(k)}, json_decode( ${phpStr(JSON.stringify(v))}, true ) );`]);
	}
	return id;
}

export function phpStr(s) {
	return "'" + String(s).replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";
}

function shellSplit(s) {
	const out = [];
	let cur = '', q = null;
	for (const ch of s) {
		if (q) { if (ch === q) q = null; else cur += ch; }
		else if (ch === '"' || ch === "'") q = ch;
		else if (/\s/.test(ch)) { if (cur) { out.push(cur); cur = ''; } }
		else cur += ch;
	}
	if (cur) out.push(cur);
	return out;
}

/**
 * Log in through wp-login.php.
 *
 * wp-login.php focuses and selects #user_login from a 200 ms timer after load; typing too
 * early can end up in the wrong field. Wait for it, verify the values, retry if needed.
 */
export async function login(page, user = 'admin', pass = 'password') {
	for (let attempt = 1; attempt <= 3; attempt++) {
		await page.goto('/wp-login.php');
		await page.waitForLoadState('load');
		await page.waitForTimeout(500);
		await page.fill('#user_login', user);
		await page.fill('#user_pass', pass);
		if ((await page.inputValue('#user_login')) !== user || (await page.inputValue('#user_pass')) !== pass) {
			continue;
		}
		await Promise.all([page.waitForURL(/wp-admin/, { timeout: 120_000 }), page.click('#wp-submit')]);
		return;
	}
	throw new Error(`login(${user}) failed: could not fill the login form`);
}

/** Collect console errors + page errors for later assertions. */
export function trackErrors(page) {
	const errors = [];
	page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`));
	page.on('console', (m) => {
		if (m.type() === 'error') errors.push(`console.error: ${m.text()}`);
	});
	return errors;
}

/** Wait until the block editor is ready (data stores + canvas). */
export async function waitForEditor(page) {
	await page.waitForFunction(
		() => window.wp?.data?.select('core/editor')?.getCurrentPostId?.() && window.wp?.data?.select('core/block-editor'),
		null,
		{ timeout: 120_000 }
	);
	await page.evaluate(() => {
		const prefs = window.wp.data.dispatch('core/preferences');
		prefs?.set('core/edit-post', 'welcomeGuide', false);
		prefs?.set('core/edit-site', 'welcomeGuide', false);
		prefs?.set('core', 'enableChoosePatternModal', false);
	});
	// Let block parsing / validation settle.
	await page.waitForFunction(() => !window.wp.data.select('core/editor').isSavingPost?.(), null, { timeout: 60_000 });
	await page.waitForTimeout(500);
}

/** Open an existing post in the block editor. */
export async function openEditor(page, postId) {
	// Stale edit locks from earlier tests (other users) would show the "post taken over" modal.
	try { wp(['post', 'meta', 'delete', String(postId), '_edit_lock']); } catch {}
	await page.goto(`/wp-admin/post.php?post=${postId}&action=edit`);
	await waitForEditor(page);
}

/** Open a new post of a given type in the block editor. */
export async function newPost(page, postType = 'post') {
	await page.goto(`/wp-admin/post-new.php?post_type=${postType}`);
	await waitForEditor(page);
}

/**
 * Walk all blocks in the editor and return invalid ones
 * [{ clientId, name, path, issues }]. A block is invalid if the editor
 * flagged it ("This block contains unexpected or invalid content") or if it
 * was parsed into core/missing (unregistered block type).
 */
export async function getInvalidBlocks(page, { allowMissing = false } = {}) {
	return page.evaluate((allowMissingArg) => {
		const { select } = window.wp.data;
		const out = [];
		const walk = (blocks, path) => {
			blocks.forEach((b, i) => {
				const p = `${path}${path ? ' > ' : ''}${b.name}[${i}]`;
				if (b.isValid === false) {
					out.push({
						clientId: b.clientId,
						name: b.name,
						path: p,
						issues: (b.validationIssues || []).map((x) => {
							try { return x.args ? x.args.map(String).join(' ').slice(0, 500) : String(x); }
							catch { return 'unprintable issue'; }
						}),
					});
				}
				if (!allowMissingArg && b.name === 'core/missing') {
					out.push({ clientId: b.clientId, name: b.name, path: p, issues: [`unregistered block: ${b.attributes?.originalName}`] });
				}
				walk(b.innerBlocks || [], p);
			});
		};
		walk(select('core/block-editor').getBlocks(), '');
		return out;
	}, allowMissing);
}

/** Assert that every block in the currently open editor is valid. */
export async function assertBlocksValid(page, options = {}) {
	const invalid = await getInvalidBlocks(page, options);
	expect(invalid, `Invalid blocks:\n${JSON.stringify(invalid, null, 2)}`).toEqual([]);
	// Belt and braces: the visible warning must not be present in the canvas.
	const canvas = page.frameLocator('iframe[name="editor-canvas"]');
	const warning = /(This block contains unexpected or invalid content|Block contains unexpected or invalid content)/i;
	const hasIframe = (await page.locator('iframe[name="editor-canvas"]').count()) > 0;
	const scope = hasIframe ? canvas.locator('body') : page.locator('.editor-styles-wrapper');
	await expect(scope).not.toContainText(warning, { timeout: 5_000 });
}

/** Open a post in the editor and assert all its blocks are valid. */
export async function assertPostBlocksValid(page, postId, options = {}) {
	await openEditor(page, postId);
	await assertBlocksValid(page, options);
}

/** Get the editor's block tree (name, attributes, innerBlocks) for assertions. */
export async function getBlockTree(page) {
	return page.evaluate(() => {
		const strip = (blocks) => blocks.map((b) => ({ name: b.name, attributes: b.attributes, innerBlocks: strip(b.innerBlocks || []) }));
		return strip(window.wp.data.select('core/block-editor').getBlocks());
	});
}

/** Insert a block programmatically (like the inserter would) and return its clientId. */
export async function insertBlock(page, name, attributes = {}, innerBlocks = []) {
	return page.evaluate(({ name, attributes, innerBlocks }) => {
		const { createBlock } = window.wp.blocks;
		const build = (spec) => createBlock(spec[0], spec[1] || {}, (spec[2] || []).map(build));
		const block = createBlock(name, attributes, innerBlocks.map(build));
		window.wp.data.dispatch('core/block-editor').insertBlocks(block);
		return block.clientId;
	}, { name, attributes, innerBlocks });
}

/** Save the current post and wait until saving has finished; returns the saved post_content. */
export async function savePost(page) {
	await page.evaluate(() => window.wp.data.dispatch('core/editor').savePost());
	await page.waitForFunction(() => {
		const s = window.wp.data.select('core/editor');
		return !s.isSavingPost() && !s.isAutosavingPost();
	}, null, { timeout: 120_000 });
	return page.evaluate(() => window.wp.data.select('core/editor').getEditedPostContent());
}

/** Serialized content as the editor would save it right now. */
export async function getEditedContent(page) {
	return page.evaluate(() => window.wp.data.select('core/editor').getEditedPostContent());
}

/** Is a block type registered in the editor? Returns its settings (or null). */
export async function getBlockType(page, name) {
	return page.evaluate((n) => {
		const t = window.wp.blocks.getBlockType(n);
		if (!t) return null;
		return JSON.parse(JSON.stringify({ name: t.name, title: t.title, attributes: t.attributes, supports: t.supports, parent: t.parent, ancestor: t.ancestor, variations: (t.variations || []).map((v) => ({ name: v.name, title: v.title, attributes: v.attributes, scope: v.scope, isDefault: v.isDefault })), deprecatedCount: (t.deprecated || []).length, transforms: t.transforms ? { from: (t.transforms.from || []).map((x) => ({ type: x.type, blocks: x.blocks, tag: x.tag })), to: (t.transforms.to || []).map((x) => ({ type: x.type, blocks: x.blocks })) } : null }));
	}, name);
}

/** Select a block in the editor canvas by clientId. */
export async function selectBlock(page, clientId) {
	await page.evaluate((id) => window.wp.data.dispatch('core/block-editor').selectBlock(id), clientId);
}

/** Front-end HTML of a URL (fetched in the page context, logged-out unless page is logged in). */
export async function fetchHtml(page, url) {
	const res = await page.request.get(url);
	return { status: res.status(), html: await res.text() };
}
