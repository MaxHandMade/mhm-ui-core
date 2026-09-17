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

		$icon = sanitize_html_class( self::text( $props['icon'] ?? '' ) );
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
	 * Delta line when the direction is up/down, else the sub line, else nothing.
	 *
	 * @param array<string, mixed> $props Card props.
	 */
	private static function line( array $props ): string {
		$delta = $props['delta'] ?? null;
		if ( is_array( $delta ) ) {
			$direction = self::text( $delta['direction'] ?? '' );
			if ( 'up' === $direction || 'down' === $direction ) {
				return '<p class="' . esc_attr( 'mhmui-stat-card__delta mhmui-stat-card__delta--' . $direction ) . '">'
					. esc_html( self::text( $delta['text'] ?? '' ) ) . '</p>';
			}
		}

		$sub = self::text( $props['sub'] ?? '' );
		return '' === $sub ? '' : '<p class="mhmui-stat-card__sub">' . esc_html( $sub ) . '</p>';
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
}
