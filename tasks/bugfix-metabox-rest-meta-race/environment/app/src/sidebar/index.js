/**
 * "Product details" panel in the block editor's document sidebar.
 */
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel, store as editorStore } from '@wordpress/editor';
import { useSelect } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import { TextControl, CheckboxControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const POST_TYPE = 'acme_product';

function ProductDetailsPanel() {
	const postType = useSelect(
		( select ) => select( editorStore ).getCurrentPostType(),
		[]
	);
	const [ meta, setMeta ] = useEntityProp( 'postType', POST_TYPE, 'meta' );

	if ( postType !== POST_TYPE || ! meta ) {
		return null;
	}

	const update = ( key ) => ( value ) => setMeta( { ...meta, [ key ]: value } );

	return (
		<PluginDocumentSettingPanel
			name="acme-product-details"
			title={ __( 'Product details', 'acme-product-fields' ) }
			className="acme-pf-panel"
		>
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Price', 'acme-product-fields' ) }
				value={ meta._acme_price ?? '' }
				onChange={ update( '_acme_price' ) }
				inputMode="decimal"
			/>
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'SKU', 'acme-product-fields' ) }
				value={ meta._acme_sku ?? '' }
				onChange={ update( '_acme_sku' ) }
			/>
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Badge text', 'acme-product-fields' ) }
				value={ meta._acme_badge ?? '' }
				onChange={ update( '_acme_badge' ) }
			/>
			<CheckboxControl
				__nextHasNoMarginBottom
				label={ __( 'Featured product', 'acme-product-fields' ) }
				checked={ !! meta._acme_featured }
				onChange={ update( '_acme_featured' ) }
			/>
			<CheckboxControl
				__nextHasNoMarginBottom
				label={ __( 'In stock', 'acme-product-fields' ) }
				checked={ !! meta._acme_in_stock }
				onChange={ update( '_acme_in_stock' ) }
			/>
		</PluginDocumentSettingPanel>
	);
}

registerPlugin( 'acme-product-fields', { render: ProductDetailsPanel } );
