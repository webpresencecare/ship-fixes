<?php
/**
 * Settings: which fixes are on, on its own on a site and network-wide.
 *
 * @package Ship\Fixes
 */

use Ship\Fixes\Fixes;
use Ship\Fixes\Settings;

/**
 * @covers \Ship\Fixes\Settings
 */
class SettingsTest extends Ship_Fixes_TestCase {

	private const FIX = 'table_of_contents';

	public function test_on_its_own_each_site_decides(): void {
		$this->assertFalse( Settings::network_wide() );
		$this->assertTrue( Settings::site_may_change() );
		$this->assertSame( array_keys( Fixes::FIXES ), array_keys( Settings::get() ) );
		$this->assertSame( Fixes::FIXES[ self::FIX ]['default'], Settings::get()[ self::FIX ], 'Nobody decided: the default.' );

		$this->assertFalse(
			Settings::update_site(
				array(
					self::FIX => 'false',
					'unknown' => 'x',
				)
			)[ self::FIX ]
		);
		$this->assertSame( array( self::FIX => false ), get_option( Settings::OPTION ), 'Unknown keys are not stored.' );

		$other = self::factory()->blog->create();
		switch_to_blog( $other );
		$this->assertTrue( Settings::get()[ self::FIX ], 'Another site keeps its own choice.' );
		restore_current_blog();
	}

	public function test_network_wide_the_network_decides_and_sites_may_differ(): void {
		$this->activate_for_network();
		$this->assertTrue( Settings::network_wide() );
		$this->assertTrue( Settings::network_values()['site_overrides'] );

		Settings::update_network( array( self::FIX => false ) );
		$this->assertFalse( Settings::get()[ self::FIX ], 'A site that decided nothing follows the network.' );

		Settings::update_site( array( self::FIX => true ) );
		$this->assertTrue( Settings::get()[ self::FIX ], 'What the site saved wins.' );

		Settings::update_network( array( 'site_overrides' => false ) );
		$this->assertFalse( Settings::site_may_change() );
		$this->assertFalse( Settings::get()[ self::FIX ], 'Sites may not decide: the network applies.' );
	}
}
