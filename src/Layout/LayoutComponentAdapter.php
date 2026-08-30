<?php
/**
 * The component-rendering contract the package requires from its consumer.
 *
 * @package MHMUiCore\Layout
 */

declare(strict_types=1);

namespace MHMUiCore\Layout;

/**
 * What the package needs from a component.
 *
 * The consumer's abstract BaseAdapter stays in the consumer: its normalize()
 * calls a product service (CanonicalAttributeMapper). Moving the interface
 * instead of the abstract class dissolves the only cross-namespace binding
 * without injecting anything.
 */
interface LayoutComponentAdapter {

	/**
	 * Render markup for one component instance.
	 *
	 * @param array<string,mixed> $attributes  Raw attributes from the manifest.
	 * @param string              $instance_id Unique id for this instance.
	 */
	public function render( array $attributes, string $instance_id ): string;
}
