/**
 * Acme UI Kit – block editor side (plain script, no build step).
 * All three blocks are rendered on the server; the editor shows a server-side preview
 * and edits the items in the sidebar.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;
	var ToggleControl = wp.components.ToggleControl;
	var Button = wp.components.Button;
	var ServerSideRender = wp.serverSideRender;

	/**
	 * Sidebar editor for a list of items.
	 *
	 * @param {Object}   props
	 * @param {Array}    props.items    Items.
	 * @param {Array}    props.fields   [ [ key, label, multiline ] ].
	 * @param {Function} props.onChange Called with the new list.
	 * @param {Object}   props.blank    New item.
	 */
	function ItemsEditor( props ) {
		var items = props.items || [];
		function update( index, key, value ) {
			var next = items.map( function ( item, i ) {
				if ( i !== index ) {
					return item;
				}
				var copy = Object.assign( {}, item );
				copy[ key ] = value;
				return copy;
			} );
			props.onChange( next );
		}
		return el(
			Fragment,
			null,
			items.map( function ( item, index ) {
				return el(
					PanelBody,
					{ key: index, title: ( item.title || item.caption || __( 'Item', 'acme-ui-kit' ) ) + ' #' + ( index + 1 ), initialOpen: false },
					props.fields.map( function ( field ) {
						var Control = field[ 2 ] ? TextareaControl : TextControl;
						return el( Control, {
							key: field[ 0 ],
							label: field[ 1 ],
							value: item[ field[ 0 ] ] || '',
							onChange: function ( value ) {
								update( index, field[ 0 ], value );
							},
						} );
					} ),
					el(
						Button,
						{
							isDestructive: true,
							variant: 'link',
							onClick: function () {
								props.onChange( items.filter( function ( _, i ) {
									return i !== index;
								} ) );
							},
						},
						__( 'Remove', 'acme-ui-kit' )
					)
				);
			} ),
			el(
				PanelBody,
				{ title: __( 'Add', 'acme-ui-kit' ), initialOpen: true },
				el(
					Button,
					{
						variant: 'secondary',
						onClick: function () {
							props.onChange( items.concat( [ Object.assign( {}, props.blank ) ] ) );
						},
					},
					__( 'Add item', 'acme-ui-kit' )
				)
			)
		);
	}

	function edit( name, attribute, fields, blank, extra ) {
		return function ( props ) {
			var controls = [
				el( ItemsEditor, {
					key: 'items',
					items: props.attributes[ attribute ],
					fields: fields,
					blank: blank,
					onChange: function ( value ) {
						var change = {};
						change[ attribute ] = value;
						props.setAttributes( change );
					},
				} ),
			];
			if ( extra ) {
				controls.unshift( extra( props ) );
			}
			return el(
				'div',
				useBlockProps(),
				el( InspectorControls, null, controls ),
				el( ServerSideRender, { block: name, attributes: props.attributes } )
			);
		};
	}

	wp.blocks.registerBlockType( 'acme/tabs', {
		title: __( 'Tabs', 'acme-ui-kit' ),
		icon: 'index-card',
		category: 'design',
		edit: edit( 'acme/tabs', 'tabs', [ [ 'title', __( 'Title', 'acme-ui-kit' ) ], [ 'content', __( 'Content', 'acme-ui-kit' ), true ] ], { title: '', content: '' } ),
		save: function () {
			return null;
		},
	} );

	wp.blocks.registerBlockType( 'acme/accordion', {
		title: __( 'Accordion', 'acme-ui-kit' ),
		icon: 'list-view',
		category: 'design',
		edit: edit(
			'acme/accordion',
			'items',
			[ [ 'title', __( 'Title', 'acme-ui-kit' ) ], [ 'content', __( 'Content', 'acme-ui-kit' ), true ] ],
			{ title: '', content: '' },
			function ( props ) {
				return el(
					PanelBody,
					{ key: 'options', title: __( 'Options', 'acme-ui-kit' ) },
					el( ToggleControl, {
						label: __( 'Only one open at a time', 'acme-ui-kit' ),
						checked: !! props.attributes.single,
						onChange: function ( value ) {
							props.setAttributes( { single: value } );
						},
					} )
				);
			}
		),
		save: function () {
			return null;
		},
	} );

	wp.blocks.registerBlockType( 'acme/carousel', {
		title: __( 'Carousel', 'acme-ui-kit' ),
		icon: 'images-alt2',
		category: 'design',
		edit: edit( 'acme/carousel', 'slides', [ [ 'caption', __( 'Caption', 'acme-ui-kit' ) ], [ 'color', __( 'Background colour', 'acme-ui-kit' ) ] ], { caption: '', color: '#eef1f8' } ),
		save: function () {
			return null;
		},
	} );
} )( window.wp );
