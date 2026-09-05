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

			// A non-string instance_id (e.g. 123) is an ordinary shape a generated
			// manifest can produce. BlueprintValidator::validate_page() rejects it
			// too, but this class must not rely on validate() having run first --
			// build() is independently reachable through LayoutEngine::build(). A
			// silent (string) cast would make $data's "instance_id" lie about what
			// the manifest actually contained, so this returns a code instead of
			// coercing: the alternative is a TypeError out of the adapter's own
			// `string $instance_id` render() parameter.
			if ( ! is_string( $instance_id ) ) {
				return new WP_Error(
					$this->contract->error_code( ErrorCodes::INVALID_INSTANCE ),
					'',
					array( 'instance_id' => $instance_id )
				);
			}

			$component_config = $components_map[ $component_id ] ?? null;
			if ( ! $component_config ) {
				return new WP_Error(
					$this->contract->error_code( ErrorCodes::UNKNOWN_COMPONENT ),
					'',
					array( 'component_id' => $component_id )
				);
			}

			$type = $component_config['type'] ?? '';

			// Same reasoning as instance_id above: AdapterRegistry::get_adapter()
			// takes `string $type`, and a component whose type is not a string has
			// no valid adapter for it regardless -- MISSING_ADAPTER is the accurate
			// code, not a cast that hides the shape.
			if ( ! is_string( $type ) ) {
				return new WP_Error(
					$this->contract->error_code( ErrorCodes::MISSING_ADAPTER ),
					'',
					array( 'type' => $type )
				);
			}

			/*
			 * Same reasoning as instance_id and type above, and the same audit
			 * finding: the adapter's render() takes `array $attributes`, so a
			 * string here leaves the engine as a TypeError instead of a code.
			 * build() is reachable through LayoutEngine without validate() having
			 * run, so this check is not a duplicate of the validator's -- it is the
			 * half that holds when the validator was skipped.
			 */
			if ( ! is_array( $attributes ) ) {
				return new WP_Error(
					$this->contract->error_code( ErrorCodes::INVALID_INSTANCE ),
					'',
					array( 'attributes' => $attributes )
				);
			}

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
			// The wrapper class carries the CONSUMER's own markup_prefix, not a
			// literal "mhm-": a second product must see its own namespace in its
			// own markup, not this package's. For the current consumer, whose
			// markup_prefix is "mhm", sprintf( '%s-layout-component', 'mhm' )
			// reproduces "mhm-layout-component" byte-for-byte -- this is
			// behaviour-neutral for published post_content and existing CSS.
			$markup .= sprintf(
				'<div class="%s-layout-component" data-component-type="%s" data-instance-id="%s">%s</div>' . PHP_EOL,
				esc_attr( $this->contract->markup_prefix() ),
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

		// Wrap in a root layout container that carries the design tokens. Same
		// markup_prefix reasoning as the component wrapper above -- this div is
		// never itself scanned (it is built after scan_for_prohibited_patterns()
		// runs), so it does not need the exemption regex, only the class name.
		return sprintf(
			'<div class="%s-layout-root" style="%s">%s</div>',
			esc_attr( $this->contract->markup_prefix() ),
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
		//
		// The two checks below do NOT share one surface set. A Tailwind CDN
		// reference is a URL, so the framework check must cover class/src/href.
		// A CSS class cannot be a URL, so widening the utility-fragment check the
		// same way only invites false positives: "/uploads/hero-w-1200.jpg" and
		// "/blog/m-12/" are ordinary asset paths that happen to contain a
		// word-boundary "w-"/"m-", not a leaked utility class.
		//
		// <style> bodies are a FRAMEWORK-only surface, not a utility-fragment one:
		// Tailwind v4's documented entry point is a bare "@import 'tailwindcss';"
		// inside a stylesheet, which the class/src/href scan never sees. Prose
		// inside a <style> block is not a realistic shape (this package's own
		// adapters render component markup, not stylesheets written as prose), so
		// there is no equivalent false-positive risk to the one class/src/href
		// widening would create.
		$framework_surfaces = array();
		$class_surfaces     = array();

		if ( preg_match_all( '/(class|src|href)\s*=\s*(["\'])(.*?)\2/is', $markup, $matches, PREG_SET_ORDER ) > 0 ) {
			foreach ( $matches as $match ) {
				$framework_surfaces[] = $match[3];

				if ( 'class' === strtolower( $match[1] ) ) {
					$class_surfaces[] = $match[3];
				}
			}
		}

		if ( preg_match_all( '/<style\b[^>]*>(.*?)<\/style>/is', $markup, $style_matches ) > 0 ) {
			$framework_surfaces = array_merge( $framework_surfaces, $style_matches[1] );
		}

		$haystack = implode( ' ', $framework_surfaces );

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
		// The lookbehind itself is anchored with \b ("(?<!\b" . $prefix . "-)"),
		// not just "(?<!" . $prefix . "-)": a fixed-length lookbehind matches
		// trailing TEXT, not a whole token, so without the inner \b a class from
		// an unrelated word merely ending in the prefix -- "xmhm-bg-card" under
		// markup_prefix "mhm" -- read as carrying "mhm-" and passed unflagged.
		// The \b forces the lookbehind to match only a genuine "mhm-" token
		// boundary, so "xmhm-bg-card" is correctly flagged while "mhm-bg-card"
		// (and this class's own "mhm-layout-component"/"mhm-layout-root"
		// wrappers) stay exempt.
		$prefix    = preg_quote( $this->contract->markup_prefix(), '/' );
		$fragments = implode( '|', array_map( static fn( string $f ): string => preg_quote( $f, '/' ), ForbiddenPatterns::UTILITY_FRAGMENTS ) );

		foreach ( $class_surfaces as $surface ) {
			if ( preg_match( '/(?<!\b' . $prefix . '-)\b(' . $fragments . ')([a-z0-9-]+)/i', $surface, $hit ) === 1 ) {
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
