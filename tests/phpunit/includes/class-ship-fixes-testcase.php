<?php
/**
 * Shared helpers for the tests.
 *
 * @package Ship\Fixes
 */


/**
 * Base test case.
 */
abstract class Ship_Fixes_TestCase extends WP_UnitTestCase {

	/**
	 * Activates the plugin for the whole network (the tests load it as a must-use plugin, which is neither).
	 *
	 * @return void
	 */
	protected function activate_for_network(): void {
		$file            = plugin_basename( SHIP_FIXES_FILE );
		$active          = get_site_option( 'active_sitewide_plugins', array() );
		$active          = is_array( $active ) ? $active : array();
		$active[ $file ] = time();
		update_site_option( 'active_sitewide_plugins', $active );
	}

	/**
	 * A user with a role on the current site, made a super admin when asked.
	 *
	 * @param string $role  Role.
	 * @param bool   $super Super admin.
	 * @return int User ID.
	 */
	protected function user( string $role, bool $super = false ): int {
		$id = self::factory()->user->create( array( 'role' => $role ) );
		if ( $super ) {
			grant_super_admin( $id );
		}
		return $id;
	}
}
