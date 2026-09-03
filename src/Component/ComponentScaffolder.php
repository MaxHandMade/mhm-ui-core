<?php
/**
 * Writes the files a new component starts from.
 *
 * @package MHMUiCore\Component
 */

declare(strict_types=1);

namespace MHMUiCore\Component;

use InvalidArgumentException;
use RuntimeException;

/**
 * Phase 4 of the design document: `wp mhm-ui make:component`, minus WP-CLI.
 *
 * Pure PHP on purpose so the unit suite can prove what gets written. The CLI
 * command is a thin wrapper that only parses flags and prints.
 *
 * Four files per component, mirroring the artefacts the design document says
 * the factory derives: the contract (declaration), the renderer (the only
 * hand-written thing), block.json (so the editor discovers the block), and a
 * test skeleton pinning the contract's defaults.
 */
final class ComponentScaffolder {

	/**
	 * Factory carrying the product identity.
	 *
	 * @var ComponentFactory
	 */
	private $factory;

	/**
	 * PHP namespace for the generated renderer class, e.g. "MHMRentiva\Components".
	 *
	 * @var string
	 */
	private $php_namespace;

	/**
	 * Bind.
	 *
	 * @param ComponentFactory $factory       Product factory.
	 * @param string           $php_namespace Namespace for generated classes.
	 *
	 * @throws InvalidArgumentException When the namespace is malformed.
	 */
	public function __construct( ComponentFactory $factory, string $php_namespace ) {
		if ( 1 !== preg_match( '/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $php_namespace ) ) {
			throw new InvalidArgumentException( 'ComponentScaffolder: PHP namespace is malformed.' );
		}
		$this->factory       = $factory;
		$this->php_namespace = $php_namespace;
	}

	/**
	 * The files a contract produces, as path => contents, relative to the product root.
	 *
	 * @param ComponentContract $contract Contract.
	 * @return array<string, string>
	 */
	public function files( ComponentContract $contract ): array {
		$slug  = $contract->slug();
		$class = self::class_name( $slug );
		$kebab = $contract->kebab();

		return array(
			"contracts/{$slug}.php"                     => $this->contract_file( $contract ),
			"src/Components/{$class}Renderer.php"       => $this->renderer_file( $contract, $class ),
			"blocks/{$kebab}/block.json"                => (string) json_encode( $this->factory->block_json( $contract ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n", // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- the scaffolder also runs outside WordPress (unit suite, CI), where wp_json_encode() does not exist.
			"tests/Components/{$class}ContractTest.php" => $this->test_file( $contract, $class ),
		);
	}

	/**
	 * Write the files under a root. Refuses to overwrite.
	 *
	 * @param ComponentContract $contract Contract.
	 * @param string            $root     Product root directory.
	 * @return list<string> Absolute paths written.
	 *
	 * @throws RuntimeException When a target exists or cannot be written. Loud:
	 *                          a scaffold that silently overwrote a hand-written
	 *                          renderer would destroy the one thing it cannot
	 *                          regenerate.
	 */
	public function write( ComponentContract $contract, string $root ): array {
		$root    = rtrim( $root, '/\\' );
		$written = array();

		$files = $this->files( $contract );
		foreach ( array_keys( $files ) as $relative ) {
			if ( file_exists( $root . '/' . $relative ) ) {
				throw new RuntimeException( esc_html( "ComponentScaffolder: refusing to overwrite {$relative}." ) );
			}
		}

		foreach ( $files as $relative => $contents ) {
			$path = $root . '/' . $relative;
			$dir  = dirname( $path );
			if ( ! is_dir( $dir ) && ! mkdir( $dir, 0755, true ) && ! is_dir( $dir ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- scaffolding a developer source tree, not a site upload; WP_Filesystem is for the latter.
				throw new RuntimeException( esc_html( "ComponentScaffolder: cannot create {$dir}." ) );
			}
			if ( false === file_put_contents( $path, $contents ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- scaffolding a developer's source tree, not a site's uploads.
				throw new RuntimeException( esc_html( "ComponentScaffolder: cannot write {$path}." ) );
			}
			$written[] = $path;
		}

		return $written;
	}

	/**
	 * StudlyCase class name from a slug.
	 *
	 * @param string $slug Contract slug.
	 * @return string
	 */
	public static function class_name( string $slug ): string {
		return str_replace( ' ', '', ucwords( str_replace( '_', ' ', $slug ) ) );
	}

	/**
	 * The contracts/{slug}.php file -- the declaration, as a PHP array file.
	 *
	 * @param ComponentContract $contract Contract.
	 * @return string
	 */
	private function contract_file( ComponentContract $contract ): string {
		$settings = array();
		foreach ( $contract->settings() as $name => $setting ) {
			$entry = array(
				'type'    => $setting['type'],
				'default' => $setting['default'],
				'label'   => $setting['label'],
			);
			if ( array() !== $setting['enum'] ) {
				$entry['enum'] = $setting['enum'];
			}
			$settings[ $name ] = $entry;
		}

		$declaration = array(
			'slug'     => $contract->slug(),
			'title'    => $contract->title(),
			'settings' => $settings,
			'data'     => $contract->data_keys(),
			'block'    => $contract->block_hints(),
		);

		return "<?php\n"
			. "/**\n"
			. " * Component contract: {$contract->slug()}.\n"
			. " *\n"
			. " * Every part of the design is FIXED (lives in the renderer template), DATA\n"
			. " * (the renderer queries it; listed under 'data') or a SETTING (the user\n"
			. " * chooses it; declared under 'settings'). The shortcode attributes, block\n"
			. " * attributes and Elementor controls are all derived from 'settings'.\n"
			. " */\n\n"
			. "declare(strict_types=1);\n\n"
			. 'return ' . self::export( $declaration, 0 ) . ";\n";
	}

	/**
	 * The src/Components/{Class}Renderer.php file -- the one hand-written file, started.
	 *
	 * @param ComponentContract $contract   Contract.
	 * @param string            $class_stem Class name stem.
	 * @return string
	 */
	private function renderer_file( ComponentContract $contract, string $class_stem ): string {
		$lines = array();
		foreach ( array_keys( $contract->settings() ) as $name ) {
			$lines[] = "\t\t// \$settings['{$name}']";
		}
		foreach ( $contract->data_keys() as $key ) {
			$lines[] = "\t\t// \$data['{$key}'] -- query it here";
		}
		$hints = implode( "\n", $lines );

		return "<?php\n"
			. "/**\n"
			. " * Renderer for the {$contract->slug()} component.\n"
			. " *\n"
			. " * This is the only hand-written surface. Settings arrive typed by the\n"
			. " * contract; the four surfaces (shortcode, block, Elementor, Layout) all call\n"
			. " * this one method, so keep it the single source of the markup.\n"
			. " */\n\n"
			. "declare(strict_types=1);\n\n"
			. "namespace {$this->php_namespace};\n\n"
			. "use MHMUiCore\\Component\\ComponentRenderer;\n\n"
			. "final class {$class_stem}Renderer implements ComponentRenderer {\n\n"
			. "\t/**\n"
			. "\t * @param array<string, mixed> \$settings Declared settings, typed.\n"
			. "\t * @param array<string, mixed> \$context  surface / instance_id / content.\n"
			. "\t */\n"
			. "\tpublic function render( array \$settings, array \$context ): string {\n"
			. "{$hints}\n"
			. "\t\t\$id = esc_attr( (string) \$context['instance_id'] );\n\n"
			. "\t\treturn '<div class=\"{$this->factory->prefix()}-{$contract->kebab()}\" data-instance=\"' . \$id . '\"></div>';\n"
			. "\t}\n"
			. "}\n";
	}

	/**
	 * The tests/Components/{Class}ContractTest.php file -- pins the declared defaults.
	 *
	 * @param ComponentContract $contract   Contract.
	 * @param string            $class_stem Class name stem.
	 * @return string
	 */
	private function test_file( ComponentContract $contract, string $class_stem ): string {
		return "<?php\n"
			. "declare(strict_types=1);\n\n"
			. "use MHMUiCore\\Component\\ComponentContract;\n"
			. "use PHPUnit\\Framework\\TestCase;\n\n"
			. "final class {$class_stem}ContractTest extends TestCase {\n\n"
			. "\tpublic function test_defaults_are_the_declared_ones(): void {\n"
			. "\t\t\$contract = new ComponentContract( require __DIR__ . '/../../contracts/{$contract->slug()}.php' );\n"
			. "\t\tself::assertSame( " . self::export( $contract->defaults(), 2 ) . ", \$contract->sanitize( array() ) );\n"
			. "\t}\n"
			. "}\n";
	}

	/**
	 * Export a value in WordPress style: array( ... ), tabs, trailing commas.
	 *
	 * @param mixed $value  Value.
	 * @param int   $indent Current indent level.
	 * @return string
	 */
	private static function export( $value, int $indent ): string {
		if ( is_array( $value ) ) {
			if ( array() === $value ) {
				return 'array()';
			}
			$pad   = str_repeat( "\t", $indent + 1 );
			$lines = array();
			$list  = array_keys( $value ) === range( 0, count( $value ) - 1 );
			foreach ( $value as $k => $v ) {
				$key     = $list ? '' : self::export( $k, 0 ) . ' => ';
				$lines[] = $pad . $key . self::export( $v, $indent + 1 ) . ',';
			}
			return "array(\n" . implode( "\n", $lines ) . "\n" . str_repeat( "\t", $indent ) . ')';
		}
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}
		if ( null === $value ) {
			return 'null';
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}
		return "'" . str_replace( array( '\\', "'" ), array( '\\\\', "\\'" ), (string) $value ) . "'";
	}
}
