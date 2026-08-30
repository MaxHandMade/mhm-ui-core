<?php
/**
 * The generic half of the consumer's old BaseAdapter: shortcode string building.
 *
 * @package MHMUiCore\Layout
 */

declare(strict_types=1);

namespace MHMUiCore\Layout;

/**
 * The generic half of the consumer's old BaseAdapter.
 *
 * A final class with a static method, not a trait: to_shortcode() touches no
 * instance state, so trait-as-inheritance was the wrong mechanism for a pure
 * formatter. It also keeps the method independently testable and analysable
 * -- a trait with no using class in this package cannot be either, because
 * PHPStan only analyses a trait's body in the context of a class that uses
 * it (see https://phpstan.org/blog/how-phpstan-analyses-traits), and this
 * package does not use it: the only consumer is the CONSUMER's own
 * BaseAdapter, which lives in a different repository and a later plan. The
 * consumer keeps its existing `protected function to_shortcode(...)` and
 * makes it a one-line delegation to this method, so its own three concrete
 * adapters call `$this->to_shortcode(...)` exactly as they do today.
 */
final class ShortcodeMarkup {

	/**
	 * Build a WordPress shortcode string.
	 *
	 * @param string               $tag  Shortcode tag, without brackets.
	 * @param array<string,scalar> $atts Attributes.
	 */
	public static function to_shortcode( string $tag, array $atts ): string {
		$string = '[' . $tag;

		foreach ( $atts as $key => $val ) {
			$string .= sprintf( ' %s="%s"', esc_attr( (string) $key ), esc_attr( (string) $val ) );
		}

		return $string . ']';
	}
}
