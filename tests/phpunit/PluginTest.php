<?php
/**
 * Headers, constants and what the plugin registers.
 *
 * @package Ship\Fixes
 */

use Ship\Fixes\Fixes;
use Ship\Fixes\Rest;

/**
 * @coversNothing
 */
class PluginTest extends Ship_Fixes_TestCase {

	/**
	 * The main file's headers.
	 *
	 * @return array<string, string>
	 */
	private function headers(): array {
		return get_file_data(
			SHIP_FIXES_FILE,
			array(
				'Version'    => 'Version',
				'WP'         => 'Requires at least',
				'PHP'        => 'Requires PHP',
				'TextDomain' => 'Text Domain',
				'Network'    => 'Network',
				'UpdateURI'  => 'Update URI',
			)
		);
	}

	public function test_headers_match_the_constants_and_the_conventions(): void {
		$headers = $this->headers();
		$slug    = basename( SHIP_FIXES_FILE, '.php' );
		$this->assertSame( SHIP_FIXES_VERSION, $headers['Version'] );
		$this->assertSame( '7.1', $headers['WP'] );
		$this->assertSame( '8.4', $headers['PHP'] );
		$this->assertSame( $slug, $headers['TextDomain'] );
		$this->assertSame( 'https://github.com/webpresencecare/' . $slug, $headers['UpdateURI'], 'Always set, so wordpress.org never offers another plugin of the same name as an update.' );
	}

	public function test_no_network_header_the_plugin_is_activated_site_by_site(): void {
		$this->assertSame( '', $this->headers()['Network'] );
	}

	public function test_version_is_the_same_in_package_json_and_readme(): void {
		$package = json_decode( (string) file_get_contents( SHIP_FIXES_DIR . '/package.json' ), true );
		$this->assertIsArray( $package );
		$this->assertSame( SHIP_FIXES_VERSION, $package['version'] );
		$this->assertMatchesRegularExpression( '/^Stable tag: ' . preg_quote( SHIP_FIXES_VERSION, '/' ) . '$/m', (string) file_get_contents( SHIP_FIXES_DIR . '/readme.txt' ) );
	}

	public function test_registers_the_routes_and_every_fix_has_its_class_and_texts(): void {
		$this->assertArrayHasKey( '/' . Rest::NAMESPACE . '/settings', rest_get_server()->get_routes() );
		$this->assertArrayHasKey( '/' . Rest::NAMESPACE . '/network-settings', rest_get_server()->get_routes() );
		foreach ( Fixes::FIXES as $id => $fix ) {
			$this->assertMatchesRegularExpression( '/^[a-z][a-z0-9_]*$/', $id );
			$this->assertTrue( method_exists( $fix['class'], 'init' ) );
		}
		$this->assertSame( array_keys( Fixes::FIXES ), array_column( Fixes::texts(), 'id' ) );
	}
}
