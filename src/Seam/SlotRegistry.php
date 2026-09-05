<?php
/**
 * Empty slots a free core declares and a Pro add-on fills.
 *
 * @package MHMUiCore\Seam
 */

declare(strict_types=1);

namespace MHMUiCore\Seam;

use InvalidArgumentException;

/**
 * Responsibility 3 of the design document: the seam's shape.
 *
 * "The free core offers EMPTY SLOTS (registries, hook extension points,
 * capability filters); the Pro plugin plugs into them." This is the registry.
 * The free core DECLARES slots -- by name, up front, in one place -- and the
 * Pro FILLS them. Filling a slot nobody declared throws: the design document's
 * whole argument for a whitelist is that the wrong shape fails LOUDLY (a missing
 * class, a red test) rather than silently, and a fill that lands nowhere is
 * exactly the silent failure a blacklist produces.
 *
 * Two semantics, mirroring WordPress's own: apply() threads a value through
 * every fill (a filter), run() just calls them (an action). Both also bridge to
 * $wp_filter when WordPress is loaded, so a third party can hook the same
 * seam without knowing this class exists.
 */
final class SlotRegistry {

	private const NAME_PATTERN = '/^[a-z][a-z0-9_]*$/';

	/**
	 * Product prefix, e.g. "rentiva". Namespaces the WordPress hook names.
	 *
	 * @var string
	 */
	private $prefix;

	/**
	 * Optional segment between prefix and slot in bridged hook names.
	 *
	 * @var string
	 */
	private $infix = '';

	/**
	 * Declared slots: name => description.
	 *
	 * @var array<string, string>
	 */
	private $slots = array();

	/**
	 * Fills: slot => list of [priority, callable].
	 *
	 * @var array<string, list<array{0:int, 1:callable}>>
	 */
	private $fills = array();

	/**
	 * Build a registry for one product.
	 *
	 * @param string $prefix Product prefix, ^[a-z][a-z0-9_]*$.
	 * @param string $infix  Optional segment between the prefix and the slot in
	 *                       bridged hook names. Empty by default; see hook_name().
	 *
	 * @throws InvalidArgumentException When the prefix or infix is malformed.
	 */
	public function __construct( string $prefix, string $infix = '' ) {
		if ( 1 !== preg_match( self::NAME_PATTERN, $prefix ) ) {
			throw new InvalidArgumentException( 'SlotRegistry: prefix must match ^[a-z][a-z0-9_]*$.' );
		}
		if ( '' !== $infix && 1 !== preg_match( self::NAME_PATTERN, $infix ) ) {
			throw new InvalidArgumentException( 'SlotRegistry: infix must match ^[a-z][a-z0-9_]*$ when given.' );
		}
		$this->prefix = $prefix;
		$this->infix  = $infix;
	}

	/**
	 * Declare an empty slot. The free core calls this.
	 *
	 * @param string $slot        Slot name, ^[a-z][a-z0-9_]*$.
	 * @param string $description What a fill is expected to do -- documentation
	 *                            for the Pro author, kept with the declaration.
	 * @return void
	 *
	 * @throws InvalidArgumentException When the name is malformed or already declared.
	 */
	public function declare_slot( string $slot, string $description = '' ): void {
		if ( 1 !== preg_match( self::NAME_PATTERN, $slot ) ) {
			throw new InvalidArgumentException( 'SlotRegistry: slot name must match ^[a-z][a-z0-9_]*$.' );
		}
		if ( isset( $this->slots[ $slot ] ) ) {
			throw new InvalidArgumentException(
				esc_html( sprintf( 'SlotRegistry: slot "%s" is already declared.', $slot ) )
			);
		}
		$this->slots[ $slot ] = $description;
		$this->fills[ $slot ] = array();
	}

	/**
	 * Fill a declared slot. The Pro add-on calls this.
	 *
	 * @param string   $slot     Declared slot name.
	 * @param callable $fill     Callback.
	 * @param int      $priority Lower runs first, like WordPress.
	 * @return void
	 *
	 * @throws InvalidArgumentException When the slot was never declared.
	 */
	public function fill( string $slot, callable $fill, int $priority = 10 ): void {
		if ( ! isset( $this->slots[ $slot ] ) ) {
			throw new InvalidArgumentException(
				esc_html( sprintf( 'SlotRegistry: slot "%s" is not declared by the core.', $slot ) )
			);
		}
		$this->fills[ $slot ][] = array( $priority, $fill );
		usort(
			$this->fills[ $slot ],
			static fn( array $a, array $b ): int => $a[0] <=> $b[0]
		);
	}

