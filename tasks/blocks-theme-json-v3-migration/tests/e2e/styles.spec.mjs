// Rendered styles of the Acme Magazine style guide (front end + editor), hidden E2E tests.
import { test, expect } from '@playwright/test';
import { login, openEditor, assertBlocksValid, wp, wpEval } from '../wpsb/e2e/helpers.mjs';

const SLUG = 'style-guide';
const postId = () => Number(wp(['post', 'list', '--post_type=post', `--name=${SLUG}`, '--field=ID']));

/** Computed style properties for selectors (in a frame or page). */
async function styles(target, spec) {
	return target.evaluate((spec) => {
		const out = {};
		for (const [sel, props] of Object.entries(spec)) {
			const el = document.querySelector(sel);
			if (!el) {
				out[sel] = null;
				continue;
			}
			const cs = getComputedStyle(el);
			out[sel] = Object.fromEntries(props.map((p) => [p, cs.getPropertyValue(p).trim()]));
		}
		return out;
	}, spec);
}

const USER_STYLES_PHP = (styles) => `
$id = WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
$data = json_decode( get_post( $id )->post_content, true );
$data['styles'] = array_replace_recursive( $data['styles'] ?? array(), json_decode( '${JSON.stringify(styles)}', true ) );
wp_update_post( array( 'ID' => $id, 'post_content' => wp_json_encode( $data ) ) );
`;

let savedUserStyles;

