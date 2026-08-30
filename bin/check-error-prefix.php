<?php
/**
 * G1 -- the prefix gate.
 *
 * Three assertions, because the obvious one is only half the promise:
 *   (a) every code MATCHES ^zzz_[a-z_]+$ -- catches a code written with NO
 *       prefix at all (e.g. 'unknown_component'), the likelier slip when
 *       porting eleven literals, which assertion (b) alone would wave through;
 *   (b) no product prefix leaks -- catches a hardcoded 'mhmrentiva_...';
 *   (c) the code is EXACTLY 'zzz_' . $suffix, not merely shaped like it --
 *       catches a sample keyed under the wrong suffix (a copy-paste slip in
 *       mhmuicore_gate_error_samples() that triggers a DIFFERENT check than
 *       the one its array key claims), which (a) alone would wave through
 *       because a mis-keyed sample's code still matches ^zzz_[a-z_]+$.
 *
 * A fourth checks the message stays empty: the engine's only currency is the
 * code plus $data, and a message that sneaks back in is exactly the defect
 * this port exists to close (see BlueprintValidator's class docblock).
 *
 * COVERAGE IS COMPLETE
 *
 * ErrorCodes::ALL ships all eleven suffixes, and this gate now demands a
 * sample for every one of them: the sample set returned by
 * mhmuicore_gate_error_samples() must be EXACTLY ErrorCodes::ALL, no more, no
 * fewer. Coverage was staged while CompositionBuilder did not exist yet
 * (BlueprintValidator raises seven suffixes, CompositionBuilder the other
 * four); that split, and its "staged-uncovered" list, is gone now that both
 * engine classes exist and mhmuicore_gate_error_samples() covers all eleven.
 *
 * WHY THE PINNED LIST BELOW DUPLICATES ErrorCodes::ALL INSTEAD OF READING IT
 *
 * Measured: comparing $covered_suffixes against ErrorCodes::ALL is a
 * tautology against a RENAME, because both sides are keyed/built from the
 * same constants and move together. Renaming ErrorCodes::MISSING_ADAPTER's
 * value from 'missing_adapter' to 'adapter_missing' leaves this gate green:
 * the sample array key (`$samples[ ErrorCodes::MISSING_ADAPTER ]`) and
 * ErrorCodes::ALL both silently pick up the new string, and the raised code
 * ('zzz_adapter_missing') still matches assertion (a)'s generic shape. Only a
 * full DELETE is caught today, and only because it crashes loudly (an
 * undefined class constant), not because the gate's own logic detects it.
 * The literal list below is copy-pasted, not derived, ON PURPOSE: it is the
 * one thing in this file that does NOT move when ErrorCodes.php changes, so a
 * rename now shows up as a genuine divergence between "what the pinned
 * contract promises" and "what the code currently produces". Do not "DRY"
 * this back into `ErrorCodes::ALL` -- the duplication is the point.
 *
 * @package MHMUiCore
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../tests/Fixtures/wp-function-stubs.php';
require_once __DIR__ . '/../tests/Fixtures/error-samples.php';

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

// ─── Inventory check: sample set covers exactly the pinned suffix list ─────

$samples = mhmuicore_gate_error_samples( $contract );

$covered_suffixes = array_keys( $samples );
sort( $covered_suffixes );

/*
 * PINNED LITERALS -- see the class docblock above for why this list is
 * copy-pasted from ErrorCodes::ALL instead of reading it. Do not replace this
 * array with `ErrorCodes::ALL`: that reintroduces the tautology this gate
 * exists to close.
 */
$pinned_suffixes = array(
	'unsupported_version',
	'invalid_blueprint',
	'invalid_components',
	'invalid_page',
	'invalid_instance',
	'no_pages',
	'unknown_component',
	'missing_adapter',
	'forbidden_pattern',
	'tailwind_leakage',
	'utility_leakage',
);
sort( $pinned_suffixes );

if ( $covered_suffixes !== $pinned_suffixes ) {
	$failures[] = sprintf(
		'coverage: sample set is {%s}, expected exactly the pinned suffix list {%s}',
		implode( ', ', $covered_suffixes ),
		implode( ', ', $pinned_suffixes )
	);
}

// ─── Per-sample predicate: own code, prefix present, product prefix absent, no message ──

foreach ( $samples as $suffix => $error ) {
	$code = $error->get_error_code();

	/*
	 * WP_Error::get_error_code() is typed `string|int` by WordPress core
	 * itself (a code CAN be numeric in general WP_Error usage) -- this gate's
	 * own job is asserting that THIS package's codes never are, so the check
	 * belongs here, not as a cast that would only silence PHPStan without
	 * proving anything.
	 */
	if ( ! is_string( $code ) ) {
		$failures[] = sprintf( '%s: code is not a string (got %s)', $suffix, get_debug_type( $code ) );
		continue;
	}

	if ( 'zzz_' . $suffix !== $code ) {
		$failures[] = sprintf( '%s: raised code "%s" is not this sample\'s own code ("zzz_%s")', $suffix, $code, $suffix );
	}

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
