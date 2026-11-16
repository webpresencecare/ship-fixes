<?php
/**
 * On a single site (no multisite): the site's own settings for its administrators, no network screen; uninstall.
 * Run with phpunit-single.xml.dist.
 *
 * @package Ship\Fixes
 */

use Ship\Fixes\Admin;
use Ship\Fixes\Settings;
use Ship\Fixes\Uninstall;

/**
 * @covers \Ship\Fixes\Settings
 * @covers \Ship\Fixes\Uninstall
 */
class SingleSiteTest extends WP_UnitTestCase {

	public function test_administrators_manage_the_sites_settings(): void {
		$this->assertFalse( is_multisite() );
		$this->assertFalse( Settings::network_wide() );
		$this->assertTrue( Settings::site_may_change() );
		$this->assertStringContainsString( 'options-general.php?page=' . Admin::PAGE, Admin::url() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertSame( 200, rest_do_request( new WP_REST_Request( 'GET', '/ship-fixes/v1/settings' ) )->get_status() );
		Settings::update_site( array( 'table_of_contents' => false ) );
		$this->assertFalse( Settings::get()['table_of_contents'] );
	}

	public function test_uninstall_removes_the_settings(): void {
		Settings::update_site( array( 'table_of_contents' => false ) );
		Uninstall::run();
		$this->assertFalse( get_option( Settings::OPTION ) );
	}
}
