<?php
/**
 * Gutenberg block surface, derived from a contract.
 *
 * @package MHMUiCore\Component\Surfaces
 */

declare(strict_types=1);

namespace MHMUiCore\Component\Surfaces;

use MHMUiCore\Component\ComponentContract;
use MHMUiCore\Component\ComponentRenderer;

/**
 * The block.json file and a server-rendered block, both from a contract.
 *
 * Server-rendered on purpose: the house standard says the front end is not
 * React -- blocks and Elementor widgets render on the server, React lives only
 * in the block editor. The same renderer that serves the shortcode serves the
 * block, so the two can never drift.
 */
final class BlockSurface {

	/**
	 * Supports every generated block starts from.
	 *
	 * These are the ones a product would have to write identically for every
	 * block (the second-plugin test); a contract's block hints may override any.
	 *
	 * @var array<string, mixed>
	 */
	private const DEFAULT_SUPPORTS = array(
		'html'            => false,
		'anchor'          => true,
		'className'       => true,
		'customClassName' => true,
	);

	/**
	 * The block name a contract registers under.
	 *
	 * @param ComponentContract $contract  Contract.
	 * @param string            $block_namespace Block namespace, e.g. "mhm-rentiva".
	 * @return non-empty-string e.g. "mhm-rentiva/featured-vehicles".
	 */
	public static function name( ComponentContract $contract, string $block_namespace ): string {
		return $block_namespace . '/' . $contract->kebab();
	}

	/**
	 * The block.json file as data.
	 *
	 * Written to disk by the scaffolder; read at runtime by register().
	 *
	 * @param ComponentContract $contract    Contract.
	 * @param string            $block_namespace Block namespace.
	 * @param string            $text_domain     The product's text domain.
	 * @return array<string, mixed>
	 */
	public static function block_json( ComponentContract $contract, string $block_namespace, string $text_domain ): array {
		$hints = $contract->block_hints();

		$supports = self::DEFAULT_SUPPORTS;
		if ( isset( $hints['supports'] ) && is_array( $hints['supports'] ) ) {
			$supports = array_merge( $supports, $hints['supports'] );
		}

		$json = array(
			'$schema'     => 'https://schemas.wp.org/trunk/block.json',
			'apiVersion'  => 3,
			'name'        => self::name( $contract, $block_namespace ),
			'title'       => $contract->title(),
			'category'    => isset( $hints['category'] ) ? (string) $hints['category'] : 'widgets',
			'icon'        => isset( $hints['icon'] ) ? (string) $hints['icon'] : 'block-default',
			'description' => isset( $hints['description'] ) ? (string) $hints['description'] : '',
			'textdomain'  => $text_domain,
			'supports'    => $supports,
			'attributes'  => self::attributes( $contract ),
		);

		foreach ( array( 'editorStyle', 'style', 'editorScript', 'viewScript' ) as $asset_key ) {
			if ( isset( $hints[ $asset_key ] ) ) {
				$json[ $asset_key ] = $hints[ $asset_key ];
			}
		}

		return $json;
	}

	/**
	 * Block attributes derived from the contract's settings.
	 *
	 * @param ComponentContract $contract Contract.
	 * @return array<string, array<string, mixed>>
	 */
	public static function attributes( ComponentContract $contract ): array {
		$attributes = array();
		foreach ( $contract->settings() as $name => $setting ) {
			$attribute = array(
				'type'    => self::json_type( $setting['type'] ),
				'default' => $setting['default'],
			);
			if ( ComponentContract::TYPE_ENUM === $setting['type'] ) {
				$attribute['enum'] = $setting['enum'];
			}
			$attributes[ $name ] = $attribute;
		}
		return $attributes;
	}

	/**
	 * Map a contract type onto a block.json attribute type.
	 *
	 * @param string $type Contract type.
	 * @return string
	 */
	private static function json_type( string $type ): string {
		switch ( $type ) {
			case ComponentContract::TYPE_BOOLEAN:
				return 'boolean';
			case ComponentContract::TYPE_INTEGER:
				return 'integer';
			default:
				return 'string';
		}
	}

	/**
	 * Register the block with WordPress, server-rendered through the renderer.
	 *
	 * @param ComponentContract $contract    Contract.
	 * @param ComponentRenderer $renderer    Renderer.
	 * @param string            $block_namespace Block namespace.
	 * @param string            $text_domain     Product text domain.
	 * @return string The registered block name.
	 */
	public static function register( ComponentContract $contract, ComponentRenderer $renderer, string $block_namespace, string $text_domain ): string {
		$json = self::block_json( $contract, $block_namespace, $text_domain );
		$name = self::name( $contract, $block_namespace );

		/*
		 * api_version is deliberately absent: core reads it from block.json when
		 * the editor loads the block, and the php-stubs type it as a string while
		 * WP_Block_Type stores an int. Passing it buys nothing and fights the types.
		 */
		$args = array(
			'title'           => (string) $json['title'],
			'category'        => (string) $json['category'],
			'icon'            => (string) $json['icon'],
			'description'     => (string) $json['description'],
			'supports'        => (array) $json['supports'],
			'attributes'      => (array) $json['attributes'],
			'render_callback' => static function ( $attributes, $content = '' ) use ( $contract, $renderer, $name ): string {
				return self::render( $contract, $renderer, $name, is_array( $attributes ) ? $attributes : array(), (string) $content );
			},
		);

		register_block_type( $name, $args );

		return $name;
	}

	/**
	 * The render callback body, public so it is testable without the registry.
	 *
	 * @param ComponentContract    $contract   Contract.
	 * @param ComponentRenderer    $renderer   Renderer.
	 * @param string               $name       Block name (part of the instance id).
	 * @param array<string, mixed> $attributes Raw block attributes.
	 * @param string               $content    Inner content.
	 * @return string
	 */
	public static function render( ComponentContract $contract, ComponentRenderer $renderer, string $name, array $attributes, string $content = '' ): string {
		static $counter = 0;
		++$counter;

		return $renderer->render(
			$contract->sanitize( $attributes ),
			array(
				'surface'     => 'block',
				'instance_id' => str_replace( '/', '-', $name ) . '-' . $counter,
				'content'     => $content,
			)
		);
	}
}
