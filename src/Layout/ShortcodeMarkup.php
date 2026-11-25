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
	 * A reference esc_attr() keeps that decodes to `'`, or a raw one should an
	 * attribute_escape filter leave it.
	 */
	private const APOSTROPHE_REF = "/'|&(?:apos|#0*39|#x0*27);/i";

	/**
	 * Characters no attribute value carries whole: `]` ends the shortcode,
	 * `\` is unescaped by the attribute parser, NO-BREAK SPACE and ZERO WIDTH
	 * SPACE become a plain space there, kses drops C0 controls other than tab
	 * and line feed, and 7.2 kses folds a carriage return into the line feed.
	 */
	private const UNCARRIED = '/[\]\x5C\x00-\x08\x0B-\x1F]|\x{00A0}|\x{200B}/u';

	/**
	 * A numeric reference to one of UNCARRIED's control characters.
	 * html_entity_decode()'s HTML5 mode leaves these encoded; 7.2 kses decodes
	 * `&#13;` to a carriage return all the same.
	 */
	private const CONTROL_REF = '/&#(?:0*(?:[0-8]|1[1-9]|2[0-9]|3[01])|x0*(?:[0-8]|[b-f]|1[0-9a-f]));/i';

	/**
	 * Whether a value reaches a shortcode callback byte for byte on every
	 * supported WordPress version, after to_shortcode() and the kses pass post
	 * content goes through.
	 *
	 * The value is judged as text twice: as written (what versions before 7.2
	 * see) and with the references esc_attr() keeps decoded (what 7.2's HTML
	 * API kses makes of post text). A reference esc_attr() neutralises, such
	 * as `&rsqb;`, stays literal in both. It is false when either text holds
	 * an UNCARRIED character, or a double quote and an apostrophe together
	 * (an attribute has no escape for its own delimiter), and for text
	 * esc_attr() empties (invalid UTF-8). Measured with do_shortcode() after
	 * wp_kses_post() on 7.1 and 7.2-RC2
	 * (tests/Integration/ShortcodeMarkupRoundTripTest.php).
	 *
	 * to_shortcode() still writes such a value as before (refusing there would
	 * be a fatal on sites a newer copy serves); this is the consumer's check,
	 * before it writes post content.
	 *
	 * @param string $value Attribute value, unescaped.
	 */
	public static function can_carry( string $value ): bool {
		$escaped = esc_attr( $value );

		if ( '' === $escaped ) {
			return '' === $value;
		}

		$decoded = html_entity_decode( $escaped, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		if ( 1 === preg_match( self::CONTROL_REF, $decoded ) ) {
			return false;
		}

		foreach ( array( $value, $decoded ) as $text ) {
			if ( 1 === preg_match( self::UNCARRIED, $text ) ) {
				return false;
			}
			if ( false !== strpos( $text, '"' ) && false !== strpos( $text, "'" ) ) {
				return false;
			}
		}

		return true;
	}

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
