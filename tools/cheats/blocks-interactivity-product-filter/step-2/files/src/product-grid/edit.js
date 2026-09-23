/**
 * Editor UI: settings in the sidebar, server-side preview in the canvas.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	ToggleControl,
	SelectControl,
	RangeControl,
	FormTokenField,
	Disabled,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import ServerSideRender from '@wordpress/server-side-render';

export default function Edit( { attributes, setAttributes, name } ) {
	const {
		heading,
		categories,
		defaultCategory,
		showSearch,
		orderBy,
		columns,
		perPage,
	} = attributes;

	const terms = useSelect(
		( select ) =>
			select( coreStore ).getEntityRecords( 'taxonomy', 'acme_product_cat', {
				per_page: 100,
				hide_empty: false,
			} ) || [],
		[]
	);
	const slugs = terms.map( ( term ) => term.slug );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Grid', 'acme-catalog' ) }>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Heading', 'acme-catalog' ) }
						value={ heading }
						onChange={ ( value ) => setAttributes( { heading: value } ) }
					/>
					<FormTokenField
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Only these categories', 'acme-catalog' ) }
						value={ categories }
						suggestions={ slugs }
						onChange={ ( value ) => setAttributes( { categories: value } ) }
					/>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Initially selected category', 'acme-catalog' ) }
						value={ defaultCategory }
						options={ [
							{ value: '', label: __( 'All', 'acme-catalog' ) },
							...( categories.length ? categories : slugs ).map(
								( slug ) => ( { value: slug, label: slug } )
							),
						] }
						onChange={ ( value ) =>
							setAttributes( { defaultCategory: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show search field', 'acme-catalog' ) }
						checked={ showSearch }
						onChange={ ( value ) => setAttributes( { showSearch: value } ) }
					/>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Order by', 'acme-catalog' ) }
						value={ orderBy }
						options={ [
							{ value: 'title', label: __( 'Name', 'acme-catalog' ) },
							{ value: 'price', label: __( 'Price', 'acme-catalog' ) },
							{ value: 'date', label: __( 'Newest first', 'acme-catalog' ) },
						] }
						onChange={ ( value ) => setAttributes( { orderBy: value } ) }
					/>
					<RangeControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Columns', 'acme-catalog' ) }
						min={ 1 }
						max={ 6 }
						value={ columns }
						onChange={ ( value ) => setAttributes( { columns: value || 3 } ) }
					/>
					<RangeControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Products per page', 'acme-catalog' ) }
						help={ __( '0 shows all products on one page.', 'acme-catalog' ) }
						min={ 0 }
						max={ 48 }
						value={ perPage }
						onChange={ ( value ) => setAttributes( { perPage: value || 0 } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps() }>
				<Disabled>
					<ServerSideRender block={ name } attributes={ attributes } />
				</Disabled>
			</div>
		</>
	);
}
