/**
 * YouTube privacy-enhanced mode (fix `youtube_no_cookie`), in the block editor: a hidden variation of the Embed
 * block whose pattern matches youtube-nocookie.com/embed/ links. The Embed block upgrades a link that matches a
 * variation's pattern to that variation's attributes, as it does youtube.com links, so these become YouTube embeds
 * (provider `youtube`) that keep their aspect ratio when the window narrows (`responsive`). WordPress's own YouTube
 * variation is the one shown: the Embed block tells its variations apart by provider only.
 *
 * WordPress's own YouTube variation already matches youtube.com links, /embed/ ones included. The player itself moves
 * to youtube-nocookie.com on the server (`YouTube_No_Cookie`).
 */
import { registerBlockVariation } from '@wordpress/blocks';

registerBlockVariation( 'core/embed', {
	name: 'ship-fixes-youtube-no-cookie',
	patterns: [
		/^https?:\/\/(www\.)?youtube-nocookie\.com\/embed\/[\w-]+\/?([?#].*)?$/i,
	],
	attributes: { providerNameSlug: 'youtube', responsive: true },
	// Not offered anywhere (inserter, transforms): it only teaches the Embed block the links.
	scope: [],
} );
