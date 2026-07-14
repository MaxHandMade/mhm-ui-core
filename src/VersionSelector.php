<?php
/**
 * Version-aware selection logic for competing bundled copies of ui-core.
 *
 * @package MHM\UiCore
 */

declare(strict_types=1);

namespace MHM\UiCore;

/**
 * Picks the winning copy of ui-core when several plugins each bundle their own.
 *
 * Kept dependency-free and WordPress-free so it can be unit tested in isolation.
 *
 * @package MHM\UiCore
 */
final class VersionSelector {

	/**
	 * Select the bootstrap file of the highest registered version.
	 *
	 * @param array<string, string> $candidates Version string => bootstrap file path.
	 * @return string|null Bootstrap path of the highest version, or null when empty.
	 */
	public static function select( array $candidates ): ?string {
		$winner = null;

		foreach ( $candidates as $version => $bootstrap ) {
			if ( null === $winner || version_compare( (string) $version, $winner, '>' ) ) {
				$winner = (string) $version;
			}
		}

		if ( null === $winner ) {
			return null;
		}

		return $candidates[ $winner ];
	}
}
