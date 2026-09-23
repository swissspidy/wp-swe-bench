/**
 * Settings → SEO.
 *
 * Reads and saves the `acme_seo_settings` setting through /wp/v2/settings (the core
 * "site" entity), shows success/error notices from the notices store.
 */
import {
	Button,
	CheckboxControl,
	Notice,
	Panel,
	PanelBody,
	SelectControl,
	Spinner,
	TextareaControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { store as coreStore, useEntityProp } from '@wordpress/core-data';
import { useDispatch, useSelect } from '@wordpress/data';
import domReady from '@wordpress/dom-ready';
import { createRoot, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

const config = window.acmeSeoSettings || { postTypes: [], separators: [ '-' ] };
const NOTICE_ID = 'acme-seo-settings';

const NETWORKS = [
	[ 'facebook', __( 'Facebook page URL', 'acme-seo' ) ],
	[ 'instagram', __( 'Instagram URL', 'acme-seo' ) ],
	[ 'linkedin', __( 'LinkedIn URL', 'acme-seo' ) ],
	[ 'youtube', __( 'YouTube channel URL', 'acme-seo' ) ],
];

/**
 * Turn a REST error into a readable message (including per-field details).
 *
 * @param {Object} error apiFetch error.
 * @return {string} Message.
 */
function errorMessage( error ) {
	const parts = [ error?.message || __( 'The settings could not be saved.', 'acme-seo' ) ];
	const params = error?.data?.params;
	if ( params && typeof params === 'object' ) {
		Object.values( params ).forEach( ( detail ) => parts.push( String( detail ) ) );
	}
	return parts.join( ' ' );
}

function Notices() {
	const notices = useSelect( ( select ) => select( noticesStore ).getNotices(), [] );
	const { removeNotice } = useDispatch( noticesStore );
	return notices
		.filter( ( notice ) => notice.type !== 'snackbar' )
		.map( ( notice ) => (
			<Notice
				key={ notice.id }
				status={ notice.status }
				isDismissible
				onRemove={ () => removeNotice( notice.id ) }
			>
				{ notice.content }
			</Notice>
		) );
}

function ExcludeField( { ids, onChange } ) {
	const [ text, setText ] = useState( ids.join( ', ' ) );
	useEffect( () => {
		const parsed = text
			.split( ',' )
			.map( ( s ) => s.trim() )
			.filter( ( s ) => /^\d+$/.test( s ) )
			.map( Number );
		if ( parsed.join( ',' ) !== ids.join( ',' ) ) {
			setText( ids.join( ', ' ) );
		}
		// Only re-sync when the saved value changes from outside.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ ids.join( ',' ) ] );
	return (
		<TextControl
			__nextHasNoMarginBottom
			__next40pxDefaultSize
			label={ __( 'Exclude from sitemap (post IDs)', 'acme-seo' ) }
			help={ __( 'Comma separated.', 'acme-seo' ) }
			value={ text }
			onChange={ ( value ) => {
				setText( value );
				onChange(
					value
						.split( ',' )
						.map( ( s ) => s.trim() )
						.filter( ( s ) => /^\d+$/.test( s ) && Number( s ) > 0 )
						.map( Number )
						.filter( ( id, i, all ) => all.indexOf( id ) === i )
				);
			} }
		/>
	);
}

function SettingsApp() {
	const [ settings, setSettings ] = useEntityProp( 'root', 'site', 'acme_seo_settings' );
	const isSaving = useSelect( ( select ) => select( coreStore ).isSavingEntityRecord( 'root', 'site' ), [] );
	const { saveEditedEntityRecord } = useDispatch( coreStore );
	const { createSuccessNotice, createErrorNotice, removeNotice } = useDispatch( noticesStore );

	if ( ! settings ) {
		return <Spinner />;
	}

	const set = ( section, key ) => ( value ) =>
		setSettings( { ...settings, [ section ]: { ...settings[ section ], [ key ]: value } } );

	const save = async () => {
		if ( isSaving ) {
			return;
		}
		removeNotice( NOTICE_ID );
		try {
			await saveEditedEntityRecord( 'root', 'site', undefined, { throwOnError: true } );
			createSuccessNotice( __( 'Settings saved.', 'acme-seo' ), { id: NOTICE_ID } );
		} catch ( error ) {
			createErrorNotice( errorMessage( error ), { id: NOTICE_ID } );
		}
	};

	const { titles, indexing, social, sitemap, verification } = settings;
	const togglePostType = ( name, checked ) =>
		set( 'indexing', 'noindex_post_types' )(
			checked
				? [ ...indexing.noindex_post_types.filter( ( t ) => t !== name ), name ]
				: indexing.noindex_post_types.filter( ( t ) => t !== name )
		);

	return (
		<form
			className="acme-seo-settings__form"
			onSubmit={ ( event ) => {
				event.preventDefault();
				save();
			} }
		>
			<Notices />
			<Panel>
				<PanelBody title={ __( 'Titles', 'acme-seo' ) }>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Title separator', 'acme-seo' ) }
						value={ titles.separator }
						options={ config.separators.map( ( sep ) => ( { label: sep, value: sep } ) ) }
						onChange={ set( 'titles', 'separator' ) }
					/>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Home page title', 'acme-seo' ) }
						help={ __( 'Placeholders: %%sitename%%, %%tagline%%, %%sep%%', 'acme-seo' ) }
						value={ titles.home_title }
						onChange={ set( 'titles', 'home_title' ) }
					/>
					<TextareaControl
						__nextHasNoMarginBottom
						label={ __( 'Home page meta description', 'acme-seo' ) }
						value={ titles.home_description }
						onChange={ set( 'titles', 'home_description' ) }
					/>
				</PanelBody>
				<PanelBody title={ __( 'Search engines', 'acme-seo' ) }>
					<fieldset>
						<legend>{ __( 'Hide from search engines', 'acme-seo' ) }</legend>
						{ config.postTypes.map( ( type ) => (
							<CheckboxControl
								__nextHasNoMarginBottom
								key={ type.name }
								label={ type.label }
								checked={ indexing.noindex_post_types.includes( type.name ) }
								onChange={ ( checked ) => togglePostType( type.name, checked ) }
							/>
						) ) }
					</fieldset>
					<CheckboxControl
						__nextHasNoMarginBottom
						label={ __( 'Noindex author archives', 'acme-seo' ) }
						checked={ indexing.noindex_author_archives }
						onChange={ set( 'indexing', 'noindex_author_archives' ) }
					/>
					<CheckboxControl
						__nextHasNoMarginBottom
						label={ __( 'Noindex date archives', 'acme-seo' ) }
						checked={ indexing.noindex_date_archives }
						onChange={ set( 'indexing', 'noindex_date_archives' ) }
					/>
					<CheckboxControl
						__nextHasNoMarginBottom
						label={ __( 'Noindex tag archives', 'acme-seo' ) }
						checked={ indexing.noindex_tag_archives }
						onChange={ set( 'indexing', 'noindex_tag_archives' ) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Enable XML sitemap', 'acme-seo' ) }
						checked={ sitemap.enabled }
						onChange={ set( 'sitemap', 'enabled' ) }
					/>
					<ExcludeField ids={ sitemap.exclude } onChange={ set( 'sitemap', 'exclude' ) } />
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Google verification code', 'acme-seo' ) }
						value={ verification.google }
						onChange={ set( 'verification', 'google' ) }
					/>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Bing verification code', 'acme-seo' ) }
						value={ verification.bing }
						onChange={ set( 'verification', 'bing' ) }
					/>
				</PanelBody>
				<PanelBody title={ __( 'Social', 'acme-seo' ) }>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Enable Open Graph tags', 'acme-seo' ) }
						checked={ social.og_enabled }
						onChange={ set( 'social', 'og_enabled' ) }
					/>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						type="number"
						min={ 0 }
						label={ __( 'Default share image (attachment ID)', 'acme-seo' ) }
						value={ String( social.default_image || '' ) }
						onChange={ ( value ) => set( 'social', 'default_image' )( Math.max( 0, parseInt( value, 10 ) || 0 ) ) }
					/>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Twitter/X username', 'acme-seo' ) }
						help={ __( 'Without the @.', 'acme-seo' ) }
						value={ social.twitter_handle }
						onChange={ set( 'social', 'twitter_handle' ) }
					/>
					{ NETWORKS.map( ( [ network, label ] ) => (
						<TextControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							key={ network }
							type="url"
							label={ label }
							value={ social.profiles[ network ] }
							onChange={ ( value ) =>
								set( 'social', 'profiles' )( { ...social.profiles, [ network ]: value } )
							}
						/>
					) ) }
				</PanelBody>
			</Panel>
			<p className="submit">
				<Button
					variant="primary"
					type="submit"
					isBusy={ isSaving }
				>
					{ __( 'Save changes', 'acme-seo' ) }
				</Button>
			</p>
		</form>
	);
}

domReady( () => {
	const root = document.getElementById( 'acme-seo-settings-app' );
	if ( root ) {
		createRoot( root ).render( <SettingsApp /> );
	}
} );
