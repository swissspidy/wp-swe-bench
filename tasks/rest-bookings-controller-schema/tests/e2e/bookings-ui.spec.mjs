// The office screen (wp-admin → Bookings) and the availability widget must keep working
// for their users after the API rework.
import { test, expect } from '@playwright/test';
import { login, wpEval, trackErrors } from '../wpsb/e2e/helpers.mjs';

const rows = (where) =>
	JSON.parse(wpEval(`global $wpdb; echo wp_json_encode( $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}acme_bookings WHERE ${where}", ARRAY_A ) );`));
const idByNotes = (notes) => Number(rows(`notes = '${notes}'`)[0].id);
const ymd = (offsetDays) => new Date(Date.now() + offsetDays * 86400000).toISOString().slice(0, 10);
const realErrors = (errors) => errors.filter((e) => !/Failed to load resource/i.test(e));

test.describe('office screen', () => {
	test('lists, paginates and filters bookings', async ({ page }) => {
		const errors = trackErrors(page);
		await login(page, 'mira', 'password');
		await page.goto('/wp-admin/admin.php?page=acme-bookings');
		const bodyRows = page.locator('#acme-bookings-table tbody tr[data-id]');
		await expect(bodyRows).toHaveCount(20);
		await expect(page.locator('#acme-bookings-page-info')).toHaveText(/Page 1 of 2/);
		await expect(page.locator('#acme-bookings-prev')).toBeDisabled();

		await page.click('#acme-bookings-next');
		await expect(page.locator('#acme-bookings-page-info')).toHaveText(/Page 2 of 2/);
		await expect(bodyRows).toHaveCount(5);
		const anniversary = page.locator(`#acme-bookings-table tr[data-id="${idByNotes('Anniversary trip')}"]`);
		await expect(anniversary.locator('.column-room')).toHaveText('Garden Room');
		await expect(anniversary.locator('.column-customer')).toHaveText('Alice Traveller');
		await expect(anniversary.locator('.column-start')).toHaveText('2026-11-02 14:00');
		await expect(anniversary.locator('.column-status')).toHaveText('Confirmed');
		await expect(anniversary.locator('.column-total')).toContainText('258');

		const gardenId = wpEval(`echo get_page_by_path( 'garden-room', OBJECT, 'acme_room' )->ID;`);
		await page.selectOption('#acme-bookings-filter-room', gardenId);
		await expect(bodyRows).toHaveCount(3);
		await expect(page.locator('#acme-bookings-page-info')).toHaveText(/Page 1 of 1/);
		await page.selectOption('#acme-bookings-filter-status', 'cancelled');
		await expect(bodyRows).toHaveCount(1);
		await expect(page.locator(`#acme-bookings-table tr[data-id="${idByNotes('seed:legacy-canceled')}"] .column-status`)).toHaveText('Cancelled');

		await page.selectOption('#acme-bookings-filter-room', '');
		await expect(bodyRows).toHaveCount(rows(`status IN ('cancelled','canceled')`).length);
		await page.selectOption('#acme-bookings-filter-status', 'confirmed');
		const confirmed = rows(`status IN ('confirmed','approved')`).length;
		await expect(bodyRows).toHaveCount(confirmed);
		await expect(page.locator(`#acme-bookings-table tr[data-id="${idByNotes('seed:legacy-approved')}"] .column-status`)).toHaveText('Confirmed');
		expect(realErrors(errors)).toEqual([]);
	});

	test('creates, confirms, cancels and deletes bookings', async ({ page }) => {
		const errors = trackErrors(page);
		await login(page, 'mira', 'password');
		await page.goto('/wp-admin/admin.php?page=acme-bookings');
		await expect(page.locator('#acme-bookings-table tbody tr[data-id]')).toHaveCount(20);

		const atticId = wpEval(`echo get_page_by_path( 'attic-single', OBJECT, 'acme_room' )->ID;`);
		const carolId = wpEval(`echo get_user_by( 'login', 'carol' )->ID;`);
		await page.selectOption('#acme-new-room', atticId);
		await page.selectOption('#acme-new-customer', carolId);
		await page.fill('#acme-new-start', '2027-10-10');
		await page.fill('#acme-new-end', '2027-10-12');
		await page.fill('#acme-new-guests', '1');
		await page.selectOption('#acme-new-status', 'confirmed');
		await page.fill('#acme-new-notes', 'Booked by phone');
		await page.click('#acme-bookings-new button[type="submit"]');
		await expect(page.locator('.acme-bookings-message')).toHaveText(/Booking #\d+ added/);
		const created = rows(`notes = 'Booked by phone'`);
		expect(created).toHaveLength(1);
		expect(created[0]).toMatchObject({
			room_id: atticId,
			customer_id: carolId,
			start_date: '2027-10-10 14:00:00',
			end_date: '2027-10-12 11:00:00',
			status: 'confirmed',
		});

		// Conflicting booking: error shown, nothing saved.
		await page.selectOption('#acme-new-room', atticId);
		await page.fill('#acme-new-start', '2027-10-11');
		await page.fill('#acme-new-end', '2027-10-13');
		await page.fill('#acme-new-notes', 'Double booking');
		await page.click('#acme-bookings-new button[type="submit"]');
		await expect(page.locator('.acme-bookings-message')).toHaveClass(/is-error/);
		await expect(page.locator('.acme-bookings-message')).not.toHaveText('');
		expect(rows(`notes = 'Double booking'`)).toHaveLength(0);

		// Confirm → cancel → delete the pending "Late arrival" booking (2026-11-05, on page 2).
		const lateId = idByNotes('Late arrival');
		await page.selectOption('#acme-bookings-filter-status', 'pending');
		const row = page.locator(`#acme-bookings-table tr[data-id="${lateId}"]`);
		await row.locator('.acme-confirm').click();
		await expect.poll(() => rows(`id = ${lateId}`)[0].status).toBe('confirmed');
		await expect(row).toHaveCount(0);
		await page.selectOption('#acme-bookings-filter-status', '');
		await expect(page.locator('#acme-bookings-page-info')).toHaveText(/Page 1 of 2/);
		await expect(page.locator('#acme-bookings-table tbody tr[data-id]')).toHaveCount(20);
		await page.click('#acme-bookings-next');
		await expect(row.locator('.column-status')).toHaveText('Confirmed');
		await row.locator('.acme-cancel').click();
		await expect.poll(() => rows(`id = ${lateId}`)[0].status).toBe('cancelled');
		await expect(row.locator('.column-status')).toHaveText('Cancelled');

		page.once('dialog', (d) => d.dismiss());
		await row.locator('.acme-delete').click();
		await page.waitForTimeout(1000);
		expect(rows(`id = ${lateId}`)).toHaveLength(1);
		page.once('dialog', (d) => d.accept());
		await row.locator('.acme-delete').click();
		await expect.poll(() => rows(`id = ${lateId}`).length).toBe(0);
		await expect(row).toHaveCount(0);
		expect(realErrors(errors)).toEqual([]);
	});
});

test.describe('availability widget', () => {
	test('shows booked dates to visitors', async ({ page }) => {
		const errors = trackErrors(page);
		const start = ymd(5);
		const end = ymd(7);
		wpEval(`global $wpdb; $wpdb->insert( $wpdb->prefix . 'acme_bookings', array( 'room_id' => get_page_by_path( 'garden-room', OBJECT, 'acme_room' )->ID, 'customer_id' => get_user_by( 'login', 'bob' )->ID, 'start_date' => '${start} 14:00:00', 'end_date' => '${end} 11:00:00', 'status' => 'confirmed', 'guests' => 1, 'total' => '258.00', 'notes' => 'e2e-widget', 'created_at' => gmdate( 'Y-m-d H:i:s' ) ) );`);
		await page.goto('/book-garden-room/');
		await expect(page.locator(`.acme-availability__booked li[data-start="${start}"]`)).toBeVisible();
		await expect(page.locator(`.acme-availability__booked li[data-start="${start}"]`)).toHaveAttribute('data-end', end);
		await expect(page.locator('.acme-availability__login')).toBeVisible();
		await expect(page.locator('.acme-availability__form')).toHaveCount(0);
		expect(realErrors(errors)).toEqual([]);
	});

	test('lets a logged-in customer request a booking', async ({ page }) => {
		const errors = trackErrors(page);
		await login(page, 'carol', 'password');
		await page.goto('/book-garden-room/');
		const widget = page.locator('.acme-availability');
		await expect(widget.locator('.acme-availability__booked li').first()).toBeVisible();
		const start = ymd(20);
		const end = ymd(22);
		await widget.locator('input[name="start"]').fill(start);
		await widget.locator('input[name="end"]').fill(end);
		await widget.locator('input[name="guests"]').fill('2');
		await widget.locator('.acme-availability__request').click();
		const message = widget.locator('.acme-availability__message');
		await expect(message).toHaveText(/#\d+/);
		const id = Number((await message.textContent()).match(/#(\d+)/)[1]);
		const [booking] = rows(`id = ${id}`);
		const carolId = wpEval(`echo get_user_by( 'login', 'carol' )->ID;`);
		expect(booking).toMatchObject({
			customer_id: carolId,
			start_date: `${start} 14:00:00`,
			end_date: `${end} 11:00:00`,
			status: 'pending',
			guests: '2',
		});
		await expect(widget.locator(`.acme-availability__booked li[data-start="${start}"]`)).toBeVisible();

		// Same dates again: refused, nothing stored.
		await widget.locator('input[name="start"]').fill(start);
		await widget.locator('input[name="end"]').fill(end);
		await widget.locator('.acme-availability__request').click();
		await expect(message).toHaveClass(/is-error/);
		await expect(message).not.toHaveText(/#\d+/);
		expect(rows(`start_date = '${start} 14:00:00'`)).toHaveLength(1);
		expect(realErrors(errors)).toEqual([]);
	});
});
