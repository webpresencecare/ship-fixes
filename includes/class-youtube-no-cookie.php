<?php
/**
 * Fix: YouTube videos in Embed blocks play from youtube-nocookie.com (YouTube's "privacy-enhanced mode"), whatever
 * the form of the link.
 *
 * WordPress's own oEmbed does the work: it asks YouTube's oEmbed service for the player (from the server; the answer,
 * with the video's real title, is cached in the post's `_oembed_*` meta). This fix only changes what is shown: the
 * player's address moves from www.youtube.com/embed/ to www.youtube-nocookie.com/embed/, with the link's start time
 * and without YouTube's share-tracking parameters. The caches keep YouTube's answer as it came, so switching the fix
 * off shows the players as WordPress makes them, without any cache to empty, and players cached before the fix
 * was on are changed too.
 *
 * - Front end: `embed_oembed_html` (cached and fresh results).
 * - Editor preview: the oEmbed proxy's answer (`rest_request_after_callbacks`, also for its own cached answers).
 * - Links WordPress has no provider for (`/embed/` links on youtube.com and youtube-nocookie.com): YouTube's oEmbed
 *   service is asked about the video's watch link instead (`oembed_providers`, `oembed_fetch_url`). On the front end,
 *   youtube.com `/embed/` and `/v/` links go to WordPress's own embed handler first, which takes everything after
 *   /embed/ for the video, query included (`embed/<id>?start=60` fails); an embed handler just before it embeds the
 *   video's watch link instead.
 * - When YouTube's answer cannot be had (offline, video removed): a player written here, with a generic title
 *   (`embed_maybe_make_link`; in the editor, instead of the proxy's "cannot embed").
 * - Editor: a variation of the Embed block (src/admin/youtube-no-cookie) makes pasted youtube-nocookie.com links
 *   responsive YouTube embeds, as WordPress's own variation does for youtube.com links.
 *
 * @package Ship\Fixes
 */

declare( strict_types=1 );

namespace Ship\Fixes;

use WP_Embed;
use WP_Error;
use WP_HTML_Tag_Processor;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plays YouTube videos from youtube-nocookie.com.
 *
 * @phpstan-type Video array{id: string, list: string, start: int}
 */
final class YouTube_No_Cookie {

	/** Where the players play from. */
	public const HOST = 'www.youtube-nocookie.com';

	/** Links WordPress has no oEmbed provider for: the player's own address, on youtube.com or youtube-nocookie.com. */
	public const EMBED_LINKS = '#^https?://((m|www)\.)?youtube(-nocookie)?\.com/embed/#i';

	/** The embed handler for youtube.com /embed/ and /v/ links, before WordPress's own (`youtube_embed_url`, 10). */
	public const HANDLER = 'ship_fixes_youtube_embed_url';

	/** YouTube's oEmbed service, as in WordPress's own provider list. */
	private const OEMBED = 'https://www.youtube.com/oembed';

	/** WordPress's oEmbed proxy route, which the Embed block asks for previews. */
	private const PROXY_ROUTE = '/oembed/1.0/proxy';

	/** YouTube's share-tracking parameters, left out of the player's address. */
	private const TRACKING = array( 'si', 'feature', 'pp' );

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		wp_embed_register_handler( self::HANDLER, '#^https?://((m|www)\.)?youtube\.com/(v|embed)/#i', array( __CLASS__, 'embed_link' ), 9 );
		add_filter( 'oembed_providers', array( __CLASS__, 'providers' ) );
		add_filter( 'oembed_fetch_url', array( __CLASS__, 'fetch_url' ), 10, 2 );
		add_filter( 'embed_oembed_html', array( __CLASS__, 'front_end' ), 10, 2 );
		add_filter( 'embed_maybe_make_link', array( __CLASS__, 'unembedded' ), 10, 2 );
		add_filter( 'rest_request_after_callbacks', array( __CLASS__, 'preview' ), 10, 3 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editor' ) );
	}

