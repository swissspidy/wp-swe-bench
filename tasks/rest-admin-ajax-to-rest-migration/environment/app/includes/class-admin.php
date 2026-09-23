<?php
/**
 * The Inventory screen.
 *
 * Static markup lives here (the warehouse's QA scripts rely on the ids and
 * classes); rows and interactions are handled by assets/admin.js.
 *
 * @package Acme\Inventory
 */

namespace Acme\Inventory;

defined( 'ABSPATH' ) || exit;

/**
 * Admin.
 */
class Admin {

	const SLUG = 'acme-inventory';

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Menu.
	 */
	public function menu() {
		add_menu_page(
			__( 'Inventory', 'acme-inventory' ),
			__( 'Inventory', 'acme-inventory' ),
			acme_inventory_capability(),
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-archive',
			56
		);
	}

	/**
	 * Assets.
	 *
	 * @param string $hook_suffix Screen.
	 */
	public function enqueue( $hook_suffix ) {
		if ( 'toplevel_page_' . self::SLUG !== $hook_suffix ) {
			return;
		}
		wp_enqueue_style( 'acme-inventory-admin', ACME_INVENTORY_URL . 'assets/admin.css', array(), ACME_INVENTORY_VERSION );
		wp_enqueue_script( 'acme-inventory-admin', ACME_INVENTORY_URL . 'assets/admin.js', array( 'jquery' ), ACME_INVENTORY_VERSION, true );
		wp_localize_script(
			'acme-inventory-admin',
			'acmeInventory',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'acme_inventory' ),
				'i18n'    => array(
					'saved'         => __( 'Saved.', 'acme-inventory' ),
					'deleted'       => __( 'Item deleted.', 'acme-inventory' ),
					/* translators: %d: number of items. */
					'adjusted'      => __( '%d items adjusted.', 'acme-inventory' ),
					'error'         => __( 'Something went wrong.', 'acme-inventory' ),
					'noItems'       => __( 'No items found.', 'acme-inventory' ),
					'selectItems'   => __( 'Select at least one item.', 'acme-inventory' ),
					/* translators: 1: current page, 2: number of pages. */
					'pageOf'        => __( 'Page %1$d of %2$d', 'acme-inventory' ),
					'delete'        => __( 'Delete', 'acme-inventory' ),
					/* translators: %s: product name. */
					'confirmDelete' => __( 'Delete "%s" from the inventory?', 'acme-inventory' ),
				),
			)
		);
	}

	/**
	 * Screen markup.
	 */
	public function render() {
		$export_url = add_query_arg(
			array(
				'action' => 'acme_inv_export',
				'nonce'  => wp_create_nonce( 'acme_inventory' ),
			),
			admin_url( 'admin-ajax.php' )
		);
		?>
		<div class="wrap" id="acme-inventory">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Inventory', 'acme-inventory' ); ?></h1>
			<a class="page-title-action" id="acme-inv-export" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export CSV', 'acme-inventory' ); ?></a>
			<hr class="wp-header-end" />

			<div id="acme-inv-notice" class="notice inline" role="status" hidden><p></p></div>

			<div class="acme-inv-toolbar">
				<label class="screen-reader-text" for="acme-inv-search"><?php esc_html_e( 'Search SKU or name', 'acme-inventory' ); ?></label>
				<input type="search" id="acme-inv-search" placeholder="<?php esc_attr_e( 'Search SKU or name…', 'acme-inventory' ); ?>" />
				<label><input type="checkbox" id="acme-inv-low-stock" /> <?php esc_html_e( 'Low stock only', 'acme-inventory' ); ?></label>
			</div>

			<div class="acme-inv-bulk">
				<label for="acme-inv-bulk-delta"><?php esc_html_e( 'Adjust selected by', 'acme-inventory' ); ?></label>
				<input type="number" id="acme-inv-bulk-delta" step="1" />
				<label for="acme-inv-bulk-reason" class="screen-reader-text"><?php esc_html_e( 'Reason', 'acme-inventory' ); ?></label>
				<input type="text" id="acme-inv-bulk-reason" placeholder="<?php esc_attr_e( 'Reason (e.g. stock count)', 'acme-inventory' ); ?>" />
				<button type="button" class="button" id="acme-inv-bulk-apply"><?php esc_html_e( 'Apply adjustment', 'acme-inventory' ); ?></button>
			</div>

			<table class="widefat striped" id="acme-inv-table">
				<thead>
					<tr>
						<td class="check-column"><input type="checkbox" id="acme-inv-select-all" aria-label="<?php esc_attr_e( 'Select all', 'acme-inventory' ); ?>" /></td>
						<th scope="col" class="column-sku"><?php esc_html_e( 'SKU', 'acme-inventory' ); ?></th>
						<th scope="col" class="column-name"><?php esc_html_e( 'Name', 'acme-inventory' ); ?></th>
						<th scope="col" class="column-stock"><?php esc_html_e( 'Stock', 'acme-inventory' ); ?></th>
						<th scope="col" class="column-location"><?php esc_html_e( 'Location', 'acme-inventory' ); ?></th>
						<th scope="col" class="column-updated"><?php esc_html_e( 'Updated', 'acme-inventory' ); ?></th>
						<th scope="col" class="column-actions"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'acme-inventory' ); ?></span></th>
					</tr>
				</thead>
				<tbody>
					<tr class="acme-inv-loading"><td colspan="7"><?php esc_html_e( 'Loading…', 'acme-inventory' ); ?></td></tr>
				</tbody>
			</table>

			<div class="acme-inv-pager">
				<button type="button" class="button" id="acme-inv-prev" aria-label="<?php esc_attr_e( 'Previous page', 'acme-inventory' ); ?>">&lsaquo;</button>
				<span id="acme-inv-page-info"></span>
				<button type="button" class="button" id="acme-inv-next" aria-label="<?php esc_attr_e( 'Next page', 'acme-inventory' ); ?>">&rsaquo;</button>
			</div>
		</div>
		<?php
	}
}
