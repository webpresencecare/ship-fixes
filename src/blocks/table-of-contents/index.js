/**
 * The Table of Contents block (`core/table-of-contents`), from Gutenberg 24.4.1 (GPL-2.0-or-later). This script is
 * only loaded where the plugin registered the block on the server, that is where nothing else did.
 */
import { getBlockType, registerBlockType } from '@wordpress/blocks';
import { tableOfContents as icon } from '@wordpress/icons';

import deprecated from './deprecated';
import edit from './edit';
import metadata from './block.json';
import './style.scss';

if ( ! getBlockType( metadata.name ) ) {
	registerBlockType( metadata, { icon, deprecated, edit, example: {} } );
}
