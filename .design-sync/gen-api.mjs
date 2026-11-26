// design-sync input generator for mhm-ui-core (cfg.buildCmd; run before the converter).
//
// The package ships raw JSX with no .d.ts, and every component is an
// `export default function X` -- a shape the converter's JSDoc reader skips.
// The package already has a machine-readable API: src-react/components.json
// (props with type hints + fixtures, the input of the package's own gates).
// This script turns it into what the converter reads, so nothing is
// hand-copied and nothing drifts:
//   - config.json `dtsPropsFor`: one TS props body per component;
//   - .design-sync/.cache/docs/<Name>.md (`docsDir`): the component's own JSDoc
//     (first sentence = summary), its props, and its verified preview as the
//     example.
// Deterministic: same manifest + sources -> byte-identical output.

import { readFileSync, writeFileSync, mkdirSync, rmSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname( fileURLToPath( import.meta.url ) );
const ROOT = join( HERE, '..' );
const manifest = JSON.parse( readFileSync( join( ROOT, 'src-react', 'components.json' ), 'utf8' ) );
const cfgPath = join( HERE, 'config.json' );
const cfg = JSON.parse( readFileSync( cfgPath, 'utf8' ) );

const ROLE = '"success" | "warning" | "danger" | "info" | "neutral"';
const PRIM = new Set( [ 'string', 'number', 'boolean' ] );

/** Split on commas / pipes that are not nested in {} [] () <>. */
function splitTop( s, sep ) {
	const out = [];
	let depth = 0;
	let cur = '';
	for ( const ch of s ) {
		if ( '{[(<'.includes( ch ) ) depth++;
		if ( '}])>'.includes( ch ) ) depth--;
		if ( ch === sep && depth === 0 ) {
			out.push( cur );
			cur = '';
		} else {
			cur += ch;
		}
	}
	out.push( cur );
	return out.map( ( x ) => x.trim() ).filter( Boolean );
}

/** A manifest type hint -> [ts type, optional]. */
function conv( raw ) {
	let t = raw.trim();
	const optional = t.endsWith( '?' );
	if ( optional ) t = t.slice( 0, -1 ).trim();
	return [ convType( t ), optional ];
}

function convType( t ) {
	if ( t.startsWith( '{' ) && t.endsWith( '}' ) ) {
		const inner = t.slice( 1, -1 ).trim();
		if ( inner.startsWith( '[' ) ) return `{ ${ inner } }`;
		const fields = splitTop( inner, ',' ).map( ( f ) => {
			const i = f.indexOf( ':' );
			const [ ty, opt ] = conv( f.slice( i + 1 ) );
			return `${ f.slice( 0, i ).trim() }${ opt ? '?' : '' }: ${ ty }`;
		} );
		return `{ ${ fields.join( '; ' ) } }`;
	}
	const arr = /^Array<(.*)>$/.exec( t );
	if ( arr ) return `Array<${ convType( arr[ 1 ] ) }>`;
	const parts = splitTop( t, '|' );
	if ( parts.length > 1 ) {
		return parts.map( ( p ) => ( /^\d+$/.test( p ) || PRIM.has( p ) ? p : JSON.stringify( p ) ) ).join( ' | ' );
	}
	switch ( t ) {
		case 'array': return 'any[]';
		case 'function': return '(...args: any[]) => void';
		case 'node': return 'React.ReactNode';
		case 'icon': return 'string';
		case 'role': return ROLE;
		default: return PRIM.has( t ) ? t : 'unknown';
	}
}

/** Every /** block of a file as [start, end, text]. */
function blocks( src ) {
	const out = [];
	for ( const m of src.matchAll( /\/\*\*([\s\S]*?)\*\//g ) ) {
		const text = m[ 1 ]
			.split( '\n' )
			.map( ( l ) => l.replace( /^\s*\*\s?/, '' ) )
			.join( '\n' )
			.trim();
		out.push( [ m.index, m.index + m[ 0 ].length, text ] );
	}
	return out;
}

/**
 * The component's own doc block. Measured on this package: it is not always
 * directly above `export default function X` (a helper or a typedef can sit
 * between, StatsGrid documents itself at the top of the file, ErrorBoundary is
 * a class exported by name at the end). In order:
 *   1. the block directly above `export default function|class X`, `class X`
 *      or `function X`;
 *   2. the first block that documents `props` (`@param {Object} props`);
 *   3. the file's leading block.
 */
function jsdoc( src, name ) {
	const all = blocks( src ).filter( ( b ) => ! /^@typedef\b/.test( b[ 2 ] ) );
	const decl = new RegExp( `(?:export\\s+default\\s+)?(?:function|class)\\s+${ name }\\b` ).exec( src );
	if ( decl ) {
		const above = all.filter( ( b ) => b[ 1 ] <= decl.index ).pop();
		if ( above && src.slice( above[ 1 ], decl.index ).trim() === '' ) return above[ 2 ];
	}
	const withProps = all.find( ( b ) => /@param\s+\{Object\}\s+props\b/.test( b[ 2 ] ) );
	if ( withProps ) return withProps[ 2 ];
	const lead = all[ 0 ];
	return lead && src.slice( 0, lead[ 0 ] ).replace( /^import[^\n]*\n/gm, '' ).trim() === '' ? lead[ 2 ] : '';
}

/** The doc's @param lines for props.x, as { name: description }. */
function paramDocs( doc ) {
	const out = {};
	const tags = doc.split( /\n(?=@)/ ).filter( ( t ) => t.startsWith( '@param' ) );
	for ( const tag of tags ) {
		const m = /^@param\s+\{[^}]*\}\s+\[?props\.([A-Za-z0-9_]+)[^\]\s]*\]?\s*([\s\S]*)$/.exec( tag );
		if ( m ) out[ m[ 1 ] ] = m[ 2 ].replace( /\s+/g, ' ' ).trim();
	}
	return out;
}

