<?php
/**
 * Blueprint Validator
 *
 * @package MHMUiCore\Layout
 */

declare(strict_types=1);

namespace MHMUiCore\Layout;

use WP_Error;

/**
 * Validates Layout Manifests against structural requirements and governance rules.
 *
 * MESSAGE-FREE BY DESIGN: this package owns no consumer text domain, and
 * WordPress's string extractor reads code without executing it, so any domain
 * passed in as a variable would silently drop the string from every .pot. Every
 * WP_Error this class returns therefore carries an empty message and puts its
 * context in $data instead; the consumer renders the human sentence, in its own
 * domain, from the code and that data.
 */
final class BlueprintValidator {

	/**
	 * Forbidden markup/token substrings (Tailwind / utility-framework leakage).
	 *
	 * Staged here as a private class constant for this task, ported verbatim
	 * from the consumer's ContractRules::FORBIDDEN_PATTERNS. Task 8 extracts it
	 * into a shared ForbiddenPatterns class used by both this validator and
	 * CompositionBuilder, and rewires this file to reference it.
	 *
	 * @var list<string>
	 */
	private const FORBIDDEN_PATTERNS = array(
		'tw-',
		'tailwind',
		'antialiased',
		'flex-1',
	);

	/**
	 * The consumer identity used to build machine-readable error codes.
	 *
	 * @var LayoutContract
	 */
	private $contract;

	/**
	 * Constructor.
	 *
	 * @param LayoutContract $contract The layout contract instance.
	 */
	public function __construct( LayoutContract $contract ) {
		$this->contract = $contract;
	}

	/**
	 * Validates raw manifest data.
	 *
	 * @param array<string,mixed> $manifest Manifest data.
	 * @return true|WP_Error
	 */
	public function validate( array $manifest ) {
		// 1. Root structure check.
		$required_keys = array( 'version', 'source', 'pages', 'tokens', 'components', 'constraints' );
		foreach ( $required_keys as $key ) {
			if ( ! isset( $manifest[ $key ] ) ) {
				return new WP_Error(
					$this->contract->error_code( ErrorCodes::INVALID_BLUEPRINT ),
					'',
					array( 'key' => $key )
				);
			}
		}

		// 1.1 Strict Version Check (Phase 1 supports v1.x).
		if (
			version_compare( (string) $manifest['version'], '1.0.0', '<' ) ||
			version_compare( (string) $manifest['version'], '2.0.0', '>=' )
		) {
			return new WP_Error(
				$this->contract->error_code( ErrorCodes::UNSUPPORTED_VERSION ),
				'',
				array( 'version' => $manifest['version'] )
			);
		}

		// 2. Forbidden pattern scan (Tailwind leakage).
		// Strictly scan pages and tokens only to avoid self-triggering in constraints definition.
		// Both keys are guaranteed set by the root structure check above -- no
		// "?? array()" fallback needed, and PHPStan proves it dead code if added.
		$scannable_data = array(
			'pages'  => $manifest['pages'],
			'tokens' => $manifest['tokens'],
		);
		$json_to_scan   = (string) wp_json_encode( $scannable_data );

		foreach ( self::FORBIDDEN_PATTERNS as $pattern ) {
			if ( false !== stripos( $json_to_scan, $pattern ) ) {
				return new WP_Error(
					$this->contract->error_code( ErrorCodes::FORBIDDEN_PATTERN ),
					'',
					array( 'pattern' => $pattern )
				);
			}
		}

		// 3. Pages validation.
		if ( ! is_array( $manifest['pages'] ) || empty( $manifest['pages'] ) ) {
			return new WP_Error(
				$this->contract->error_code( ErrorCodes::NO_PAGES ),
				'',
				array()
			);
		}

		foreach ( $manifest['pages'] as $index => $page ) {
			$error = $this->validate_page( $page, (int) $index );
			if ( is_wp_error( $error ) ) {
				return $error;
			}
		}

		// 4. Components validation.
		if ( ! is_array( $manifest['components'] ) ) {
			return new WP_Error(
				$this->contract->error_code( ErrorCodes::INVALID_COMPONENTS ),
				'',
				array()
			);
		}

		return true;
	}

	/**
	 * Validates a single page entry.
	 *
	 * @param array<string,mixed> $page  Page data.
	 * @param int                 $index Index for error reporting.
	 * @return WP_Error|null
	 */
	private function validate_page( array $page, int $index ): ?WP_Error {
		$required_keys = array( 'slug', 'layout', 'composition' );
		foreach ( $required_keys as $key ) {
			if ( ! isset( $page[ $key ] ) ) {
				return new WP_Error(
					$this->contract->error_code( ErrorCodes::INVALID_PAGE ),
					'',
					array(
						'page_index' => $index,
						'key'        => $key,
					)
				);
			}
		}

		// Validate composition components against allowlist.
		if ( is_array( $page['composition'] ) ) {
			foreach ( $page['composition'] as $comp_idx => $instance ) {
				$component_id = is_array( $instance ) ? ( $instance['component_id'] ?? '' ) : '';
				if ( ! $component_id ) {
					continue;
				}

				// Note: Actual component type check happens during import phase against Registry.
				// Here we just ensure basic instance metadata exists.
				if ( ! isset( $instance['instance_id'] ) ) {
					return new WP_Error(
						$this->contract->error_code( ErrorCodes::INVALID_INSTANCE ),
						'',
						array(
							'instance_index' => $comp_idx,
							'page_index'     => $index,
						)
					);
				}
			}
		}

		return null;
	}
}
