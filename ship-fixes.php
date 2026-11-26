<?php
/**
 * Plugin Name:       Ship Fixes
 * Plugin URI:        https://www.webpresencecare.com/plugins
 * Description:       Small fixes and stopgaps for my sites, each one switched on or off: the Table of Contents block without the Gutenberg plugin, and YouTube videos in privacy-enhanced mode.
 * Version:           0.3.0
 * Requires at least: 7.2
 * Requires PHP:      8.4
 * Author:            Patricia BT
 * Author URI:        https://patriciabt.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ship-fixes
 * Domain Path:       /languages
 * Update URI:        https://github.com/webpresencecare/ship-fixes
 *
 * @package Ship\Fixes
 */

declare( strict_types=1 );

namespace Ship\Fixes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SHIP_FIXES_VERSION', '0.3.0' );
define( 'SHIP_FIXES_FILE', __FILE__ );
define( 'SHIP_FIXES_DIR', __DIR__ );

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = __NAMESPACE__ . '\\';
		if ( ! str_starts_with( $class_name, $prefix ) ) {
			return;
		}
		$name = substr( $class_name, strlen( $prefix ) );
		$file = __DIR__ . '/includes/class-' . strtolower( str_replace( '_', '-', $name ) ) . '.php';
		if ( ! str_contains( $name, '\\' ) && is_readable( $file ) ) {
			require_once $file;
		}
	}
);

add_action( 'plugins_loaded', array( Plugin::class, 'boot' ) );
