<?php

declare(strict_types=1);

namespace MHMUiCore\Tests;

use MHMUiCore\VersionSelector;
use PHPUnit\Framework\TestCase;

final class VersionSelectorTest extends TestCase {

	public function test_returns_null_when_no_candidates(): void {
		$this->assertNull( VersionSelector::select( array() ) );
	}

	public function test_returns_the_only_candidate(): void {
		$this->assertSame(
			'/a/bootstrap.php',
			VersionSelector::select( array( '1.0.0' => '/a/bootstrap.php' ) )
		);
	}

	public function test_returns_highest_version_regardless_of_registration_order(): void {
		$candidates = array(
			'1.2.0'  => '/b/bootstrap.php',
			'1.10.0' => '/c/bootstrap.php',
			'1.9.0'  => '/a/bootstrap.php',
		);

		$this->assertSame( '/c/bootstrap.php', VersionSelector::select( $candidates ) );
	}

	public function test_compares_semantically_not_lexically(): void {
		// Lexical string sort would pick "1.9.0" over "1.10.0". Semver must not.
		$candidates = array(
			'1.9.0'  => '/old/bootstrap.php',
			'1.10.0' => '/new/bootstrap.php',
		);

		$this->assertSame( '/new/bootstrap.php', VersionSelector::select( $candidates ) );
	}

	public function test_prerelease_loses_to_stable(): void {
		$candidates = array(
			'2.0.0-beta.1' => '/beta/bootstrap.php',
			'2.0.0'        => '/stable/bootstrap.php',
		);

		$this->assertSame( '/stable/bootstrap.php', VersionSelector::select( $candidates ) );
	}
}
