<?php
/**
 * PHP renderer for the kit's key-figure card.
 *
 * @package MHMUiCore\Kit
 */

declare(strict_types=1);

namespace MHMUiCore\Kit;

/**
 * One statistic, rendered to an HTML string.
 *
 * Twin of src-react/components/StatCard.jsx: the same vocabularies, the same
 * DOM, the same class set -- gate 6 compares them fixture by fixture. It
 * registers nothing with WordPress (design standard v2 §8: a kit primitive is
 * not a content component, so it must not grow a shortcode or a block).
 *
 * Consumers call mhmuicore_stat_card_html(), not this class: WPCS reads the
 * class token of a static call as the function name, so a static call can
 * never be declared an escaping function (measured 2026-09-17, spec §6).
 *
 * Never throws: a wrong type prints an empty string in that slot, because one
 * key figure must not take a whole admin page down.
 */
final class StatCard {

	public const TONES = array( 'success', 'warning', 'danger', 'info', 'neutral' );

	public const DIRECTIONS = array( 'up', 'down', 'flat' );

	private const DATA_KEY = '/^[a-z0-9-]{1,32}$/';

	/**
	 * Render one stat card to an HTML string.
	 *
	 * `delta` is `{ direction: one of self::DIRECTIONS, text: string }`. Since
	 * 0.12.0 `text` must be PLAIN -- no arrow, no sign -- because this method
	 * itself renders the direction mark for `up`/`down` (see DIRECTION_MARKS);
	 * a consumer that still puts one in `text` will show two. This is a
	 * breaking change from <=0.11.x, where the consumer's text was the only
	 * non-colour cue (WCAG 1.4.1) and an unsigned text left up/down
	 * distinguishable by colour alone.
	 *
	 * Since 0.13.0, `delta.label` is an optional accessible name for the delta
	 * line: an already-translated string the CONSUMER supplies (e.g. "artış" /
	 * "azalış" / "rose 5% this month"), rendered as visually-hidden text inside
	 * the delta line so up vs. down is no longer silent to a screen reader. See
	 * self::delta_label() for the full reasoning. Without it, behaviour is
	 * exactly 0.12.0 -- no fallback string is invented.
	 *
	 * @param array<string, mixed> $props label, value, icon?, tone?, sub?, delta?, emphasis?, data?.
	 * @return string Escaped HTML.
	 */
	public static function render_html( array $props ): string {
		$classes = array( 'mhmui-stat-card' );

		$tone = self::text( $props['tone'] ?? '' );
		if ( in_array( $tone, self::TONES, true ) ) {
			$classes[] = 'mhmui-stat-card--' . $tone;
		}
		if ( true === ( $props['emphasis'] ?? false ) ) {
			$classes[] = 'mhmui-stat-card--emphasis';
		}

		$html = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '"' . self::data_attributes( $props['data'] ?? null ) . '>';

		$icon = sanitize_html_class( self::presence_text( $props['icon'] ?? '' ) );
		if ( '' !== $icon ) {
			$html .= '<span class="' . esc_attr( 'dashicons dashicons-' . $icon ) . '" aria-hidden="true"></span>';
		}

		$html .= '<div class="mhmui-stat-card__body">'
			. '<p class="mhmui-stat-card__label">' . esc_html( self::text( $props['label'] ?? '' ) ) . '</p>'
			. '<p class="mhmui-stat-card__value">' . esc_html( self::text( $props['value'] ?? '' ) ) . '</p>'
			. self::line( $props )
			. '</div>';

		return $html . '</div>';
	}

	/**
	 * Up/down direction marks (measured 2026-09-17: 0.11.1 relied on the
	 * consumer's own delta.text carrying an arrow or sign, which left an
	 * unsigned text like "3 this month" distinguishable only by the delta
	 * line's colour -- WCAG 1.4.1. The kit now supplies the mark itself,
	 * because only the kit knows the direction vocabulary.
	 */
	private const DIRECTION_MARKS = array(
		'up'   => "\u{2191}",
		'down' => "\u{2193}",
	);

