<?php

declare(strict_types=1);

namespace MHMUiCore\Tests;

use PHPUnit\Framework\TestCase;

/**
 * G3' -- the binding gate.
 *
 * The facade (bootstrap.php functions) is chosen by HIGHEST version; classes
 * under src/ were previously assumed to be PSR-4 autoloaded, which measured
 * false 2026-08-30: neither consumer loads vendor/autoload.php at all. The
 * winning bootstrap must therefore bind src/ itself, which also makes
 * facade/engine version skew impossible -- they are the same copy by
 * construction.
 *
 * WHY THIS RUNS IN A SUBPROCESS, NOT THE NORMAL HARNESS
 *
 * tests/bootstrap.php requires vendor/autoload.php, which already maps
 * MHMUiCore\ to src/ via Composer's own PSR-4 autoloader. A test that boots
 * bootstrap.php inside THIS process and then does class_exists() would pass
 * whether or not bootstrap.php registers anything at all -- Composer's
 * autoloader would resolve the class regardless, proving nothing about the
 * loader this task adds. Production has no such autoloader in scope (that is
 * the whole reason this task exists), so the test recreates that condition:
 * a bare `php` process, started fresh, with only the copied bootstrap.php
 * required and no composer autoload anywhere on its include path.
 *
 * The probe class is MHMUiCore\Layout\LayoutEngine, the package's own facade
 * target -- not a synthetic stand-in. Twelve classes now live under src/, so
 * setUp() copies the real src/ tree into the temp copy instead of writing a
 * single fabricated class file: the spec's predicate is "the winning bootstrap
 * resolves ITS OWN src/", and a synthetic probe class proves only that the
 * loader can find A file, not that it resolves the package's actual classes.
 */
final class AutoloaderTest extends TestCase {

	/**
	 * Absolute path to the temp copy built for the running test, cleaned up in
	 * tearDown(). Empty until setUp() runs.
	 *
	 * @var string
	 */
	private string $copy_dir = '';

	protected function setUp(): void {
		parent::setUp();

		$this->copy_dir = sys_get_temp_dir() . '/uicore-autoloader-' . uniqid( '', true );

		mkdir( $this->copy_dir, 0777, true );

		copy( dirname( __DIR__ ) . '/bootstrap.php', $this->copy_dir . '/bootstrap.php' );

		self::copy_directory( dirname( __DIR__ ) . '/src', $this->copy_dir . '/src' );
	}

	protected function tearDown(): void {
		self::remove_directory( $this->copy_dir );
		$this->copy_dir = '';

		parent::tearDown();
	}

	public function test_the_registered_loader_resolves_the_booted_copys_own_class(): void {
		$result = self::boot_copy_and_probe( $this->copy_dir, 'MHMUiCore\\Layout\\LayoutEngine' );

		$this->assertSame(
			0,
			$result['exit_code'],
			"Subprocess did not exit cleanly. stderr: {$result['stderr']}"
		);
		$this->assertSame(
			'',
			$result['stderr'],
			"Subprocess wrote to stderr -- a fatal or warning that must not happen: {$result['stderr']}"
		);
		$this->assertSame(
			'true',
			trim( $result['stdout'] ),
			'MHMUiCore\\Layout\\LayoutEngine must resolve through the loader bootstrap.php registers, ' .
			'with no Composer autoloader in scope: the winning copy must bind its own src/.'
		);
	}

	public function test_the_registered_loader_ignores_foreign_namespaces(): void {
		// A greedy autoloader would break every consumer that already has its
		// own for a different namespace. It must return silently -- and, above
		// all, must not fatal -- for anything not MHMUiCore\.
		$result = self::boot_copy_and_probe( $this->copy_dir, 'NotOurs\\Whatever' );

		$this->assertSame(
			0,
			$result['exit_code'],
			"Subprocess must not fatal on a foreign namespace. stderr: {$result['stderr']}"
		);
		$this->assertSame( '', $result['stderr'] );
		$this->assertSame( 'false', trim( $result['stdout'] ) );
	}

	/**
	 * Boot a copied bootstrap.php in a fresh `php` subprocess -- one with no
	 * Composer autoloader anywhere in scope -- and report whether it resolved
	 * $class_name.
	 *
	 * The generated one-liner is assembled with var_export() rather than string
	 * concatenation so that the copy's (OS-dependent, backslash-bearing on
	 * Windows) path and the class name are embedded as safely-escaped PHP
	 * literals, not shell-escaped: the subprocess is started via the array form
	 * of proc_open(), which bypasses the shell entirely.
	 *
	 * @param string $copy_dir   Absolute path to the temp copy built by setUp().
	 * @param string $class_name Fully-qualified class name to probe for.
	 * @return array{stdout: string, stderr: string, exit_code: int}
	 */
	private static function boot_copy_and_probe( string $copy_dir, string $class_name ): array {
		$code = sprintf(
			'define(%s, %s); require %s; var_export( class_exists( %s ) );',
			var_export( 'ABSPATH', true ),
			var_export( '/', true ),
			var_export( $copy_dir . '/bootstrap.php', true ),
			var_export( $class_name, true )
		);

		$descriptors = array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);

		$process = proc_open( array( PHP_BINARY, '-r', $code ), $descriptors, $pipes );

		self::assertIsResource( $process, 'Failed to start the PHP subprocess for the loader probe.' );

		fclose( $pipes[0] );
		$stdout = stream_get_contents( $pipes[1] );
		$stderr = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );

		$exit_code = proc_close( $process );

		return array(
			'stdout'    => false === $stdout ? '' : $stdout,
			'stderr'    => false === $stderr ? '' : $stderr,
			'exit_code' => $exit_code,
		);
	}

	/**
	 * Recursively copy the package's real src/ tree into the temp copy, so the
	 * subprocess resolves the same classes production does -- not a synthetic
	 * stand-in.
	 *
	 * @param string $source Absolute path to the real src/ directory.
	 * @param string $dest   Absolute path to create and populate.
	 */
	private static function copy_directory( string $source, string $dest ): void {
		mkdir( $dest, 0777, true );

		$items = scandir( $source );
		if ( false === $items ) {
			return;
		}

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$source_path = $source . '/' . $item;
			$dest_path   = $dest . '/' . $item;

			if ( is_dir( $source_path ) ) {
				self::copy_directory( $source_path, $dest_path );
			} else {
				copy( $source_path, $dest_path );
			}
		}
	}

	/**
	 * Recursively remove a temp directory tree created by setUp().
	 *
	 * @param string $dir Absolute path.
	 */
	private static function remove_directory( string $dir ): void {
		if ( '' === $dir || ! is_dir( $dir ) ) {
			return;
		}

		$items = scandir( $dir );
		if ( false === $items ) {
			return;
		}

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$path = $dir . '/' . $item;

			if ( is_dir( $path ) ) {
				self::remove_directory( $path );
			} else {
				unlink( $path );
			}
		}

		rmdir( $dir );
	}
}
