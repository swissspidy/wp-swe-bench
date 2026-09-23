/**
 * Editor script: a "Shortcode" panel on the member screen, so editors can
 * copy the [team_member] shortcode for classic content.
 */
import { __, sprintf } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';

import './bindings';

function MemberShortcodePanel() {
	const { postType, postId } = useSelect( ( select ) => {
		const editor = select( editorStore );
		return {
			postType: editor.getCurrentPostType(),
			postId: editor.getCurrentPostId(),
		};
	}, [] );

	if ( postType !== 'acme_member' ) {
		return null;
	}

	return (
		<PluginDocumentSettingPanel
			name="acme-team-shortcode"
			title={ __( 'Shortcode', 'acme-team' ) }
		>
			<code>{ sprintf( '[team_member id="%d"]', postId ) }</code>
		</PluginDocumentSettingPanel>
	);
}

registerPlugin( 'acme-team-shortcode', { render: MemberShortcodePanel } );
