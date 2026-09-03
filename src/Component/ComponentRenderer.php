<?php
/**
 * The one thing a product writes by hand.
 *
 * @package MHMUiCore\Component
 */

declare(strict_types=1);

namespace MHMUiCore\Component;

/**
 * Renders one component instance from typed settings.
 *
 * The design document: "the only hand-written thing is the renderer" -- the
 * design translated into a PHP template using prefixed CSS and tokens. The
 * package never sees the template; it only promises that whatever surface the
 * instance came from, $settings arrives already coerced through the contract.
 */
interface ComponentRenderer {

	/**
	 * Render markup.
	 *
	 * The context carries three keys: 'surface' (one of 'shortcode', 'block',
	 * 'elementor', 'layout'), 'instance_id' (unique on the page) and 'content'
	 * (enclosed content, when the surface carries any).
	 *
	 * @param array<string, mixed> $settings Declared settings, typed by ComponentContract::sanitize().
	 * @param array<string, mixed> $context  Surface context, keys described above.
	 * @return string HTML. The renderer escapes; the surfaces return it untouched.
	 */
	public function render( array $settings, array $context ): string;
}
