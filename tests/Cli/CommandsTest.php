<?php
declare( strict_types = 1 );

namespace MHMUiCore\Tests\Cli;

use MHMUiCore\Cli\CheckPurityCommand;
use MHMUiCore\Cli\MakeComponentCommand;
use PHPUnit\Framework\TestCase;
use WP_CLI;

final class CommandsTest extends TestCase {

	/** @var string */
	private $root;

	public static function setUpBeforeClass(): void {
		require_once __DIR__ . '/../Fixtures/wp-function-stubs.php';
		require_once __DIR__ . '/../Fixtures/wp-cli-stubs.php';
	}

	protected function setUp(): void {
		WP_CLI::$output = array();
		$this->root     = sys_get_temp_dir() . '/uicore-cli-' . bin2hex( random_bytes( 4 ) );
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

	private function levels(): array {
		return array_column( WP_CLI::$output, 0 );
	}

	public function test_make_component_writes_four_files_and_reports_success(): void {
		( new MakeComponentCommand() )(
			array( 'hero' ),
			array(
				'prefix'          => 'pilot',
				'block-namespace' => 'pilot',
				'text-domain'     => 'pilot-td',
				'php-namespace'   => 'Pilot\\Components',
				'dir'             => $this->root,
			)
		);

		self::assertSame( 4, count( array_filter( $this->levels(), static fn( $l ) => 'log' === $l ) ) );
		self::assertContains( 'success', $this->levels() );
		self::assertNotContains( 'error', $this->levels() );
		self::assertFileExists( $this->root . '/blocks/hero/block.json' );
	}

	public function test_make_component_dry_run_writes_nothing(): void {
		( new MakeComponentCommand() )(
			array( 'hero' ),
			array( 'prefix' => 'pilot', 'block-namespace' => 'pilot', 'text-domain' => 'pilot-td', 'php-namespace' => 'P', 'dir' => $this->root, 'dry-run' => true )
		);
		self::assertFileDoesNotExist( $this->root . '/contracts/hero.php' );
		self::assertNotContains( 'error', $this->levels() );
	}

	public function test_make_component_with_a_bad_identity_errors_instead_of_writing(): void {
		( new MakeComponentCommand() )( array( 'hero' ), array( 'prefix' => 'Bad', 'dir' => $this->root ) );
		self::assertContains( 'error', $this->levels() );
		self::assertFileDoesNotExist( $this->root . '/contracts/hero.php' );
	}

	public function test_check_purity_reports_findings_and_fails(): void {
		mkdir( $this->root . '/src' );
		file_put_contents( $this->root . '/src/L.php', "<?php\nfunction validate_license() {}\n" );

		( new CheckPurityCommand() )( array( $this->root ) );

		self::assertContains( 'error', $this->levels() );
		self::assertStringContainsString( 'license_code', implode( "\n", array_column( WP_CLI::$output, 1 ) ) );
	}

	public function test_check_purity_passes_a_clean_tree(): void {
		mkdir( $this->root . '/src' );
		file_put_contents( $this->root . '/src/C.php', "<?php\nfunction ok() { return 1; }\n" );

		( new CheckPurityCommand() )( array( $this->root ) );

		self::assertContains( 'success', $this->levels() );
		self::assertNotContains( 'error', $this->levels() );
	}

	public function test_check_purity_refuses_a_missing_directory(): void {
		( new CheckPurityCommand() )( array( $this->root . '/nope' ) );
		self::assertContains( 'error', $this->levels() );
	}
}
