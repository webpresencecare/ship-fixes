<?php
/**
 * Uninstall: removes the plugin's settings from every site (see includes/class-uninstall.php).
 *
 * @package Ship\Fixes
 */

declare( strict_types=1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-settings.php';
require_once __DIR__ . '/includes/class-uninstall.php';

Ship\Fixes\Uninstall::run();