	/**
	 * Delta line when the direction is up/down, else the sub line, else nothing.
	 *
	 * @param array<string, mixed> $props Card props.
	 */
	private static function line( array $props ): string {
		$delta = $props['delta'] ?? null;
		if ( is_array( $delta ) ) {
			$direction = self::text( $delta['direction'] ?? '' );
			if ( 'up' === $direction || 'down' === $direction ) {
				return '<p class="' . esc_attr( 'mhmui-stat-card__delta mhmui-stat-card__delta--' . $direction ) . '"'
					. ' data-direction="' . esc_attr( $direction ) . '">'
					. '<span class="mhmui-stat-card__delta-mark" aria-hidden="true">' . esc_html( self::DIRECTION_MARKS[ $direction ] ) . '</span>'
					. self::delta_label( $delta )
					. esc_html( self::text( $delta['text'] ?? '' ) ) . '</p>';
			}
		}

		$sub = self::presence_text( $props['sub'] ?? '' );
		return '' === $sub ? '' : '<p class="mhmui-stat-card__sub">' . esc_html( $sub ) . '</p>';
	}

	/**
	 * The delta line's accessible name, supplied by the consumer.
	 *
	 * Since 0.13.0, `delta.label` is an already-translated string (e.g. "artış" /
	 * "rose 5% this month") the CONSUMER provides so an up delta and a down delta
	 * do not announce identically to assistive technology -- 0.12.0's direction
	 * mark is `aria-hidden` and `data-direction` is not exposed either, so up vs.
	 * down was invisible to a screen reader (both just read the plain `text`,
	 * e.g. "3 this month"). This package has no text domain (`composer
	 * check:no-i18n`) and cannot invent that string itself; when the consumer
	 * does not supply one, behaviour is unchanged from 0.12.0 -- no fallback text
	 * is invented here.
	 *
	 * Visually hidden (the visible mark stays `aria-hidden`), present in the
	 * accessibility tree: the standard clip-to-1px pattern in both stylesheets'
	 * `.mhmui-stat-card__delta-sr` rule.
	 *
	 * The trailing space is INSIDE the span's own text, not a bare text node
	 * after it: without it the DOM's text content runs the label and
	 * delta.text together as one word (measured 2026-09-18: "artış3 this
	 * month"). Keeping it inside the span leaves the class set gate 6
	 * compares unchanged -- the span still emits exactly one class either way.
	 *
	 * @param array<string, mixed> $delta The delta prop.
	 */
	private static function delta_label( array $delta ): string {
		$label = self::presence_text( $delta['label'] ?? '' );
		return '' === $label ? '' : '<span class="mhmui-stat-card__delta-sr">' . esc_html( $label . ' ' ) . '</span>';
	}

	/**
	 * Render the allowlisted data-* attributes.
	 *
	 * @param mixed $data Key/value map.
	 */
	private static function data_attributes( $data ): string {
		if ( ! is_array( $data ) ) {
			return '';
		}
		$out = '';
		foreach ( $data as $key => $value ) {
			if ( ! is_string( $key ) || 1 !== preg_match( self::DATA_KEY, $key ) || ! ( is_string( $value ) || is_int( $value ) || is_float( $value ) ) ) {
				continue;
			}
			$out .= ' data-' . $key . '="' . esc_attr( (string) $value ) . '"';
		}
		return $out;
	}

	/**
	 * Coerce a scalar prop to a string, or drop it silently.
	 *
	 * @param mixed $value Anything.
	 */
	private static function text( $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Coerce a string|int|float prop to a string, or drop it silently -- the
	 * same range as StatCard.jsx's present(). Unlike self::text(), a bool does
	 * NOT count: is_scalar() accepts bool too, so before this helper existed
	 * `icon: true` / `sub: true` coerced to "1" here and printed a
	 * dashicons-1 span / a sub line that the JSX twin, whose present() only
	 * accepts string|number, never emits for the same props (measured
	 * 2026-09-17). Used only for icon and sub, the two props JSX gates behind
	 * present(); label/value/delta.text keep self::text()'s wider is_scalar()
	 * range because JSX reads them directly with no presence check of its own
	 * to disagree with.
	 *
	 * @param mixed $value Anything.
	 */
	private static function presence_text( $value ): string {
		return ( is_string( $value ) || is_int( $value ) || is_float( $value ) ) ? (string) $value : '';
	}
}
