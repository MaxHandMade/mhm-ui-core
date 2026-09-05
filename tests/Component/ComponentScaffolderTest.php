<?php
declare( strict_types = 1 );

namespace MHMUiCore\Tests\Component;

use MHMUiCore\Component\ComponentContract;
use MHMUiCore\Component\ComponentFactory;
use MHMUiCore\Component\ComponentRenderer;
use MHMUiCore\Component\ComponentScaffolder;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ComponentScaffolderTest extends TestCase {

	/** @var string */
	private $root;

	public static function setUpBeforeClass(): void {
		require_once __DIR__ . '/../Fixtures/wp-function-stubs.php';
	}

	protected function setUp(): void {
		$this->root = sys_get_temp_dir() . '/uicore-scaffold-' . bin2hex( random_bytes( 4 ) );
		mkdir( $this->root );
	}

	protected function tearDown(): void {
		$this->rm( $this->root );
	}

	private function rm( string $path ): void {
		if ( is_dir( $path ) ) {
			foreach ( (array) scandir( $path ) as $entry ) {
				if ( '.' !== $entry && '..' !== $entry ) {
					$this->rm( $path . '/' . $entry );
				}
			}
			rmdir( $path );
		} elseif ( file_exists( $path ) ) {
			unlink( $path );
		}
	}

	private function scaffolder(): ComponentScaffolder {
		$factory = new ComponentFactory( array( 'prefix' => 'pilot', 'block_namespace' => 'pilot', 'text_domain' => 'pilot-td' ) );
		return new ComponentScaffolder( $factory, 'Pilot\\Components' );
	}

	private function hero(): ComponentContract {
		return new ComponentContract( require __DIR__ . '/../Fixtures/hero-contract.php' );
	}

	public function test_it_writes_the_four_artefacts_and_refuses_to_overwrite(): void {
		$written = $this->scaffolder()->write( $this->hero(), $this->root );

		self::assertCount( 4, $written );
		self::assertFileExists( $this->root . '/contracts/hero.php' );
		self::assertFileExists( $this->root . '/src/Components/HeroRenderer.php' );
		self::assertFileExists( $this->root . '/blocks/hero/block.json' );
		self::assertFileExists( $this->root . '/tests/Components/HeroContractTest.php' );

		$this->expectException( RuntimeException::class );
		$this->scaffolder()->write( $this->hero(), $this->root );
	}

	public function test_the_pilot_fixtures_metadata_is_what_the_factory_generates(): void {
		/*
		 * The integration test asks real WordPress what it registered, and since
		 * the metadata file now OWNS the block, every answer it gives comes from
		 * that committed file. A file that quietly drifts from the contract would
		 * turn those assertions into assertions about a stale artefact -- green,
		 * and about nothing.
		 */
		$factory  = new ComponentFactory(
			array(
				'prefix'          => 'pilot',
				'block_namespace' => 'pilot',
				'text_domain'     => 'pilot',
			)
		);
		$contract = new ComponentContract( require __DIR__ . '/../Fixtures/hero-contract.php' );
		$path     = __DIR__ . '/../Integration/fixtures/pilot/free-core/blocks/hero/block.json';

		self::assertFileExists( $path );
		self::assertSame(
			$factory->block_json( $contract ),
			json_decode( (string) file_get_contents( $path ), true ),
			'regenerate the pilot fixture: wp mhm-ui make:component writes this file'
		);
	}

	public function test_the_factory_reads_metadata_where_the_scaffolder_writes_it(): void {
		/*
		 * The scaffolder writes blocks/<kebab>/block.json and the factory registers
		 * from a blocks directory. If those two conventions ever drift, the written
		 * file is decoration: WordPress falls back to the PHP arguments and nobody
		 * finds out until a block behaves like a different block.
		 */
		$contract = $this->hero();
		$files    = $this->scaffolder()->files( $contract );

		self::assertArrayHasKey( 'blocks/' . $contract->kebab() . '/block.json', $files );
		self::assertSame( 'blocks', ComponentFactory::BLOCKS_DIRNAME );
	}

	public function test_the_written_contract_round_trips_and_the_block_json_matches_the_factory(): void {
		$this->scaffolder()->write( $this->hero(), $this->root );

		$reloaded = new ComponentContract( require $this->root . '/contracts/hero.php' );
		self::assertSame( $this->hero()->defaults(), $reloaded->defaults() );
		self::assertSame( $this->hero()->settings(), $reloaded->settings() );
		self::assertSame( array( 'items' ), $reloaded->data_keys() );

		$factory = new ComponentFactory( array( 'prefix' => 'pilot', 'block_namespace' => 'pilot', 'text_domain' => 'pilot-td' ) );
		$json    = json_decode( (string) file_get_contents( $this->root . '/blocks/hero/block.json' ), true );
		self::assertSame( $factory->block_json( $this->hero() ), $json );
	}

	public function test_the_written_renderer_is_valid_php_that_implements_the_interface(): void {
		$this->scaffolder()->write( $this->hero(), $this->root );

		require $this->root . '/src/Components/HeroRenderer.php';
		$renderer = new \Pilot\Components\HeroRenderer();
		self::assertInstanceOf( ComponentRenderer::class, $renderer );

		$html = $renderer->render( $this->hero()->defaults(), array( 'surface' => 'block', 'instance_id' => 'i1', 'content' => '' ) );
		self::assertStringContainsString( 'class="pilot-hero"', $html );
		self::assertStringContainsString( 'data-instance="i1"', $html );
	}

	public function test_the_written_test_pins_the_defaults(): void {
		$files = $this->scaffolder()->files( $this->hero() );
		$test  = $files['tests/Components/HeroContractTest.php'];

		self::assertStringContainsString( "'showButton' => true", $test );
		self::assertStringContainsString( "'columns' => 3", $test );
		self::assertStringContainsString( "'layout' => 'grid'", $test );
	}

	public function test_class_name(): void {
		self::assertSame( 'FeaturedVehicles', ComponentScaffolder::class_name( 'featured_vehicles' ) );
	}
}
