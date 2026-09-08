#!/usr/bin/env node
/**
 * tokens:build -- the "single token source" the design document asked for.
 *
 * Reads src-react/tokens.json and rewrites the block between the two marker
 * comments in assets/react/admin.css. Nothing else in the stylesheet is
 * touched, so hand-written rules and generated tokens live in one file
 * without fighting.
 *
 *   node bin/build-tokens.js          rewrite the block
 *   node bin/build-tokens.js --check  exit 1 if the block is stale (CI)
 *
 * Why a generator and not "just edit the CSS": the design document's finding
 * was two token systems drifting because a colour changed in one place and
 * not the other. A generator with a --check gate makes the drift a red CI job
 * instead of a visual bug someone notices a release later.
 */
'use strict';

const { readFileSync, writeFileSync } = require( 'node:fs' );
const { join } = require( 'node:path' );

const ROOT = join( __dirname, '..' );
const TOKENS = join( ROOT, 'src-react', 'tokens.json' );
const CSS = join( ROOT, 'assets', 'react', 'admin.css' );
const START = '/* mhmui:tokens:start -- generated from src-react/tokens.json by bin/build-tokens.js; do not edit by hand */';
const END = '/* mhmui:tokens:end */';

/**
 * Render the custom-property block for one scope of a tokens.json document.
 *
 * @param {{scopes: Record<string,Record<string,string>>}} doc Parsed tokens.json.
 * @param {string} selector Scope key, e.g. '.mhmui-admin'.
 * @return {string} CSS block, START and END markers included.
 */
function renderTokensBlock( doc, selector ) {
	const map = doc.scopes[ selector ];
	if ( ! map ) {
		throw new Error( `tokens.json has no scope "${ selector }"; refusing to render an empty block.` );
	}
	const names = Object.keys( map );
	const width = Math.max( ...names.map( ( n ) => n.length ) ) + '--mhmui-:'.length;
	const lines = names.map( ( name ) => {
		const prop = `--mhmui-${ name }:`;
		return `\t${ prop.padEnd( width + 1 ) }${ map[ name ] };`;
	} );
	return [ START, `${ selector } {`, ...lines, '}', END ].join( '\n' );
}

/**
 * The legacy flat view of the token document.
 *
 * tokens.json is a public export (package.json `exports`, index.js, the
 * design-system generator). External readers that access the raw file (like
 * build-design-system.py) expect a top-level `tokens` key. We maintain it as
 * a mirror of scopes['.mhmui-admin'], pinned by test gate. This view is
 * deliberately NOT what the generator counts — the generator counts from
 * scopes['.mhmui-admin'] so the count always matches what was rendered.
 *
 * @param {{tokens: Record<string,string>, scopes: Record<string,Record<string,string>>}} doc Parsed tokens.json.
 * @return {Record<string,string>} The legacy flat view (doc.tokens), for external consumers.
 */
function flatTokens( doc ) {
	return doc.tokens;
}

/**
 * Replace the marked block inside a stylesheet.
 *
 * @param {string} css   Stylesheet contents.
 * @param {string} block New block.
 * @return {string} Updated stylesheet.
 */
function replaceBlock( css, block ) {
	const start = css.indexOf( START );
	const end = css.indexOf( END );
	if ( start === -1 || end === -1 || end < start ) {
		throw new Error( 'admin.css has no tokens markers; refusing to guess where the block goes.' );
	}
	return css.slice( 0, start ) + block + css.slice( end + END.length );
}

function main( argv ) {
	const doc = JSON.parse( readFileSync( TOKENS, 'utf8' ) );
	const current = readFileSync( CSS, 'utf8' );
	const next = replaceBlock( current, renderTokensBlock( doc, '.mhmui-admin' ) );

	if ( argv.includes( '--check' ) ) {
		if ( next !== current ) {
			process.stderr.write( 'tokens:check: assets/react/admin.css is stale -- run `npm run tokens:build`.\n' );
			process.exit( 1 );
		}
		process.stdout.write( `tokens:check: ${ Object.keys( doc.scopes[ '.mhmui-admin' ] ).length } token(s) in sync.\n` );
		return;
	}

	writeFileSync( CSS, next );
	process.stdout.write( `tokens:build: wrote ${ Object.keys( doc.scopes[ '.mhmui-admin' ] ).length } token(s) into assets/react/admin.css.\n` );
}

module.exports = { renderTokensBlock, replaceBlock, flatTokens, START, END };

if ( require.main === module ) {
	main( process.argv.slice( 2 ) );
}
