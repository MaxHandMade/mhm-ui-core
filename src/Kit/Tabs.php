<?php
/**
 * PHP renderer for the kit's page-section tabs.
 *
 * @package MHMUiCore\Kit
 */

declare(strict_types=1);

namespace MHMUiCore\Kit;

/**
 * Page-section navigation as real links -- twin of
 * src-react/components/Tabs.jsx: same markup, same classes (gate 6 holds the
 * class sets together; tests/Kit/TabsTest.php pins the badge bytes).
 *
 * The href rule has two branches (spec 2026-09-23 v2.1 §3.1):
 *   1. a RAW empty href is skipped, before escaping -- both twins do this,
 *      and it is what the unit suite and gate 6 can see;
 *   2. an href esc_url() empties (javascript: and other disallowed
 *      protocols) is skipped too -- PHP only, measured on real WordPress in
 *      tests/Integration/KitEscapingTest.php. Checking only after escaping
 *      would let the marking stub hide a divergence; checking only before
 *      would draw `<a href="">` for a hostile value.
 */
final class Tabs {

	/**
	 * Render the tabs.
	 *
	 * @param array<string, mixed> $props { label, current, items: list<{ id, label, href, badge?, badgeLabel? }> }.
	 * @return string Escaped HTML.
	 */
	public static function render_html( array $props ): string {
		$label   = self::text( $props['label'] ?? '' );
		$current = self::text( $props['current'] ?? '' );
		$items   = is_array( $props['items'] ?? null ) ? $props['items'] : array();

		$html = '<nav class="mhmui-tabs"' . ( '' !== $label ? ' aria-label="' . esc_attr( $label ) . '"' : '' ) . '>';

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$raw = self::text( $item['href'] ?? '' );
			if ( '' === $raw ) {
				continue;
			}
			$href = esc_url( $raw );
			if ( '' === $href ) {
				continue;
			}

			$is_current = '' !== $current && self::text( $item['id'] ?? '' ) === $current;

			$html .= '<a class="' . ( $is_current ? 'mhmui-tabs__tab mhmui-tabs__tab--current' : 'mhmui-tabs__tab' ) . '"'
				. ' href="' . $href . '"'
				. ( $is_current ? ' aria-current="page"' : '' ) . '>'
				. esc_html( self::text( $item['label'] ?? '' ) )
				. self::badge( $item )
				. '</a>';
		}

		return $html . '</nav>';
	}

	/**
	 * The count chip, or '' when there is nothing to count.
	 *
	 * @param array<mixed> $item Tab item.
	 * @return string
	 */
	private static function badge( array $item ): string {
		$raw   = $item['badge'] ?? null;
		$count = is_numeric( $raw ) ? (int) $raw : 0;
		if ( $count <= 0 ) {
			return '';
		}
		$sr = self::text( $item['badgeLabel'] ?? '' );
		if ( '' === $sr ) {
			return '<span class="mhmui-tabs__badge">' . esc_html( (string) $count ) . '</span>';
		}
		return '<span class="mhmui-tabs__badge"><span aria-hidden="true">' . esc_html( (string) $count ) . '</span>'
			. '<span class="mhmui-tabs__badge-sr">' . esc_html( $sr ) . '</span></span>';
	}

	/**
	 * A scalar prop as a string; anything else as ''.
	 *
	 * @param mixed $value Any prop value.
	 * @return string
	 */
	private static function text( $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}
}