test.describe('Acme Magazine styles', () => {
	test.beforeAll(() => {
		savedUserStyles = wpEval('echo get_post( WP_Theme_JSON_Resolver::get_user_global_styles_post_id() )->post_content;');
	});
	test.afterEach(() => {
		const b64 = Buffer.from(savedUserStyles).toString('base64');
		wpEval(`wp_update_post( array( 'ID' => WP_Theme_JSON_Resolver::get_user_global_styles_post_id(), 'post_content' => base64_decode( '${b64}' ) ) );`);
	});

	test('desktop: presets, headings, section styles and elements look as before', async ({ page }) => {
		await page.setViewportSize({ width: 1440, height: 1000 });
		await page.goto(`/${SLUG}/`);
		const s = await styles(page, {
			body: ['background-color', 'color'],
			'.sg-h1': ['font-size'],
			'.sg-h2': ['font-size'],
			'.sg-h3': ['font-size'],
			'h1.wp-block-post-title': ['font-size'],
			'.sg-small': ['font-size'],
			'.sg-body': ['font-size'],
			'.sg-large': ['font-size'],
			'.sg-xlarge': ['font-size'],
			'.sg-huge': ['font-size'],
			'.sg-colors': ['color', 'background-color'],
			'.sg-spaced': ['padding-top', 'padding-left'],
			'.sg-card': ['background-color', 'border-top-width', 'border-top-style', 'border-top-color', 'border-left-width', 'border-top-left-radius', 'padding-top', 'padding-left', 'padding-bottom', 'box-shadow'],
			'.sg-card-link': ['color'],
			'.sg-inverted': ['background-color', 'color', 'padding-top', 'padding-bottom', 'padding-left', 'padding-right'],
			'.sg-inverted-link': ['color'],
			'.sg-inverted-heading': ['color'],
			'.sg-quote': ['border-left-width', 'border-left-style', 'border-left-color', 'padding-left', 'font-style'],
			'.sg-button .wp-block-button__link': ['border-top-left-radius', 'padding-top', 'padding-left', 'background-color'],
			'.is-style-kicker': ['color', 'font-size', 'letter-spacing', 'text-transform'],
		});
		expect(s.body).toEqual({ 'background-color': 'rgb(250, 247, 242)', color: 'rgb(28, 25, 23)' });
		expect(s['.sg-h1']['font-size']).toBe('56px');
		expect(s['.sg-h2']['font-size']).toBe('40px');
		expect(s['.sg-h3']['font-size']).toBe('28px');
		expect(s['h1.wp-block-post-title']['font-size']).toBe('56px');
		expect(s['.sg-small']['font-size']).toBe('14px');
		expect(s['.sg-body']['font-size']).toBe('18px');
		expect(s['.sg-large']['font-size']).toBe('28px');
		expect(s['.sg-xlarge']['font-size']).toBe('40px');
		expect(s['.sg-huge']['font-size']).toBe('56px');
		expect(s['.sg-colors']).toEqual({ color: 'rgb(194, 65, 12)', 'background-color': 'rgb(253, 230, 138)' });
		expect(s['.sg-spaced']).toEqual({ 'padding-top': '32px', 'padding-left': '48px' });
		expect(s['.sg-card']).toEqual({
			'background-color': 'rgb(255, 255, 255)',
			'border-top-width': '1px',
			'border-top-style': 'solid',
			'border-top-color': 'rgb(228, 223, 214)',
			'border-left-width': '1px',
			'border-top-left-radius': '12px',
			'padding-top': '32px',
			'padding-left': '32px',
			'padding-bottom': '32px',
			'box-shadow': 'rgba(28, 25, 23, 0.12) 0px 1px 3px 0px',
		});
		expect(s['.sg-card-link'].color).toBe('rgb(194, 65, 12)');
		expect(s['.sg-inverted']).toEqual({
			'background-color': 'rgb(22, 22, 22)',
			color: 'rgb(246, 243, 238)',
			'padding-top': '48px',
			'padding-bottom': '48px',
			'padding-left': '32px',
			'padding-right': '32px',
		});
		expect(s['.sg-inverted-link'].color).toBe('rgb(255, 179, 71)');
		expect(s['.sg-inverted-heading'].color).toBe('rgb(255, 255, 255)');
		expect(s['.sg-quote']).toEqual({ 'border-left-width': '4px', 'border-left-style': 'solid', 'border-left-color': 'rgb(194, 65, 12)', 'padding-left': '24px', 'font-style': 'italic' });
		// Pills + the site's saved Styles customization (accent button background).
		expect(s['.sg-button .wp-block-button__link']).toEqual({ 'border-top-left-radius': '999px', 'padding-top': '12px', 'padding-left': '24px', 'background-color': 'rgb(194, 65, 12)' });
		expect(s['.is-style-kicker']).toEqual({ color: 'rgb(120, 113, 108)', 'font-size': '14px', 'letter-spacing': '1.12px', 'text-transform': 'uppercase' });

		await page.hover('.sg-link');
		await expect.poll(async () => (await styles(page, { '.sg-link': ['color'] }))['.sg-link'].color).toBe('rgb(154, 52, 18)');
	});

	test('mobile: large sizes scale down, small ones do not', async ({ page }) => {
		await page.setViewportSize({ width: 320, height: 800 });
		await page.goto(`/${SLUG}/`);
		const s = await styles(page, {
			'.sg-h1': ['font-size'],
			'.sg-h2': ['font-size'],
			'.sg-h3': ['font-size'],
			'.sg-small': ['font-size'],
			'.sg-body': ['font-size'],
			'.sg-large': ['font-size'],
			'.sg-xlarge': ['font-size'],
			'.sg-huge': ['font-size'],
			'.is-style-kicker': ['font-size'],
		});
		const px = Object.fromEntries(Object.entries(s).map(([k, v]) => [k, v && v['font-size']]));
		expect(px).toEqual({
			'.sg-h1': '36px',
			'.sg-h2': '28px',
			'.sg-h3': '22px',
			'.sg-small': '14px',
			'.sg-body': '18px',
			'.sg-large': '22px',
			'.sg-xlarge': '28px',
			'.sg-huge': '36px',
			'.is-style-kicker': '14px',
		});
	});

	test('Styles UI customizations of buttons, links, quotes and section styles take effect', async ({ page }) => {
		wpEval(
			USER_STYLES_PHP({
				elements: {
					button: { border: { radius: '4px' } },
					link: { ':hover': { color: { text: '#0000ff' } } },
					h2: { typography: { fontSize: '30px' } },
				},
				blocks: {
					'core/quote': { typography: { fontStyle: 'normal' } },
					'core/group': {
						variations: {
							card: { color: { background: '#ffe4e6' }, border: { radius: '0px' } },
							inverted: { color: { background: '#003300' }, elements: { link: { color: { text: '#00ff00' } } } },
						},
					},
					'core/paragraph': { variations: { kicker: { color: { text: '#ff0000' } } } },
				},
			})
		);
		await page.setViewportSize({ width: 1440, height: 1000 });
		await page.goto(`/${SLUG}/`);
		const s = await styles(page, {
			'.sg-button .wp-block-button__link': ['border-top-left-radius', 'background-color'],
			'.sg-quote': ['font-style'],
			'.sg-h2': ['font-size'],
			'.sg-card': ['background-color', 'border-top-left-radius', 'border-top-color'],
			'.sg-inverted': ['background-color', 'color'],
			'.sg-inverted-link': ['color'],
			'.is-style-kicker': ['color', 'text-transform'],
		});
		expect(s['.sg-button .wp-block-button__link']).toEqual({ 'border-top-left-radius': '4px', 'background-color': 'rgb(194, 65, 12)' });
		expect(s['.sg-quote']['font-style']).toBe('normal');
		expect(s['.sg-h2']['font-size']).toBe('30px');
		expect(s['.sg-card']).toEqual({ 'background-color': 'rgb(255, 228, 230)', 'border-top-left-radius': '0px', 'border-top-color': 'rgb(228, 223, 214)' });
		expect(s['.sg-inverted']).toEqual({ 'background-color': 'rgb(0, 51, 0)', color: 'rgb(246, 243, 238)' });
		expect(s['.sg-inverted-link'].color).toBe('rgb(0, 255, 0)');
		expect(s['.is-style-kicker']).toEqual({ color: 'rgb(255, 0, 0)', 'text-transform': 'uppercase' });
		await page.hover('.sg-link');
		await expect.poll(async () => (await styles(page, { '.sg-link': ['color'] }))['.sg-link'].color).toBe('rgb(0, 0, 255)');
	});

	test('the editor shows the same styles as the front end', async ({ page }) => {
		await login(page);
		await openEditor(page, postId());
		await assertBlocksValid(page);
		const frame = page.frameLocator('iframe[name="editor-canvas"]');
		await frame.locator('.sg-card').first().waitFor({ timeout: 60_000 });
		const handle = await page.locator('iframe[name="editor-canvas"]').elementHandle();
		const canvas = await handle.contentFrame();
		await page.waitForTimeout(1500);
		const s = await styles(canvas, {
			'.sg-card': ['background-color', 'border-top-width', 'border-top-color', 'border-top-left-radius', 'padding-top', 'box-shadow'],
			'.sg-inverted': ['background-color', 'color', 'padding-top'],
			'.sg-inverted-link': ['color'],
			'.sg-inverted-heading': ['color'],
			'.sg-quote': ['border-left-width', 'border-left-color', 'font-style'],
			'.sg-h1': ['font-size'],
			'.sg-button .wp-block-button__link': ['border-top-left-radius'],
			'.is-style-kicker': ['text-transform', 'letter-spacing'],
		});
		expect(s['.sg-card']).toEqual({
			'background-color': 'rgb(255, 255, 255)',
			'border-top-width': '1px',
			'border-top-color': 'rgb(228, 223, 214)',
			'border-top-left-radius': '12px',
			'padding-top': '32px',
			'box-shadow': 'rgba(28, 25, 23, 0.12) 0px 1px 3px 0px',
		});
		expect(s['.sg-inverted']).toEqual({ 'background-color': 'rgb(22, 22, 22)', color: 'rgb(246, 243, 238)', 'padding-top': '48px' });
		expect(s['.sg-inverted-link'].color).toBe('rgb(255, 179, 71)');
		expect(s['.sg-inverted-heading'].color).toBe('rgb(255, 255, 255)');
		expect(s['.sg-quote']).toEqual({ 'border-left-width': '4px', 'border-left-color': 'rgb(194, 65, 12)', 'font-style': 'italic' });
		expect(s['.sg-button .wp-block-button__link']['border-top-left-radius']).toBe('999px');
		expect(s['.is-style-kicker']).toEqual({ 'text-transform': 'uppercase', 'letter-spacing': '1.12px' });

		// The group style picker still offers Card and Inverted.
		const groupStyles = await page.evaluate(() => window.wp.data.select('core/blocks').getBlockStyles('core/group').map((s) => s.name));
		expect(groupStyles).toEqual(expect.arrayContaining(['card', 'inverted']));
	});
});
