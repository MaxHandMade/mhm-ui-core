#!/usr/bin/env node
// Measures the SHIPPED SURFACE, never the working tree: an uncommitted edit is
// invisible here on purpose. See the spec, section 4.
import { execFileSync } from 'node:child_process';
import { mkdtempSync, rmSync, readdirSync, statSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, relative, extname, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import stylelint from 'stylelint';
import { parse } from '@babel/parser';

const P1_EXT = new Set( [ '.css', '.js', '.jsx', '.php' ] );
const DOC_EXT = new Set( [ '.md', '.json', '' ] );

const HERE = dirname( fileURLToPath( import.meta.url ) );
// The repo to measure. Defaults to this package; the regression suite points it
// at throwaway fixture repos so the archive itself can be shaped (empty sets,
// extra files, a broken archive) without touching this tree.
const REPO = process.argv[ 2 ] ?? join( HERE, '..' );
// Resolved against the script, never the caller's cwd: a gate that reads its own
// config relative to cwd silently changes meaning when invoked from elsewhere.
// Also deliberately read from OUTSIDE the archive: .stylelintrc.json is
// export-ignore'd (Task 1), so it never lands inside the shipped surface this
// gate extracts below — it has to come from the real checkout instead.
const CONFIG = join( HERE, '..', '.stylelintrc.json' );

let dir;
try {
	dir = mkdtempSync( join( tmpdir(), 'uicore-gate-' ) );
	execFileSync( 'sh', [ '-c', `git -C "${ REPO }" archive HEAD | tar -x -C "${ dir }"` ], { stdio: 'pipe' } );
} catch ( e ) {
	console.error( `MEASURE-FAILED: could not extract the shipped surface: ${ e.message }` );
	process.exit( 2 ); // skipping is not passing
}

const walk = ( d ) => readdirSync( d ).flatMap( ( n ) => {
	const p = join( d, n );
	return statSync( p ).isDirectory() ? walk( p ) : [ p ];
} );

const all = walk( dir );
const css = all.filter( ( f ) => extname( f ) === '.css' );
const js  = all.filter( ( f ) => [ '.js', '.jsx' ].includes( extname( f ) ) );

const violations = [];
const rel = ( f ) => relative( dir, f ).replace( /\\/g, '/' );

// G-b: record the measurement itself, by name — and ONLY what this gate actually
// reads. The .php share of the P1 set belongs to bin/check-php-namespace.php;
// listing it here would file a scan that never happened.
console.log( `SCANNED-CSS: ${ css.map( rel ).sort().join( ' ' ) }` );
console.log( `SCANNED-JS: ${ js.map( rel ).sort().join( ' ' ) }` );

// G-e: shipped files that belong to no subject are printed, not failed.
for ( const f of all ) {
	if ( ! P1_EXT.has( extname( f ) ) && ! DOC_EXT.has( extname( f ) ) ) {
		console.log( `UNCLAIMED: ${ rel( f ) } (no predicate owns this extension)` );
	}
}

// Each recorded violation is { text, name }: `text` is the line printed below —
// one per location, so the gate always says WHERE (G-b) — while `name` is the
// offending identifier used only to de-duplicate the SUMMARY count further
// down. A structural guard such as the empty-set check below has no
// property/selector name to de-duplicate against, so its own text doubles as
// its name: it is inherently unique.

// G-a: an empty subject is a broken gate, not a clean one. Each set is checked
// separately: one can go empty while the other stays full, and a single combined
// check would mask exactly that.
if ( css.length === 0 ) violations.push( { text: 'EMPTY-SET: no shipped CSS file matched', name: 'EMPTY-SET:css' } );
if ( js.length === 0 ) violations.push( { text: 'EMPTY-SET: no shipped JS/JSX file matched', name: 'EMPTY-SET:js' } );

// The `custom-property-pattern` message configured in .stylelintrc.json is a
// fixed string (no {property}/{pattern} interpolation), so `warning.text` never
// carries the offending property name — only rule metadata does. Slicing the
// source at [column, endColumn) recovers the exact token for either rule
// instead: it works whether or not a rule's message happens to interpolate the
// name, so it doesn't have to change if a message wording changes later.
const sourceLines = new Map();
const lineOf = ( file, n ) => {
	if ( ! sourceLines.has( file ) ) {
		sourceLines.set( file, readFileSync( file, 'utf8' ).split( /\r?\n/ ) );
	}
	return sourceLines.get( file )[ n - 1 ] ?? '';
};
const offendingName = ( file, w ) => {
	const token = lineOf( file, w.line ).slice( w.column - 1, ( w.endColumn ?? w.column ) - 1 );
	return token || `${ rel( file ) }:${ w.line }:${ w.column }`;
};

// An empty `files` array makes stylelint reject with NoFilesFoundError instead
// of returning an empty result — skip the call entirely when there is nothing
// to lint. The EMPTY-SET violation above already covers this case; crashing
// here would turn a reported violation into an uncaught exception instead.
const result = css.length > 0
	? await stylelint.lint( { files: css, configFile: CONFIG } )
	: { results: [] };
for ( const r of result.results ) {
	for ( const w of r.warnings ) {
		violations.push( {
			text: `VIOLATION: ${ rel( r.source ) }:${ w.line } ${ w.rule } — ${ w.text }`,
			name: offendingName( r.source, w ),
		} );
	}
}

// P1a-js / P1b: the shipped JS/JSX half of the surface. A foreign custom-property
// name (or one assembled at runtime, which can never be verified statically) is
// found by walking the Babel AST — never by matching raw text — so a docblock
// example showing `--mhm-primary:#000;` is indistinguishable from a comment to
// this scanner, exactly because it never reaches a StringLiteral/TemplateLiteral
// node in the first place.
const isForeignName = ( v ) => typeof v === 'string' && v.startsWith( '--' ) && ! v.startsWith( '--mhmui-' );

// A "static text node" is a StringLiteral, or a TemplateLiteral with no
// interpolation (`` `--mhm-x` ``, a computed object key, the literal quasi
// behind `String.raw` — all fully known at parse time, same as an ordinary
// string). Returns undefined for anything else, including an *interpolated*
// template literal, which is handled separately below because it is only
// partially static.
const staticTextValue = ( node ) => {
	if ( node?.type === 'StringLiteral' ) return node.value;
	if ( node?.type === 'TemplateLiteral' && node.expressions.length === 0 ) {
		const q = node.quasis[ 0 ]?.value;
		return q?.cooked ?? q?.raw;
	}
	return undefined;
};
const isDashPrefixedStatic = ( node ) => {
	const v = staticTextValue( node );
	return typeof v === 'string' && v.startsWith( '--' );
};

for ( const f of js ) {
	const ast = parse( readFileSync( f, 'utf8' ), { sourceType: 'module', plugins: [ 'jsx' ] } );
	// Comments are a separate AST channel (CommentLine/CommentBlock nodes), so
	// walking for StringLiteral/TemplateLiteral/BinaryExpression/CallExpression
	// nodes never sees them, however they attach to the tree.
	JSON.stringify( ast, ( key, node ) => {
		if ( ! node || typeof node !== 'object' || ! node.type ) return node;

		if ( ( node.type === 'StringLiteral' || node.type === 'TemplateLiteral' ) && isForeignName( staticTextValue( node ) ) ) {
			const v = staticTextValue( node );
			violations.push( {
				text: `VIOLATION: ${ rel( f ) }:${ node.loc.start.line } foreign-custom-property — ${ v }`,
				name: v,
			} );
		}
		// P1b: a name assembled at runtime can never be verified statically, so the
		// assembly itself is the violation — there is no single name to de-duplicate
		// against, so (as with the EMPTY-SET guards above) the text doubles as the name.
		// This is deliberately shape-general rather than one hand-picked example:
		// either side of a `+` concatenation, or the receiver of a `.concat()` call,
		// can carry the static `--` fragment, and it can be written as a plain
		// string or a template literal. What it can NOT be made to catch is a name
		// assembled entirely from non-literal parts (a variable, an import,
		// String.fromCharCode) — that is not statically detectable by any check of
		// this kind, and is not attempted here.
		if ( node.type === 'BinaryExpression' && node.operator === '+'
			&& ( isDashPrefixedStatic( node.left ) || isDashPrefixedStatic( node.right ) ) ) {
			const text = `VIOLATION: ${ rel( f ) }:${ node.loc.start.line } dynamic-custom-property-name`;
			violations.push( { text, name: text } );
		}
		if ( node.type === 'CallExpression' && node.callee?.type === 'MemberExpression'
			&& node.callee.property?.name === 'concat' && isDashPrefixedStatic( node.callee.object ) ) {
			const text = `VIOLATION: ${ rel( f ) }:${ node.loc.start.line } dynamic-custom-property-name`;
			violations.push( { text, name: text } );
		}
		// An interpolated template whose static prefix already carries the `--`
		// boundary (`` `--${ns}-x` ``) is exactly as unverifiable once assembled,
		// even though no `+` or `.concat()` is involved.
		if ( node.type === 'TemplateLiteral' && node.expressions.length > 0
			&& node.quasis[ 0 ]?.value.raw.startsWith( '--' ) ) {
			const text = `VIOLATION: ${ rel( f ) }:${ node.loc.start.line } dynamic-custom-property-name`;
			violations.push( { text, name: text } );
		}
		return node;
	} );
}

rmSync( dir, { recursive: true, force: true } );
violations.forEach( ( v ) => console.log( v.text ) );

// The spec counts DISTINCT offending names, not raw occurrences: a single
// property referenced from fifty places is one name, not fifty (this is why
// Task 4's read-side var()/JSX detection can push into the same `violations`
// array without restructuring anything here). The VIOLATION lines above stay
// one per location on purpose — this count is the only thing that collapses.
const distinctNames = new Set( violations.map( ( v ) => v.name ) );
console.log( `SUMMARY: ${ distinctNames.size } violation(s)` );
process.exit( violations.length > 0 ? 1 : 0 );
