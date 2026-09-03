<?php
/**
 * Layout-engine adapter, derived from a contract.
 *
 * @package MHMUiCore\Component\Surfaces
 */

declare(strict_types=1);

namespace MHMUiCore\Component\Surfaces;

use MHMUiCore\Component\ComponentContract;
use MHMUiCore\Component\ComponentRenderer;
use MHMUiCore\Layout\LayoutComponentAdapter;

/**
 * The fourth surface: the same component, placeable by the Layout engine.
 *
 * A blueprint manifest names components by type and passes attributes; this
 * adapter runs those attributes through the contract and the shared renderer,
 * so a page imported from a manifest renders byte-for-byte what the shortcode
 * and the block render.
 */
final class LayoutAdapterSurface {

	/**
	 * Build an adapter for LayoutContract's "adapters" map.
	 *
	 * @param ComponentContract $contract Contract.
	 * @param ComponentRenderer $renderer Renderer.
	 * @return LayoutComponentAdapter
	 */
	public static function adapter( ComponentContract $contract, ComponentRenderer $renderer ): LayoutComponentAdapter {
		return new class( $contract, $renderer ) implements LayoutComponentAdapter {

			/**
			 * Contract.
			 *
			 * @var ComponentContract
			 */
			private $contract;

			/**
			 * Renderer.
			 *
			 * @var ComponentRenderer
			 */
			private $renderer;

			/**
			 * Bind.
			 *
			 * @param ComponentContract $contract Contract.
			 * @param ComponentRenderer $renderer Renderer.
			 */
			public function __construct( ComponentContract $contract, ComponentRenderer $renderer ) {
				$this->contract = $contract;
				$this->renderer = $renderer;
			}

			/**
			 * Render one manifest instance.
			 *
			 * @param array<string,mixed> $attributes  Raw attributes from the manifest.
			 * @param string              $instance_id Unique id for this instance.
			 * @return string
			 */
			public function render( array $attributes, string $instance_id ): string {
				return $this->renderer->render(
					$this->contract->sanitize( $attributes ),
					array(
						'surface'     => 'layout',
						'instance_id' => $instance_id,
						'content'     => '',
					)
				);
			}
		};
	}
}
