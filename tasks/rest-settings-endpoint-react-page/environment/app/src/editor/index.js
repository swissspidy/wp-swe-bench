/**
 * "SEO" panel in the block editor's document sidebar.
 */
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { TextControl, TextareaControl, ToggleControl } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { __ } from '@wordpress/i18n';

function SeoPanel() {
	const { meta, supportsMeta } = useSelect( ( select ) => {
		const editor = select( editorStore );
		const postMeta = editor.getEditedPostAttribute( 'meta' );
		return {
			meta: postMeta || {},
			supportsMeta: !! postMeta && '_acme_seo_title' in postMeta,
		};
	}, [] );
	const { editPost } = useDispatch( editorStore );

	if ( ! supportsMeta ) {
		return null;
	}

	const set = ( key ) => ( value ) => editPost( { meta: { [ key ]: value } } );

	return (
		<PluginDocumentSettingPanel name="acme-seo" title={ __( 'SEO', 'acme-seo' ) }>
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'SEO title', 'acme-seo' ) }
				help={ __( 'Placeholders: %%sitename%%, %%tagline%%, %%sep%%', 'acme-seo' ) }
				value={ meta._acme_seo_title || '' }
				onChange={ set( '_acme_seo_title' ) }
			/>
			<TextareaControl
				__nextHasNoMarginBottom
				label={ __( 'Meta description', 'acme-seo' ) }
				value={ meta._acme_seo_description || '' }
				onChange={ set( '_acme_seo_description' ) }
			/>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Hide from search engines', 'acme-seo' ) }
				checked={ !! meta._acme_seo_noindex }
				onChange={ set( '_acme_seo_noindex' ) }
			/>
		</PluginDocumentSettingPanel>
	);
}

registerPlugin( 'acme-seo', { render: SeoPanel } );
