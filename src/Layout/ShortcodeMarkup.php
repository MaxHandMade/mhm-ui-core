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
	 * An already-encoded quote counts as the quote it encodes: esc_attr()
	 * keeps a valid entity as it is, and 7.2 kses decodes it in post text the
	 * same way, so `&quot;` would end a double-quoted attribute just as `"`
	 * does. Such entities are written as the raw character, which is what a
	 * 7.2 shortcode receives for them anyway.
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
			$val = self::decode_quotes( (string) $val );

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

	/**
	 * Turn every character reference an HTML5 parser reads as " or ' into
	 * that character: named (any case, `&quot` also without its semicolon, as
	 * HTML5 allows in text), decimal and hexadecimal with any leading zeros.
	 *
	 * @param string $value Attribute value.
	 */
	private static function decode_quotes( string $value ): string {
		$value = (string) preg_replace( '/&(?:quot|#0*34(?![0-9])|#x0*22(?![0-9a-f]));?/i', '"', $value );

		return (string) preg_replace( '/&(?:apos;|#0*39(?![0-9]);?|#x0*27(?![0-9a-f]);?)/i', "'", $value );
	}
}
