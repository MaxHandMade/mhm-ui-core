<?php
/**
 * Composition Builder (Hybrid Strategy)
 *
 * Assembles blueprint composition into WordPress-compatible markup.
 *
 * @package MHMUiCore\Layout
 */

declare(strict_types=1);

namespace MHMUiCore\Layout;

use WP_Error;

/**
 * Assembles a blueprint page's composition into rendered markup.
 *
 * Implements the Hybrid Composition strategy: manifest meta plus rendered
 * content. MESSAGE-FREE BY DESIGN, same reasoning as BlueprintValidator's own
 * class docblock: every WP_Error this class returns carries an empty message
 * and puts its context in $data instead.
 */
final class CompositionBuilder {

	/**
	 * The consumer identity used to build machine-readable error codes and to
	 * exempt its markup class prefix from the utility-leak scan.
	 *
	 * @var LayoutContract
	 */
	private $contract;

	/**
	 * Adapter lookup, built from the contract.
	 *
	 * @var AdapterRegistry
	 */
	private $registry;

	/**
	 * Design-token to CSS custom-property translator.
	 *
	 * @var TokenMapper
	 */
	private $token_mapper;

	/**
	 * Constructor.
	 *
	 * @param LayoutContract $contract The layout contract instance.
	 */
	public function __construct( LayoutContract $contract ) {
		$this->contract     = $contract;
		$this->registry     = new AdapterRegistry( $contract );
		$this->token_mapper = new TokenMapper();
	}

	/**
	 * Builds the final post content markup from blueprint composition.
	 *
	 * @param array<string,mixed> $manifest Full blueprint manifest.
	 * @param array<string,mixed> $page     Specific page entry from manifest.
	 * @return string|WP_Error Rendered markup.
	 */
	public function build( array $manifest, array $page ) {
		$markup         = '';
		$composition    = $page['composition'] ?? array();
		$components_map = $manifest['components'] ?? array();

		foreach ( $composition as $instance ) {
			$component_id = $instance['component_id'] ?? '';
			$instance_id  = $instance['instance_id'] ?? '';
			$attributes   = $instance['attributes'] ?? array();

			$component_config = $components_map[ $component_id ] ?? null;
			if ( ! $component_config ) {
				return new WP_Error(
					$this->contract->error_code( ErrorCodes::UNKNOWN_COMPONENT ),
					'',
					array( 'component_id' => $component_id )
				);
			}

			$type = $component_config['type'] ?? '';

			// 1. Get adapter from the registry.
			$adapter = $this->registry->get_adapter( $type );
			if ( ! $adapter ) {
				return new WP_Error(
					$this->contract->error_code( ErrorCodes::MISSING_ADAPTER ),
					'',
					array( 'type' => $type )
				);
			}

			// 2. Render component via adapter.
			$component_markup = $adapter->render( $attributes, $instance_id );

			// 3. Wrap in layout container if defined in contract.
			// For Phase 1, we use a simple div wrapper that follows MHM CSS standards.
			$markup .= sprintf(
				'<div class="mhm-layout-component" data-component-type="%s" data-instance-id="%s">%s</div>' . PHP_EOL,
				esc_attr( $type ),
				esc_attr( $instance_id ),
				$component_markup
			);
		}

		// 4. Final Sanity Scan (Tailwind Prohibition Gate).
		$markup_error = $this->scan_for_prohibited_patterns( $markup );
		if ( is_wp_error( $markup_error ) ) {
			return $markup_error;
		}

		// 5. Apply Design Tokens (Phase 2).
		$tokens       = $manifest['tokens'] ?? array();
		$token_styles = $this->token_mapper->map_to_style_string( $tokens );

		// Wrap in a root layout container that carries the design tokens.
		return sprintf(
			'<div class="mhm-layout-root" style="%s">%s</div>',
			esc_attr( $token_styles ),
			$markup
		);
	}

	/**
	 * Scans rendered markup for prohibited patterns (Tailwind strings and raw framework artifacts).
	 *
	 * @param string $markup Rendered markup.
	 * @return true|WP_Error
	 */
	private function scan_for_prohibited_patterns( string $markup ) {
		// Only the surfaces where a framework can actually leak: class attributes
		// and resource URLs. The previous scan was a stripos over the entire
		// rendered markup, so a second product whose legitimate copy contains the
		// word "tailwind" was rejected outright.
		$surfaces = array();

		if ( preg_match_all( '/(?:class|src|href)\s*=\s*(["\'])(.*?)\1/is', $markup, $matches ) > 0 ) {
			$surfaces = $matches[2];
		}

		$haystack = implode( ' ', $surfaces );

		foreach ( ForbiddenPatterns::FRAMEWORK as $pattern ) {
			if ( stripos( $haystack, $pattern ) !== false ) {
				return new WP_Error(
					$this->contract->error_code( ErrorCodes::TAILWIND_LEAKAGE ),
					'',
					array( 'pattern' => $pattern )
				);
			}
		}

		// We use negative lookbehind so a class already carrying the consumer's
		// own markup prefix (e.g. "evimora-bg-card") is not reported as leakage.
		$prefix    = preg_quote( $this->contract->markup_prefix(), '/' );
		$fragments = implode( '|', array_map( static fn( string $f ): string => preg_quote( $f, '/' ), ForbiddenPatterns::UTILITY_FRAGMENTS ) );

		foreach ( $surfaces as $surface ) {
			if ( preg_match( '/(?<!' . $prefix . '-)\b(' . $fragments . ')([a-z0-9-]+)/i', $surface, $hit ) === 1 ) {
				return new WP_Error(
					$this->contract->error_code( ErrorCodes::UTILITY_LEAKAGE ),
					'',
					array( 'fragment' => $hit[1] . $hit[2] )
				);
			}
		}

		return true;
	}
}
