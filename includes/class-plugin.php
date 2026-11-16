<?php
/**
 * Start-up: hooks every part.
 *
 * @package Ship\Fixes
 */

declare( strict_types=1 );

namespace Ship\Fixes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boots the plugin.
 */
final class Plugin {

	/**
	 * Hooks everything (on plugins_loaded). Works on a single site and on a network.
	 *
	 * @return void
	 */
	public static function boot(): void {
		Fixes::init();
		Rest::init();
		if ( is_admin() ) {
			Admin::init();
		}
	}
}
