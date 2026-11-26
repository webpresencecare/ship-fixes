<?php
/**
 * The YouTube privacy-enhanced links fix: the embed handler, the editor's preview and the front end, without any
 * request leaving the site.
 *
 * @package Ship\Fixes
 */

use Ship\Fixes\YouTube_No_Cookie;

/**
 * @covers \Ship\Fixes\YouTube_No_Cookie
 */
class YouTubeNoCookieTest extends Ship_Fixes_TestCase {

	private const URL = 'https://www.youtube-nocookie.com/embed/eMTEmPNwkGQ';

	/**
	 * Addresses of the HTTP requests made during a test.
	 *
	 * @var array<int, string>
	 */
	private array $requests = array();

	public function set_up(): void {
		parent::set_up();
		add_filter( 'pre_http_request', array( $this, 'block_request' ), 10, 3 );
	}

	/**
	 * Records and refuses every HTTP request.
	 *
	 * @param mixed  $pre  Short-circuit value.
	 * @param array  $args Request arguments.
	 * @param string $url  Address.
	 * @return WP_Error
	 */
	public function block_request( $pre, $args, $url ): WP_Error {
		$this->requests[] = $url;
		return new WP_Error( 'http_request_blocked', 'No requests in these tests.' );
	}

	/**
	 * The player's iframe in some HTML, as a tag processor on it.
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
	 * Embed attributes.
	 *
	 * @param int $width  Width.
	 * @param int $height Height.
	 * @return array<string, int>
	 */
	private function size( int $width, int $height ): array {
		return array(
			'width'  => $width,
			'height' => $height,
		);
	}

	public function test_handler_registered(): void {
		global $wp_embed;
		$this->assertSame( YouTube_No_Cookie::PATTERN, $wp_embed->handlers[10][ YouTube_No_Cookie::HANDLER ]['regex'] );
	}

	public function test_editor_script_with_its_dependencies(): void {
		YouTube_No_Cookie::editor();
		$script = wp_scripts()->query( 'ship-fixes-youtube-no-cookie' );
		$this->assertInstanceOf( _WP_Dependency::class, $script );
		$this->assertContains( 'wp-blocks', $script->deps );
		$this->assertTrue( wp_script_is( 'ship-fixes-youtube-no-cookie', 'enqueued' ) );
		$this->assertSame( 10, has_action( 'enqueue_block_editor_assets', array( YouTube_No_Cookie::class, 'editor' ) ) );
	}

	public function test_links_it_embeds_and_links_it_leaves(): void {
		foreach ( array( self::URL, 'http://youtube-nocookie.com/embed/eMTEmPNwkGQ?start=30', 'https://www.youtube-nocookie.com/embed/videoseries?list=PL123', self::URL . '/' ) as $url ) {
			$this->assertMatchesRegularExpression( YouTube_No_Cookie::PATTERN, $url );
		}
		foreach ( array( 'https://www.youtube.com/watch?v=eMTEmPNwkGQ', 'https://www.youtube-nocookie.com/watch?v=eMTEmPNwkGQ', 'https://evil.example/www.youtube-nocookie.com/embed/x', 'https://www.youtube-nocookie.com.evil.example/embed/x', 'https://www.youtube-nocookie.com/embed/a"b' ) as $url ) {
			$this->assertDoesNotMatchRegularExpression( YouTube_No_Cookie::PATTERN, $url );
		}
	}

