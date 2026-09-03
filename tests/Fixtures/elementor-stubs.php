<?php
/**
 * Minimal Elementor surface so the unit suite (and PHPStan) can see the parent
 * class ElementorWidgetFactory extends. Records what a widget registers so a
 * test can assert on it. Guarded: a no-op wherever real Elementor is loaded.
 *
 * @package MHMUiCore
 */

declare(strict_types=1);

namespace Elementor;

if ( ! class_exists( 'Elementor\Widget_Base', false ) ) {
	/**
	 * Stand-in for Elementor's widget base.
	 */
	class Widget_Base {

		/**
		 * Controls added, id => args, in order.
		 *
		 * @var array<string, array<string, mixed>>
		 */
		public $controls = array();

		/**
		 * Sections opened, in order.
		 *
		 * @var list<string>
		 */
		public $sections = array();

		/**
		 * Settings the test injects for get_settings_for_display().
		 *
		 * @var array<string, mixed>
		 */
		public $settings = array();

		/**
		 * Constructor signature mirrors Elementor's.
		 *
		 * @param array<string, mixed> $data Widget data.
		 * @param mixed                $args Args.
		 */
		public function __construct( array $data = array(), $args = null ) {}

		/**
		 * Fake id.
		 *
		 * @return string
		 */
		public function get_id(): string {
			return 'stub';
		}

		/**
		 * Settings.
		 *
		 * @param string|null $key Setting key.
		 * @return mixed
		 */
		public function get_settings_for_display( $key = null ) {
			return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null );
		}

		/**
		 * Open a section.
		 *
		 * @param string               $id   Section id.
		 * @param array<string, mixed> $args Args.
		 * @return void
		 */
		protected function start_controls_section( string $id, array $args = array() ): void {
			$this->sections[] = $id;
		}

		/**
		 * Close a section.
		 *
		 * @return void
		 */
		protected function end_controls_section(): void {}

		/**
		 * Add a control.
		 *
		 * @param string               $id   Control id.
		 * @param array<string, mixed> $args Args.
		 * @return void
		 */
		protected function add_control( string $id, array $args = array() ): void {
			$this->controls[ $id ] = $args;
		}
	}
}

if ( ! class_exists( 'Elementor\Controls_Manager', false ) ) {
	/**
	 * Stand-in for Elementor's control type constants.
	 */
	class Controls_Manager {
		public const SWITCHER = 'switcher';
		public const SELECT   = 'select';
		public const TEXT     = 'text';
		public const NUMBER   = 'number';
	}
}
