<?php
/**
 * PHP renderer for the kit's page header.
 *
 * @package MHMUiCore\Kit
 */

declare(strict_types=1);

namespace MHMUiCore\Kit;

/**
 * The header of a detail view -- twin of src-react/components/PageHeader.jsx:
 * same markup, same classes (gate 6 holds the class sets together;
 * tests/Kit/PageHeaderTest.php pins the bytes against the string
 * PageHeader.test.jsx pins).
 *
 * Born in 0.27.0 for sub-pages a consumer draws in PHP (detail, edit, add):
 * the README rule says they start with a back link, and a hand-copied header
 * drifts from the package the first time the JSX changes.
 *
 * Where the twins differ, by necessity:
 *   - title, labels, badge text and meta are text (esc_html), not nodes;
 *   - `actions` is an HTML string (buttons, links) run through
 *     wp_kses_post() here, so the function's output is safe on its own;
 *   - back has no onClick -- a PHP page navigates;
 *   - an href esc_url() empties (javascript: and other disallowed protocols)
 *     draws no back link, as in the Tabs twin.
 */
final class PageHeader {

	const LEVELS = array( 1, 2, 3 );

	/**
	 * Render the header.
	 *
	 * @param array<string, mixed> $props { title, back?: { label, href }, badge?: { text, tone? }, badges?: list<{ text, tone? }>, meta?, actions?, level? }.
	 * @return string Escaped HTML.
	 */
	public static function render_html( array $props ): string {
		$level = $props['level'] ?? 2;
		// JS cannot tell 1.0 from 1, and json_decode() hands PHP the float:
		// a whole-number float is that level, as in the JSX (0.27.2).
		// Bounded before the cast: (int) of a huge float is out of range.
		if ( is_float( $level ) && $level >= 1 && $level <= 3 && floor( $level ) === $level ) {
			$level = (int) $level;
		}
		$level = in_array( $level, self::LEVELS, true ) ? $level : 2;
		$badge = self::row( $props['badge'] ?? null );

		$html = '<div class="mhmui-page-header">' . self::back( self::row( $props['back'] ?? null ) );

		$html .= '<div class="mhmui-page-header__title-row">'
			. '<h' . $level . ' class="mhmui-page-header__title">' . esc_html( self::text( $props['title'] ?? '' ) ) . '</h' . $level . '>';

		$badges = is_array( $props['badges'] ?? null ) ? array_values( $props['badges'] ) : array();
		$badges = array_map( array( self::class, 'row' ), $badges );
		$all    = array_merge( array( $badge ), $badges );

		if ( array() !== $badges && array() !== array_filter( $all, array( self::class, 'has_text' ) ) ) {
			$html .= '<div class="mhmui-page-header__badges">';
			foreach ( $all as $item ) {
				$html .= self::status( $item );
			}
			$html .= '</div>';
		} else {
			$html .= self::status( $badge );
		}

		// Emptiness is judged after kses: actions that were only an <input>
		// draw no empty wrapper (Fable 0.27.0).
		// Blank is judged before kses too: a blank string draws no wrapper
		// whatever kses makes of it (0.27.2).
		$actions = $props['actions'] ?? '';
		$actions = is_string( $actions ) && ! self::is_blank( $actions ) ? trim( wp_kses_post( $actions ) ) : '';
		if ( ! self::is_blank( $actions ) ) {
			$html .= '<div class="mhmui-page-header__actions">' . $actions . '</div>';
		}

		$html .= '</div>';

		$meta = $props['meta'] ?? '';
		if ( is_string( $meta ) && '' !== $meta ) {
			$html .= '<p class="mhmui-page-header__meta">' . esc_html( $meta ) . '</p>';
		}

		return $html . '</div>';
	}

	/**
	 * The back link, or '' -- no nameless link and no link without a target.
	 *
	 * @param array<mixed>|null $back { label, href }.
	 * @return string
	 */
	private static function back( ?array $back ): string {
		if ( null === $back ) {
			return '';
		}
		$label = $back['label'] ?? null;
		$raw   = $back['href'] ?? '';
		if ( ! is_string( $raw ) || '' === $raw || ! is_scalar( $label ) || is_bool( $label ) || self::is_blank( (string) $label ) ) {
			return '';
		}
		// Judged on the escaped text too: on a UTF-8 site esc_html() empties an
		// invalid-UTF-8 label, which would draw a nameless link; on a site with
		// another charset it keeps the label, and so does the link (Fable 0.27.2).
		$name = esc_html( (string) $label );
		$href = esc_url( $raw );
		if ( '' === $href || '' === $name ) {
			return '';
		}
		return '<a class="mhmui-page-header__back" href="' . $href . '"><span aria-hidden="true">← </span>' . $name . '</a>';
	}

	/**
	 * One StatusBadge, or '' when the item has no text.
	 *
	 * @param array<mixed>|null $item { text, tone? }.
	 * @return string
	 */
	private static function status( ?array $item ): string {
		if ( null === $item || ! self::has_text( $item ) ) {
			return '';
		}
		$tone = $item['tone'] ?? null;
		$tone = null === $tone ? 'neutral' : sanitize_html_class( self::text( $tone ) );
		return '<span class="mhmui-status mhmui-status--' . $tone . '">' . esc_html( self::text( $item['text'] ?? '' ) ) . '</span>';
	}

	/**
	 * JS truthiness of item.text, the test both twins branch on: '' and 0 are
	 * empty, '0' is not.
	 *
	 * @param array<mixed>|null $item Badge row.
	 * @return bool
	 */
	private static function has_text( ?array $item ): bool {
		if ( null === $item ) {
			return false;
		}
		$text = $item['text'] ?? null;
		if ( is_string( $text ) ) {
			return '' !== $text;
		}
		return ( is_int( $text ) || is_float( $text ) ) && 0 != $text; // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- 0 and 0.0 alike.
	}

	/**
	 * A prop row as an array: a json_decode() row (stdClass) is read as its
	 * public properties, as in the other twins (0.26.0); anything else is null.
	 *
	 * @param mixed $value Prop value.
	 * @return array<mixed>|null
	 */
	private static function row( $value ): ?array {
		if ( $value instanceof \stdClass ) {
			$value = get_object_vars( $value );
		}
		return is_array( $value ) ? $value : null;
	}

	/**
	 * Blank the way JS String.prototype.trim() sees it -- PHP's trim() leaves a
	 * no-break space, so a "\u{00A0}" label drew a nameless link the JSX twin
	 * skipped (Fable 0.27.0). Invalid UTF-8 is not blank (the match fails):
	 * kses keeps such actions, and back() judges a label on its escaped text.
	 *
	 * @param string $value Text.
	 * @return bool
	 */
	private static function is_blank( string $value ): bool {
		return 1 === preg_match( '/^[\p{Zs}\x{09}\x{0A}\x{0B}\x{0C}\x{0D}\x{FEFF}\x{2028}\x{2029}]*$/u', $value );
	}

	/**
	 * A scalar prop as a string; anything else as ''.
	 *
	 * @param mixed $value Any prop value.
	 * @return string
	 */
	private static function text( $value ): string {
		return is_scalar( $value ) && ! is_bool( $value ) ? (string) $value : '';
	}
}
