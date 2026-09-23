// Recipes in the block editor: the "Recipe details" panel and the Recipe card block.
import { test, expect } from '@playwright/test';
import { login, wp, wpEval, openEditor, newPost, assertBlocksValid, getBlockTree, savePost, trackErrors } from '../wpsb/e2e/helpers.mjs';

const recipeId = (slug) => Number(wp(['post', 'list', '--post_type=acme_recipe', `--name=${slug}`, '--field=ID', '--post_status=any']));
const storedMeta = (id) =>
	JSON.parse(
		wpEval(`echo wp_json_encode( array(
			'ingredients' => get_post_meta( ${id}, '_acme_recipe_ingredients', true ),
			'prep' => acme_recipes_parse_minutes( get_post_meta( ${id}, '_acme_recipe_prep_time', true ) ),
			'cook' => acme_recipes_parse_minutes( get_post_meta( ${id}, '_acme_recipe_cook_time', true ) ),
			'servings' => (int) get_post_meta( ${id}, '_acme_recipe_servings', true ),
			'difficulty' => strtolower( (string) get_post_meta( ${id}, '_acme_recipe_difficulty', true ) ),
			'notes' => get_post_meta( ${id}, '_acme_recipe_notes', true ),
		) );`)
	);

/** Open the document sidebar and the "Recipe details" panel; returns the sidebar locator. */
async function openRecipePanel(page) {
	// Dismiss the welcome guide if it shows up.
	await page.evaluate(() => window.wp.data.dispatch('core/preferences').set('core/edit-post', 'welcomeGuide', false));
	const modal = page.locator('.components-modal__screen-overlay');
	if (await modal.count()) {
		await page.keyboard.press('Escape');
		await expect(modal).toHaveCount(0);
	}
	await page.evaluate(() => {
		const editPost = window.wp.data.dispatch('core/edit-post');
		editPost?.openGeneralSidebar?.('edit-post/document');
	});
	const sidebar = page.locator('.interface-complementary-area').first();
	await expect(sidebar).toBeVisible();
	const toggle = sidebar.getByRole('button', { name: 'Recipe details', exact: true });
	await expect(sidebar.getByText('Recipe details', { exact: true }).first()).toBeVisible({ timeout: 30_000 });
	if ((await toggle.count()) && (await toggle.first().getAttribute('aria-expanded')) === 'false') {
		await toggle.first().click();
	}
	await expect(sidebar.getByLabel('Servings', { exact: true })).toBeVisible();
	return sidebar;
}

async function rows(sidebar) {
	const amounts = await sidebar.getByLabel('Amount', { exact: true }).evaluateAll((els) => els.map((e) => e.value));
	const units = await sidebar.getByLabel('Unit', { exact: true }).evaluateAll((els) => els.map((e) => e.value));
	const items = await sidebar.getByLabel('Ingredient', { exact: true }).evaluateAll((els) => els.map((e) => e.value));
	return items.map((item, i) => [amounts[i], units[i], item]);
}

