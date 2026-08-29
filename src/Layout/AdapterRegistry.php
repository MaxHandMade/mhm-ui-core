<?php
/**
 * Adapter registry that holds instance state.
 *
 * @package MHMUiCore
 */

declare(strict_types=1);

namespace MHMUiCore\Layout;

/**
 * Instance registry for component adapters.
 *
 * The registry is now instance state on the engine, not a static table.
 * The consumer's boot_defaults() ritual disappears with it: production called
 * it from exactly two places, both inside the WP-CLI command, so the "when does
 * registration happen" question the design doc left open answers itself.
 * Registered adapters ARE the component vocabulary -- there is no separate
 * allow-list to drift from them. Adapters must be render-stateless: unlike the
 * old registry, which built a fresh instance per call, these are long-lived.
 */
final class AdapterRegistry {

	/**
	 * The layout contract instance.
	 *
	 * @var LayoutContract
	 */
	private $contract;

	/**
	 * Constructor.
	 *
	 * @param LayoutContract $contract The layout contract instance.
	 */
	public function __construct( LayoutContract $contract ) {
		$this->contract = $contract;
	}

	/**
	 * Check if a type is registered.
	 *
	 * @param string $type The component type.
	 * @return bool True if the type is registered, false otherwise.
	 */
	public function has( string $type ): bool {
		return null !== $this->contract->adapter( $type );
	}

	/**
	 * Get an adapter for a type.
	 *
	 * @param string $type The component type.
	 * @return LayoutComponentAdapter|null The adapter or null if not found.
	 */
	public function get_adapter( string $type ): ?LayoutComponentAdapter {
		return $this->contract->adapter( $type );
	}
}
