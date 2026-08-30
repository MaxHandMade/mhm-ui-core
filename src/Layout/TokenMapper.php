<?php
/**
 * Translates blueprint tokens into package-standard CSS variables.
 *
 * @package MHMUiCore\Layout
 */

declare(strict_types=1);

namespace MHMUiCore\Layout;

/**
 * Token Mapper
 *
 * Translates blueprint manifest tokens into inline CSS custom properties.
 * Implements D2 Governance: no global explosion, deterministic fallbacks.
 *
 * The mapping is this class's own constant, not consumer configuration: a
 * second plugin's answer for "which design token feeds which CSS variable"
 * would be identical, and making each plugin re-declare it would turn the
 * package into a plan rewritten per plugin (same house rule as
 * LayoutContract's Tailwind ban and token map).
 */
final class TokenMapper {

	/**
	 * Source manifest key (dot notation) => target CSS custom-property name.
	 *
	 * Targets are namespaced `--mhmui-bp-*` -- "bp" for "blueprint" -- so a
	 * blueprint cannot write the names the rest of the product reads. These
	 * land as an inline style on the layout root, and an inline custom
	 * property beats an inherited one on every descendant: without this
	 * separation, a published blueprint would silently redefine the palette
	 * for its whole subtree. The `--mhmui-` prefix itself is enforced by this
	 * package's own bin/check-php-namespace.php gate.
	 *
	 * Source keys (left side) are consumer-facing: stored manifests in
	 * consumers' databases are keyed on them and must never change here.
	 *
	 * @var array<string,string>
	 */
	public const TOKEN_MAPPING = array(
		'colors.primary'    => '--mhmui-bp-primary',
		'colors.secondary'  => '--mhmui-bp-secondary',
		'colors.text'       => '--mhmui-bp-text-primary',
		'colors.background' => '--mhmui-bp-bg-main',
		'colors.surface'    => '--mhmui-bp-bg-soft',
		'colors.accent'     => '--mhmui-bp-accent',
		'spacing.unit'      => '--mhmui-bp-spacing-base',
		'radius.main'       => '--mhmui-bp-border-radius',
		'fonts.body'        => '--mhmui-bp-font-family',
	);

	/**
	 * Maps manifest tokens to a CSS inline style string.
	 *
	 * @param array<string,mixed> $tokens Raw tokens from a blueprint manifest.
	 * @return string Sanity-checked CSS variable string (e.g. "--mhmui-bp-primary: #000;").
	 */
	public function map_to_style_string( array $tokens ): string {
		$style_rules = array();

		foreach ( self::TOKEN_MAPPING as $source_key => $target_var ) {
			$value = $this->resolve_token_value( $tokens, $source_key );

			if ( $value ) {
				$sanitized_value = $this->sanitize_css_value( $value );
				if ( null !== $sanitized_value ) {
					$style_rules[] = sprintf( '%s: %s;', $target_var, $sanitized_value );
				}
			}
		}

		return implode( ' ', $style_rules );
	}

	/**
	 * Resolves a token value using dot notation for nested arrays.
	 *
	 * @param array<string,mixed> $tokens Manifest tokens.
	 * @param string              $path   Dot-notated path, e.g. "colors.primary".
	 * @return mixed The resolved value, or null when the path does not exist.
	 */
	private function resolve_token_value( array $tokens, string $path ) {
		$keys    = explode( '.', $path );
		$current = $tokens;

		foreach ( $keys as $key ) {
			if ( ! isset( $current[ $key ] ) ) {
				return null;
			}
			$current = $current[ $key ];
		}

		return $current;
	}

	/**
	 * Sanitizes a single CSS custom-property value.
	 *
	 * This method's output is embedded, unquoted, inside an inline `style`
	 * attribute string built by map_to_style_string() (one value per
	 * `--mhmui-bp-*: VALUE;` declaration). esc_attr() escapes quotes and angle
	 * brackets -- it does NOT escape ";", "(", ")" or "/". A value that
	 * reaches the browser unescaped there can close the current declaration
	 * and open a second, attacker-chosen one (e.g.
	 * "rgba(0); background:url(//evil.example/x.png)"), or fetch a remote
	 * resource via url()/@import -- entirely inside what looks like a single
	 * token value. The checks below reject that SHAPE before esc_attr() ever
	 * runs. Do not lean on esc_attr() to catch this class of value; it will
	 * not, and removing this pre-check re-opens the injection.
	 *
	 * @param mixed $value Raw token value.
	 * @return string|null The sanitized value, or null when it must be dropped.
	 */
	private function sanitize_css_value( $value ): ?string {
		if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
			return null;
		}

		$value = trim( (string) $value );

		// A value may not carry a second declaration, escape into a new rule
		// block, or fetch a resource. Case-insensitive and tolerant of
		// whitespace before the paren ("url  (" is exactly as dangerous as
		// "url(").
		if ( preg_match( '/[;{}]|url\s*\(|expression\s*\(|@import/i', $value ) === 1 ) {
			return null;
		}

		// Block internal framework references.
		if ( stripos( $value, 'tailwind' ) !== false || stripos( $value, 'tw-' ) !== false ) {
			return null;
		}

		// Allow basic CSS patterns (colors, units, inherit). The colour-function
		// branch parses the parenthesised content instead of accepting
		// anything ("^rgba?\(.*\)$" was the hole the checks above close a
		// second time): only digits, dots, percent signs, commas, slashes and
		// whitespace may appear between the parens.
		$patterns = array(
			'/^#[a-fA-F0-9]{3,8}$/',
			'/^(?:rgb|rgba|hsl|hsla)\(\s*[0-9.%\s,\/]+\)$/',
			'/^[0-9.]+(px|rem|em|%|vh|vw|ch)?$/',
			'/^[a-zA-Z0-9\s,\-\'"]+$/',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $value ) === 1 ) {
				return esc_attr( $value );
			}
		}

		return null;
	}
}
