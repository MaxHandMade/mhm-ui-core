<?php
/**
 * Shortcode surface, derived from a contract.
 *
 * @package MHMUiCore\Component\Surfaces
 */

declare(strict_types=1);

namespace MHMUiCore\Component\Surfaces;

use MHMUiCore\Component\ComponentContract;
use MHMUiCore\Component\ComponentRenderer;

/**
 * [prefix_slug attr="..."] from a contract.
 *
 * The attribute allowlist IS the contract's settings: an undeclared attribute
 * never reaches the renderer, and every declared one arrives typed. That is the
 * "attribute allowlist" the design document lists as a generated artefact.
 */
final class ShortcodeSurface {

	/**
	 * The tag a contract registers under.
	 *
	 * @param ComponentContract $contract Contract.
	 * @param string            $prefix   Product prefix, e.g. "rentiva".
	 * @return non-empty-string e.g. "rentiva_featured_vehicles".
	 */
	public static function tag( ComponentContract $contract, string $prefix ): string {
		return $prefix . '_' . $contract->slug();
	}

	/**
	 * Register the shortcode with WordPress.
	 *
	 * @param ComponentContract $contract Contract.
	 * @param ComponentRenderer $renderer Renderer.
	 * @param string            $prefix   Product prefix.
	 * @return string The registered tag.
	 */
	public static function register( ComponentContract $contract, ComponentRenderer $renderer, string $prefix ): string {
		$tag = self::tag( $contract, $prefix );

		add_shortcode(
			$tag,
			static function ( $atts, $content = '' ) use ( $contract, $renderer, $tag ): string {
				// WordPress hands '' (not an empty array) to a tag written without attributes.
				return self::handle( $contract, $renderer, $tag, (array) $atts, (string) $content );
			}
		);

		return $tag;
	}

	/**
	 * The shortcode callback body, public so it is testable without add_shortcode().
	 *
	 * @param ComponentContract    $contract Contract.
	 * @param ComponentRenderer    $renderer Renderer.
	 * @param string               $tag      Registered tag (becomes part of the instance id).
	 * @param array<string, mixed> $atts     Raw attributes.
	 * @param string               $content  Enclosed content.
	 * @return string
	 */
	public static function handle( ComponentContract $contract, ComponentRenderer $renderer, string $tag, array $atts, string $content = '' ): string {
		// Keys arrive lowercased from WordPress's shortcode parser; contracts may
		// declare camelCase (block attributes are camelCase by convention), so a
		// case-insensitive match is what makes one contract serve both surfaces.
		$lookup = array();
		foreach ( array_keys( $contract->settings() ) as $name ) {
			$lookup[ strtolower( $name ) ] = $name;
		}
		$mapped = array();
		foreach ( $atts as $key => $value ) {
			$lower = strtolower( (string) $key );
			if ( isset( $lookup[ $lower ] ) ) {
				$mapped[ $lookup[ $lower ] ] = $value;
			}
		}

		static $counter = 0;
		++$counter;

		return $renderer->render(
			$contract->sanitize( $mapped ),
			array(
				'surface'     => 'shortcode',
				'instance_id' => $tag . '-' . $counter,
				'content'     => $content,
			)
		);
	}
}
