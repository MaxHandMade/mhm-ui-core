<?php
/**
 * Layout Diff Service
 *
 * @package MHMUiCore\Layout
 */

declare(strict_types=1);

namespace MHMUiCore\Layout;

/**
 * Provides deterministic manifest-level diffing.
 */
final class DiffService {

	/**
	 * Compute diff between two manifests.
	 *
	 * @param array<int|string, mixed> $current  Current manifest map.
	 * @param array<int|string, mixed> $previous Previous manifest map.
	 * @return array<string, mixed> Diff result summary.
	 */
	public static function diff( array $current, array $previous ): array {
		return array(
			'tokens'     => self::diff_tokens( $current['tokens'] ?? array(), $previous['tokens'] ?? array() ),
			'components' => self::diff_components( $current['components'] ?? array(), $previous['components'] ?? array() ),
			'pages'      => self::diff_pages( $current['pages'] ?? array(), $previous['pages'] ?? array() ),
		);
	}

	/**
	 * Diff tokens.
	 *
	 * @param array<int|string, mixed> $current  Current tokens map.
	 * @param array<int|string, mixed> $previous Previous tokens map.
	 * @return array<string, mixed>
	 */
	private static function diff_tokens( array $current, array $previous ): array {
		$added   = array_diff_key( $current, $previous );
		$removed = array_diff_key( $previous, $current );
		$changed = array();

		foreach ( array_intersect_key( $current, $previous ) as $key => $val ) {
			if ( $val !== $previous[ $key ] ) {
				$changed[ $key ] = array(
					'from' => $previous[ $key ],
					'to'   => $val,
				);
			}
		}

		return array(
			'added'   => array_keys( $added ),
			'removed' => array_keys( $removed ),
			'changed' => $changed,
		);
	}

	/**
	 * Diff components.
	 *
	 * @param array<int|string, mixed> $current  Current components map.
	 * @param array<int|string, mixed> $previous Previous components map.
	 * @return array<string, mixed>
	 */
	private static function diff_components( array $current, array $previous ): array {
		$added   = array_diff_key( $current, $previous );
		$removed = array_diff_key( $previous, $current );
		$changed = array();

		foreach ( array_intersect_key( $current, $previous ) as $key => $comp ) {
			if ( $comp !== $previous[ $key ] ) {
				$changed[ $key ] = array(
					'type_changed' => ( $comp['type'] ?? '' ) !== ( $previous[ $key ]['type'] ?? '' ),
					// Deep comparison could be added here if needed.
				);
			}
		}

		return array(
			'added'   => array_keys( $added ),
			'removed' => array_keys( $removed ),
			'changed' => array_keys( $changed ),
		);
	}

	/**
	 * Diff pages structure (basic).
	 *
	 * @param array<int|string, mixed> $current  Current pages list.
	 * @param array<int|string, mixed> $previous Previous pages list.
	 * @return array<string, mixed>
	 */
	private static function diff_pages( array $current, array $previous ): array {
		// Basic count and index check.
		return array(
			'count_changed' => count( $current ) !== count( $previous ),
			'current_count' => count( $current ),
			'prev_count'    => count( $previous ),
		);
	}
}
