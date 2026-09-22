/**
 * Saved markup (2.0+): only the inner blocks. The wrapper, title and
 * accessibility attributes are rendered on the server (see render.php), so
 * that every saved format – including 1.x content – renders the same markup.
 */
import { InnerBlocks } from '@wordpress/block-editor';

export default function save() {
	return <InnerBlocks.Content />;
}
