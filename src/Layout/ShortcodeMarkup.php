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
	 * A reference esc_attr() keeps that decodes to `"`.
	 */
	private const DOUBLE_QUOTE_REF = '/&(?:quot|#0*34|#x0*22);/i';

	/**
	 * A reference esc_attr() keeps that decodes to `'`.
	 */
	private const APOSTROPHE_REF = '/&(?:apos|#0*39|#x0*27);/i';

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
	 * The choice is made on the esc_attr()'d string, which is what kses
	 * sees: a reference that esc_attr() keeps and 7.2 kses decodes to a
	 * quote (`&quot;`, `&#34;`, `&#x22;`) counts as that quote, and a
	 * reference esc_attr() neutralises (`&quot` without its semicolon, as in
	 * `?a=1&quote=2`, or `&QUOT;`) does not -- those were already safe.
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
			$escaped = esc_attr( (string) $val );

			// No quote reference, or an apostrophe beside it, or a value
			// esc_attr() emptied as invalid UTF-8: the 0.23.5 bytes.
			if ( 1 !== preg_match( self::DOUBLE_QUOTE_REF, $escaped ) || 1 === preg_match( self::APOSTROPHE_REF, $escaped ) ) {
				$string .= sprintf( ' %s="%s"', $key, $escaped );
				continue;
			}

			$string .= sprintf( " %s='%s'", $key, preg_replace( self::DOUBLE_QUOTE_REF, '"', $escaped ) );
		}

		return $string . ']';
	}
}
