<?php
/**
 * Uninstall (called by uninstall.php): the plugin keeps nothing but its settings, and removes them from every site.
 *
 * @package Ship\Fixes
 */

declare( strict_types=1 );

namespace Ship\Fixes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Removes the plugin's settings.
 */
final class Uninstall {

	/**
	 * Runs on every site.
	 *
	 * @return void
	 */
	public static function run(): void {
		if ( ! is_multisite() ) {
			delete_option( Settings::OPTION );
			return;
		}
		$sites = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);
		foreach ( $sites as $site_id ) {
			switch_to_blog( (int) $site_id );
			delete_option( Settings::OPTION );
			restore_current_blog();
		}
		delete_site_option( Settings::NETWORK_OPTION );
	}
}