const docsRel = '.design-sync/.cache/docs';
const docsDir = join( ROOT, docsRel );
rmSync( docsDir, { recursive: true, force: true } );
mkdirSync( docsDir, { recursive: true } );

const dtsPropsFor = {};
for ( const name of Object.keys( cfg.componentSrcMap ).sort() ) {
	const entry = manifest[ name ];
	const src = readFileSync( join( ROOT, cfg.componentSrcMap[ name ] ), 'utf8' );
	const props = entry ? entry.props || {} : {};
	const body = Object.entries( props ).map( ( [ k, raw ] ) => {
		const [ ty, opt ] = conv( raw );
		return `${ k }${ opt ? '?' : '' }: ${ ty };`;
	} );
	if ( body.length ) dtsPropsFor[ name ] = body.join( ' ' );

	const full = jsdoc( src, name );
	const doc = full.replace( /^@(?:param|return)[\s\S]*$/m, '' ).trim();
	const notes = paramDocs( full );
	const lines = [ doc || `${ name } -- a component of @mhm/ui-core.`, '' ];
	if ( body.length ) {
		lines.push( '## Props', '' );
		for ( const [ k, raw ] of Object.entries( props ) ) {
			lines.push( `- \`${ k }\`: \`${ raw }\`${ notes[ k ] ? ` — ${ notes[ k ] }` : '' }` );
		}
		lines.push( '' );
	}
	// Examples come from the verified preview (.design-sync/previews/<Name>.tsx),
	// never from the manifest fixtures: many fixtures are deliberate bad inputs
	// for the package's gates (tone "purple", icon "%41", onConfirm "function").
	let preview = '';
	try {
		preview = readFileSync( join( HERE, 'previews', `${ name }.tsx` ), 'utf8' ).replace( /\r\n/g, '\n' ).trim();
	} catch {}
	if ( preview ) {
		lines.push(
			'## Examples',
			'',
			'Each export is one verified preview, rendered inside `<AdminShell>` (see README). Import from `window.MHMUiCore` in a design.',
			'',
			'```tsx',
			preview,
			'```',
			''
		);
	}
	writeFileSync( join( docsDir, `${ name }.md` ), lines.join( '\n' ) );
}

// Tokens for the artifact's Colors/Spacing pages and for designs: the kit
// declares them on `.mhmui-admin` (its generated block between the
// mhmui:tokens markers in admin.css), and the converter reads tokens only
// from :root. Re-declare that block's own declarations on :root, verbatim.
const adminCss = readFileSync( join( ROOT, 'assets', 'react', 'admin.css' ), 'utf8' );
const tokStart = adminCss.indexOf( '/* mhmui:tokens:start' );
const tokEnd = adminCss.indexOf( '/* mhmui:tokens:end */' );
const block = tokStart >= 0 && tokEnd > tokStart ? adminCss.slice( tokStart, tokEnd ) : '';
const decls = block.slice( block.indexOf( '{' ) + 1, block.lastIndexOf( '}' ) ).trim();
if ( ! /--mhmui-/.test( decls ) ) {
	console.error( 'gen-api: no token block between the mhmui:tokens markers in admin.css' );
	process.exit( 1 );
}
writeFileSync(
	join( HERE, '.cache', 'tokens-root.css' ),
	`/* generated by .design-sync/gen-api.mjs from admin.css's mhmui:tokens block */\n:root {\n\t${ decls }\n}\n`
);

cfg.dtsPropsFor = dtsPropsFor;
cfg.docsDir = docsRel;
writeFileSync( cfgPath, JSON.stringify( cfg, null, 2 ) + '\n' );
console.log( `gen-api: ${ Object.keys( dtsPropsFor ).length } props bodies, ${ Object.keys( cfg.componentSrcMap ).length } docs -> ${ docsRel }` );
