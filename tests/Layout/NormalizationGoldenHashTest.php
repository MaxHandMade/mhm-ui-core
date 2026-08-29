<?php

declare(strict_types=1);

namespace MHMUiCore\Tests\Layout;

use MHMUiCore\Layout\Normalization;
use PHPUnit\Framework\TestCase;

/**
 * G6 -- the golden hash gate.
 *
 * The consumer (mhm-rentiva) stores sha256(json(normalize(manifest))) in
 * WordPress post meta, and its rollback path recomputes that hash with
 * CURRENT code, hard-failing with "manifest data corruption detected" on any
 * mismatch. Now that Normalization ships from an independently versioned
 * package, a harmless-looking change to normalize() -- reordering a ksort(),
 * touching field order, changing scalar coercion -- silently makes every
 * pre-existing stored row un-rollbackable. Nothing else catches that: the
 * consumer's own rollback test creates its own data in-process, so it agrees
 * with whatever normalize() does today and stays green forever regardless of
 * what "today" means.
 *
 * THE LITERAL BELOW IS DATA-FORMAT LAW, NOT A TEST FIXTURE.
 *
 * It was derived from the PRE-MOVE consumer class
 * (MHMRentiva\Layout\Versioning\LayoutNormalization::normalize()) against
 * tests/fixtures/multi-page-manifest.json in mhm-rentiva, run through PHP's
 * CLI directly -- never from this ported class. Do not regenerate it from
 * Normalization::normalize()'s own output: that would turn this gate into a
 * mirror of the code it exists to police, and it would stop being able to
 * catch anything at all. If a future change makes this assertion fail, the
 * change is a BREAKING data-format change that needs a migration path for
 * already-stored rows -- it does not get a new literal pasted over it.
 */
final class NormalizationGoldenHashTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		require_once __DIR__ . '/../Fixtures/wp-function-stubs.php';
	}

	private const GOLDEN_SHA256 = '0b08de910c887301b3dea5781b6a8cdf4e42082b4a853a73f792e5aac5b4e304';

	public function test_normalization_is_byte_stable_against_the_stored_contract(): void {
		$manifest = json_decode( (string) file_get_contents( __DIR__ . '/../Fixtures/manifests/golden.json' ), true );

		$this->assertIsArray( $manifest );
		$this->assertSame(
			self::GOLDEN_SHA256,
			hash( 'sha256', (string) wp_json_encode( Normalization::normalize( $manifest ) ) )
		);
	}
}
