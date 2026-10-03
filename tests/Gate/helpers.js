const { execFileSync } = require( 'node:child_process' );
const { mkdtempSync, writeFileSync, mkdirSync } = require( 'node:fs' );
const { tmpdir } = require( 'node:os' );
const { join, dirname } = require( 'node:path' );

const CSS_GATE = join( __dirname, '..', '..', 'bin', 'check-css-namespace.mjs' );
const PHP_GATE = join( __dirname, '..', '..', 'bin', 'check-php-namespace.php' );

/** A compliant baseline every fixture starts from. */
const CLEAN = {
	'src-react/admin.css': '.mhmui-admin { --mhmui-blue: #2271b1; color: var( --mhmui-blue ); }\n',
	'src-react/index.js': "export const token = '--mhmui-ok';\n",
	'bootstrap.php': "<?php\ndefine( 'X', '--mhmui-ok' );\n",
};

/**
 * Builds a throwaway git repo whose ARCHIVE is exactly `files`.
 * `git add -A` is safe here and only here: this repo is created empty inside
 * the OS temp dir and never contains the user's work.
 */
function fixtureRepo( files ) {
	const dir = mkdtempSync( join( tmpdir(), 'uicore-fixture-' ) );
	for ( const [ path, body ] of Object.entries( { ...CLEAN, ...files } ) ) {
		const full = join( dir, path );
		mkdirSync( dirname( full ), { recursive: true } );
		writeFileSync( full, body );
	}
	execFileSync( 'git', [ 'init', '-q' ], { cwd: dir } );
	execFileSync( 'git', [ 'add', '-A' ], { cwd: dir } );
	execFileSync( 'git', [ '-c', 'user.email=t@t', '-c', 'user.name=t', 'commit', '-qm', 'fixture' ], { cwd: dir } );
	return dir;
}

/** @return {number} the gate's own exit code. */
function runGate( repo, gate = CSS_GATE ) {
	const argv = gate === PHP_GATE ? [ 'php', [ gate, repo ] ] : [ 'node', [ gate, repo ] ];
	try {
		execFileSync( argv[ 0 ], argv[ 1 ], { stdio: 'pipe' } );
		return 0;
	} catch ( e ) {
		return e.status;
	}
}

/**
 * Like runGate(), but also returns the gate's stdout (and stderr) so a test
 * can assert on WHICH predicate fired (via its VIOLATION line, or — for a
 * MEASURE-FAILED path that never reaches stdout — its stderr message), not
 * just the exit code. A fixture that trips more than one predicate can
 * produce the same exit code whether or not the predicate under test still
 * fires at all — asserting the exit code alone would then pass for the
 * wrong reason.
 *
 * @return {{ code: number, out: string, err: string }}
 */
function runGateOut( repo, gate = CSS_GATE ) {
	const bin = gate === PHP_GATE ? 'php' : 'node';
	try {
		return { code: 0, out: execFileSync( bin, [ gate, repo ], { encoding: 'utf8' } ), err: '' };
	} catch ( e ) {
		return { code: e.status, out: String( e.stdout ?? '' ), err: String( e.stderr ?? '' ) };
	}
}

/** Body of the first rule whose selector list is exactly `selector`. */
function ruleBody( css, selector ) {
	const code = css.replace( /\/\*[\s\S]*?\*\//g, '' );
	const esc = selector.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
	const m = code.match( new RegExp( `(^|})\\s*${ esc }\\s*{([^}]*)}` ) );
	return m ? m[ 2 ] : null;
}

/** WCAG 2.x relative luminance of a #rgb / #rrggbb colour. */
function luminance( hex ) {
	let h = String( hex ).replace( '#', '' );
	if ( 3 === h.length ) {
		h = h.replace( /./g, '$&$&' );
	}
	if ( ! /^[0-9a-f]{6}$/i.test( h ) ) {
		throw new Error( `contrastRatio: not a hex colour: ${ hex }` );
	}
	const [ r, g, b ] = [ 0, 2, 4 ].map( ( i ) => {
		const c = parseInt( h.slice( i, i + 2 ), 16 ) / 255;
		return c <= 0.03928 ? c / 12.92 : Math.pow( ( c + 0.055 ) / 1.055, 2.4 );
	} );
	return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

/** WCAG 2.x contrast ratio of two hex colours (1..21). */
function contrastRatio( fgHex, bgHex ) {
	const [ a, b ] = [ luminance( fgHex ), luminance( bgHex ) ];
	return ( Math.max( a, b ) + 0.05 ) / ( Math.min( a, b ) + 0.05 );
}

module.exports = { fixtureRepo, runGate, runGateOut, ruleBody, contrastRatio, CSS_GATE, PHP_GATE };
