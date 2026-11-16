<?php
/**
 * Uninstall: the settings go from every site.
 *
 * @package Ship\Fixes
 */

use Ship\Fixes\Settings;
use Ship\Fixes\Uninstall;

/**
 * @covers \Ship\Fixes\Uninstall
 */
class UninstallTest extends Ship_Fixes_TestCase {

	public function test_removes_the_settings_of_every_site_and_of_the_network(): void {
		Settings::update_site( array( 'table_of_contents' => false ) );
		$other = self::factory()->blog->create();
		switch_to_blog( $other );
		Settings::update_site( array( 'table_of_contents' => false ) );
		restore_current_blog();
		Settings::update_network( array( 'site_overrides' => false ) );

		Uninstall::run();

		$this->assertFalse( get_option( Settings::OPTION ) );
		$this->assertFalse( get_site_option( Settings::NETWORK_OPTION ) );
		switch_to_blog( $other );
		$this->assertFalse( get_option( Settings::OPTION ) );
		restore_current_blog();
	}
}
