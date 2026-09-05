<?php
/**
 * The pilot's FREE CORE -- Phase 5 of the design document, as a fixture.
 *
 * "From day one, two plugins (free core + Pro add-on) on ui-core, the seam
 * born with them." This file is the free core. It declares one component
 * through the factory (all four surfaces derive from it), declares the slots
 * and capabilities the Pro add-on may fill, and contains none of the three
 * forbidden things -- PurityScanner proves that in PilotSeamTest.
 *
 * @package MHMUiCore
 */

declare(strict_types=1);

use MHMUiCore\Component\ComponentContract;
use MHMUiCore\Component\ComponentFactory;
use MHMUiCore\Component\ComponentRenderer;

/**
 * The one hand-written thing: the renderer.
 */
final class Pilot_Hero_Renderer implements ComponentRenderer {

	/**
	 * Render.
	 *
	 * @param array<string, mixed> $settings Typed settings.
	 * @param array<string, mixed> $context  Surface context.
	 * @return string
	 */
	public function render( array $settings, array $context ): string {
		$badge = '';
		if ( $GLOBALS['pilot_caps']->has( 'pro_badge' ) ) {
			$badge = '<span class="pilot-hero__badge">PRO</span>';
		}

		$button = $settings['showButton'] ? '<a class="pilot-hero__button" href="#">Go</a>' : '';

		$html = '<section class="pilot-hero pilot-hero--' . esc_attr( (string) $settings['layout'] ) . '" data-columns="' . esc_attr( (string) $settings['columns'] ) . '">'
			. '<h2 class="pilot-hero__title">' . esc_html( (string) $settings['title'] ) . '</h2>'
			. $badge
			. $button
			. '</section>';

		// The seam: whatever the Pro add-on appends comes through the declared slot.
		return (string) $GLOBALS['pilot_seam']->apply( 'hero_after', $html, $settings );
	}
}

$GLOBALS['pilot_seam'] = mhmuicore_slot_registry( 'pilot' );
$GLOBALS['pilot_seam']->declare_slot( 'hero_after', 'Filter the hero markup after it is rendered.' );

$GLOBALS['pilot_caps'] = mhmuicore_capabilities( 'pilot' );

$GLOBALS['pilot_factory'] = mhmuicore_component_factory(
	array(
		'prefix'          => 'pilot',
		'block_namespace' => 'pilot',
		'text_domain'     => 'pilot',
		// What a real product passes: the directory the scaffolder wrote into.
		// Without it WordPress never opens block.json, and the block it registers
		// is not the block the file describes.
		'blocks_dir'      => __DIR__ . '/' . ComponentFactory::BLOCKS_DIRNAME,
	)
);

$GLOBALS['pilot_component'] = $GLOBALS['pilot_factory']->register(
	new ComponentContract( require dirname( __DIR__, 4 ) . '/Fixtures/hero-contract.php' ),
	new Pilot_Hero_Renderer()
);
