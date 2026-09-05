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
use RuntimeException;

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
	 * Asset fields in block.json, and the register_block_type() argument each fills.
	 *
	 * The plural `*_handles` form, which is what core has taken since 6.1 and what
	 * its own type declaration accepts; the singular names are the deprecated ones.
	 *
	 * @var array<string, string>
	 */
	private const ASSET_KEYS = array(
		'editorScript' => 'editor_script_handles',
		'viewScript'   => 'view_script_handles',
		'style'        => 'style_handles',
		'editorStyle'  => 'editor_style_handles',
	);

	/**
	 * Refuse metadata that names a different block than the factory does.
	 *
	 * `name` is the one key the arguments never carry, so the file always wins it.
	 * A stale file therefore registered `other/hero` while this method's caller
	 * returned `pilot/hero`, and the shortcode, the Layout adapter and every
	 * `<!-- wp:pilot/hero -->` pointed at a block nobody had registered. Two
	 * answers to "what is this block called" is a product error, and a loud one at
	 * boot beats a silent one in the editor.
	 *
	 * @param string $metadata Directory holding block.json.
	 * @param string $name     The name the factory registers under.
	 * @return void
	 *
	 * @throws RuntimeException When the file cannot be read, carries no name, or
	 *                          carries a different one.
	 */
	private static function assert_metadata_agrees( string $metadata, string $name ): void {
		$raw = file_get_contents( $metadata . '/block.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading the package's own metadata from disk, not a remote fetch.
		if ( false === $raw ) {
			throw new RuntimeException(
				esc_html( sprintf( 'BlockSurface: %s/block.json could not be read.', $metadata ) )
			);
		}

		$declared = json_decode( $raw, true );
		if ( ! is_array( $declared ) || ! isset( $declared['name'] ) || ! is_string( $declared['name'] ) ) {
			throw new RuntimeException(
				esc_html( sprintf( 'BlockSurface: %s/block.json is not readable metadata: it declares no block name.', $metadata ) )
			);
		}

		if ( $declared['name'] !== $name ) {
			throw new RuntimeException(
				esc_html(
					sprintf(
						'BlockSurface: %1$s/block.json names "%2$s" but the factory registers "%3$s". Regenerate the metadata.',
						$metadata,
						$declared['name'],
						$name
					)
				)
			);
		}
	}

	/**
	 * The directory holding this contract's block.json, or null when there is none.
	 *
	 * @param ComponentContract $contract   Contract.
	 * @param string|null       $blocks_dir Directory the product scaffolds into.
	 * @return string|null
	 */
	private static function metadata_dir( ComponentContract $contract, ?string $blocks_dir ): ?string {
		if ( null === $blocks_dir || '' === $blocks_dir ) {
			return null;
		}

		$dir = rtrim( str_replace( '\\', '/', $blocks_dir ), '/' ) . '/' . $contract->kebab();

		return is_file( $dir . '/block.json' ) ? $dir : null;
	}

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
	 * @param ComponentContract $contract        Contract.
	 * @param ComponentRenderer $renderer        Renderer.
	 * @param string            $block_namespace Block namespace.
	 * @param string            $text_domain     Product text domain.
	 * @param string|null       $blocks_dir      Directory holding <kebab>/block.json,
	 *                                           as written by the scaffolder.
	 * @return string The registered block name.
	 *
	 * @throws RuntimeException When metadata is present but names a different
	 *                          block, cannot be read, or WordPress refuses it.
	 */
	public static function register( ComponentContract $contract, ComponentRenderer $renderer, string $block_namespace, string $text_domain, ?string $blocks_dir = null ): string {
		$json = self::block_json( $contract, $block_namespace, $text_domain );
		$name = self::name( $contract, $block_namespace );

		$render = static function ( $attributes, $content = '' ) use ( $contract, $renderer, $name ): string {
			return self::render( $contract, $renderer, $name, is_array( $attributes ) ? $attributes : array(), (string) $content );
		};

		/*
		 * When the scaffolded metadata is on disk it IS the block, and the only
		 * argument left is the render callback -- a PHP closure no JSON can carry.
		 *
		 * Passing the contract's own title, supports and attributes beside the file
		 * looked like belt and braces and was the defect wearing a new coat: core
		 * merges `$settings = array_merge( $settings, $args )`, so every argument
		 * REPLACES the file's answer. A product that opened block.json to switch
		 * wide alignment on would have watched nothing happen, and which half won
		 * varied key by key. An audit measured it.
		 */
		$metadata = self::metadata_dir( $contract, $blocks_dir );

		if ( null !== $metadata ) {
			self::assert_metadata_agrees( $metadata, $name );

			if ( false === register_block_type( $metadata, array( 'render_callback' => $render ) ) ) {
				throw new RuntimeException(
					esc_html( sprintf( 'BlockSurface: WordPress refused the metadata in %s.', $metadata ) )
				);
			}

			return $name;
		}

		/*
		 * No file: the contract answers for the block, and apiVersion is simply not
		 * among the answers it can give. Core documents that argument as a string
		 * while storing an int, and a block with no metadata has no apiVersion to
		 * declare -- pass `blocks_dir` to have one.
		 */
		$args = array(
			'title'           => (string) $json['title'],
			'category'        => (string) $json['category'],
			'icon'            => (string) $json['icon'],
			'description'     => (string) $json['description'],
			'supports'        => (array) $json['supports'],
			'attributes'      => (array) $json['attributes'],
			'render_callback' => $render,
		);

		foreach ( self::ASSET_KEYS as $json_key => $arg_key ) {
			if ( isset( $json[ $json_key ] ) ) {
				$args[ $arg_key ] = array( (string) $json[ $json_key ] );
			}
		}

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
