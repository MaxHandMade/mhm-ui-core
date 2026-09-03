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
 * Render the custom-property block for a tokens.json document.
 *
 * @param {{scope: string[], tokens: Record<string,string>}} doc Parsed tokens.json.
 * @return {string} CSS block, START and END markers included.
 */
function renderTokensBlock( doc ) {
	const names = Object.keys( doc.tokens );
	const width = Math.max( ...names.map( ( n ) => n.length ) ) + '--mhmui-:'.length;
	const lines = names.map( ( name ) => {
		const prop = `--mhmui-${ name }:`;
		return `\t${ prop.padEnd( width + 1 ) }${ doc.tokens[ name ] };`;
	} );
	return [ START, `${ doc.scope.join( ',\n' ) } {`, ...lines, '}', END ].join( '\n' );
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
	const next = replaceBlock( current, renderTokensBlock( doc ) );

	if ( argv.includes( '--check' ) ) {
		if ( next !== current ) {
			process.stderr.write( 'tokens:check: assets/react/admin.css is stale -- run `npm run tokens:build`.\n' );
			process.exit( 1 );
		}
		process.stdout.write( `tokens:check: ${ Object.keys( doc.tokens ).length } token(s) in sync.\n` );
		return;
	}

	writeFileSync( CSS, next );
	process.stdout.write( `tokens:build: wrote ${ Object.keys( doc.tokens ).length } token(s) into assets/react/admin.css.\n` );
}

module.exports = { renderTokensBlock, replaceBlock, START, END };

if ( require.main === module ) {
	main( process.argv.slice( 2 ) );
}
