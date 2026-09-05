const { execFileSync } = require( 'node:child_process' );
const { join } = require( 'node:path' );

/**
 * The npm tarball is a shipped surface like the composer archive, and it had no
 * gate at all.
 *
 * An audit ran `npm pack --dry-run` and found 22 files, five of them
 * `*.test.js` / `*.test.jsx`. The composer side has refused to ship its test
 * suite since v0.3.2 -- a consumer's ESLint lints what it finds in its own tree
 * and reported 95 no-undef findings from THIS package's jest globals -- and the
 * npm side ships into the same trees for the same reason.
 */
describe( 'the npm tarball', () => {
	const root = join( __dirname, '..', '..' );

	/** @return {string[]} Paths inside the tarball npm would publish. */
	function packedFiles() {
		const out = execFileSync(
			'npm',
			[ 'pack', '--dry-run', '--json' ],
			{ cwd: root, encoding: 'utf8', shell: process.platform === 'win32' }
		);

		return JSON.parse( out )[ 0 ].files.map( ( entry ) => entry.path );
	}

	test( 'N-1 the test suite does not ship to consumers', () => {
		const tests = packedFiles().filter( ( path ) => /\.test\.jsx?$/.test( path ) );

		expect( tests ).toEqual( [] );
	} );

	test( 'N-2 the modules a consumer imports do ship', () => {
		const files = packedFiles();

		expect( files ).toContain( 'src-react/index.js' );
		expect( files ).toContain( 'src-react/tokens.json' );
		expect( files ).toContain( 'src-react/components/StatCard.jsx' );
	} );
} );
