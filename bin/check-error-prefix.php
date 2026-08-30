<?php
/**
 * G1 -- the prefix gate.
 *
 * Two assertions, because the obvious one is only half the promise:
 *   (a) every code MATCHES ^zzz_[a-z_]+$ -- catches a code written with NO
 *       prefix at all (e.g. 'unknown_component'), the likelier slip when
 *       porting eleven literals, which assertion (b) alone would wave through;
 *   (b) no product prefix leaks -- catches a hardcoded 'mhmrentiva_...'.
 * A third checks the message stays empty: the engine's only currency is the
 * code plus $data, and a message that sneaks back in is exactly the defect
 * this port exists to close (see BlueprintValidator's class docblock).
 *
 * COVERAGE IS COMPLETE (Controller ruling R11, finished)
 *
 * ErrorCodes::ALL ships all eleven suffixes, and this gate now demands a
 * sample for every one of them: the sample set returned by
 * mhmuicore_gate_error_samples() must be EXACTLY ErrorCodes::ALL, no more, no
 * fewer. Coverage was staged while CompositionBuilder did not exist yet
 * (BlueprintValidator raises seven suffixes, CompositionBuilder the other
 * four); that split, and its "staged-uncovered" list, is gone now that both
 * engine classes exist and mhmuicore_gate_error_samples() covers all eleven.
 *
 * @package MHMUiCore
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../tests/Fixtures/wp-function-stubs.php';
require_once __DIR__ . '/../tests/Fixtures/error-samples.php';

use MHMUiCore\Layout\ErrorCodes;
use MHMUiCore\Layout\LayoutContract;
use MHMUiCore\Tests\Fixtures\FixtureAdapter;

$contract = new LayoutContract(
	array(
		'error_prefix'  => 'zzz',
		'markup_prefix' => 'fixture',
		'adapters'      => array(
			'hero'           => new FixtureAdapter(),
			// Rendered markup crafted to trip exactly one CompositionBuilder
			// check each, the same one-field-per-sample discipline
			// mhmuicore_gate_error_samples() applies to its manifests.
			'tailwind_probe' => new FixtureAdapter( '<div class="tw-flex"></div>' ),
			'utility_probe'  => new FixtureAdapter( '<div class="bg-red-500"></div>' ),
		),
	)
);

$failures = array();

// ─── Inventory check (R11) ─────────────────────────────────────────────────

$samples = mhmuicore_gate_error_samples( $contract );

$covered_suffixes = array_keys( $samples );
sort( $covered_suffixes );

$all_suffixes = ErrorCodes::ALL;
sort( $all_suffixes );

if ( $covered_suffixes !== $all_suffixes ) {
	$failures[] = sprintf(
		'coverage: sample set is {%s}, expected exactly ErrorCodes::ALL {%s}',
		implode( ', ', $covered_suffixes ),
		implode( ', ', $all_suffixes )
	);
}

// ─── Per-sample predicate: prefix present, product prefix absent, no message ──

foreach ( $samples as $suffix => $error ) {
	$code = $error->get_error_code();

	if ( 1 !== preg_match( '/^zzz_[a-z_]+$/', $code ) ) {
		$failures[] = sprintf( '%s: code "%s" does not carry the injected prefix', $suffix, $code );
	}

	if ( false !== strpos( $code, 'mhmrentiva' ) ) {
		$failures[] = sprintf( '%s: product prefix leaked into "%s"', $suffix, $code );
	}

	if ( '' !== $error->get_error_message() ) {
		$failures[] = sprintf( '%s: the package produced human text', $suffix );
	}
}

foreach ( $failures as $failure ) {
	fwrite( STDERR, 'check-error-prefix: ' . $failure . PHP_EOL );
}

printf( 'SUMMARY: %d%s', count( $failures ), PHP_EOL );

exit( array() === $failures ? 0 : 1 );
