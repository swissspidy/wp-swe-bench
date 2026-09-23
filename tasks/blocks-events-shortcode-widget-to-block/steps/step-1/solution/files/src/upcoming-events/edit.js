/**
 * Editor UI of the Upcoming Events block: settings in the sidebar, server-rendered preview.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
	Disabled,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

import metadata from './block.json';
import { MAX_LIMIT } from './attributes';

export default function Edit( { attributes, setAttributes } ) {
	const { limit, category, showPast, layout, title, showVenue } = attributes;
	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Listing settings', 'acme-events' ) }>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Heading', 'acme-events' ) }
						value={ title }
						onChange={ ( value ) => setAttributes( { title: value } ) }
					/>
					<RangeControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Number of events', 'acme-events' ) }
						value={ limit }
						min={ 1 }
						max={ MAX_LIMIT }
						onChange={ ( value ) => setAttributes( { limit: value || 1 } ) }
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Categories', 'acme-events' ) }
						help={ __( 'Comma separated event category slugs. Leave empty for all.', 'acme-events' ) }
						value={ category }
						onChange={ ( value ) => setAttributes( { category: value } ) }
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Layout', 'acme-events' ) }
						value={ layout }
						options={ [
							{ value: 'list', label: __( 'List', 'acme-events' ) },
							{ value: 'grid', label: __( 'Grid', 'acme-events' ) },
						] }
						onChange={ ( value ) => setAttributes( { layout: value } ) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Include past events', 'acme-events' ) }
						checked={ showPast }
						onChange={ ( value ) => setAttributes( { showPast: value } ) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show venue', 'acme-events' ) }
						checked={ showVenue }
						onChange={ ( value ) => setAttributes( { showVenue: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<Disabled>
					<ServerSideRender block={ metadata.name } attributes={ attributes } />
				</Disabled>
			</div>
		</>
	);
}
