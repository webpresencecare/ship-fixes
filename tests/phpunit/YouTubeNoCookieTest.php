<?php
/**
 * The YouTube privacy-enhanced mode fix: the link parser and start times, the players moved to youtube-nocookie.com
 * (cached before the fix, fresh, in the editor's preview), the fallback player, and nothing changed when it is off.
 * YouTube's oEmbed service is answered here: no request leaves the tests.
 *
 * @package Ship\Fixes
 */

use Ship\Fixes\Fixes;
use Ship\Fixes\Plugin;
use Ship\Fixes\Settings;
use Ship\Fixes\YouTube_No_Cookie;

/**
 * @covers \Ship\Fixes\YouTube_No_Cookie
 */
class YouTubeNoCookieTest extends Ship_Fixes_TestCase {

	/**
	 * The links asked about from YouTube's oEmbed service, and any other address requested.
	 *
	 * @var array<int, string>
	 */
	private array $requests = array();

	/**
	 * Whether YouTube's oEmbed service answers.
	 *
	 * @var bool
	 */
	private bool $online = true;

	public function set_up(): void {
		parent::set_up();
		add_filter( 'pre_http_request', array( $this, 'answer' ), 10, 3 );
	}

	/**
	 * Answers YouTube's oEmbed requests as YouTube does (its player on www.youtube.com, the video's title); refuses
	 * every other request. Records each one.
	 *
	 * @param mixed  $pre  Short-circuit value.
	 * @param array  $args Request arguments.
	 * @param string $url  Address.
	 * @return array<string, mixed>|WP_Error
	 */
	public function answer( $pre, $args, $url ) {
		$query = array();
		wp_parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
		if ( ! str_starts_with( $url, 'https://www.youtube.com/oembed?' ) || ! $this->online ) {
			$this->requests[] = $url;
			return new WP_Error( 'http_request_blocked', 'No requests in these tests.' );
		}
		$this->requests[] = (string) $query['url'];
		$video            = YouTube_No_Cookie::parse( (string) $query['url'] );
		if ( null === $video ) {
			return array(
				'headers'  => array(),
				'body'     => 'Not Found',
				'response' => array(
					'code'    => 404,
					'message' => 'Not Found',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		}
		$src  = '' !== $video['id'] ? 'https://www.youtube.com/embed/' . $video['id'] . '?feature=oembed' : 'https://www.youtube.com/embed/videoseries?list=' . $video['list'];
		$html = sprintf( '<iframe width="560" height="315" src="%s" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen title="Title of %s"></iframe>', $src, $video['id'] . $video['list'] );
		return array(
			'headers'  => array( 'content-type' => 'application/json' ),
			'body'     => (string) wp_json_encode(
				array(
					'type'          => 'video',
					'version'       => '1.0',
					'provider_name' => 'YouTube',
					'provider_url'  => 'https://www.youtube.com/',
					'title'         => 'Title of ' . $video['id'],
					'width'         => 560,
					'height'        => 315,
					'html'          => $html,
				)
			),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * The first iframe in some HTML, as a tag processor on it.
	 *
	 * @param string $html HTML.
	 * @return WP_HTML_Tag_Processor
	 */
	private function iframe( string $html ): WP_HTML_Tag_Processor {
		$processor = new WP_HTML_Tag_Processor( $html );
		$this->assertTrue( $processor->next_tag( array( 'tag_name' => 'iframe' ) ), 'An iframe: ' . $html );
		return $processor;
	}

	/**
	 * A post with an Embed block of a link, shown as on the front end.
	 *
	 * @param string   $url     Link.
	 * @param int|null $post_id The post to show again, if any.
	 * @return array{0: int, 1: string} Post ID and its content's HTML.
	 */
	private function front( string $url, ?int $post_id = null ): array {
		$post_id ??= self::factory()->post->create(
			array(
				'post_content' => '<!-- wp:embed {"url":"' . esc_url( $url ) . '","type":"video","providerNameSlug":"youtube","responsive":true} -->'
					. '<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube"><div class="wp-block-embed__wrapper">' . "\n" . esc_url( $url ) . "\n" . '</div></figure><!-- /wp:embed -->',
			)
		);
		$this->go_to( (string) get_permalink( $post_id ) );
		setup_postdata( get_post( $post_id ) );
		return array( $post_id, (string) apply_filters( 'the_content', get_post_field( 'post_content', $post_id ) ) );
	}

	/**
	 * Asks the oEmbed proxy for a preview, as the Embed block does.
	 *
	 * @param string $url Link.
	 * @return WP_REST_Response
	 */
	private function preview( string $url ): WP_REST_Response {
		$request = new WP_REST_Request( 'GET', '/oembed/1.0/proxy' );
		$request->set_query_params(
			array(
				'url'      => $url,
				'maxwidth' => 600,
			)
		);
		return rest_get_server()->dispatch( $request );
	}

	/**
	 * The parser's answers for Patricia's links and the other forms.
	 *
	 * @return array<string, array{0: string, 1: array{id: string, list: string, start: int}|null}>
	 */
	public static function links(): array {
		$video = static fn ( string $id, int $start = 0, string $playlist = '' ): array => array(
			'id'    => $id,
			'list'  => $playlist,
			'start' => $start,
		);
		return array(
			'youtu.be'                     => array( 'https://youtu.be/eMTEmPNwkGQ', $video( 'eMTEmPNwkGQ' ) ),
			'youtu.be with si'             => array( 'https://youtu.be/jzuTOdM4kRI?si=SKFPywCGF82_VjsQ', $video( 'jzuTOdM4kRI' ) ),
			'watch with t in seconds'      => array( 'https://www.youtube.com/watch?v=0zQjLy-f_Dg&t=2117s', $video( '0zQjLy-f_Dg', 2117 ) ),
			'youtu.be shared'              => array( 'https://youtu.be/HVVpa_-pLkE?feature=shared', $video( 'HVVpa_-pLkE' ) ),
			'nocookie embed'               => array( 'https://www.youtube-nocookie.com/embed/eMTEmPNwkGQ', $video( 'eMTEmPNwkGQ' ) ),
			'watch, v not first, http, m.' => array( 'http://m.youtube.com/watch?feature=shared&v=0zQjLy-f_Dg&t=2117', $video( '0zQjLy-f_Dg', 2117 ) ),
			'encoded ampersand'            => array( 'https://www.youtube.com/watch?v=0zQjLy-f_Dg&amp;t=35m17s', $video( '0zQjLy-f_Dg', 2117 ) ),
			'no www, hours'                => array( 'https://youtube.com/watch?v=0zQjLy-f_Dg&t=1h2m3s', $video( '0zQjLy-f_Dg', 3723 ) ),
			'youtube.com embed with start' => array( 'https://www.youtube.com/embed/eMTEmPNwkGQ?start=2117', $video( 'eMTEmPNwkGQ', 2117 ) ),
			'fragment'                     => array( 'https://www.youtube.com/watch?v=0zQjLy-f_Dg#t=1m5s', $video( '0zQjLy-f_Dg', 65 ) ),
			'v'                            => array( 'https://www.youtube.com/v/eMTEmPNwkGQ', $video( 'eMTEmPNwkGQ' ) ),
			'shorts'                       => array( 'https://www.youtube.com/shorts/HVVpa_-pLkE', $video( 'HVVpa_-pLkE' ) ),
			'live'                         => array( 'https://www.youtube.com/live/jzuTOdM4kRI?si=x', $video( 'jzuTOdM4kRI' ) ),
			'playlist'                     => array( 'https://www.youtube.com/playlist?list=PLx0sYbCqOb8TBPRdmBHs5Iftvv9TPboYG', $video( '', 0, 'PLx0sYbCqOb8TBPRdmBHs5Iftvv9TPboYG' ) ),
			'video in a playlist'          => array( 'https://www.youtube.com/watch?v=eMTEmPNwkGQ&list=PLx0sYbCqOb8TBPRdmBHs5Iftvv9TPboYG', $video( 'eMTEmPNwkGQ', 0, 'PLx0sYbCqOb8TBPRdmBHs5Iftvv9TPboYG' ) ),
			'videoseries'                  => array( 'https://www.youtube-nocookie.com/embed/videoseries?list=PL123', $video( '', 0, 'PL123' ) ),
			'huge start'                   => array( 'https://youtu.be/eMTEmPNwkGQ?t=9999999999999999h', $video( 'eMTEmPNwkGQ' ) ),
			'unreadable start'             => array( 'https://youtu.be/eMTEmPNwkGQ?t=soon', $video( 'eMTEmPNwkGQ' ) ),
			'other site'                   => array( 'https://vimeo.com/76979871', null ),
			'look-alike host'              => array( 'https://www.youtube.com.example.org/watch?v=eMTEmPNwkGQ', null ),
			'not a video'                  => array( 'https://www.youtube.com/@channel', null ),
			'bad id'                       => array( 'https://youtu.be/eMTEm"Q', null ),
			'not http'                     => array( 'javascript://youtu.be/eMTEmPNwkGQ', null ),
		);
	}

	/**
	 * @dataProvider links
	 *
	 * @param string     $url      Link.
	 * @param array|null $expected Video, or null.
	 */
	public function test_parses_links( string $url, ?array $expected ): void {
		$this->assertSame( $expected, YouTube_No_Cookie::parse( $url ) );
	}

	public function test_player_address_keeps_start_and_drops_tracking(): void {
		$video = YouTube_No_Cookie::parse( 'https://www.youtube.com/watch?v=0zQjLy-f_Dg&t=2117s&si=abc' );
		$this->assertSame( 'https://www.youtube-nocookie.com/embed/0zQjLy-f_Dg?start=2117', YouTube_No_Cookie::player_src( $video, 'https://www.youtube.com/embed/0zQjLy-f_Dg?feature=oembed&pp=x' ) );
		$this->assertSame( 'https://www.youtube-nocookie.com/embed/0zQjLy-f_Dg?start=2117', YouTube_No_Cookie::player_src( $video ), 'Without YouTube\'s player: from the video.' );
		$list = YouTube_No_Cookie::parse( 'https://www.youtube.com/playlist?list=PL123' );
		$this->assertSame( 'https://www.youtube-nocookie.com/embed/videoseries?list=PL123', YouTube_No_Cookie::player_src( $list ) );
	}

	public function test_front_end_fresh_answer_with_title_start_and_untouched_cache(): void {
		[ $post_id, $html ] = $this->front( 'https://www.youtube.com/watch?v=0zQjLy-f_Dg&t=2117s' );
		$player             = $this->iframe( $html );
		$this->assertSame( 'https://www.youtube-nocookie.com/embed/0zQjLy-f_Dg?start=2117', $player->get_attribute( 'src' ) );
		$this->assertSame( 'Title of 0zQjLy-f_Dg', $player->get_attribute( 'title' ), 'The video\'s own title.' );
		$this->assertStringNotContainsString( 'www.youtube.com/embed', $html );
		$this->assertSame( array( 'https://www.youtube.com/watch?v=0zQjLy-f_Dg&t=2117s' ), $this->requests );

		$cached = '';
		foreach ( get_post_meta( $post_id ) as $key => $values ) {
			if ( str_starts_with( (string) $key, '_oembed_' ) && ! str_starts_with( (string) $key, '_oembed_time_' ) ) {
				$cached .= (string) $values[0];
			}
		}
		$this->assertStringContainsString( 'https://www.youtube.com/embed/0zQjLy-f_Dg?feature=oembed', $cached, 'The cache keeps YouTube\'s answer.' );
	}

	public function test_front_end_answer_cached_before_the_fix(): void {
		remove_filter( 'embed_oembed_html', array( YouTube_No_Cookie::class, 'front_end' ) );
		[ $post_id, $before ] = $this->front( 'https://youtu.be/jzuTOdM4kRI?si=SKFPywCGF82_VjsQ' );
		$this->assertSame( 'https://www.youtube.com/embed/jzuTOdM4kRI?feature=oembed', $this->iframe( $before )->get_attribute( 'src' ) );

		add_filter( 'embed_oembed_html', array( YouTube_No_Cookie::class, 'front_end' ), 10, 2 );
		$this->requests = array();
		[ , $after ]    = $this->front( '', $post_id );
		$player         = $this->iframe( $after );
		$this->assertSame( 'https://www.youtube-nocookie.com/embed/jzuTOdM4kRI', $player->get_attribute( 'src' ) );
		$this->assertSame( 'Title of jzuTOdM4kRI', $player->get_attribute( 'title' ) );
		$this->assertSame( array(), $this->requests, 'From the cache: no new request, no cache to empty.' );
	}

	public function test_nocookie_and_embed_links_asked_as_watch_links(): void {
		[ , $html ] = $this->front( 'https://www.youtube-nocookie.com/embed/eMTEmPNwkGQ' );
		$player     = $this->iframe( $html );
		$this->assertSame( 'https://www.youtube-nocookie.com/embed/eMTEmPNwkGQ', $player->get_attribute( 'src' ) );
		$this->assertSame( 'Title of eMTEmPNwkGQ', $player->get_attribute( 'title' ) );

		$this->front( 'https://www.youtube.com/embed/videoseries?list=PL123' );
		$this->assertSame( array( 'https://www.youtube.com/watch?v=eMTEmPNwkGQ', 'https://www.youtube.com/playlist?list=PL123' ), $this->requests );
	}

	public function test_youtube_embed_links_with_a_query_on_the_front_end(): void {
		global $wp_embed;
		$this->assertArrayHasKey( YouTube_No_Cookie::HANDLER, $wp_embed->handlers[9], 'Before WordPress\'s own (10).' );
		[ , $html ] = $this->front( 'https://www.youtube.com/embed/jzuTOdM4kRI?start=60' );
		$player     = $this->iframe( $html );
		$this->assertSame( 'https://www.youtube-nocookie.com/embed/jzuTOdM4kRI?start=60', $player->get_attribute( 'src' ) );
		$this->assertSame( 'Title of jzuTOdM4kRI', $player->get_attribute( 'title' ) );
		$this->assertSame( array( 'https://www.youtube.com/watch?v=jzuTOdM4kRI&t=60' ), $this->requests, 'One request, for the watch link: not WordPress\'s handler\'s broken one.' );
	}

	public function test_front_end_player_written_here_when_youtube_cannot_answer(): void {
		$this->online = false;
		[ , $html ]   = $this->front( 'https://youtu.be/HVVpa_-pLkE?feature=shared&t=90' );
		$player       = $this->iframe( $html );
		$this->assertSame( 'https://www.youtube-nocookie.com/embed/HVVpa_-pLkE?start=90', $player->get_attribute( 'src' ) );
		$this->assertSame( 'YouTube video player', $player->get_attribute( 'title' ) );
		$this->assertSame( 'strict-origin-when-cross-origin', $player->get_attribute( 'referrerpolicy' ) );
	}

	public function test_other_links_and_players_untouched(): void {
		$vimeo = '<iframe src="https://player.vimeo.com/video/76979871" title="Vimeo"></iframe>';
		$this->assertSame( $vimeo, YouTube_No_Cookie::rewrite( $vimeo, 'https://vimeo.com/76979871' ) );
		$this->assertSame( 'https://vimeo.com/76979871', YouTube_No_Cookie::unembedded( 'https://vimeo.com/76979871', 'https://vimeo.com/76979871' ) );
		$odd = '<iframe src="https://www.youtube.com/channel/x"></iframe>';
		$this->assertSame( $odd, YouTube_No_Cookie::rewrite( $odd, 'https://youtu.be/eMTEmPNwkGQ' ), 'Only YouTube players move.' );
	}

	public function test_editor_preview_fresh_and_from_the_proxys_cache(): void {
		wp_set_current_user( $this->user( 'editor' ) );
		foreach ( array( 'fresh', 'cached' ) as $round ) {
			$response = $this->preview( 'https://youtu.be/eMTEmPNwkGQ?t=30' );
			$this->assertSame( 200, $response->get_status(), $round );
			$data   = (array) $response->get_data();
			$player = $this->iframe( (string) $data['html'] );
			$this->assertSame( 'https://www.youtube-nocookie.com/embed/eMTEmPNwkGQ?start=30', $player->get_attribute( 'src' ), $round );
			$this->assertSame( 'Title of eMTEmPNwkGQ', $player->get_attribute( 'title' ), $round );
			$this->assertSame( 'YouTube', $data['provider_name'] );
		}
		$this->assertCount( 1, $this->requests, 'The second answer came from the proxy\'s cache.' );
	}

	public function test_editor_preview_player_written_here_when_youtube_cannot_answer(): void {
		wp_set_current_user( $this->user( 'editor' ) );
		$this->online = false;
		// No provider answers the first; WordPress's proxy gives the second to the embed handlers.
		foreach ( array( 'https://www.youtube-nocookie.com/embed/eMTEmPNwkGQ', 'https://www.youtube.com/embed/eMTEmPNwkGQ' ) as $url ) {
			$response = $this->preview( $url );
			$this->assertSame( 200, $response->get_status(), $url );
			$data = (array) $response->get_data();
			$this->assertSame( 'video', $data['type'], $url );
			$this->assertSame( 'YouTube', $data['provider_name'], $url );
			$player = $this->iframe( (string) $data['html'] );
			$this->assertSame( 'https://www.youtube-nocookie.com/embed/eMTEmPNwkGQ', $player->get_attribute( 'src' ), $url );
			$this->assertSame( 'YouTube video player', $player->get_attribute( 'title' ), $url );
		}
	}

	public function test_editor_preview_keeps_the_proxys_permission_check(): void {
		wp_set_current_user( $this->user( 'subscriber' ) );
		$this->assertSame( 403, $this->preview( 'https://youtu.be/eMTEmPNwkGQ' )->get_status() );
		$this->assertSame( array(), $this->requests );
	}

	public function test_nothing_changed_when_the_fix_is_off(): void {
		$hooks = array(
			'oembed_providers'             => 'providers',
			'oembed_fetch_url'             => 'fetch_url',
			'embed_oembed_html'            => 'front_end',
			'embed_maybe_make_link'        => 'unembedded',
			'rest_request_after_callbacks' => 'preview',
		);
		foreach ( $hooks as $hook => $method ) {
			remove_filter( $hook, array( YouTube_No_Cookie::class, $method ) );
		}
		wp_embed_unregister_handler( YouTube_No_Cookie::HANDLER, 9 );
		remove_action( 'enqueue_block_editor_assets', array( YouTube_No_Cookie::class, 'editor' ) );
		Settings::update_site( array( 'youtube_no_cookie' => false ) );
		Fixes::init();

		$this->assertFalse( has_filter( 'embed_oembed_html', array( YouTube_No_Cookie::class, 'front_end' ) ) );
		$this->assertFalse( has_filter( 'rest_request_after_callbacks', array( YouTube_No_Cookie::class, 'preview' ) ) );
		[ , $html ] = $this->front( 'https://youtu.be/eMTEmPNwkGQ' );
		$this->assertSame( 'https://www.youtube.com/embed/eMTEmPNwkGQ?feature=oembed', $this->iframe( $html )->get_attribute( 'src' ), 'As WordPress makes it.' );
		global $wp_embed;
		$this->assertArrayNotHasKey( YouTube_No_Cookie::HANDLER, $wp_embed->handlers[9] ?? array(), 'No embed handler of the fix.' );

		Settings::update_site( array( 'youtube_no_cookie' => true ) );
		YouTube_No_Cookie::init();
	}

	public function test_editor_script_with_its_dependencies(): void {
		YouTube_No_Cookie::editor();
		$script = wp_scripts()->query( 'ship-fixes-youtube-no-cookie' );
		$this->assertInstanceOf( _WP_Dependency::class, $script );
		$this->assertContains( 'wp-blocks', $script->deps );
		$this->assertSame( 10, has_action( 'enqueue_block_editor_assets', array( YouTube_No_Cookie::class, 'editor' ) ) );
	}

	public function test_new_texts_in_french(): void {
		// The tests load the plugin from outside the plugins folder, where load_plugin_textdomain() cannot find it:
		// the French file itself, and the loader on init (checked on the dev network).
		$this->assertSame( 10, has_action( 'init', array( Plugin::class, 'load_textdomain' ) ) );
		unload_textdomain( 'ship-fixes', true );
		load_textdomain( 'ship-fixes', SHIP_FIXES_DIR . '/languages/ship-fixes-fr_FR.mo' );
		$texts = array( __( 'YouTube video player', 'ship-fixes' ), __( 'YouTube privacy-enhanced mode', 'ship-fixes' ) );
		unload_textdomain( 'ship-fixes', true );

		$this->assertSame( array( 'Lecteur vidéo YouTube', 'YouTube en mode Confidentialité avancée' ), $texts );
	}
}