	/**
	 * Whether a slot is declared.
	 *
	 * @param string $slot Slot name.
	 * @return bool
	 */
	public function is_declared( string $slot ): bool {
		return isset( $this->slots[ $slot ] );
	}

	/**
	 * Whether anything has filled a slot.
	 *
	 * @param string $slot Slot name.
	 * @return bool
	 */
	public function has_fills( string $slot ): bool {
		return isset( $this->fills[ $slot ] ) && array() !== $this->fills[ $slot ];
	}

	/**
	 * Every declared slot with its description.
	 *
	 * @return array<string, string>
	 */
	public function slots(): array {
		return $this->slots;
	}

	/**
	 * The WordPress hook name a slot bridges to.
	 *
	 * NO INFIX BY DEFAULT, and that default is the point.
	 *
	 * This name is the consuming product's PUBLIC extension surface: it is what a
	 * third party writes in add_filter(), what goes in that product's
	 * documentation, and what a WordPress.org reviewer greps for. It used to read
	 * "<prefix>_seam_<slot>", which planted a word from THIS package's vocabulary
	 * into every consumer's public API -- and specifically the word the house's
	 * WordPress.org record attaches to a rejection, where `pro_seam` markers and
	 * `allowsSeam()` edition checks were read as crippleware. The hooks bridged
	 * here gate nothing and are neutral infrastructure, but the word buys the
	 * consumer nothing and costs it an argument with a human reviewer.
	 *
	 * The shape the submission standard endorses has no infix at all:
	 * `apply_filters( 'mhm_rentiva_blocks_registry', $blocks )`.
	 *
	 * A product with its own convention passes an infix to the constructor.
	 *
	 * @param string $slot Slot name.
	 * @return non-empty-string e.g. "rentiva_hero_after", or "rentiva_ext_hero_after" with an infix.
	 */
	public function hook_name( string $slot ): string {
		$infix = '' === $this->infix ? '' : $this->infix . '_';

		return $this->prefix . '_' . $infix . $slot;
	}

	/**
	 * Filter semantics: thread $value through every fill, then through WordPress.
	 *
	 * @param string $slot  Declared slot.
	 * @param mixed  $value Starting value.
	 * @param mixed  ...$args Extra arguments handed to each fill after $value.
	 * @return mixed
	 *
	 * @throws InvalidArgumentException When the slot was never declared.
	 */
	public function apply( string $slot, $value, ...$args ) {
		$this->assert_declared( $slot );

		foreach ( $this->fills[ $slot ] as $entry ) {
			$value = $entry[1]( $value, ...$args );
		}

		if ( function_exists( 'apply_filters' ) ) {
			$value = apply_filters( $this->hook_name( $slot ), $value, ...$args ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- the prefix is the product's, validated in the constructor.
		}

		return $value;
	}

	/**
	 * Action semantics: call every fill, then WordPress.
	 *
	 * @param string $slot    Declared slot.
	 * @param mixed  ...$args Arguments handed to each fill.
	 * @return void
	 *
	 * @throws InvalidArgumentException When the slot was never declared.
	 */
	public function run( string $slot, ...$args ): void {
		$this->assert_declared( $slot );

		foreach ( $this->fills[ $slot ] as $entry ) {
			$entry[1]( ...$args );
		}

		if ( function_exists( 'do_action' ) ) {
			do_action( $this->hook_name( $slot ), ...$args ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- the prefix is the product's, validated in the constructor.
		}
	}

	/**
	 * Throw unless the slot is declared.
	 *
	 * @param string $slot Slot name.
	 * @return void
	 *
	 * @throws InvalidArgumentException When undeclared.
	 */
	private function assert_declared( string $slot ): void {
		if ( ! isset( $this->slots[ $slot ] ) ) {
			throw new InvalidArgumentException(
				esc_html( sprintf( 'SlotRegistry: slot "%s" is not declared.', $slot ) )
			);
		}
	}
}
