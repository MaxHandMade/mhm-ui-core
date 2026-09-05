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

		foreach ( ForbiddenPatterns::FRAMEWORK as $pattern ) {
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
			// A non-array page (e.g. "pages": ["a", "b"]) is an ordinary shape for
			// a hand-written or generated manifest, not a programmer error: it
			// must return a WP_Error, not TypeError out of validate_page()'s own
			// `array $page` parameter type.
			if ( ! is_array( $page ) ) {
				return new WP_Error(
					$this->contract->error_code( ErrorCodes::INVALID_PAGE ),
					'',
					array( 'page_index' => $index )
				);
			}

			$error = $this->validate_page( $page, $index );
			if ( is_wp_error( $error ) ) {
				return $error;
			}
		}

		/*
		 * 3.5 The shapes the ENGINE reads but never looked at.
		 *
		 * tokens is JSON-encoded above and handed to TokenMapper; constraints is
		 * read by the consumer's own contract. A string in either place passes
		 * every check up to here and fails somewhere further downstream, which is
		 * the failure mode this validator exists to prevent.
		 */
		foreach ( array( 'tokens', 'constraints' ) as $key ) {
			if ( ! is_array( $manifest[ $key ] ) ) {
				return new WP_Error(
					$this->contract->error_code( ErrorCodes::INVALID_BLUEPRINT ),
					'',
					array( 'key' => $key )
				);
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
	 * @param int|string          $index The page's manifest array key, reported into
	 *                                   $data exactly as received -- not cast to int --
	 *                                   so a malformed non-numeric key surfaces honestly
	 *                                   instead of being fabricated as page 0.
	 * @return WP_Error|null
	 */
	private function validate_page( array $page, $index ): ?WP_Error {
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

		/*
		 * slug and layout are strings the importer builds a post and a template
		 * name from. An array or an int there passes "isset" and breaks later,
		 * away from the manifest that caused it.
		 */
		foreach ( array( 'slug', 'layout' ) as $key ) {
			if ( ! is_string( $page[ $key ] ) ) {
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

		/*
		 * A composition that is not a list was SILENTLY approved: the old check
		 * was `if ( is_array( … ) )`, so every non-array simply skipped the loop
		 * and the page validated. Skipping a check is not passing it.
		 */
		if ( ! is_array( $page['composition'] ) ) {
			return new WP_Error(
				$this->contract->error_code( ErrorCodes::INVALID_PAGE ),
				'',
				array(
					'page_index' => $index,
					'key'        => 'composition',
				)
			);
		}

		foreach ( $page['composition'] as $comp_idx => $instance ) {
			$error = $this->validate_instance( $instance, $comp_idx, $index );
			if ( is_wp_error( $error ) ) {
				return $error;
			}
		}

		return null;
	}

	/**
	 * Validates one composition instance.
	 *
	 * Every shape CompositionBuilder::build() would hand straight to an adapter is
	 * checked here, because the class's own rule is that the validator must not
	 * approve what the builder cannot render. An audit found the rule half applied:
	 * instance_id and type were checked, `attributes` was not, and a string there
	 * made validate() return true and build() throw a TypeError out of the
	 * adapter -- breaking the engine's one promise, that domain errors come back
	 * as WP_Error.
	 *
	 * @param mixed      $instance   Instance as the manifest carried it.
	 * @param int|string $comp_idx   Its key in the composition.
	 * @param int|string $page_index The page's key.
	 * @return WP_Error|null
	 */
	private function validate_instance( $instance, $comp_idx, $page_index ): ?WP_Error {
		$fail = function ( array $extra ) use ( $comp_idx, $page_index ): WP_Error {
			return new WP_Error(
				$this->contract->error_code( ErrorCodes::INVALID_INSTANCE ),
				'',
				array_merge(
					array(
						'instance_index' => $comp_idx,
						'page_index'     => $page_index,
					),
					$extra
				)
			);
		};

		if ( ! is_array( $instance ) ) {
			return $fail( array( 'instance' => $instance ) );
		}

		$component_id = $instance['component_id'] ?? '';
		if ( ! is_string( $component_id ) ) {
			return $fail( array( 'component_id' => $component_id ) );
		}

		if ( '' === $component_id ) {
			return null;
		}

		// Note: the actual component type is checked during import against the
		// registry. Here the instance's own metadata must be renderable.
		if ( ! isset( $instance['instance_id'] ) || ! is_string( $instance['instance_id'] ) ) {
			return $fail( array() );
		}

		if ( isset( $instance['attributes'] ) && ! is_array( $instance['attributes'] ) ) {
			return $fail( array( 'attributes' => $instance['attributes'] ) );
		}

		return null;
	}
}
