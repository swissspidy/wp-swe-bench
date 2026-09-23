// Settings → SEO screen (React app saving through /wp/v2/settings).
import { test, expect } from '@playwright/test';
import { login, wpEval, trackErrors, openEditor, wp } from '../wpsb/e2e/helpers.mjs';

const SETTINGS_REQUEST = /wp(\/|%2F)v2(\/|%2F)settings/;

function stored() {
	return JSON.parse(
		wpEval(
			`wp_set_current_user( 1 ); $r = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/settings' ) ); echo wp_json_encode( $r->get_data()['acme_seo_settings'] ?? null );`
		)
	);
}

function savedNotice(page) {
	return page.getByText('Settings saved.').filter({ visible: true });
}

async function openScreen(page) {
	await page.goto('/wp-admin/options-general.php?page=acme-seo');
	await expect(page.getByLabel('Home page title')).toBeVisible({ timeout: 60_000 });
	// Wait until the settings were loaded into the form.
	await expect(page.getByLabel('Home page title')).not.toHaveValue('', { timeout: 60_000 });
}

test.describe('Settings → SEO', () => {
	test('shows the migrated settings and saves changes through the REST API', async ({ page }) => {
		const errors = trackErrors(page);
		await login(page);
		await openScreen(page);

		await expect(page.getByLabel('Home page title')).toHaveValue('Welcome to %%sitename%% %%sep%% %%tagline%%');
		await expect(page.getByLabel('Title separator')).toHaveValue('|');
		await expect(page.getByLabel('Twitter/X username')).toHaveValue('AcmeCorp');
		await expect(page.getByLabel('Facebook page URL')).toHaveValue('https://www.facebook.com/acmewidgets');
		await expect(page.getByLabel('Enable Open Graph tags')).toBeChecked();
		await expect(page.getByLabel('Pages', { exact: true })).toBeChecked();
		await expect(page.getByLabel('Events', { exact: true })).not.toBeChecked();
		await expect(page.getByLabel('Noindex author archives')).toBeChecked();
		await expect(page.getByLabel('Noindex date archives')).not.toBeChecked();

		let navigations = 0;
		page.on('framenavigated', (frame) => {
			if (frame === page.mainFrame()) navigations++;
		});

		await page.getByLabel('Home page title').fill('Home %%sep%% %%sitename%%');
		await page.getByLabel('Title separator').selectOption('»');
		await page.getByLabel('Twitter/X username').fill('acme_news');
		await page.getByLabel('Enable Open Graph tags').uncheck();
		await page.getByLabel('Events', { exact: true }).check();
		await page.getByLabel('Noindex date archives').check();

		const saved = page.waitForResponse(
			(r) => SETTINGS_REQUEST.test(r.url()) && ['POST', 'PUT', 'PATCH'].includes(r.request().method()),
			{ timeout: 60_000 }
		);
		await page.getByRole('button', { name: 'Save changes' }).click();
		const response = await saved;
		expect(response.status()).toBe(200);
		await expect(savedNotice(page).first()).toBeVisible({ timeout: 30_000 });
		expect(navigations, 'saving must not reload the page').toBe(0);

		const s = stored();
		expect(s.titles.home_title).toBe('Home %%sep%% %%sitename%%');
		expect(s.titles.separator).toBe('»');
		expect(s.social.twitter_handle).toBe('acme_news');
		expect(s.social.og_enabled).toBe(false);
		expect([...s.indexing.noindex_post_types].sort()).toEqual(['event', 'page']);
		expect(s.indexing.noindex_date_archives).toBe(true);
		// Untouched fields keep their values.
		expect(s.social.profiles.facebook).toBe('https://www.facebook.com/acmewidgets');
		expect(s.verification.bing).toBe('0123456789ABCDEF0123456789ABCDEF');

		await page.reload();
		await openScreen(page);
		await expect(page.getByLabel('Home page title')).toHaveValue('Home %%sep%% %%sitename%%');
		await expect(page.getByLabel('Title separator')).toHaveValue('»');
		await expect(page.getByLabel('Twitter/X username')).toHaveValue('acme_news');
		await expect(page.getByLabel('Enable Open Graph tags')).not.toBeChecked();
		await expect(page.getByLabel('Events', { exact: true })).toBeChecked();

		const home = await page.request.get('/');
		const html = await home.text();
		expect(html).toContain('<title>Home » Acme Widgets</title>');
		expect(html).toContain('content="@acme_news"');
		expect(html).not.toContain('property="og:title"');

		expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
	});

	test('server-side validation errors are shown and nothing is saved', async ({ page }) => {
		await login(page);
		await openScreen(page);
		const before = stored();

		await page.getByLabel('Home page meta description').fill('Changed description');
		await page.getByLabel('Twitter/X username').fill('not a valid handle!');
		await page.getByRole('button', { name: 'Save changes' }).click();

		const error = page.locator('.components-notice.is-error, .notice-error, .components-snackbar').first();
		await expect(error).toBeVisible({ timeout: 30_000 });
		await expect(savedNotice(page)).toHaveCount(0);
		// The form keeps what the user typed.
		await expect(page.getByLabel('Twitter/X username')).toHaveValue('not a valid handle!');
		await expect(page.getByLabel('Home page meta description')).toHaveValue('Changed description');

		expect(stored()).toEqual(before);

		// Fixing the value and saving again works.
		await page.getByLabel('Twitter/X username').fill('fixed_handle');
		await page.getByRole('button', { name: 'Save changes' }).click();
		await expect(savedNotice(page).first()).toBeVisible({ timeout: 30_000 });
		const after = stored();
		expect(after.social.twitter_handle).toBe('fixed_handle');
		expect(after.titles.home_description).toBe('Changed description');
	});

	test('editors cannot open the screen', async ({ page }) => {
		await login(page, 'erin', 'password');
		await page.goto('/wp-admin/options-general.php?page=acme-seo');
		await expect(page.locator('body')).toContainText('not allowed');
		await page.goto('/wp-admin/');
		await expect(page.locator('#adminmenu a[href="options-general.php?page=acme-seo"]')).toHaveCount(0);
	});

	test('the block editor SEO panel is still registered', async ({ page }) => {
		await login(page);
		const id = Number(wp(['post', 'list', '--post_type=post', '--name=widget-launch', '--field=ID']));
		await openEditor(page, id);
		const registered = await page.evaluate(() => !!window.wp?.plugins?.getPlugin('acme-seo'));
		expect(registered).toBe(true);
	});
});
