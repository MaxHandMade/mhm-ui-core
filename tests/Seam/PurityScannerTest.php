<?php
declare( strict_types = 1 );

namespace MHMUiCore\Tests\Seam;

use MHMUiCore\Seam\PurityScanner;
use PHPUnit\Framework\TestCase;

final class PurityScannerTest extends TestCase {

	/** @var string */
	private $root;

	protected function setUp(): void {
		$this->root = sys_get_temp_dir() . '/uicore-purity-' . bin2hex( random_bytes( 4 ) );
		mkdir( $this->root . '/src', 0755, true );
		mkdir( $this->root . '/vendor/x', 0755, true );
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

	public function test_the_scanner_passes_its_own_self_test(): void {
		self::assertSame( array(), ( new PurityScanner() )->self_test() );
	}

	public function test_each_forbidden_class_is_found_with_file_and_line(): void {
		file_put_contents( $this->root . '/src/Http.php', "<?php\n\n\$r = wp_remote_post( \$url );\n" );
		file_put_contents( $this->root . '/src/Lic.php', "<?php\nclass L { public function activate_license() {} }\n" );
		file_put_contents( $this->root . '/src/Lim.php', "<?php\nif ( \$n >= \$this->free_limit ) { return; }\n" );
		// vendor/ is never the free core's own code.
		file_put_contents( $this->root . '/vendor/x/y.php', "<?php\nwp_remote_get( 'x' );\n" );

		$hits = ( new PurityScanner() )->scan( $this->root );

		$by_class = array();
		foreach ( $hits as $hit ) {
			$by_class[ $hit['class'] ][] = basename( $hit['file'] ) . ':' . $hit['line'];
		}

		self::assertSame( array( 'Http.php:3' ), $by_class[ PurityScanner::CLASS_HTTP ] );
		self::assertSame( array( 'Lic.php:2' ), $by_class[ PurityScanner::CLASS_LICENSE ] );
		self::assertSame( array( 'Lim.php:2' ), $by_class[ PurityScanner::CLASS_LIMIT ] );
		self::assertCount( 3, $hits );
	}

	public function test_a_clean_core_produces_no_findings(): void {
		file_put_contents(
			$this->root . '/src/Clean.php',
			"<?php\n// wp_remote_get in a comment is a mention, not a call.\n"
			. "function keys() { return get_option( 'licensed_keys' ); }\n"
			. "\$max_items = 20; // a real pagination size, not a tier cap\n"
		);
		self::assertSame( array(), ( new PurityScanner() )->scan( $this->root ) );
	}

	public function test_a_mention_without_a_call_is_not_http(): void {
		$hits = ( new PurityScanner() )->scan_source( "<?php\n\$name = 'wp_remote_get';\nif ( function_exists( 'wp_remote_get' ) ) {}\n" );
		self::assertSame( array(), $hits );
	}

	public function test_a_missing_directory_yields_no_findings_not_a_crash(): void {
		self::assertSame( array(), ( new PurityScanner() )->scan( $this->root . '/does-not-exist' ) );
	}
}
