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
		add_action( 'init', array( __CLASS__, 'load_textdomain' ) );
		Fixes::init();
		Rest::init();
		if ( is_admin() ) {
			Admin::init();
		}
	}

	/**
	 * Loads the plugin's own translations (languages/); translations from translate.wordpress.org, when there are
	 * some, win. On init: earlier triggers WordPress's "translation loading was triggered too early" notice.
	 *
	 * @return void
	 */
	public static function load_textdomain(): void {
		load_plugin_textdomain( 'ship-fixes', false, dirname( plugin_basename( SHIP_FIXES_FILE ) ) . '/languages' );
	}
}
