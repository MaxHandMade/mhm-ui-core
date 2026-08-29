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

module.exports = { fixtureRepo, runGate, CSS_GATE, PHP_GATE };
