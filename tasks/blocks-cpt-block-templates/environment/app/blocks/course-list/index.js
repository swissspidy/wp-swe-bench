/**
 * Course List block (editor). Plain script, no build step: uses the wp.* globals.
 */
( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, RangeControl, SelectControl, TextControl } = wp.components;
	const { createElement: el } = wp.element;
	const { __ } = wp.i18n;
	const ServerSideRender = wp.serverSideRender;

	registerBlockType( 'acme-courses/course-list', {
		edit( { attributes, setAttributes } ) {
			const blockProps = useBlockProps();
			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Courses', 'acme-courses' ) },
						el( TextControl, {
							label: __( 'Topic slug', 'acme-courses' ),
							value: attributes.topic,
							onChange: ( topic ) => setAttributes( { topic } ),
						} ),
						el( RangeControl, {
							label: __( 'Number of courses', 'acme-courses' ),
							min: 1,
							max: 24,
							value: attributes.number,
							onChange: ( number ) => setAttributes( { number } ),
						} ),
						el( SelectControl, {
							label: __( 'Order by', 'acme-courses' ),
							value: attributes.orderby,
							options: [
								{ label: __( 'Newest', 'acme-courses' ), value: 'date' },
								{ label: __( 'Title', 'acme-courses' ), value: 'title' },
								{ label: __( 'Manual order', 'acme-courses' ), value: 'menu_order' },
							],
							onChange: ( orderby ) => setAttributes( { orderby } ),
						} )
					)
				),
				el( ServerSideRender, { block: 'acme-courses/course-list', attributes } )
			);
		},
		save: () => null,
	} );
} )( window.wp );
