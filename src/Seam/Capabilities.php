<?php
/**
 * Capabilities a Pro add-on grants to a free core.
 *
 * @package MHMUiCore\Seam
 */

declare(strict_types=1);

namespace MHMUiCore\Seam;

use InvalidArgumentException;

/**
 * The capability half of the seam.
 *
 * A capability is something the core CAN DO ONCE PRO IS PRESENT -- never
 * something the core withholds until Pro is present. The design document's
 * three absences hold here by construction: the core has no artificial limit
 * (there is nothing here that counts or caps), no licence code (granting is a
 * plain method call from the add-on, not a key check), and no outbound HTTP.
 *
 * Read that distinction as a rule for the core's own code: `if ( ! $caps->has(
 * 'x' ) ) { refuse_something_the_core_could_do(); }` is crippleware and does
 * not belong in a free core. `if ( $caps->has( 'x' ) ) { do_more(); }` is the
 * seam working as designed.
 */
final class Capabilities {

	private const NAME_PATTERN = '/^[a-z][a-z0-9_]*$/';

	/**
	 * Product prefix, for the WordPress filter name.
	 *
	 * @var string
	 */
	private $prefix;

	/**
	 * Granted capability names.
	 *
	 * @var array<string, true>
	 */
	private $granted = array();

	/**
	 * Build for one product.
	 *
	 * @param string $prefix Product prefix, ^[a-z][a-z0-9_]*$.
	 *
	 * @throws InvalidArgumentException When the prefix is malformed.
	 */
	public function __construct( string $prefix ) {
		if ( 1 !== preg_match( self::NAME_PATTERN, $prefix ) ) {
			throw new InvalidArgumentException( 'Capabilities: prefix must match ^[a-z][a-z0-9_]*$.' );
		}
		$this->prefix = $prefix;
	}

	/**
	 * Grant a capability. The Pro add-on calls this.
	 *
	 * @param string $capability Name, ^[a-z][a-z0-9_]*$.
	 * @return void
	 *
	 * @throws InvalidArgumentException When the name is malformed.
	 */
	public function grant( string $capability ): void {
		if ( 1 !== preg_match( self::NAME_PATTERN, $capability ) ) {
			throw new InvalidArgumentException( 'Capabilities: capability name must match ^[a-z][a-z0-9_]*$.' );
		}
		$this->granted[ $capability ] = true;
	}

	/**
	 * Whether a capability is granted, by the add-on or by a WordPress filter.
	 *
	 * @param string $capability Name.
	 * @return bool
	 */
	public function has( string $capability ): bool {
		$granted = isset( $this->granted[ $capability ] );

		if ( function_exists( 'apply_filters' ) ) {
			$granted = (bool) apply_filters( $this->prefix . '_capability_' . $capability, $granted ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- the prefix is the product's, validated in the constructor.
		}

		return $granted;
	}

	/**
	 * Every granted capability name.
	 *
	 * @return list<string>
	 */
	public function granted(): array {
		return array_keys( $this->granted );
	}
}
