<?php
/**
 * Version-aware registration for ui-core.
 *
 * Every plugin that bundles ui-core requires THIS file directly (not via the
 * composer autoloader) and registers its own copy. At plugins_loaded priority 0
 * the highest registered version wins and boots; the others stand down.
 *
 * The registry is deliberately built from plain functions guarded by
 * function_exists(), not a class: a class would itself be duplicated across
 * every vendor/ directory, and the first autoloaded copy would win before any
 * version comparison could happen.
 *
 * @package MHM\UiCore
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'mhm_ui_core_register' ) ) {

	/**
	 * Register a bundled copy of ui-core.
	 *
	 * @param string $version         Semantic version of this copy.
	 * @param string $bootstrap_file  Absolute path to this copy's bootstrap.php.
	 */
	function mhm_ui_core_register( string $version, string $bootstrap_file ): void {
		global $mhm_ui_core_candidates;

		if ( ! is_array( $mhm_ui_core_candidates ) ) {
			$mhm_ui_core_candidates = array();
		}

		$mhm_ui_core_candidates[ $version ] = $bootstrap_file;
	}

	/**
	 * Boot the highest registered copy of ui-core.
	 */
	function mhm_ui_core_boot(): void {
		global $mhm_ui_core_candidates;

		if ( ! is_array( $mhm_ui_core_candidates ) || array() === $mhm_ui_core_candidates ) {
			return;
		}

		$winner = null;

		foreach ( $mhm_ui_core_candidates as $version => $bootstrap ) {
			if ( null === $winner || version_compare( (string) $version, $winner, '>' ) ) {
				$winner = (string) $version;
			}
		}

		if ( null !== $winner && file_exists( $mhm_ui_core_candidates[ $winner ] ) ) {
			require_once $mhm_ui_core_candidates[ $winner ];
		}
	}

	add_action( 'plugins_loaded', 'mhm_ui_core_boot', 0 );
}
