<?php
/**
 * Fix: YouTube's privacy-enhanced links (`https://www.youtube-nocookie.com/embed/<video>`) in WordPress's Embed block.
 *
 * WordPress embeds youtube.com and youtu.be links through YouTube's oEmbed service, which knows no youtube-nocookie.com
 * links, so the Embed block says it cannot embed them. This adds an embed handler (WordPress's own API for links
 * without an oEmbed provider) that writes the player itself, as YouTube's "privacy-enhanced mode" embed code does,
 * without asking YouTube's oEmbed service or the link's page anything.
 *
 * In the editor, a variation of the Embed block (src/admin/youtube-no-cookie) makes these links YouTube embeds that
 * keep their aspect ratio, as youtube.com links are. The Embed block asks WordPress's oEmbed proxy for a preview. The proxy tries oEmbed discovery first
 * (a request to the link's page) and only then the embed handlers, and calls the result "Embed Handler". For these
 * links the proxy answers straight from the handler instead, as YouTube, so the block becomes the YouTube embed,
 * with its classes and aspect ratio, and no request leaves the site.
 *
 * @package Ship\Fixes
 */

declare( strict_types=1 );

namespace Ship\Fixes;

use WP_Embed;
use WP_HTML_Tag_Processor;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Embeds youtube-nocookie.com links.
 */
final class YouTube_No_Cookie {

	/** The embed handler's ID. */
	public const HANDLER = 'ship_fixes_youtube_no_cookie';

	/** The links it embeds: the video (or `videoseries` for a playlist) after /embed/, then any query or fragment. */
	public const PATTERN = '#^https?://(?:www\.)?youtube-nocookie\.com/embed/([A-Za-z0-9_-]+)/?(?:[?\#].*)?$#i';

	/** WordPress's oEmbed proxy route, which the Embed block asks for previews. */
	private const PROXY_ROUTE = '/oembed/1.0/proxy';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		wp_embed_register_handler( self::HANDLER, self::PATTERN, array( __CLASS__, 'embed' ) );
		add_filter( 'rest_dispatch_request', array( __CLASS__, 'preview' ), 10, 3 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editor' ) );
	}

	/**
	 * In the block editor: the Embed block's variation for these links (src/admin/youtube-no-cookie), so a pasted link
	 * becomes a responsive YouTube embed as youtube.com links do.
	 *
	 * @return void
	 */
	public static function editor(): void {
		$asset = Admin::asset( 'youtube-no-cookie' );
		wp_enqueue_script( 'ship-fixes-youtube-no-cookie', plugins_url( 'build/admin/youtube-no-cookie.js', SHIP_FIXES_FILE ), $asset['dependencies'], $asset['version'], array( 'in_footer' => true ) );
	}

	/**
	 * The player for a link (embed handler callback).
	 *
	 * @param array<int, string>   $matches Matches of PATTERN: 1 is the video.
	 * @param array<string, mixed> $attr    Width and height (WordPress's defaults where none were given).
	 * @param string               $url     The link.
	 * @return string
	 */
	public static function embed( array $matches, array $attr, string $url ): string {
		$src   = 'https://www.youtube-nocookie.com/embed/' . rawurlencode( $matches[1] );
		$query = wp_parse_url( $url, PHP_URL_QUERY );
		if ( is_string( $query ) && '' !== $query ) {
			$src .= '?' . $query;
		}

		// 16:9, as wide as allowed unless that is too high.
		$width  = isset( $attr['width'] ) ? absint( $attr['width'] ) : 0;
		$width  = $width ? $width : 560;
		$height = (int) round( $width * 9 / 16 );
		$max    = isset( $attr['height'] ) ? absint( $attr['height'] ) : 0;
		if ( $max && $height > $max ) {
			$height = $max;
			$width  = (int) round( $max * 16 / 9 );
		}

		// The attributes of YouTube's own embed code; the referrer is needed, YouTube refuses to play without it.
		$player = new WP_HTML_Tag_Processor( '<iframe></iframe>' );
		$player->next_tag();
		$player->set_attribute( 'width', (string) $width );
		$player->set_attribute( 'height', (string) $height );
		// The processor escapes it as a URL.
		$player->set_attribute( 'src', $src );
		$player->set_attribute( 'title', __( 'YouTube video player', 'ship-fixes' ) );
		$player->set_attribute( 'frameborder', '0' );
		$player->set_attribute( 'allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share' );
		$player->set_attribute( 'referrerpolicy', 'strict-origin-when-cross-origin' );
		$player->set_attribute( 'allowfullscreen', true );
		return $player->get_updated_html();
	}

	/**
	 * Answers the oEmbed proxy for these links, after its permission check (rest_dispatch_request filter).
	 *
	 * @param mixed           $result  Result so far (null: the route's callback runs).
	 * @param WP_REST_Request $request The request.
	 * @param string          $route   The route.
	 * @return mixed
	 */
	public static function preview( mixed $result, WP_REST_Request $request, string $route ): mixed {
		global $wp_embed;

		$url = $request->get_param( 'url' );
		if ( null !== $result || self::PROXY_ROUTE !== $route || ! is_string( $url ) || ! preg_match( self::PATTERN, $url ) || ! $wp_embed instanceof WP_Embed ) {
			return $result;
		}

		// As the proxy itself does: WP_Embed takes width and height.
		$attr = array();
		if ( $request->get_param( 'maxwidth' ) ) {
			$attr['width'] = absint( $request->get_param( 'maxwidth' ) );
		}
		if ( $request->get_param( 'maxheight' ) ) {
			$attr['height'] = absint( $request->get_param( 'maxheight' ) );
		}
		$html = $wp_embed->get_embed_handler_html( $attr, $url );
		if ( ! is_string( $html ) || '' === $html ) {
			return $result;
		}

		return (object) array(
			'version'       => '1.0',
			'type'          => 'video',
			'provider_name' => 'YouTube',
			'provider_url'  => 'https://www.youtube.com/',
			'html'          => $html,
		);
	}
}
