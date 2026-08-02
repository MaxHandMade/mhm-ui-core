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
 * Prefix: every global this package declares uses the single token `mhmuicore`
 * (constants `MHMUICORE_`, namespace `MHMUiCore`). Package version 0.2.0
 * renamed all of them. The 0.1.x names split at their first delimiter into a
 * three-character leading token -- too short to be unique, and named as
 * unacceptable by a WordPress.org reviewer in the plugin this package ships
 * inside. The old spellings are deliberately not repeated anywhere in this
 * package's shipped source, because a release ZIP is greppable and reprinting
 * them in a comment would defeat the rename.
 *
 * That rename BREAKS the loader contract: a plugin still bundling 0.1.x calls
 * a registration function 0.2.0 no longer declares. The two loaders are wholly
 * disjoint -- different function names, different constants, per-name
 * function_exists() guards -- so an un-migrated 0.1.x copy cannot fatal
 * against this one; it simply runs its own (inert) bootstrap alongside, and
 * the two registries arbitrate versions separately. No compatibility alias is
 * offered, for the greppability reason above. Consumers move by bumping their
 * composer constraint to ^0.2 and calling mhmuicore_register().
 *
 * @package MHMUiCore
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'mhmuicore_register' ) ) {

	/**
	 * Register a bundled copy of ui-core.
	 *
	 * @param string $version         Semantic version of this copy.
	 * @param string $bootstrap_file  Absolute path to this copy's bootstrap.php.
	 */
	function mhmuicore_register( string $version, string $bootstrap_file ): void {
		global $mhmuicore_candidates;

		if ( ! is_array( $mhmuicore_candidates ) ) {
			$mhmuicore_candidates = array();
		}

		$mhmuicore_candidates[ $version ] = $bootstrap_file;
	}

	/**
	 * Boot the highest registered copy of ui-core.
	 */
	function mhmuicore_boot(): void {
		global $mhmuicore_candidates;

		if ( ! is_array( $mhmuicore_candidates ) || array() === $mhmuicore_candidates ) {
			return;
		}

		$winner = null;

		foreach ( $mhmuicore_candidates as $version => $bootstrap ) {
			if ( null === $winner || version_compare( (string) $version, $winner, '>' ) ) {
				$winner = (string) $version;
			}
		}

		// $winner is guaranteed non-null here: the guard above rejects an empty
		// registry, so the loop ran at least once and assigned a version string.
		if ( file_exists( $mhmuicore_candidates[ $winner ] ) ) {
			require_once $mhmuicore_candidates[ $winner ];
		}
	}

	add_action( 'plugins_loaded', 'mhmuicore_boot', 0 );
}
