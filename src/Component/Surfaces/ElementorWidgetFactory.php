<?php
/**
 * Builds the Elementor widget class for a contract.
 *
 * @package MHMUiCore\Component\Surfaces
 */

declare(strict_types=1);

namespace MHMUiCore\Component\Surfaces;

use Elementor\Widget_Base;
use MHMUiCore\Component\ComponentContract;
use MHMUiCore\Component\ComponentRenderer;

/**
 * Separate file, separate class: the anonymous class below names
 * \Elementor\Widget_Base as its parent, and PHP resolves that the moment this
 * expression is evaluated. Keeping it out of ElementorSurface means the surface
 * can be autoloaded, unit-tested and reasoned about on a site with no Elementor
 * at all; only this factory needs Elementor present, and ElementorSurface::widget()
 * checks before calling it.
 */
final class ElementorWidgetFactory {

	/**
	 * Instantiate a widget bound to the contract.
	 *
	 * @param ComponentContract $contract Contract.
	 * @param ComponentRenderer $renderer Renderer.
	 * @param string            $prefix   Product prefix.
	 * @return Widget_Base
	 */
	public static function make( ComponentContract $contract, ComponentRenderer $renderer, string $prefix ): Widget_Base {
		return new class( $contract, $renderer, $prefix ) extends Widget_Base {

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
			 * Product prefix.
			 *
			 * @var string
			 */
			private $prefix;

			/**
			 * Bind.
			 *
			 * @param ComponentContract $contract Contract.
			 * @param ComponentRenderer $renderer Renderer.
			 * @param string            $prefix   Product prefix.
			 */
			public function __construct( ComponentContract $contract, ComponentRenderer $renderer, string $prefix ) {
				$this->contract = $contract;
				$this->renderer = $renderer;
				$this->prefix   = $prefix;
				parent::__construct();
			}

			/**
			 * Widget machine name.
			 *
			 * @return string
			 */
			public function get_name(): string {
				return $this->prefix . '_' . $this->contract->slug();
			}

			/**
			 * Widget title.
			 *
			 * @return string
			 */
			public function get_title(): string {
				return $this->contract->title();
			}

			/**
			 * Widget category.
			 *
			 * @return array<int, string>
			 */
			public function get_categories(): array {
				return array( $this->prefix );
			}

			/**
			 * Controls, one per declared setting.
			 *
			 * @return void
			 */
			protected function register_controls(): void {
				$this->start_controls_section( 'content', array( 'label' => $this->contract->title() ) );
				foreach ( ElementorSurface::controls( $this->contract ) as $name => $args ) {
					$this->add_control( $name, $args );
				}
				$this->end_controls_section();
			}

			/**
			 * Render through the shared renderer.
			 *
			 * The renderer escapes its own output; echoing it here is the
			 * Elementor contract (render() prints).
			 *
			 * @return void
			 */
			protected function render(): void {
				$settings = ElementorSurface::settings_from_widget( $this->contract, $this->get_settings_for_display() );

				$html = $this->renderer->render(
					$settings,
					array(
						'surface'     => 'elementor',
						'instance_id' => $this->get_name() . '-' . $this->get_id(),
						'content'     => '',
					)
				);
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the renderer is the escaping boundary by contract; the shortcode and block surfaces return this same string unescaped.
				echo $html;
			}
		};
	}
}
