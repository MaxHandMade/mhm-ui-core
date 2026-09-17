const { execFileSync } = require( 'node:child_process' );
const { readFileSync, writeFileSync } = require( 'node:fs' );
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

	/**
	 * Runs `packedFiles()` with package.json's `files` field temporarily
	 * replaced by `files`, restoring the original content afterwards even if
	 * the assertion throws. This is the same manual step the exclusion's own
	 * commit was verified with (drop the `!src-react/kit-classes.json` line,
	 * watch it ship, put the line back) turned into something the suite runs
	 * itself, so a future edit that narrows or reorders `files` and drops the
	 * exclusion is caught here instead of on the next `npm pack --dry-run`
	 * someone happens to read by eye.
	 *
	 * @return {string[]} Paths `npm pack --dry-run` would publish under `files`.
	 */
	function packedFilesWithFilesList( files ) {
		const pkgPath = join( root, 'package.json' );
		const original = readFileSync( pkgPath, 'utf8' );
		const pkg = JSON.parse( original );
		pkg.files = files;
		writeFileSync( pkgPath, JSON.stringify( pkg, null, '\t' ) + '\n' );
		try {
			return packedFiles();
		} finally {
			writeFileSync( pkgPath, original );
		}
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

	test( 'N-3 kit-classes.json (the JSX class map, not the shipped surface) does not ship to consumers', () => {
		expect( packedFiles() ).not.toContain( 'src-react/kit-classes.json' );
	} );

	test( 'N-4 not vacuous: a files list without the kit-classes.json exclusion ships it', () => {
		const files = packedFilesWithFilesList( [
			'src-react',
			'!src-react/**/*.test.js',
			'!src-react/**/*.test.jsx',
		] );

		expect( files ).toContain( 'src-react/kit-classes.json' );
	} );
} );