	/**
	 * The video, playlist and start time of a YouTube link, or null when it is not one.
	 *
	 * Accepted: youtu.be/<id>, youtube.com/watch?v=<id>, /embed/<id>, /v/<id>, /shorts/<id>, /live/<id>, /playlist?list=<list>,
	 * /embed/videoseries?list=<list>, also on youtube-nocookie.com; http or https, with or without www. or m.
	 *
	 * @param string $url Link.
	 * @phpstan-return Video|null
	 * @return array<string, int|string>|null
	 */
	public static function parse( string $url ): ?array {
		$parts = wp_parse_url( trim( str_replace( '&amp;', '&', $url ) ) );
		if ( ! is_array( $parts ) || ! isset( $parts['host'], $parts['scheme'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			return null;
		}
		$host = (string) preg_replace( '/^(www|m)\./', '', strtolower( $parts['host'] ) );
		if ( ! in_array( $host, array( 'youtu.be', 'youtube.com', 'youtube-nocookie.com' ), true ) ) {
			return null;
		}

		$query = array();
		wp_parse_str( $parts['query'] ?? '', $query );
		$path = explode( '/', trim( $parts['path'] ?? '', '/' ) );
		$id   = match ( true ) {
			'youtu.be' === $host                               => $path[0],
			'watch' === $path[0]                               => is_string( $query['v'] ?? null ) ? $query['v'] : '',
			in_array( $path[0], array( 'embed', 'v', 'shorts', 'live' ), true ) => $path[1] ?? '',
			default                                            => '',
		};
		// Video IDs are 11 characters; so is `videoseries`, the playlist player's path.
		$id   = 'videoseries' !== $id && 1 === preg_match( '/^[A-Za-z0-9_-]{11}$/', $id ) ? $id : '';
		$list = is_string( $query['list'] ?? null ) && 1 === preg_match( '/^[A-Za-z0-9_-]+$/', $query['list'] ) ? $query['list'] : '';
		if ( '' === $id && '' === $list ) {
			return null;
		}

		return array(
			'id'    => $id,
			'list'  => $list,
			'start' => self::start( $query, $parts['fragment'] ?? '' ),
		);
	}

	/**
	 * The start time of a link in seconds (0: from the beginning): `start` or `t` in the query, else `t` in the
	 * fragment; written 2117, 2117s, 35m17s or 1h2m3s.
	 *
	 * @param array<mixed> $query    The link's query.
	 * @param string       $fragment The link's fragment.
	 * @return int
	 */
	public static function start( array $query, string $fragment ): int {
		$in_fragment = array();
		wp_parse_str( $fragment, $in_fragment );
		foreach ( array( $query['start'] ?? null, $query['t'] ?? null, $in_fragment['t'] ?? null, $in_fragment['start'] ?? null ) as $time ) {
			if ( ! is_string( $time ) || '' === $time ) {
				continue;
			}
			// Digits capped: a huge number would overflow into a float.
			if ( 1 === preg_match( '/^(?:(\d{1,3})h)?(?:(\d{1,4})m)?(?:(\d{1,6})s?)?$/i', $time, $units ) ) {
				return (int) ( $units[1] ?? 0 ) * HOUR_IN_SECONDS + (int) ( $units[2] ?? 0 ) * MINUTE_IN_SECONDS + (int) ( $units[3] ?? 0 );
			}
		}
		return 0;
	}

	/**
	 * The player's address on youtube-nocookie.com: from YouTube's own player address when there is one (its path and
	 * query), else from the video; the link's start time added, share-tracking parameters left out.
	 *
	 * @param array  $video  The video.
	 * @param string $player YouTube's player address, if any.
	 * @phpstan-param Video $video
	 * @return string
	 */
	public static function player_src( array $video, string $player = '' ): string {
		$query = array();
		$path  = '';
		if ( '' !== $player ) {
			$parts = wp_parse_url( $player );
			$path  = is_array( $parts ) ? (string) ( $parts['path'] ?? '' ) : '';
			wp_parse_str( is_array( $parts ) ? (string) ( $parts['query'] ?? '' ) : '', $query );
		}
		if ( ! str_starts_with( $path, '/embed/' ) ) {
			$path  = '/embed/' . ( '' !== $video['id'] ? $video['id'] : 'videoseries' );
			$query = '' !== $video['list'] ? array( 'list' => $video['list'] ) : array();
		}
		$query = array_diff_key( $query, array_flip( self::TRACKING ) );
		if ( $video['start'] > 0 ) {
			$query['start'] = $video['start'];
		}
		return 'https://' . self::HOST . $path . ( array() === $query ? '' : '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 ) );
	}

	/**
	 * The video's watch link (or the playlist's), with its start time: the link YouTube's oEmbed service knows.
	 *
	 * @param array $video The video.
	 * @phpstan-param Video $video
	 * @return string
	 */
	public static function watch( array $video ): string {
		if ( '' === $video['id'] ) {
			return 'https://www.youtube.com/playlist?list=' . $video['list'];
		}
		return 'https://www.youtube.com/watch?v=' . $video['id']
			. ( '' !== $video['list'] ? '&list=' . $video['list'] : '' )
			. ( $video['start'] > 0 ? '&t=' . $video['start'] : '' );
	}

	/**
	 * Moves the YouTube players in some HTML to youtube-nocookie.com. HTML of other links is returned as it is.
	 *
	 * @param string $html The embed's HTML.
	 * @param string $url  The link it was made from.
	 * @return string
	 */
	public static function rewrite( string $html, string $url ): string {
		$video = self::parse( $url );
		if ( null === $video ) {
			return $html;
		}
		$processor = new WP_HTML_Tag_Processor( $html );
		while ( $processor->next_tag( array( 'tag_name' => 'iframe' ) ) ) {
			$src = $processor->get_attribute( 'src' );
			if ( ! is_string( $src ) || null === self::parse( $src ) ) {
				continue;
			}
			// The processor escapes it as a URL.
			$processor->set_attribute( 'src', self::player_src( $video, $src ) );
			$title = $processor->get_attribute( 'title' );
			if ( ! is_string( $title ) || '' === trim( $title ) ) {
				$processor->set_attribute( 'title', __( 'YouTube video player', 'ship-fixes' ) );
			}
		}
		return $processor->get_updated_html();
	}

	/**
	 * A player written here, for when YouTube's answer cannot be had: 16:9 as wide as allowed, a generic title.
	 *
	 * @param array                $video The video.
	 * @param array<string, mixed> $attr  Width and height, if any.
	 * @phpstan-param Video $video
	 * @return string
	 */
	public static function player( array $video, array $attr = array() ): string {
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
		$player->set_attribute( 'src', self::player_src( $video ) );
		$player->set_attribute( 'title', __( 'YouTube video player', 'ship-fixes' ) );
		$player->set_attribute( 'frameborder', '0' );
		$player->set_attribute( 'allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share' );
		$player->set_attribute( 'referrerpolicy', 'strict-origin-when-cross-origin' );
		$player->set_attribute( 'allowfullscreen', true );
		return $player->get_updated_html();
	}

	/**
	 * YouTube's oEmbed service for /embed/ links too (oembed_providers filter).
	 *
	 * @param mixed $providers Providers: pattern => array( endpoint, is regex ).
	 * @return mixed
	 */
	public static function providers( mixed $providers ): mixed {
		if ( is_array( $providers ) ) {
			$providers[ self::EMBED_LINKS ] = array( self::OEMBED, true );
		}
		return $providers;
	}

	/**
	 * For /embed/ links, asks YouTube's oEmbed service about the video's watch link, which it knows
	 * (oembed_fetch_url filter).
	 *
	 * @param mixed  $provider The oEmbed request's address, with the link.
	 * @param string $url      The link.
	 * @return mixed
	 */
	public static function fetch_url( mixed $provider, string $url ): mixed {
		$video = 1 === preg_match( self::EMBED_LINKS, $url ) ? self::parse( $url ) : null;
		if ( ! is_string( $provider ) || null === $video ) {
			return $provider;
		}
		return add_query_arg( 'url', rawurlencode( self::watch( $video ) ), $provider );
	}

	/**
	 * Front end, youtube.com /embed/ and /v/ links: embeds the video's watch link, as WordPress's own handler means to
	 * (embed handler callback). False leaves the link to WordPress's handler.
	 *
	 * @param array<int, string>   $matches Matches of the handler's pattern.
	 * @param array<string, mixed> $attr    Embed attributes, with WordPress's defaults.
	 * @param string               $url     The link.
	 * @param array<string, mixed> $rawattr Embed attributes as given.
	 * @return string|false
	 */
	public static function embed_link( array $matches, array $attr, string $url, array $rawattr ): string|false {
		global $wp_embed;
		$video = self::parse( $url );
		if ( null === $video || ! $wp_embed instanceof WP_Embed ) {
			return false;
		}
		$html = $wp_embed->shortcode( $rawattr, self::watch( $video ) );
		return is_string( $html ) && '' !== $html ? $html : false;
	}

	/**
	 * Front end: the players of YouTube links, cached or fresh (embed_oembed_html filter).
	 *
	 * @param mixed  $html The embed's HTML.
	 * @param string $url  The link.
	 * @return mixed
	 */
	public static function front_end( mixed $html, string $url ): mixed {
		return is_string( $html ) ? self::rewrite( $html, $url ) : $html;
	}

	/**
	 * A YouTube link WordPress could not embed (YouTube's answer could not be had): a player written here instead of
	 * the link (embed_maybe_make_link filter).
	 *
	 * @param mixed  $output The link, or its address alone.
	 * @param string $url    The link's address.
	 * @return mixed
	 */
	public static function unembedded( mixed $output, string $url ): mixed {
		global $wp_embed;
		$video = self::parse( $url );
		if ( null === $video ) {
			return $output;
		}
		return self::player( $video, $wp_embed instanceof WP_Embed && is_array( $wp_embed->last_attr ) ? $wp_embed->last_attr : array() );
	}

	/**
	 * Editor preview: the oEmbed proxy's answer for YouTube links, fresh or cached by the proxy; when the proxy could
	 * not embed one, a player written here (rest_request_after_callbacks filter, also after a refused permission
	 * check: refusals stay as they are).
	 *
	 * @param mixed           $response The route's answer.
	 * @param mixed           $handler  The route's handler.
	 * @param WP_REST_Request $request  The request.
	 * @return mixed
	 */
	public static function preview( mixed $response, mixed $handler, WP_REST_Request $request ): mixed {
		$url   = $request->get_param( 'url' );
		$video = self::PROXY_ROUTE === $request->get_route() && is_string( $url ) ? self::parse( $url ) : null;
		if ( null === $video ) {
			return $response;
		}

		if ( $response instanceof WP_Error ) {
			if ( 'oembed_invalid_url' !== $response->get_error_code() ) {
				return $response;
			}
			return (object) array(
				'version'       => '1.0',
				'type'          => 'video',
				'provider_name' => 'YouTube',
				'provider_url'  => 'https://www.youtube.com/',
				'html'          => self::player(
					$video,
					array(
						'width'  => $request->get_param( 'maxwidth' ),
						'height' => $request->get_param( 'maxheight' ),
					)
				),
			);
		}

		if ( is_object( $response ) && isset( $response->html ) && is_string( $response->html ) ) {
			// A copy: the proxy's cache keeps YouTube's answer as it came. A YouTube video also when the answer came
			// from the embed handlers (YouTube unreachable): the proxy would call it "Embed Handler".
			$response = (object) array_merge(
				get_object_vars( $response ),
				array(
					'html'          => self::rewrite( $response->html, $url ),
					'provider_name' => 'YouTube',
					'type'          => 'video',
				)
			);
		}
		return $response;
	}

	/**
	 * In the block editor: the Embed block's variation for youtube-nocookie.com links (src/admin/youtube-no-cookie).
	 *
	 * @return void
	 */
	public static function editor(): void {
		$asset = Admin::asset( 'youtube-no-cookie' );
		wp_enqueue_script( 'ship-fixes-youtube-no-cookie', plugins_url( 'build/admin/youtube-no-cookie.js', SHIP_FIXES_FILE ), $asset['dependencies'], $asset['version'], array( 'in_footer' => true ) );
	}
}
