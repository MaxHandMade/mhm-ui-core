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

		$body  = '';
		$drawn = 0;
		foreach ( $cards as $card ) {
			if ( is_array( $card ) ) {
				$body .= StatCard::render_html( $card );
				++$drawn;
			}
		}

		$class = self::pairs( $drawn, $ceiling ) ? 'mhmui-stats-grid mhmui-stats-grid--pairs' : 'mhmui-stats-grid';

		return sprintf( '<div class="%s" style="--mhmui-columns:%d">', $class, $ceiling ) . $body . '</div>';
	}

	/**
	 * "4 or 2, never 3" (spec 2026-11-10 §2) -- twin of StatsGrid.jsx's
	 * `pairs()`. Only when the cards fill whole rows of a ceiling of 4 or 6:
	 * 3 cards under a 4 ceiling would draw 2+1, worse than today; a ceiling
	 * of 2 has nothing to halve to; at 8+ the CSS cannot keep its promise
	 * once half the row stops fitting (8 of 8 -> 3+3+2). Counts the cards
	 * it DRAWS.
	 *
	 * @param int $count   Cards drawn.
	 * @param int $ceiling Column ceiling.
	 * @return bool
	 */
	private static function pairs( int $count, int $ceiling ): bool {
		return ( 4 === $ceiling || 6 === $ceiling ) && $count > 0 && 0 === $count % $ceiling;
	}
}
