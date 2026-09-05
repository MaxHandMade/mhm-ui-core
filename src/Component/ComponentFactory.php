<?php
/**
 * The component factory: one contract in, four surfaces out.
 *
 * @package MHMUiCore\Component
 */

declare(strict_types=1);

namespace MHMUiCore\Component;

use InvalidArgumentException;
use MHMUiCore\Component\Surfaces\BlockSurface;
use MHMUiCore\Component\Surfaces\ElementorSurface;
use MHMUiCore\Component\Surfaces\LayoutAdapterSurface;
use MHMUiCore\Component\Surfaces\ShortcodeSurface;
use MHMUiCore\Layout\LayoutComponentAdapter;

/**
 * Responsibility 1 of the design document, as a runtime object.
 *
 * A product builds one factory with its own identity (prefix, block namespace,
 * text domain -- the three answers that differ per product) and registers each
 * contract + renderer pair through it. Everything that would otherwise be
 * written three times per component is derived here once.
 */
final class ComponentFactory {

	private const PREFIX_PATTERN = '/^[a-z][a-z0-9_]*$/';
	private const NS_PATTERN     = '/^[a-z][a-z0-9-]*$/';

	/**
	 * Where ComponentScaffolder writes block metadata, under the product root.
	 *
	 * Named here because both halves have to agree: the scaffolder writes
	 * `blocks/<kebab>/block.json` and this factory registers from a blocks
	 * directory. If the two conventions drift, the written file is decoration --
	 * WordPress quietly falls back to the PHP arguments and nobody finds out
	 * until a block behaves like a different block.
	 */
	public const BLOCKS_DIRNAME = 'blocks';

	/**
	 * Shortcode / Elementor prefix, e.g. "rentiva".
	 *
	 * @var string
	 */
	private $prefix;

	/**
	 * Block namespace, e.g. "mhm-rentiva".
	 *
	 * @var string
	 */
	private $block_namespace;

	/**
	 * Product text domain, written into block.json.
	 *
	 * @var string
	 */
	private $text_domain;

	/**
	 * Directory holding <kebab>/block.json, or null when the product has none.
	 *
	 * @var string|null
	 */
	private $blocks_dir;

	/**
	 * Registered components, by slug.
	 *
	 * @var array<string, RegisteredComponent>
	 */
	private $registered = array();

	/**
	 * Build a factory for one product.
	 *
	 * Keys, all required: 'prefix' (^[a-z][a-z0-9_]*$) · 'block_namespace'
	 * (^[a-z][a-z0-9-]*$) · 'text_domain'. One optional: 'blocks_dir', the
	 * directory the scaffolder wrote `<kebab>/block.json` into -- usually
	 * `__DIR__ . '/' . ComponentFactory::BLOCKS_DIRNAME`. Given it, blocks are
	 * registered FROM their metadata, which is the only way core reads the file.
	 *
	 * @param array<string, mixed> $config Product identity; keys described above.
	 *
	 * @throws InvalidArgumentException When a key is missing or malformed. No
	 *                                  defaults: a default prefix would let two
	 *                                  products register the same shortcode tag,
	 *                                  and the second one would win silently.
	 */
	public function __construct( array $config ) {
		$prefix = $config['prefix'] ?? null;
		if ( ! is_string( $prefix ) || 1 !== preg_match( self::PREFIX_PATTERN, $prefix ) ) {
			throw new InvalidArgumentException( 'ComponentFactory: "prefix" must match ^[a-z][a-z0-9_]*$.' );
		}

		$block_namespace = $config['block_namespace'] ?? null;
		if ( ! is_string( $block_namespace ) || 1 !== preg_match( self::NS_PATTERN, $block_namespace ) ) {
			throw new InvalidArgumentException( 'ComponentFactory: "block_namespace" must match ^[a-z][a-z0-9-]*$.' );
		}

		$text_domain = $config['text_domain'] ?? null;
		if ( ! is_string( $text_domain ) || '' === $text_domain ) {
			throw new InvalidArgumentException( 'ComponentFactory: "text_domain" must be a non-empty string.' );
		}

		$blocks_dir = $config['blocks_dir'] ?? null;
		if ( null !== $blocks_dir && ( ! is_string( $blocks_dir ) || '' === $blocks_dir ) ) {
			throw new InvalidArgumentException( 'ComponentFactory: "blocks_dir" must be a non-empty string when given.' );
		}

		$this->prefix          = $prefix;
		$this->block_namespace = $block_namespace;
		$this->text_domain     = $text_domain;
		$this->blocks_dir      = $blocks_dir;
	}

	/**
	 * Register a component on every surface WordPress can host right now.
	 *
	 * Shortcode and block are registered immediately. The Elementor widget is
	 * registered on Elementor's own hook if Elementor ever loads, and simply
	 * never fires otherwise. The Layout adapter is returned for the product to
	 * hand to its LayoutContract.
	 *
	 * @param ComponentContract $contract Contract.
	 * @param ComponentRenderer $renderer Renderer.
	 * @return RegisteredComponent
	 *
	 * @throws InvalidArgumentException When the slug was already registered.
	 */
	public function register( ComponentContract $contract, ComponentRenderer $renderer ): RegisteredComponent {
		$slug = $contract->slug();
		if ( isset( $this->registered[ $slug ] ) ) {
			throw new InvalidArgumentException(
				esc_html( sprintf( 'ComponentFactory: "%s" is already registered.', $slug ) )
			);
		}

		$tag   = ShortcodeSurface::register( $contract, $renderer, $this->prefix );
		$block = BlockSurface::register( $contract, $renderer, $this->block_namespace, $this->text_domain, $this->blocks_dir );

		$prefix = $this->prefix;
		if ( function_exists( 'add_action' ) ) {
			add_action(
				'elementor/widgets/register',
				static function ( $manager ) use ( $contract, $renderer, $prefix ): void {
					$widget = ElementorSurface::widget( $contract, $renderer, $prefix );
					if ( null !== $widget && is_object( $manager ) && method_exists( $manager, 'register' ) ) {
						$manager->register( $widget );
					}
				}
			);
		}

		$component = new RegisteredComponent(
			$contract,
			$tag,
			$block,
			LayoutAdapterSurface::adapter( $contract, $renderer )
		);

		$this->registered[ $slug ] = $component;

		return $component;
	}

	/**
	 * The block.json for a contract, without registering anything.
	 *
	 * The scaffolder writes this to disk so the block editor can discover the
	 * block's metadata the way core expects.
	 *
	 * @param ComponentContract $contract Contract.
	 * @return array<string, mixed>
	 */
	public function block_json( ComponentContract $contract ): array {
		return BlockSurface::block_json( $contract, $this->block_namespace, $this->text_domain );
	}

	/**
	 * Every registered component's Layout adapter, keyed by slug -- exactly the
	 * "adapters" map LayoutContract takes.
	 *
	 * @return array<string, LayoutComponentAdapter>
	 */
	public function layout_adapters(): array {
		$adapters = array();
		foreach ( $this->registered as $slug => $component ) {
			$adapters[ $slug ] = $component->layout_adapter();
		}
		return $adapters;
	}

	/**
	 * A registered component, by slug.
	 *
	 * @param string $slug Contract slug.
	 * @return RegisteredComponent|null
	 */
	public function get( string $slug ): ?RegisteredComponent {
		return $this->registered[ $slug ] ?? null;
	}

	/**
	 * Product prefix.
	 *
	 * @return string
	 */
	public function prefix(): string {
		return $this->prefix;
	}
}
