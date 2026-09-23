/**
 * Editor side of the course meta blocks (price, duration, enroll button).
 * Plain script, no build step: uses the wp.* globals.
 *
 * Inside a course (post editor, Query Loop) the blocks preview the real value
 * rendered by the server; in templates without a course they show a placeholder.
 */
( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps } = wp.blockEditor;
	const { createElement: el } = wp.element;
	const { __ } = wp.i18n;
	const ServerSideRender = wp.serverSideRender;

	const placeholders = {
		'acme-courses/course-price': __( 'Course price', 'acme-courses' ),
		'acme-courses/course-duration': __( 'Course duration', 'acme-courses' ),
		'acme-courses/enroll-button': __( 'Enroll now', 'acme-courses' ),
	};

	Object.keys( placeholders ).forEach( ( name ) => {
		registerBlockType( name, {
			edit( { attributes, context } ) {
				const blockProps = useBlockProps();
				const isCourse = context.postType === 'acme_course' && context.postId;
				if ( ! isCourse ) {
					return el( 'div', blockProps, el( 'span', { className: 'acme-courses-placeholder' }, placeholders[ name ] ) );
				}
				return el(
					'div',
					blockProps,
					el( ServerSideRender, {
						block: name,
						attributes,
						urlQueryArgs: { post_id: context.postId },
						skipBlockSupportAttributes: true,
					} )
				);
			},
			save: () => null,
		} );
	} );
} )( window.wp );
