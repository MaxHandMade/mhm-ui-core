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
const START = '/* mhmui:tokens:start -- generated from src-react/tokens.json by bin/build-tokens.js; do not edit by hand */';
const END = '/* mhmui:tokens:end */';

/**
 * Selectors a scope's block is written under.
 *
 * `.mhm-stats-grid` is a legacy selector a RELEASED consumer still emits.
 * Dropping it would silently unstyle those grids, so the scope carries it as
 * a second selector rather than losing it in the schema change.
 *
 * 🔴 MEASURE IT, DO NOT QUOTE IT. This comment used to read "(six files)" with
 * no version attached, which made it read as a fact about the consumer's
 * current tree. It is not: measured 2026-09-21, Rentiva's `main` emits the
 * class in ZERO files, while its RELEASED tag v6.1.5 -- the version on
 * WordPress.org, and the one the loader serves when a newer sibling wins --
 * emits it in SIX. The number that matters is the released one, and it changes
 * only when a release changes it:
 *   git -C <rentiva> grep -l mhm-stats-grid <released-tag> -- 'src/*' 'src-react/*'
 */
const LEGACY_SELECTORS = { '.mhmui-admin': [ '.mhmui-admin', '.mhm-stats-grid' ] };

/**
 * The fence the generated block carries when it includes a legacy selector.
 *
 * selector-class-pattern forbids .mhm-* in this package (see .stylelintrc.json).
 * The token scope is the one place the package writes a legacy class on
 * purpose, so the fence is generated with it rather than bolted on by hand --
 * the block says "do not edit by hand", and a fence a human has to re-add
 * after every `npm run tokens:build` is a fence that disappears.
 */
const FENCE_OPEN = '/* stylelint-disable selector-class-pattern -- legacy scope selector, see LEGACY_SELECTORS in bin/build-tokens.js */';
const FENCE_CLOSE = '/* stylelint-enable selector-class-pattern */';

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
	const list = LEGACY_SELECTORS[ selector ] || [ selector ];
	const selectors = list.join( ',\n' );
	// Fence only when a legacy selector is actually present: a scope that has
	// migrated must not keep carrying a disable comment for a rule it no longer
	// breaks, or the fence outlives the debt and nobody notices.
	const fenced = list.some( ( s ) => ! s.startsWith( '.mhmui-' ) );
	const block = [ `${ selectors } {`, ...lines, '}' ];
	return [ START, ...( fenced ? [ FENCE_OPEN ] : [] ), ...block, ...( fenced ? [ FENCE_CLOSE ] : [] ), END ].join( '\n' );
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
	const check = argv.includes( '--check' );
	let stale = 0;
	let written = 0;

	for ( const [ selector, target ] of Object.entries( doc.targets ) ) {
		const file = join( ROOT, target );
		const current = readFileSync( file, 'utf8' );
		const next = replaceBlock( current, renderTokensBlock( doc, selector ) );

		if ( check ) {
			if ( next !== current ) {
				process.stderr.write( `tokens:check: ${ target } is stale -- run \`npm run tokens:build\`.\n` );
				stale += 1;
			}
			continue;
		}

		writeFileSync( file, next );
		written += Object.keys( doc.scopes[ selector ] ).length;
	}

	if ( check ) {
		if ( stale > 0 ) {
			process.exit( 1 );
		}
		// Counted from doc.targets, the SAME set the loop above just verified --
		// not from doc.scopes, which can hold a scope with no target. An earlier
		// round fixed exactly this class of bug in the build branch (the loop
		// counts what it wrote, not what merely exists in the source doc); this
		// mirrors that fix on the check branch, where the printed total is a
		// claim about what was just checked, not an independent re-count.
		const total = Object.keys( doc.targets )
			.reduce( ( n, selector ) => n + Object.keys( doc.scopes[ selector ] ).length, 0 );
		process.stdout.write( `tokens:check: ${ total } token(s) in sync across ${ Object.keys( doc.targets ).length } target(s).\n` );
		return;
	}

	process.stdout.write( `tokens:build: wrote ${ written } token(s) across ${ Object.keys( doc.targets ).length } target(s).\n` );
}

module.exports = { renderTokensBlock, replaceBlock, flatTokens, START, END };

if ( require.main === module ) {
	main( process.argv.slice( 2 ) );
}
