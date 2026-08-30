<?php
/**
 * The consumer identity the Layout engine needs -- and nothing else.
 *
 * @package MHMUiCore\Layout
 */

declare(strict_types=1);

namespace MHMUiCore\Layout;

use InvalidArgumentException;

/**
 * Everything the package needs from its consumer -- and nothing else.
 *
 * THREE KEYS, AND WHY NOT MORE
 *
 * House rules (the Tailwind ban, the token map) are package DEFAULTS, not
 * injected values: a second plugin's answer for them would be identical, and
 * making it re-declare them would turn the package into a plan rewritten per
 * plugin. Only identity is injected.
 *
 * markup_prefix is separate from error_prefix on purpose: the consumer's error
 * codes read "mhmrentiva_*" while its markup classes read "mhm-*". One key
 * cannot produce both.
 *
 * ARRAY, NOT POSITIONAL: the package may only ever ADD. A seventh positional
 * parameter would freeze the order forever.
 */
final class LayoutContract {

	private const PREFIX_PATTERN = '/^[a-z][a-z0-9_]*$/';

	/**
	 * Prefix for machine-readable error codes, e.g. "mhmrentiva".
	 *
	 * @var string
	 */
	private $error_prefix;

	/**
	 * Prefix for markup CSS classes, e.g. "mhm". Kept apart from $error_prefix:
	 * one key cannot produce both "mhmrentiva_*" error codes and "mhm-*" classes.
	 *
	 * @var string
	 */
	private $markup_prefix;

	/**
	 * Component type => adapter instance.
	 *
	 * @var array<string,LayoutComponentAdapter>
	 */
	private $adapters;

	/**
	 * Build the contract from a consumer-supplied configuration array.
	 *
	 * @param array<string,mixed> $config Contract configuration.
	 *
	 * @throws InvalidArgumentException When the contract is malformed. This is a
	 *                                  programmer error, not a domain error: no
	 *                                  runtime path can recover from it, so it
	 *                                  must not be a WP_Error.
	 */
	public function __construct( array $config ) {
		$this->error_prefix  = $this->read_prefix( $config, 'error_prefix' );
		$this->markup_prefix = $this->read_prefix( $config, 'markup_prefix' );

		/*
		 * REJECTED HERE, NOT WORKED AROUND IN THE SCAN: CompositionBuilder's leak
		 * scan exempts a class carrying markup_prefix via a lookbehind, but a
		 * lookbehind has no text to examine at position 0 of a surface string. A
		 * markup_prefix that IS a utility-fragment root ("bg", "p", "m", "flex",
		 * "grid", "w" -- see ForbiddenPatterns::UTILITY_FRAGMENTS) makes every one
		 * of the consumer's OWN prefixed classes indistinguishable, at the start
		 * of a class attribute, from the forbidden fragment itself: e.g. under
		 * markup_prefix "bg", the consumer's own "bg-hero-card" cannot be told
		 * apart from a leaked Tailwind "bg-*" utility, and the package would flag
		 * its own wrapper ("bg-layout-component") as leakage. Excluding just the
		 * wrapper classes from the scan would only patch that one instance; a
		 * consumer's adapters can render arbitrarily many other prefixed classes
		 * that would still collide. Rejecting the collision here closes the whole
		 * class at the one place that knows both values, and fails fast at
		 * contract construction instead of as an intermittent runtime false
		 * positive.
		 */
		foreach ( ForbiddenPatterns::UTILITY_FRAGMENTS as $fragment ) {
			if ( rtrim( $fragment, '-' ) === $this->markup_prefix ) {
				throw new InvalidArgumentException(
					esc_html(
						sprintf(
							'LayoutContract: "markup_prefix" (%s) collides with a reserved utility-class fragment.',
							$this->markup_prefix
						)
					)
				);
			}
		}

		$adapters = $config['adapters'] ?? null;

		if ( ! is_array( $adapters ) || array() === $adapters ) {
			throw new InvalidArgumentException( 'LayoutContract: "adapters" must be a non-empty array.' );
		}

		foreach ( $adapters as $type => $adapter ) {
			if ( ! $adapter instanceof LayoutComponentAdapter ) {
				/*
				 * esc_html() on an exception message is not ceremony: an uncaught
				 * exception's message is printed, so WordPress treats a throw as
				 * an output site (WordPress.Security.EscapeOutput.ExceptionNotEscaped).
				 */
				throw new InvalidArgumentException(
					esc_html(
						sprintf( 'LayoutContract: adapter "%s" must implement LayoutComponentAdapter.', (string) $type )
					)
				);
			}
		}

		/**
		 * Every element has been proven to implement LayoutComponentAdapter above.
		 *
		 * @var array<string,LayoutComponentAdapter> $adapters
		 */
		$this->adapters = $adapters;
	}

	/**
	 * Prefix a suffix into a full machine-readable error code.
	 *
	 * @param string $suffix Error code suffix, e.g. "unknown_component".
	 * @return string Full error code, e.g. "mhmrentiva_unknown_component".
	 */
	public function error_code( string $suffix ): string {
		return $this->error_prefix . '_' . $suffix;
	}

	/**
	 * The consumer's markup CSS class prefix.
	 *
	 * @return string
	 */
	public function markup_prefix(): string {
		return $this->markup_prefix;
	}

	/**
	 * Look up the adapter registered for a component type.
	 *
	 * @param string $type Component type, e.g. "hero".
	 * @return LayoutComponentAdapter|null The adapter, or null when $type is not registered.
	 */
	public function adapter( string $type ): ?LayoutComponentAdapter {
		return $this->adapters[ $type ] ?? null;
	}

	/**
	 * Read and validate one lowercase-prefix key from the config array.
	 *
	 * @param array<string,mixed> $config Contract configuration.
	 * @param string              $key    Config key to read, e.g. "error_prefix".
	 * @return string The validated prefix.
	 *
	 * @throws InvalidArgumentException When missing, empty or malformed.
	 */
	private function read_prefix( array $config, string $key ): string {
		$value = $config[ $key ] ?? null;

		if ( ! is_string( $value ) || 1 !== preg_match( self::PREFIX_PATTERN, $value ) ) {
			/*
			 * esc_html() on an exception message is not ceremony: an uncaught
			 * exception's message is printed, so WordPress treats a throw as
			 * an output site (WordPress.Security.EscapeOutput.ExceptionNotEscaped).
			 */
			throw new InvalidArgumentException(
				esc_html(
					sprintf( 'LayoutContract: "%s" must match %s.', $key, self::PREFIX_PATTERN )
				)
			);
		}

		return $value;
	}
}
