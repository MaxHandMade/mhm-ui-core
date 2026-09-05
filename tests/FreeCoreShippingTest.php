<?php
declare( strict_types = 1 );

namespace MHMUiCore\Tests;

use PHPUnit\Framework\TestCase;

/**
 * What a WordPress.org free core may carry when it bundles this package.
 *
 * This package's third responsibility is the free-core/Pro seam, and an
 * independent compliance audit of the first product that tried to bundle it
 * found the package's own shipped surface was not free-core safe:
 *
 *   - `bootstrap.php` registered its WP-CLI commands on the existence of WP_CLI
 *     rather than the existence of the command classes, so a consumer that
 *     excluded `src/Cli/` from its ZIP got `Error: Callable
 *     "MHMUiCore\Cli\MakeComponentCommand" does not exist` out of EVERY `wp`
 *     command on the site -- `wp plugin check` included, which is the tool the
 *     house standard requires before a WordPress.org submission.
 *
 *   - The one stylesheet a consumer enqueues carried `.mhmui-pro-lock`, so a
 *     free core shipped a "hide this unless Pro" rule to a reviewer who greps
 *     the ZIP. The rule is real and Pro products need it; a free core's
 *     stylesheet is simply not where it belongs.
 *
 * Both are about what LEAVES this repository, which is why they are pinned here
 * rather than left to each consumer to rediscover.
 */
final class FreeCoreShippingTest extends TestCase {

	/** @var list<string> */
	private $temp = array();

	protected function tearDown(): void {
		foreach ( $this->temp as $dir ) {
			$this->rm( $dir );
		}
		$this->temp = array();
		parent::tearDown();
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

	/**
	 * A copy of this package's shipped tree, optionally with a directory removed.
	 *
	 * @param string|null $without Path under the package root to delete.
	 * @return string The copy's root.
	 */
	private function package_copy( ?string $without = null ): string {
		$root = dirname( __DIR__ );
		$dest = sys_get_temp_dir() . '/uicore-ship-' . bin2hex( random_bytes( 4 ) );
		mkdir( $dest, 0755, true );
		$this->temp[] = $dest;

		foreach ( array( 'bootstrap.php', 'register.php' ) as $file ) {
			copy( $root . '/' . $file, $dest . '/' . $file );
		}

		$this->copy_tree( $root . '/src', $dest . '/src' );

		if ( null !== $without ) {
			$this->rm( $dest . '/' . $without );
		}

		return $dest;
	}

	private function copy_tree( string $from, string $to ): void {
		mkdir( $to, 0755, true );
		foreach ( (array) scandir( $from ) as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$source = $from . '/' . $entry;
			if ( is_dir( $source ) ) {
				$this->copy_tree( $source, $to . '/' . $entry );
				continue;
			}
			copy( $source, $to . '/' . $entry );
		}
	}

	/**
	 * Load a package copy under a WP-CLI stub and report what it registered.
	 *
	 * The stub answers the way core does: WP_CLI::add_command() refuses a class
	 * name that does not exist. Recording the call rather than throwing lets the
	 * test say WHICH command was offered, not merely that something broke.
	 *
	 * @param string $package Package copy root.
	 * @return array{code:int, lines:list<string>}
	 */
	private function boot_under_wp_cli( string $package ): array {
		$script = sys_get_temp_dir() . '/uicore-cli-probe-' . bin2hex( random_bytes( 4 ) ) . '.php';
		$this->temp[] = $script;

		file_put_contents(
			$script,
			'<?php' . "\n"
				. "define( 'ABSPATH', sys_get_temp_dir() . '/' );\n"
				. "define( 'WP_CLI', true );\n"
				. 'require ' . var_export( dirname( __DIR__ ) . '/tests/Fixtures/wp-function-stubs.php', true ) . ";\n"
				. "class WP_CLI {\n"
				. "\tpublic static function add_command( \$name, \$callable ) {\n"
				. "\t\techo 'ADDED:', \$name, ':', ( is_string( \$callable ) && class_exists( \$callable ) ) ? 'exists' : 'MISSING', \"\\n\";\n"
				. "\t}\n"
				. "}\n"
				. 'require ' . var_export( $package . '/bootstrap.php', true ) . ";\n"
				. "echo \"BOOTED\\n\";\n"
		);

		$lines = array();
		$code  = 0;
		exec( 'php ' . escapeshellarg( $script ) . ' 2>&1', $lines, $code );

		return array(
			'code'  => $code,
			'lines' => $lines,
		);
	}

	public function test_a_consumer_that_leaves_the_cli_out_still_gets_a_working_wp_cli(): void {
		$result = $this->boot_under_wp_cli( $this->package_copy( 'src/Cli' ) );

		self::assertSame( 0, $result['code'], implode( "\n", $result['lines'] ) );
		self::assertContains( 'BOOTED', $result['lines'] );
		self::assertSame(
			array(),
			array_values( array_filter( $result['lines'], static fn( string $l ): bool => str_starts_with( $l, 'ADDED:' ) ) ),
			'a command whose class is not there must not be offered to WP-CLI at all'
		);
	}

	public function test_the_commands_are_still_registered_when_they_are_there(): void {
		$result = $this->boot_under_wp_cli( $this->package_copy() );

		self::assertSame( 0, $result['code'], implode( "\n", $result['lines'] ) );

		$added = array_values( array_filter( $result['lines'], static fn( string $l ): bool => str_starts_with( $l, 'ADDED:' ) ) );

		self::assertCount( 2, $added, 'the guard must not cost the commands their registration' );
		foreach ( $added as $line ) {
			self::assertStringEndsWith( ':exists', $line );
		}
	}

	public function test_the_stylesheet_a_free_core_enqueues_carries_no_pro_lock(): void {
		$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/react/admin.css' );

		self::assertStringNotContainsString(
			'pro-lock',
			$css,
			'a WordPress.org reviewer greps the ZIP; a "hide this unless Pro" rule in the free core\'s stylesheet reads as crippleware'
		);
	}

	public function test_the_pro_lock_rule_still_exists_for_the_products_that_want_it(): void {
		$path = dirname( __DIR__ ) . '/assets/react/pro.css';

		self::assertFileExists( $path, 'the rule is not deleted, it is moved: Pro products still need it' );
		self::assertStringContainsString( '.mhmui-pro-lock', (string) file_get_contents( $path ) );
	}
}
