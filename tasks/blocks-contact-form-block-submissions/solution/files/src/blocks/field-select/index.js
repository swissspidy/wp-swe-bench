/**
 * Drop-down field block (rendered by its Contact form on the server).
 */
import { registerFieldBlock } from '../shared/field';
import metadata from './block.json';

registerFieldBlock( metadata, 'select' );
