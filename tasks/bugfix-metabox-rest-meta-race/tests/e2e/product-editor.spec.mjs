// Editing products in the block editor: sidebar panel + meta box, saved together.
import { test, expect } from '@playwright/test';
import { login, wp, wpEval, openEditor, savePost, trackErrors } from '../wpsb/e2e/helpers.mjs';

const productId = (slug) =>
	Number(wp(['post', 'list', '--post_type=acme_product', `--name=${slug}`, '--field=ID', '--post_status=any']));

/** Stored values, read the way the shop theme reads them. */
const stored = (id) =>
	JSON.parse(
		wpEval(`wp_cache_flush(); echo wp_json_encode( array(
			'price' => get_post_meta( ${id}, '_acme_price', true ),
			'sku' => get_post_meta( ${id}, '_acme_sku', true ),
			'badge' => acme_pf_get_badge( ${id} ),
			'featured' => acme_pf_is_featured( ${id} ),
			'in_stock' => acme_pf_is_in_stock( ${id} ),
			'notes' => get_post_meta( ${id}, '_acme_internal_notes', true ),
			'supplier' => get_post_meta( ${id}, '_acme_supplier', true ),
		) );`)
	);

/** Open the document sidebar and the "Product details" panel; returns the sidebar locator. */
async function openPanel(page) {
	await page.evaluate(() => window.wp.data.dispatch('core/edit-post')?.openGeneralSidebar?.('edit-post/document'));
	const sidebar = page.locator('.interface-complementary-area').first();
	await expect(sidebar).toBeVisible();
	await expect(sidebar.getByText('Product details', { exact: true }).first()).toBeVisible({ timeout: 30_000 });
	const toggle = sidebar.getByRole('button', { name: 'Product details', exact: true });
	if ((await toggle.count()) && (await toggle.first().getAttribute('aria-expanded')) === 'false') {
		await toggle.first().click();
	}
	await expect(sidebar.getByLabel('Price', { exact: true })).toBeVisible();
	return sidebar;
}

/** Expand the meta boxes pane below the canvas and return the "Internal notes" field. */
async function metaBoxField(page, label) {
	const field = page.getByLabel(label, { exact: true });
	if (!(await field.isVisible())) {
		const toggle = page.getByRole('button', { name: 'Meta Boxes', exact: true });
		if (await toggle.count()) {
			// The pane's resize handle overlaps the toggle: click it programmatically.
			await toggle.first().evaluate((button) => button.click());
		}
	}
	await field.scrollIntoViewIfNeeded();
	await expect(field).toBeVisible();
	return field;
}

/** Save and wait until the meta box request that follows the save has finished too. */
async function saveAll(page) {
	await savePost(page);
	await page.waitForFunction(
		() => {
			const s = window.wp.data.select('core/edit-post');
			return !window.wp.data.select('core/editor').isSavingPost() && !(s.isSavingMetaBoxes && s.isSavingMetaBoxes());
		},
		null,
		{ timeout: 120_000 }
	);
	await page.waitForTimeout(1500);
}

test.describe('Product details in the block editor', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('sidebar and meta box changes are all saved', async ({ page }) => {
		const errors = trackErrors(page);
		const id = productId('trail-runner-pro');
		await openEditor(page, id);
		const sidebar = await openPanel(page);

		await expect(sidebar.getByLabel('Price', { exact: true })).toHaveValue('129.00');
		await expect(sidebar.getByLabel('Featured product', { exact: true })).toBeChecked();

		await sidebar.getByLabel('Price', { exact: true }).fill('149');
		await sidebar.getByLabel('Badge text', { exact: true }).fill('Sale: "20%" off \\ today');
		await sidebar.getByLabel('Featured product', { exact: true }).uncheck();

		const notes = await metaBoxField(page, 'Internal notes');
		await notes.fill('Checked by "QA" on C:\\qa\\log.txt');

		await saveAll(page);

		expect(stored(id)).toEqual({
			price: '149.00',
			sku: 'TRP-01',
			badge: 'Sale: "20%" off \\ today',
			featured: false,
			in_stock: true,
			notes: 'Checked by "QA" on C:\\qa\\log.txt',
			supplier: 'Alpine Goods',
		});

		// Reload: the editor shows what was saved.
		await openEditor(page, id);
		const again = await openPanel(page);
		await expect(again.getByLabel('Price', { exact: true })).toHaveValue('149.00');
		await expect(again.getByLabel('Badge text', { exact: true })).toHaveValue('Sale: "20%" off \\ today');
		await expect(again.getByLabel('Featured product', { exact: true })).not.toBeChecked();
		await expect(await metaBoxField(page, 'Internal notes')).toHaveValue('Checked by "QA" on C:\\qa\\log.txt');
		expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
	});

	test('several saves in one session keep the latest sidebar values', async ({ page }) => {
		const id = productId('city-backpack');
		await openEditor(page, id);
		const sidebar = await openPanel(page);

		await sidebar.getByLabel('In stock', { exact: true }).uncheck();
		await saveAll(page);
		expect(stored(id).in_stock).toBe(false);

		await sidebar.getByLabel('Price', { exact: true }).fill('85');
		await sidebar.getByLabel('Featured product', { exact: true }).check();
		await saveAll(page);

		const values = stored(id);
		expect(values.price).toBe('85.00');
		expect(values.in_stock).toBe(false);
		expect(values.featured).toBe(true);
		expect(values.notes).toBe('Reorder in May.');
	});

	test('an imported product shows its real stock state and keeps it', async ({ page }) => {
		const id = productId('camp-stove');
		await openEditor(page, id);
		const sidebar = await openPanel(page);
		await expect(sidebar.getByLabel('In stock', { exact: true })).not.toBeChecked();
		await expect(sidebar.getByLabel('Featured product', { exact: true })).not.toBeChecked();

		const beanie = productId('wool-beanie');
		await openEditor(page, beanie);
		const beanieSidebar = await openPanel(page);
		await expect(beanieSidebar.getByLabel('In stock', { exact: true })).toBeChecked();
		await expect(beanieSidebar.getByLabel('Featured product', { exact: true })).toBeChecked();
		await beanieSidebar.getByLabel('Price', { exact: true }).fill('26');
		await saveAll(page);

		expect(stored(beanie)).toMatchObject({ price: '26.00', in_stock: true, featured: true, badge: 'Bestseller' });
	});
});
