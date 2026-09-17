<?php
/**
 * PHP renderer for a row of key-figure cards.
 *
 * @package MHMUiCore\Kit
 */

declare(strict_types=1);

namespace MHMUiCore\Kit;

/**
 * Twin of src-react/components/StatsGrid.jsx. Prints a column CEILING as a
 * custom property; the wrapping itself is CSS (admin.css / front.css), because
 * an inline track list cannot be overridden without !important.
 */
final class StatsGrid {

	/**
	 * Render a row of stat cards to an HTML string.
	 *
	 * @param array<int|string, mixed> $cards   StatCard prop arrays; non-arrays are skipped.
	 * @param mixed                    $columns Most columns on a wide container. Numeric -> max(1, n); otherwise 4.
	 * @return string Escaped HTML.
	 */
	public static function render_html( array $cards, $columns = 4 ): string {
		$ceiling = is_numeric( $columns ) ? max( 1, (int) $columns ) : 4;

		$html = sprintf( '<div class="mhmui-stats-grid" style="--mhmui-columns:%d">', $ceiling );
		foreach ( $cards as $card ) {
			if ( is_array( $card ) ) {
				$html .= StatCard::render_html( $card );
			}
		}
		return $html . '</div>';
	}
}
