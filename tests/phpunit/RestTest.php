<?php
/**
 * REST settings routes: who may use them, when, and what they accept.
 *
 * @package Ship\Fixes
 */

use Ship\Fixes\Rest;
use Ship\Fixes\Settings;

/**
 * @covers \Ship\Fixes\Rest
 */
class RestTest extends Ship_Fixes_TestCase {

	/**
	 * Sends a request to a settings route.
	 *
	 * @param string               $route  settings or network-settings.
	 * @param string               $method Method.
	 * @param array<string, mixed> $body   Body.
	 * @return WP_REST_Response
	 */
	private function call( string $route, string $method = 'GET', array $body = array() ): WP_REST_Response {
		$request = new WP_REST_Request( $method, '/' . Rest::NAMESPACE . '/' . $route );
		if ( $body ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( (string) wp_json_encode( $body ) );
		}
		return rest_do_request( $request );
	}

	public function test_site_route_for_site_administrators(): void {
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->call( 'settings' )->get_status() );

		wp_set_current_user( $this->user( 'editor' ) );
		$this->assertSame( 403, $this->call( 'settings', 'POST', array( 'table_of_contents' => false ) )->get_status() );

		wp_set_current_user( $this->user( 'administrator' ) );
		$saved = $this->call( 'settings', 'POST', array( 'table_of_contents' => false ) );
		$this->assertSame( 200, $saved->get_status() );
		$this->assertSame( array( 'table_of_contents' => false ), $saved->get_data() );
		$this->assertSame( array( 'table_of_contents' => false ), $this->call( 'settings' )->get_data() );

		$this->assertSame( 403, $this->call( 'network-settings' )->get_status(), 'A site administrator is not a network administrator.' );
		wp_set_current_user( $this->user( 'administrator', true ) );
		$this->assertSame( 404, $this->call( 'network-settings' )->get_status(), 'Not network-wide: no network settings.' );
	}

	public function test_network_route_for_network_administrators_and_site_route_when_allowed(): void {
		$this->activate_for_network();
		wp_set_current_user( $this->user( 'administrator' ) );
		$this->assertSame( 403, $this->call( 'network-settings' )->get_status(), 'A site administrator is not a network administrator.' );
		$this->assertSame( 200, $this->call( 'settings' )->get_status(), 'Sites may decide by default.' );

		wp_set_current_user( $this->user( 'administrator', true ) );
		$saved = $this->call(
			'network-settings',
			'POST',
			array(
				'table_of_contents' => false,
				'site_overrides'    => false,
			)
		);
		$this->assertSame( 200, $saved->get_status() );
		$this->assertSame(
			array(
				'table_of_contents' => false,
				'site_overrides'    => false,
			),
			$saved->get_data()
		);
		$this->assertSame( 403, $this->call( 'settings' )->get_status(), 'The network decides for every site now.' );
	}

	public function test_rejects_wrong_types(): void {
		$this->activate_for_network();
		wp_set_current_user( $this->user( 'administrator', true ) );
		$this->assertSame( 400, $this->call( 'network-settings', 'POST', array( 'site_overrides' => 'perhaps' ) )->get_status() );
		$this->assertSame( 400, $this->call( 'settings', 'POST', array( 'table_of_contents' => 'perhaps' ) )->get_status() );
		$this->assertTrue( Settings::get()['table_of_contents'] );
	}
}
