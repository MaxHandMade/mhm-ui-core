<?php
/**
 * The generic half of the consumer's old BaseAdapter: shortcode string building.
 *
 * @package MHMUiCore\Layout
 */

declare(strict_types=1);

namespace MHMUiCore\Layout;

use InvalidArgumentException;

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
	 * A value is double-quoted and esc_attr()'d, except one that holds a
	 * double quote: an encoded &quot; reached the shortcode as that literal
	 * entity before WordPress 7.2, and from 7.2 kses decodes it in post text,
	 * so the attribute ended at it. Such a value is single-quoted with its
	 * double quotes raw and everything else still escaped, which every
	 * version reads back intact. A shortcode attribute has no escape for its
	 * own delimiter, so a value holding both quote kinds has no correct
	 * spelling and is refused.
	 *
	 * @param string               $tag  Shortcode tag, without brackets.
	 * @param array<string,scalar> $atts Attributes.
	 *
	 * @throws InvalidArgumentException When a value holds both ' and ".
	 */
	public static function to_shortcode( string $tag, array $atts ): string {
		$string = '[' . $tag;

		foreach ( $atts as $key => $val ) {
			$key = esc_attr( (string) $key );
			$val = (string) $val;

			if ( ! str_contains( $val, '"' ) ) {
				$string .= sprintf( ' %s="%s"', $key, esc_attr( $val ) );
				continue;
			}

			if ( str_contains( $val, "'" ) ) {
				throw new InvalidArgumentException(
					esc_html( "ShortcodeMarkup: the value of {$key} holds both quote kinds; no shortcode attribute can carry it." )
				);
			}

			$string .= sprintf( " %s='%s'", $key, implode( '"', array_map( 'esc_attr', explode( '"', $val ) ) ) );
		}

		return $string . ']';
	}
}
