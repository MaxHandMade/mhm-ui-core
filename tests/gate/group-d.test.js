const { execFileSync } = require( 'node:child_process' );
const { appendFileSync, mkdtempSync } = require( 'node:fs' );
const { tmpdir } = require( 'node:os' );
const { join } = require( 'node:path' );
const { fixtureRepo, runGate, PHP_GATE } = require( './helpers' );

describe( 'D — the subject is the shipped archive, and the plumbing is honest', () => {
	test( 'D-1 an UNCOMMITTED injection is invisible', () => {
		const repo = fixtureRepo( {} );
		appendFileSync( join( repo, 'src-react/admin.css' ), '\n.x { color: var( --mhm-text ) }\n' );
		expect( runGate( repo ) ).toBe( 0 ); // never committed, never shipped
	} );

	test( 'D-2 a violation inside an export-ignored path is invisible', () => {
		expect( runGate( fixtureRepo( {
			'.gitattributes': '/tests/ export-ignore\n',
			'tests/bad.css': '#x { --mhm-y: 1 }\n',
		} ) ) ).toBe( 0 );
	} );

	test( 'D-3 a NEW css file is discovered, not hard-coded away', () => {
		expect( runGate( fixtureRepo( {
			'src-react/extra.css': '#x { color: red }\n',
		} ) ) ).toBe( 1 );
	} );

	test( 'D-4 an empty CSS set is a broken gate, not a clean one', () => {
		const repo = fixtureRepo( {} );
		execFileSync( 'git', [ 'rm', '-q', 'src-react/admin.css' ], { cwd: repo } );
		execFileSync( 'git', [ '-c', 'user.email=t@t', '-c', 'user.name=t', 'commit', '-qm', 'drop css' ], { cwd: repo } );
		expect( runGate( repo ) ).toBe( 1 );
	} );

	test( 'D-5 an unmeasurable archive exits 2, never 0', () => {
		const notARepo = mkdtempSync( join( tmpdir(), 'uicore-notrepo-' ) );
		expect( runGate( notARepo ) ).toBe( 2 ); // skipping is not passing
	} );

	test( 'D-6 a token name inside a PHP docblock is not a violation', () => {
		expect( runGate( fixtureRepo( {
			'bootstrap.php': "<?php\n/** Example: --mhm-primary: #000; */\n",
		} ), PHP_GATE ) ).toBe( 0 );
	} );

	test( 'D-7 a token name inside a JS comment is not a violation', () => {
		expect( runGate( fixtureRepo( {
			'src-react/apply.js': "// migrate --mhm-x to --mhmui-x\nexport const a = 1;\n",
		} ) ) ).toBe( 0 );
	} );

	test( 'D-8 a token name and an ID inside a CSS comment are not violations', () => {
		expect( runGate( fixtureRepo( {
			'src-react/admin.css': '/* #x --mhm-y */ .mhmui-admin { color: red }\n',
		} ) ) ).toBe( 0 );
	} );
} );