test.describe('Recipes in the block editor', () => {
	test('a 1.x recipe opens in the block editor and shows its details in the panel', async ({ page }) => {
		const errors = trackErrors(page);
		await login(page);
		await openEditor(page, recipeId('grandmas-goulash'));
		await assertBlocksValid(page);
		const sidebar = await openRecipePanel(page);
		await expect(sidebar.getByLabel('Prep time (minutes)', { exact: true })).toHaveValue('20');
		await expect(sidebar.getByLabel('Cook time (minutes)', { exact: true })).toHaveValue('90');
		await expect(sidebar.getByLabel('Servings', { exact: true })).toHaveValue('6');
		await expect(sidebar.getByLabel('Difficulty', { exact: true })).toHaveValue('medium');
		await expect(sidebar.getByLabel('Kitchen notes', { exact: true })).toHaveValue('Use beef shin from the Tuesday market.');
		expect(await rows(sidebar)).toEqual([
			['500', 'g', 'beef'],
			['2', '', 'onions'],
			['1', 'tbsp', 'paprika'],
			['', '', 'salt to taste'],
		]);
		// The old meta box is gone from the editor.
		await expect(page.locator('#acme-recipe-details')).toHaveCount(0);
		expect(errors.filter((e) => e.startsWith('pageerror'))).toEqual([]);
	});

	test('editing details in the panel saves them in the stored format', async ({ page }) => {
		await login(page);
		const id = recipeId('grandmas-goulash');
		await openEditor(page, id);
		const sidebar = await openRecipePanel(page);

		await sidebar.getByLabel('Servings', { exact: true }).fill('5');
		await sidebar.getByLabel('Difficulty', { exact: true }).selectOption('hard');
		await sidebar.getByLabel('Ingredient', { exact: true }).nth(1).fill('red onions');
		await sidebar.getByRole('button', { name: 'Remove ingredient' }).nth(3).click();
		await sidebar.getByRole('button', { name: 'Add ingredient' }).click();
		await sidebar.getByLabel('Amount', { exact: true }).nth(3).fill('2');
		await sidebar.getByLabel('Unit', { exact: true }).nth(3).fill('cloves');
		await sidebar.getByLabel('Ingredient', { exact: true }).nth(3).fill('garlic');
		await sidebar.getByLabel('Kitchen notes', { exact: true }).fill('Beef shin, Tuesday market. Ask for Joe.');
		await savePost(page);
		// Let any follow-up requests (e.g. meta box submissions) finish.
		await page.waitForTimeout(3000);

		const meta = storedMeta(id);
		expect(meta.ingredients).toEqual([
			{ amount: '500', unit: 'g', item: 'beef' },
			{ amount: '2', unit: '', item: 'red onions' },
			{ amount: '1', unit: 'tbsp', item: 'paprika' },
			{ amount: '2', unit: 'cloves', item: 'garlic' },
		]);
		expect(meta.servings).toBe(5);
		expect(meta.difficulty).toBe('hard');
		expect(meta.prep).toBe(20);
		expect(meta.cook).toBe(90);
		expect(meta.notes).toBe('Beef shin, Tuesday market. Ask for Joe.');

		await openEditor(page, id);
		const again = await openRecipePanel(page);
		await expect(again.getByLabel('Servings', { exact: true })).toHaveValue('5');
		expect((await rows(again)).map((r) => r[2])).toEqual(['beef', 'red onions', 'paprika', 'garlic']);

		const html = await (await page.request.get(`/?p=${id}`)).text();
		expect(html).toContain('red onions');
		expect(html).not.toContain('Ask for Joe');
	});

	test('an author can edit the details of their own recipe', async ({ page }) => {
		await login(page, 'alice', 'password');
		const id = recipeId('quick-salad');
		await openEditor(page, id);
		const sidebar = await openRecipePanel(page);
		await expect(sidebar.getByLabel('Kitchen notes', { exact: true })).toHaveValue('Alice: buy the lettuce at the organic shop.');
		await sidebar.getByLabel('Prep time (minutes)', { exact: true }).fill('12');
		await sidebar.getByRole('button', { name: 'Add ingredient' }).click();
		await sidebar.getByLabel('Ingredient', { exact: true }).nth(2).fill('croutons');
		const errors = [];
		page.on('response', (r) => {
			if (r.url().includes('recipes') && r.status() >= 400) errors.push(`${r.status()} ${r.url()}`);
		});
		await savePost(page);
		const notice = await page.evaluate(() => window.wp.data.select('core/notices').getNotices().filter((n) => n.status === 'error').map((n) => n.content));
		expect(notice).toEqual([]);
		expect(errors).toEqual([]);
		const meta = storedMeta(id);
		expect(meta.prep).toBe(12);
		expect(meta.ingredients.map((i) => i.item)).toEqual(['lettuce', 'olive oil', 'croutons']);
		expect(wpEval(`echo (int) get_post_meta( ${id}, '_acme_recipe_staff_pick', true );`)).toBe('0');
	});

	test('a new recipe gets its details from the panel', async ({ page }) => {
		await login(page);
		await newPost(page, 'acme_recipe');
		await page.evaluate(() => window.wp.data.dispatch('core/editor').editPost({ title: 'Panel Pie', status: 'publish' }));
		const sidebar = await openRecipePanel(page);
		await sidebar.getByLabel('Cook time (minutes)', { exact: true }).fill('40');
		await sidebar.getByLabel('Servings', { exact: true }).fill('8');
		if ((await sidebar.getByLabel('Ingredient', { exact: true }).count()) === 0) {
			await sidebar.getByRole('button', { name: 'Add ingredient' }).click();
		}
		await sidebar.getByLabel('Amount', { exact: true }).first().fill('4');
		await sidebar.getByLabel('Ingredient', { exact: true }).first().fill('apples');
		await savePost(page);
		const id = await page.evaluate(() => window.wp.data.select('core/editor').getCurrentPostId());
		await page.waitForTimeout(2000);
		const meta = storedMeta(id);
		expect(meta.cook).toBe(40);
		expect(meta.servings).toBe(8);
		expect(meta.ingredients).toEqual([{ amount: '4', unit: '', item: 'apples' }]);
		const html = await (await page.request.get(`/?p=${id}`)).text();
		expect((html.match(/class="acme-recipe-card[ "]/g) || []).length).toBe(1);
	});

	test('the Recipe card block places the card and round-trips', async ({ page }) => {
		await login(page);
		const id = recipeId('classic-pancakes');
		await openEditor(page, id);
		await assertBlocksValid(page);
		await page.evaluate(() => {
			const { createBlock } = window.wp.blocks;
			const blocks = window.wp.data.select('core/block-editor').getBlocks();
			window.wp.data.dispatch('core/block-editor').insertBlocks(createBlock('acme/recipe-card'), 1);
			window.wp.data.dispatch('core/block-editor').insertBlocks(createBlock('core/paragraph', { content: 'AFTER THE CARD' }), blocks.length + 1);
		});
		const canvas = page.frameLocator('iframe[name="editor-canvas"]');
		await expect(canvas.locator('.acme-recipe-card__item').first()).toHaveText('flour', { timeout: 60_000 });
		await savePost(page);

		await openEditor(page, id);
		await assertBlocksValid(page);
		const names = (await getBlockTree(page)).map((b) => b.name);
		expect(names).toContain('acme/recipe-card');

		const html = await (await page.request.get(`/?p=${id}`)).text();
		const count = (html.match(/class="acme-recipe-card[ "]/g) || []).length;
		expect(count, 'exactly one recipe card').toBe(1);
		expect(html.indexOf('class="acme-recipe-card')).toBeLessThan(html.indexOf('AFTER THE CARD'));
		expect(html).not.toContain('Mill &amp; Co');
		expect(html).not.toContain('Mill & Co');
	});
});
