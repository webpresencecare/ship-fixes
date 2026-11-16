<?php
/**
 * PHPUnit bootstrap: the WordPress test library (multisite with phpunit.xml.dist, single site with
 * phpunit-single.xml.dist), with this plugin loaded as a must-use plugin would be.
 *
 * Environment:
 * - WP_TESTS_DIR: the test library (tests/phpunit of wordpress-develop). Default: the dev project's copy.
 * - WP_TESTS_CONFIG_FILE_PATH: its wp-tests-config.php (optional; default next to the test library).
 *
 * Locally (tools/phpunit-plugin.sh) the tests use the dev project's own core copy and test database, never the dev
 * data. On GitHub (ci.yml), bin/install-wp-tests.sh installs them.
 *
 * @package Ship\Fixes
 */

$ship_fixes_tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : '/var/www/html/tools/wp-tests/tests/phpunit';

if ( getenv( 'WP_TESTS_CONFIG_FILE_PATH' ) ) {
	define( 'WP_TESTS_CONFIG_FILE_PATH', getenv( 'WP_TESTS_CONFIG_FILE_PATH' ) );
}
foreach ( array( dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills', '/var/www/html/tools/vendor/yoast/phpunit-polyfills' ) as $ship_fixes_polyfills ) {
	if ( is_dir( $ship_fixes_polyfills ) ) {
		define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $ship_fixes_polyfills );
		break;
	}
}

require_once $ship_fixes_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__, 2 ) . '/ship-fixes.php';
	}
);

require $ship_fixes_tests_dir . '/includes/bootstrap.php';

require_once __DIR__ . '/includes/class-ship-fixes-testcase.php';
