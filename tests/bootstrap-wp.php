<?php
/**
 * Bootstrap for the integration suite.
 *
 * Order is the behaviour here. functions.php must load before
 * tests_add_filter() exists; the registration must happen before
 * includes/bootstrap.php, because that file loads WordPress and WordPress
 * dispatches plugins_loaded itself. Reverse the order and the tests stay green
 * while measuring nothing.
 *
 * @package MHMUiCore
 */

declare(strict_types=1);

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php - run bin/install-wp-tests.sh first." . PHP_EOL;
	exit( 1 );
}

$_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_polyfills_path );
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once "{$_tests_dir}/includes/functions.php";

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		// The call under test. This is what a consuming plugin does, and it is
		// what registers add_action( 'plugins_loaded', 'mhmuicore_boot', 0 )
		// with real WordPress. tests_add_filter() reaches $wp_filter directly,
		// which is the only way to be registered before plugins_loaded fires.
		require dirname( __DIR__ ) . '/register.php';

		// Two copies, registered through the package's own public API. Which of
		// them boots is decided by mhmuicore_boot() when WordPress dispatches
		// plugins_loaded -- nothing in this file or in the test makes that call.
		mhmuicore_register( '0.0.1', __DIR__ . '/Integration/fixtures/low/bootstrap.php' );
		mhmuicore_register( '9.9.9', __DIR__ . '/Integration/fixtures/high/bootstrap.php' );
	}
);

require "{$_tests_dir}/includes/bootstrap.php";
