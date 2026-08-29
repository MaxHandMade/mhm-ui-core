<?php
/**
 * Real, triggering inputs for each error-code suffix the engine can raise today.
 *
 * Deliberately NOT namespaced, mirroring wp-function-stubs.php: the gate script
 * that calls mhmuicore_gate_error_samples() (bin/check-error-prefix.php) has no
 * namespace of its own, so an unqualified call there resolves only against the
 * global namespace.
 *
 * G1 asserts a positive predicate over the WP_Errors this function returns. A
 * hand-built WP_Error would make the gate test itself instead of the engine, so
 * every entry here comes out of a real BlueprintValidator::validate() call
 * against an input crafted to trip that exact check -- nothing here fabricates
 * a code or a data shape.
 *
 * STAGED COVERAGE (Controller ruling R11): BlueprintValidator raises seven of
 * the eleven canonical ErrorCodes::ALL suffixes; CompositionBuilder (Task 8,
 * does not exist in this package yet) raises the other four --
 * unknown_component, missing_adapter, tailwind_leakage, utility_leakage. This
 * function covers only the seven the validator can raise today. Task 8 extends
 * it to the remaining four and deletes this note (and the matching staged
 * exception in bin/check-error-prefix.php).
 *
 * @package MHMUiCore
 */

declare(strict_types=1);

use MHMUiCore\Layout\BlueprintValidator;
use MHMUiCore\Layout\ErrorCodes;
use MHMUiCore\Layout\LayoutContract;

if ( ! function_exists( 'mhmuicore_gate_error_samples' ) ) {
	/**
	 * One genuinely-triggered WP_Error per BlueprintValidator suffix.
	 *
	 * @param LayoutContract $contract Contract to validate against -- its
	 *                                 error_prefix is what G1 checks for.
	 * @return array<string,WP_Error> Keyed by ErrorCodes suffix.
	 *
	 * @throws \RuntimeException When a crafted input stops actually triggering
	 *                            its code -- loudly, rather than letting a stale
	 *                            fixture quietly stop proving anything.
	 */
	function mhmuicore_gate_error_samples( LayoutContract $contract ): array {
		$validator = new BlueprintValidator( $contract );

		/**
		 * A manifest that satisfies every check on its own; each sample below
		 * mutates exactly the one field its target check inspects, so the
		 * validator can only fail on that check.
		 *
		 * @return array<string,mixed>
		 */
		$valid_manifest = static function (): array {
			return array(
				'version'     => '1.0.0',
				'source'      => array(),
				'pages'       => array(
					array(
						'slug'        => 'home',
						'layout'      => 'default',
						'composition' => array(
							array(
								'component_id' => 'hero',
								'instance_id'  => 'hero-1',
							),
						),
					),
				),
				'tokens'      => array(),
				'components'  => array(),
				'constraints' => array(),
			);
		};

		$samples = array();

		// INVALID_BLUEPRINT: a manifest missing every root key trips on the first one checked.
		$samples[ ErrorCodes::INVALID_BLUEPRINT ] = $validator->validate( array() );

		// UNSUPPORTED_VERSION: all root keys present, version outside the supported 1.x range.
		$version_manifest            = $valid_manifest();
		$version_manifest['version'] = '2.0.0';
		$samples[ ErrorCodes::UNSUPPORTED_VERSION ] = $validator->validate( $version_manifest );

		// FORBIDDEN_PATTERN: a supported version, but a token value carries a banned substring.
		$pattern_manifest           = $valid_manifest();
		$pattern_manifest['tokens'] = array( 'framework' => 'tailwind' );
		$samples[ ErrorCodes::FORBIDDEN_PATTERN ] = $validator->validate( $pattern_manifest );

		// NO_PAGES: clean of forbidden patterns, but the pages list is empty.
		$no_pages_manifest          = $valid_manifest();
		$no_pages_manifest['pages'] = array();
		$samples[ ErrorCodes::NO_PAGES ] = $validator->validate( $no_pages_manifest );

		// INVALID_PAGE: one page present, missing its required 'slug'/'layout'/'composition' keys.
		$invalid_page_manifest          = $valid_manifest();
		$invalid_page_manifest['pages'] = array( array( 'unrelated' => true ) );
		$samples[ ErrorCodes::INVALID_PAGE ] = $validator->validate( $invalid_page_manifest );

		// INVALID_INSTANCE: a well-formed page whose composition instance has a component_id
		// but no instance_id.
		$invalid_instance_manifest          = $valid_manifest();
		$invalid_instance_manifest['pages'] = array(
			array(
				'slug'        => 'home',
				'layout'      => 'default',
				'composition' => array(
					array( 'component_id' => 'hero' ),
				),
			),
		);
		$samples[ ErrorCodes::INVALID_INSTANCE ] = $validator->validate( $invalid_instance_manifest );

		// INVALID_COMPONENTS: every page valid, but the top-level 'components' section is not an array.
		$invalid_components_manifest              = $valid_manifest();
		$invalid_components_manifest['components'] = 'nope';
		$samples[ ErrorCodes::INVALID_COMPONENTS ] = $validator->validate( $invalid_components_manifest );

		foreach ( $samples as $suffix => $result ) {
			if ( ! is_wp_error( $result ) ) {
				throw new \RuntimeException(
					sprintf( 'mhmuicore_gate_error_samples: the crafted input for "%s" did not trigger an error.', $suffix )
				);
			}
		}

		return $samples;
	}
}
