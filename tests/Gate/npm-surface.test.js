const { execFileSync } = require( 'node:child_process' );
const { cpSync, existsSync, mkdtempSync, readFileSync, rmSync, writeFileSync } = require( 'node:fs' );
const os = require( 'node:os' );
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
	 * Runs `npm pack --dry-run` with package.json's `files` field replaced by
	 * `files`, against a THROWAWAY COPY of the package rather than the real
	 * tracked tree -- a gate must never write the repo it is guarding, and an
	 * in-place rewrite-then-restore is one `finally` away from leaving
	 * package.json mutated on disk if the process is killed mid-test (a
	 * timeout, Ctrl+C, a worker crash all skip `finally`). The copy is built
	 * fresh in `os.tmpdir()` and removed in `finally` regardless of outcome;
	 * only `src-react`, LICENSE and README.md are copied in because those are
	 * everything `npm pack`'s own always-include rules plus this package's
	 * `files` entry can possibly list (verified against N-2's expectations).
	 *
	 * @return {string[]} Paths `npm pack --dry-run` would publish under `files`.
	 */
	function packedFilesWithFilesList( files ) {
		const pkg = JSON.parse( readFileSync( join( root, 'package.json' ), 'utf8' ) );
		pkg.files = files;

		const tmpDir = mkdtempSync( join( os.tmpdir(), 'mhmui-npm-surface-' ) );
		try {
			writeFileSync( join( tmpDir, 'package.json' ), JSON.stringify( pkg, null, '\t' ) + '\n' );
			cpSync( join( root, 'src-react' ), join( tmpDir, 'src-react' ), { recursive: true } );
			for ( const extra of [ 'LICENSE', 'README.md' ] ) {
				if ( existsSync( join( root, extra ) ) ) {
					cpSync( join( root, extra ), join( tmpDir, extra ) );
				}
			}

			const out = execFileSync(
				'npm',
				[ 'pack', '--dry-run', '--json' ],
				{ cwd: tmpDir, encoding: 'utf8', shell: process.platform === 'win32' }
			);
			return JSON.parse( out )[ 0 ].files.map( ( entry ) => entry.path );
		} finally {
			rmSync( tmpDir, { recursive: true, force: true } );
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