	public function test_player_is_youtubes_privacy_enhanced_embed_at_16_9(): void {
		preg_match( YouTube_No_Cookie::PATTERN, 'http://youtube-nocookie.com/embed/eMTEmPNwkGQ?start=30&rel=0#t', $matches );
		$player = $this->iframe( YouTube_No_Cookie::embed( $matches, $this->size( 640, 960 ), 'http://youtube-nocookie.com/embed/eMTEmPNwkGQ?start=30&rel=0#t' ) );
		$this->assertSame( 'https://www.youtube-nocookie.com/embed/eMTEmPNwkGQ?start=30&rel=0', $player->get_attribute( 'src' ), 'HTTPS, www, the query kept, the fragment left.' );
		$this->assertSame( '640', $player->get_attribute( 'width' ) );
		$this->assertSame( '360', $player->get_attribute( 'height' ) );
		$this->assertSame( 'YouTube video player', $player->get_attribute( 'title' ) );
		$this->assertSame( 'strict-origin-when-cross-origin', $player->get_attribute( 'referrerpolicy' ) );
		$this->assertTrue( $player->get_attribute( 'allowfullscreen' ) );

		$low = $this->iframe( YouTube_No_Cookie::embed( $matches, $this->size( 640, 180 ), self::URL ) );
		$this->assertSame( '320', $low->get_attribute( 'width' ), 'Narrower when the height is the limit.' );
		$this->assertSame( '180', $low->get_attribute( 'height' ) );
		$this->assertSame( array(), $this->requests );
	}

	public function test_editor_preview_is_a_youtube_video_without_any_request(): void {
		wp_set_current_user( $this->user( 'editor' ) );
		$request = new WP_REST_Request( 'GET', '/oembed/1.0/proxy' );
		$request->set_query_params(
			array(
				'url'      => self::URL,
				'maxwidth' => 600,
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = (array) $response->get_data();
		$this->assertSame( 'YouTube', $data['provider_name'], 'The block becomes the YouTube embed.' );
		$this->assertSame( 'video', $data['type'] );
		$player = $this->iframe( (string) $data['html'] );
		$this->assertSame( self::URL, $player->get_attribute( 'src' ) );
		$this->assertSame( '600', $player->get_attribute( 'width' ) );
		$this->assertSame( '338', $player->get_attribute( 'height' ) );
		$this->assertSame( array(), $this->requests, 'No oEmbed discovery.' );
	}

	public function test_editor_preview_keeps_the_proxys_permission_check(): void {
		wp_set_current_user( $this->user( 'subscriber' ) );
		$request = new WP_REST_Request( 'GET', '/oembed/1.0/proxy' );
		$request->set_query_params( array( 'url' => self::URL ) );
		$this->assertSame( 403, rest_get_server()->dispatch( $request )->get_status() );
	}

	public function test_other_links_left_to_the_proxy(): void {
		$request = new WP_REST_Request( 'GET', '/oembed/1.0/proxy' );
		$request->set_query_params( array( 'url' => 'https://www.youtube.com/watch?v=eMTEmPNwkGQ' ) );
		$this->assertNull( YouTube_No_Cookie::preview( null, $request, '/oembed/1.0/proxy' ) );
		$this->assertSame( 'earlier', YouTube_No_Cookie::preview( 'earlier', $request, '/oembed/1.0/proxy' ), 'An earlier answer stays.' );

		$request->set_query_params( array( 'url' => self::URL ) );
		$this->assertNull( YouTube_No_Cookie::preview( null, $request, '/wp/v2/posts' ) );
	}

	public function test_embed_block_on_the_front_end(): void {
		$block   = '<!-- wp:embed {"url":"' . self::URL . '","type":"video","providerNameSlug":"youtube","responsive":true,"className":"wp-embed-aspect-16-9 wp-has-aspect-ratio"} -->'
			. '<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">' . "\n" . self::URL . "\n" . '</div></figure><!-- /wp:embed -->';
		$post_id = self::factory()->post->create( array( 'post_content' => $block ) );
		$this->go_to( (string) get_permalink( $post_id ) );
		setup_postdata( get_post( $post_id ) );

		$html   = apply_filters( 'the_content', get_post_field( 'post_content', $post_id ) );
		$player = $this->iframe( $html );
		$this->assertSame( self::URL, $player->get_attribute( 'src' ) );
		$this->assertStringNotContainsString( 'youtube.com/', $html );
		$this->assertSame( array(), $this->requests );
	}
}
