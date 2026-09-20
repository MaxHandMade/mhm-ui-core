<?php
/**
 * The kit's semantic icon vocabulary.
 *
 * @package MHMUiCore\Kit
 */

declare(strict_types=1);

namespace MHMUiCore\Kit;

/**
 * Concept -> Dashicon suffix, for the kit's `icon` prop.
 *
 * Twin of src-react/icons.js: the same seed table, the same resolution order,
 * the same last-write-wins registration. Gate 6 parses SEED out of THIS source
 * and compares it pair by pair with the JSX twin, so the constant's name and
 * its `array( 'k' => 'v', ... );` shape are part of the contract.
 *
 * WHY A TABLE IN CODE AND NOT A SHARED JSON
 * src-react/ is pruned from a consumer's ZIP, so nothing under it can be a
 * runtime source for PHP. Two literal tables pinned by a gate is the only
 * shape that survives pruning.
 *
 * WHY NO KEY HERE IS A REAL DASHICON NAME
 * `location` was in the first draft of this table, mapped to `location-alt` --
 * and `.dashicons-location` exists. A consumer writing `icon => 'location'`
 * would have kept working and silently shown a DIFFERENT icon. Two independent
 * audits called that a blocker. tests/Integration/IconVocabularyTest.php now
 * measures every key against the installed WordPress's own dashicons.css, so
 * the rule is enforced rather than remembered.
 *
 * WHERE ICONS WORK
 * Admin only, today. mhmuicore_enqueue_kit('front') declares no `dashicons`
 * dependency (bootstrap.php) and front.css ships no icon source, so on the
 * front end the span renders and no glyph is drawn. This is a known limit --
 * the free core's every front-end page should not carry core's icon font for
 * one card. The real fix is the inline-SVG layer, a later slice.
 *
 * Never throws: a malformed entry is skipped, exactly as StatCard drops a
 * malformed prop.
 */
final class Icons {

	/**
	 * Domain-independent seed concepts (design K2).
	 *
	 * Every VALUE was verified present, and every KEY verified absent, in the
	 * Dashicons set of an installed WordPress 6.9.1 (350 icons) on 2026-09-20.
	 */
	private const SEED = array(
		'revenue'   => 'money-alt',
		'total'     => 'chart-bar',
		'count'     => 'list-view',
		'rate'      => 'chart-line',
		'customers' => 'admin-users',
		'items'     => 'products',
		'pending'   => 'clock',
		'active'    => 'yes-alt',
		'new'       => 'plus-alt',
		'returning' => 'update',
		'time'      => 'calendar-alt',
		'place'     => 'location-alt',
	);

	/**
	 * Concepts registered by consumers; these win over SEED.
	 *
	 * @var array<string, string>
	 */
	private static array $registered = array();

	/**
	 * Register product concepts. The last registration of a concept wins.
	 *
	 * Entries that are not string => non-empty-string are skipped in silence;
	 * they never become concepts, so resolve() keeps passing the value through
	 * as a raw suffix.
	 *
	 * SCOPE: this registry is the WINNING ui-core copy's, so it is shared by
	 * every plugin on the site. Its JSX twin is NOT -- that one lives in the
	 * registering bundle and reaches no other plugin's bundle. Each product
	 * therefore calls registerIcons() in its own bundle; see README.
	 *
	 * Call at plugins_loaded priority >= 1: the package boots at 0.
	 *
	 * @param array<mixed, mixed> $map Concept => Dashicon suffix.
	 */
	public static function register( array $map ): void {
		foreach ( $map as $concept => $suffix ) {
			if ( ! is_string( $concept ) || '' === $concept ) {
				continue;
			}
			if ( ! is_string( $suffix ) || '' === $suffix ) {
				continue;
			}
			self::$registered[ $concept ] = $suffix;
		}
	}

	/**
	 * Resolve an `icon` value: a concept becomes its suffix, anything else
	 * passes through unchanged (K1).
	 *
	 * @param string $value Concept or raw Dashicon suffix.
	 */
	public static function resolve( string $value ): string {
		return self::map()[ $value ] ?? $value;
	}

	/**
	 * The effective vocabulary: seed concepts plus registered ones.
	 *
	 * @return array<string, string>
	 */
	public static function map(): array {
		return self::$registered + self::SEED;
	}

	/**
	 * Drop every registration. A TEST SEAM: static state outlives a PHPUnit
	 * case, so without this the first test to register would decide what every
	 * later one measures. Nothing in src/ calls it.
	 */
	public static function reset(): void {
		self::$registered = array();
	}
}
