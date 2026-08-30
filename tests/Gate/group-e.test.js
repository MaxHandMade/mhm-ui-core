const { execFileSync } = require( 'node:child_process' );
const { mkdtempSync } = require( 'node:fs' );
const { tmpdir } = require( 'node:os' );
const { join } = require( 'node:path' );
const { fixtureRepo, runGateOut, CSS_GATE, PHP_GATE } = require( './helpers' );

// This group closes the gap the whole-branch review found: Group B..D were
// specified BEFORE review rounds R13 (JS) and R18 (PHP) added new predicates
// to the gates, and nobody reopened the spec afterwards. Proven by mutation:
// disabling the PHP gate's entire T_ENCAPSED_AND_WHITESPACE branch (R18), or
// the CSS gate's interpolated-template branch (R13), left 65/65 green. Every
// run below uses runGateOut and asserts the VIOLATION TYPE printed, not just
// the exit code — an assertion on the exit code alone is exactly how the
// original gap passed while measuring nothing (a fixture that trips more
// than one predicate keeps the same exit code whether or not the predicate
// actually under test still fires).
describe( 'E — the predicates later review rounds added, and the gates plumbing they share', () => {
	test( 'E-1 PHP P1a static literal bites, not R18 interpolation', () => {
		const { code, out } = runGateOut( fixtureRepo( {
			'bootstrap.php': "<?php\n$x = '--mhm-x';\n",
		} ), PHP_GATE );
		expect( code ).toBe( 1 );
		expect( out ).toContain( 'foreign-custom-property' );
		expect( out ).not.toContain( 'dynamic-custom-property-name' );
		expect( out ).toContain( 'SUMMARY: 1 violation(s)' );
	} );

	test( 'E-2 PHP double-quoted interpolation (R18) bites via dynamic, not P1a', () => {
		const { code, out } = runGateOut( fixtureRepo( {
			'bootstrap.php': '<?php\n$v = 1;\n$x = "--mhm-x-{$v}";\n',
		} ), PHP_GATE );
		expect( code ).toBe( 1 );
		expect( out ).toContain( 'dynamic-custom-property-name' );
		expect( out ).not.toContain( 'foreign-custom-property' );
		expect( out ).toContain( 'SUMMARY: 1 violation(s)' );
	} );

	test( 'E-3 PHP heredoc with interpolation (R18) bites via dynamic', () => {
		const { code, out } = runGateOut( fixtureRepo( {
			'bootstrap.php': '<?php\n$v = 1;\n$x = <<<EOT\n--mhm-x-{$v}\nEOT;\n',
		} ), PHP_GATE );
		expect( code ).toBe( 1 );
		expect( out ).toContain( 'dynamic-custom-property-name' );
		expect( out ).not.toContain( 'foreign-custom-property' );
		expect( out ).toContain( 'SUMMARY: 1 violation(s)' );
	} );

	test( 'E-4 PHP nowdoc (no interpolation possible) bites via P1a, not dynamic', () => {
		const { code, out } = runGateOut( fixtureRepo( {
			'bootstrap.php': "<?php\n$x = <<<'EOT'\n--mhm-x\nEOT;\n",
		} ), PHP_GATE );
		expect( code ).toBe( 1 );
		expect( out ).toContain( 'foreign-custom-property' );
		expect( out ).not.toContain( 'dynamic-custom-property-name' );
		expect( out ).toContain( 'SUMMARY: 1 violation(s)' );
	} );

	test( 'E-5 JS interpolated template literal (R13) bites via dynamic', () => {
		const { code, out } = runGateOut( fixtureRepo( {
			'src-react/apply.js': 'const v = 1;\nexport const a = `--mhm-x-${ v }`;\n',
		} ) );
		expect( code ).toBe( 1 );
		expect( out ).toContain( 'dynamic-custom-property-name' );
		expect( out ).not.toContain( 'foreign-custom-property' );
		expect( out ).toContain( 'SUMMARY: 1 violation(s)' );
	} );

	test.each( [
		[ 'E-6a static template literal', 'export const a = `--mhm-x`;\n' ],
		[ 'E-6b String.raw tagged template', 'export const a = String.raw`--mhm-x`;\n' ],
		[ 'E-6c computed object key', 'export const a = { [`--mhm-x`]: 1 };\n' ],
	] )( '%s bites via foreign, not dynamic', ( _label, body ) => {
		const { code, out } = runGateOut( fixtureRepo( { 'src-react/apply.js': body } ) );
		expect( code ).toBe( 1 );
		expect( out ).toContain( 'foreign-custom-property' );
		expect( out ).not.toContain( 'dynamic-custom-property-name' );
		expect( out ).toContain( 'SUMMARY: 1 violation(s)' );
	} );

	test( "E-7 JS .concat() bites via dynamic, using a compliant prefix so P1a stays silent", () => {
		const { code, out } = runGateOut( fixtureRepo( {
			'src-react/apply.js': "el.style.setProperty( '--mhmui-'.concat( ns ), v );\n",
		} ) );
		expect( code ).toBe( 1 );
		expect( out ).toContain( 'dynamic-custom-property-name' );
		expect( out ).not.toContain( 'foreign-custom-property' );
		expect( out ).toContain( 'SUMMARY: 1 violation(s)' );
	} );

	test( "E-8 the PHP gate's own G-a: an empty PHP set is a broken gate, not a clean one", () => {
		const repo = fixtureRepo( {} );
		execFileSync( 'git', [ 'rm', '-q', 'bootstrap.php' ], { cwd: repo } );
		execFileSync( 'git', [ '-c', 'user.email=t@t', '-c', 'user.name=t', 'commit', '-qm', 'drop php' ], { cwd: repo } );
		const { code, out } = runGateOut( repo, PHP_GATE );
		expect( code ).toBe( 1 );
		expect( out ).toContain( 'EMPTY-SET: no shipped PHP file matched' );
	} );

	test( "E-9 the PHP gate's own G-d: an unmeasurable archive exits 2, never 0", () => {
		const notARepo = mkdtempSync( join( tmpdir(), 'uicore-notrepo-' ) );
		const { code, err } = runGateOut( notARepo, PHP_GATE );
		expect( code ).toBe( 2 );
		expect( err ).toContain( 'MEASURE-FAILED' );
	} );

	test( "E-10 the Node gate's JS/JSX set has its own G-a, independent of the CSS set", () => {
		const repo = fixtureRepo( {} );
		execFileSync( 'git', [ 'rm', '-q', 'src-react/index.js' ], { cwd: repo } );
		execFileSync( 'git', [ '-c', 'user.email=t@t', '-c', 'user.name=t', 'commit', '-qm', 'drop js' ], { cwd: repo } );
		const { code, out } = runGateOut( repo, CSS_GATE );
		expect( code ).toBe( 1 );
		expect( out ).toContain( 'EMPTY-SET: no shipped JS/JSX file matched' );
	} );

	test( 'E-11 G-e: an unclaimed extension is reported, and does not change the exit code', () => {
		const { code, out } = runGateOut( fixtureRepo( { 'probe.scss': '.mhmui-x { color: red }\n' } ) );
		expect( code ).toBe( 0 ); // the fixture is otherwise compliant; UNCLAIMED is a warning, not a gate
		expect( out ).toContain( 'UNCLAIMED: probe.scss' );
	} );
} );
