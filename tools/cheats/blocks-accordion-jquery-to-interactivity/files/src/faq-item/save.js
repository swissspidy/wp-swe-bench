/**
 * Only the answer blocks are saved; the question markup is rendered on the server.
 */
import { InnerBlocks } from '@wordpress/block-editor';

export default function save() {
	return <InnerBlocks.Content />;
}
