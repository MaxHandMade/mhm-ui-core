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
	 * A value is double-quoted and esc_attr()'d, byte for byte as before
	 * 0.23.6, except one that holds a double quote and no apostrophe. An
	 * encoded &quot; reached the shortcode as that literal entity before
	 * WordPress 7.2, and 7.2's HTML API kses decodes it in post text, so the
	 * attribute ended at it. Such a value is single-quoted: its double quotes
	 * reach the shortcode raw, everything else in esc_attr() form.
	 *
	 * An already-encoded quote counts as the quote it encodes when choosing:
	 * esc_attr() keeps a valid entity as it is and 7.2 kses decodes it the
	 * same way, so `&quot;` ends a double-quoted attribute just as `"` does.
	 *
	 * A value holding both quote kinds has no spelling 7.2 kses leaves
	 * intact -- a shortcode attribute has no escape for its own delimiter --
	 * and keeps the old double-quoted form, which every version before 7.2
	 * and every path that skips kses still reads correctly. Refusing here
	 * would turn a title that rendered under 0.23.5 into a fatal on every
	 * site this copy serves.
	 *
	 * @param string               $tag  Shortcode tag, without brackets.
	 * @param array<string,scalar> $atts Attributes.
	 */
	public static function to_shortcode( string $tag, array $atts ): string {
		$string = '[' . $tag;

		foreach ( $atts as $key => $val ) {
			$key     = esc_attr( (string) $key );
			$val     = (string) $val;
			$escaped = esc_attr( $val );
			$decoded = self::decode_quotes( $val );

			// esc_attr() empties a value that is not valid UTF-8; the
			// single-quoted branch escapes in pieces and must not keep half.
			if ( '' === $escaped || ! str_contains( $decoded, '"' ) || str_contains( $decoded, "'" ) ) {
				$string .= sprintf( ' %s="%s"', $key, $escaped );
				continue;
			}

			$string .= sprintf( " %s='%s'", $key, implode( '"', array_map( 'esc_attr', explode( '"', $decoded ) ) ) );
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
