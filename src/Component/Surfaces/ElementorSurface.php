<?php
/**
 * Elementor surface, derived from a contract.
 *
 * @package MHMUiCore\Component\Surfaces
 */

declare(strict_types=1);

namespace MHMUiCore\Component\Surfaces;

use MHMUiCore\Component\ComponentContract;
use MHMUiCore\Component\ComponentRenderer;

/**
 * Elementor controls and a widget from a contract.
 *
 * Two halves on purpose. controls() and settings_from_widget() are pure data
 * transforms and run anywhere -- they are what the unit suite pins. widget()
 * builds a real \Elementor\Widget_Base subclass and therefore only exists once
 * Elementor is loaded; it returns null otherwise instead of fataling on a
 * parent class that is not there.
 */
final class ElementorSurface {

	/**
	 * Control specifications derived from the contract's settings.
	 *
	 * The shape mirrors what Widget_Base::add_control() takes, so a product that
	 * hand-writes a widget can still feed these straight in.
	 *
	 * @param ComponentContract $contract Contract.
	 * @return array<string, array<string, mixed>> name => control args.
	 */
	public static function controls( ComponentContract $contract ): array {
		$controls = array();
		foreach ( $contract->settings() as $name => $setting ) {
			switch ( $setting['type'] ) {
				case ComponentContract::TYPE_BOOLEAN:
					$controls[ $name ] = array(
						'label'   => $setting['label'],
						'type'    => 'switcher',
						'default' => $setting['default'] ? 'yes' : '',
					);
					break;

				case ComponentContract::TYPE_INTEGER:
					$controls[ $name ] = array(
						'label'   => $setting['label'],
						'type'    => 'number',
						'default' => $setting['default'],
					);
					break;

				case ComponentContract::TYPE_ENUM:
					$options = array();
					foreach ( $setting['enum'] as $option ) {
						$options[ $option ] = $option;
					}
					$controls[ $name ] = array(
						'label'   => $setting['label'],
						'type'    => 'select',
						'default' => $setting['default'],
						'options' => $options,
					);
					break;

				default:
					$controls[ $name ] = array(
						'label'   => $setting['label'],
						'type'    => 'text',
						'default' => $setting['default'],
					);
			}
		}
		return $controls;
	}

	/**
	 * Turn Elementor's settings array back into contract settings.
	 *
	 * Elementor switchers say 'yes' / '' where the contract says true / false;
	 * the contract's own sanitize() understands 'yes', so this only has to drop
	 * the keys Elementor adds that the contract never declared.
	 *
	 * @param ComponentContract    $contract          Contract.
	 * @param array<string, mixed> $elementor_settings From get_settings_for_display().
	 * @return array<string, mixed> Typed contract settings.
	 */
	public static function settings_from_widget( ComponentContract $contract, array $elementor_settings ): array {
		$declared = array_intersect_key( $elementor_settings, $contract->settings() );
		return $contract->sanitize( $declared );
	}

	/**
	 * A Widget_Base subclass bound to this contract and renderer, or null when
	 * Elementor is not loaded.
	 *
	 * @param ComponentContract $contract Contract.
	 * @param ComponentRenderer $renderer Renderer.
	 * @param string            $prefix   Product prefix (widget name and category).
	 * @return object|null
	 */
	public static function widget( ComponentContract $contract, ComponentRenderer $renderer, string $prefix ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return null;
		}

		return ElementorWidgetFactory::make( $contract, $renderer, $prefix );
	}
}
