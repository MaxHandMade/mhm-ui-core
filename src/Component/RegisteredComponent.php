<?php
/**
 * What the factory hands back after registering a contract.
 *
 * @package MHMUiCore\Component
 */

declare(strict_types=1);

namespace MHMUiCore\Component;

use MHMUiCore\Layout\LayoutComponentAdapter;

/**
 * The names the surfaces registered under, plus the Layout adapter.
 */
final class RegisteredComponent {

	/**
	 * Contract.
	 *
	 * @var ComponentContract
	 */
	private $contract;

	/**
	 * Shortcode tag.
	 *
	 * @var string
	 */
	private $shortcode_tag;

	/**
	 * Block name.
	 *
	 * @var string
	 */
	private $block_name;

	/**
	 * Layout adapter.
	 *
	 * @var LayoutComponentAdapter
	 */
	private $layout_adapter;

	/**
	 * Bind.
	 *
	 * @param ComponentContract      $contract       Contract.
	 * @param string                 $shortcode_tag  Registered shortcode tag.
	 * @param string                 $block_name     Registered block name.
	 * @param LayoutComponentAdapter $layout_adapter Layout adapter.
	 */
	public function __construct( ComponentContract $contract, string $shortcode_tag, string $block_name, LayoutComponentAdapter $layout_adapter ) {
		$this->contract       = $contract;
		$this->shortcode_tag  = $shortcode_tag;
		$this->block_name     = $block_name;
		$this->layout_adapter = $layout_adapter;
	}

	/**
	 * Contract.
	 *
	 * @return ComponentContract
	 */
	public function contract(): ComponentContract {
		return $this->contract;
	}

	/**
	 * Shortcode tag.
	 *
	 * @return string
	 */
	public function shortcode_tag(): string {
		return $this->shortcode_tag;
	}

	/**
	 * Block name.
	 *
	 * @return string
	 */
	public function block_name(): string {
		return $this->block_name;
	}

	/**
	 * Layout adapter.
	 *
	 * @return LayoutComponentAdapter
	 */
	public function layout_adapter(): LayoutComponentAdapter {
		return $this->layout_adapter;
	}
}
