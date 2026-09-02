<?php
/**
 * Bootstrap for the integration suite.
 *
 * Order is the behaviour here. functions.php must load before tests_add_filter()
 * exists; the registration must happen before includes/bootstrap.php, because
 * that file loads WordPress and WordPress dispatches plugins_loaded itself.
 *
 * Two of the three ways to get that order wrong fail loudly. The third does not,
 * which is what the shutdown guard below is for.
 *
 * @package MHMUiCore
 */

declare(strict_types=1);

/*
 * register.php opens with `if ( ! defined( 'ABSPATH' ) ) { exit; }`, and a bare
 * exit is status 0. Requiring it from this file's top level -- an edit that
 * looks like a tidy-up -- therefore ends the PHPUnit process inside the
 * bootstrap with no banner, no "No tests executed!", and a green CI step.
 *
 * This guard converts that silence into a failure. It runs on every exit path,
 * including the one register.php takes, and only complains when WordPress never
 * finished loading. Mutation M4 in MUTATIONS.md keeps it honest.
 */
register_shutdown_function(
	static function (): void {
		if ( ! class_exists( 'WP_UnitTestCase', false ) ) {
			fwrite( STDERR, 'Integration bootstrap ended before WordPress finished loading.' . PHP_EOL );
			exit( 1 );
		}
	}
);

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

		// A losing copy, and the package's own bootstrap as the winner. Which of
		// them loads is decided by mhmuicore_boot() when WordPress dispatches
		// plugins_loaded -- nothing in this file or in any test makes that call.
		//
		// The winner is registered under a synthetic version rather than the
		// real one on purpose: a hardcoded '0.7.0' would go stale at the next
		// release without anything noticing, and the version string is whatever
		// the consuming plugin chooses to pass anyway.
		mhmuicore_register( '0.0.1', __DIR__ . '/Integration/fixtures/low/bootstrap.php' );
		mhmuicore_register( '9.9.9', dirname( __DIR__ ) . '/bootstrap.php' );
	}
);

require "{$_tests_dir}/includes/bootstrap.php";
