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
 * COVERAGE IS STAGED, ON PURPOSE (Controller ruling R11)
 *
 * ErrorCodes::ALL ships complete -- all eleven suffixes -- because a canonical
 * inventory that grows piecemeal is not one. But BlueprintValidator (the only
 * engine class that exists in this package so far) raises only seven of them;
 * the other four belong to CompositionBuilder, which Task 8 has not written
 * yet. So this gate does not demand "every suffix in ErrorCodes::ALL has a
 * sample" -- that would be unsatisfiable today and someone would suppress it.
 * Instead it demands two things that ARE satisfiable and still catch drift:
 *   - the sample set is EXACTLY the seven validator suffixes, no more, no
 *     fewer -- named literally below, not inferred from whatever the fixture
 *     happens to return;
 *   - covered (7) + staged-uncovered (4, also named literally below) is
 *     EXACTLY ErrorCodes::ALL -- so a twelfth code added anywhere (to the
 *     inventory, to a sample, or to neither) fails this gate immediately
 *     instead of silently falling into an open-ended "not yet covered"
 *     bucket. Task 8 deletes the staged-uncovered list and folds those four
 *     into the sample set.
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
		'adapters'      => array( 'hero' => new FixtureAdapter() ),
	)
);

$failures = array();

// ─── Staged inventory check (R11) ──────────────────────────────────────────

$samples = mhmuicore_gate_error_samples( $contract );

$covered_suffixes = array_keys( $samples );
sort( $covered_suffixes );

$expected_covered = array(
	ErrorCodes::FORBIDDEN_PATTERN,
	ErrorCodes::INVALID_BLUEPRINT,
	ErrorCodes::INVALID_COMPONENTS,
	ErrorCodes::INVALID_INSTANCE,
	ErrorCodes::INVALID_PAGE,
	ErrorCodes::NO_PAGES,
	ErrorCodes::UNSUPPORTED_VERSION,
);
sort( $expected_covered );

if ( $covered_suffixes !== $expected_covered ) {
	$failures[] = sprintf(
		'coverage: sample set is {%s}, expected exactly the seven BlueprintValidator suffixes {%s}',
		implode( ', ', $covered_suffixes ),
		implode( ', ', $expected_covered )
	);
}

// Staged (Controller ruling R11): CompositionBuilder does not exist in this
// package yet (Task 8). These four are deliberately unsampled today; Task 8
// extends mhmuicore_gate_error_samples() to cover them and deletes this list.
$staged_uncovered = array(
	ErrorCodes::MISSING_ADAPTER,
	ErrorCodes::TAILWIND_LEAKAGE,
	ErrorCodes::UNKNOWN_COMPONENT,
	ErrorCodes::UTILITY_LEAKAGE,
);
sort( $staged_uncovered );

$all_suffixes = ErrorCodes::ALL;
sort( $all_suffixes );

$reconstructed = array_merge( $expected_covered, $staged_uncovered );
sort( $reconstructed );

if ( $reconstructed !== $all_suffixes ) {
	$failures[] = sprintf(
		'inventory: covered + staged-uncovered is {%s}, ErrorCodes::ALL is {%s} -- a suffix was added or removed without updating this gate',
		implode( ', ', $reconstructed ),
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
