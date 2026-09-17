const { readFileSync } = require( 'node:fs' );
const { join } = require( 'node:path' );

const ROOT = join( __dirname, '..', '..' );
const read = ( f ) => readFileSync( join( ROOT, 'assets', 'react', f ), 'utf8' );

/** Body of the first rule whose selector list is exactly `selector`. */
function ruleBody( css, selector ) {
	const code = css.replace( /\/\*[\s\S]*?\*\//g, '' );
	const esc = selector.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
	const m = code.match( new RegExp( `(^|})\\s*${ esc }\\s*{([^}]*)}` ) );
	return m ? m[ 2 ] : null;
}

function adminPageFlows( css ) {
	const body = ruleBody( css, '.mhmui-admin-page' );
	return body !== null && /max-width:\s*none/.test( body ) && ! /container/.test( body );
}

function frontPageCentresAndContains( css ) {
	const body = ruleBody( css, '.mhmui-front-page' );
	return body !== null && /margin-inline:\s*auto/.test( body ) && /container:\s*mhmui-page\s*\/\s*inline-size/.test( body ) && /max-width:\s*var\(\s*--mhmui-page-max\s*\)/.test( body );
}

/** The plain (untoned) emphasis rule sets the accent colour -- not just present
 * as a substring of the tone-override selector, which also contains this text. */
function emphasisPlainRuleSetsAccent( css ) {
	const body = ruleBody( css, '.mhmui-stat-card--emphasis .mhmui-stat-card__value' );
	return body !== null && /color:\s*var\(\s*--mhmui-accent-strong\s*\)/.test( body );
}

/** The grid rule itself wraps from the --mhmui-columns ceiling via auto-fit,
 * not merely present somewhere else in the file. */
function gridWrapsFromColumns( css, selector ) {
	const body = ruleBody( css, selector );
	return body !== null && /repeat\(\s*auto-fit/.test( body ) && /var\(\s*--mhmui-columns\s*\)/.test( body );
}

/** Splits a comma-separated selector list on its TOP-LEVEL commas only --
 * one inside a pseudo-class's parentheses (e.g. `:is( a, b )`) does not end
 * the selector it is part of. */
function splitTopLevelCommas( selectorList ) {
	const parts = [];
	let depth = 0;
	let current = '';
	for ( const ch of selectorList ) {
		if ( ch === '(' ) {
			depth++;
		} else if ( ch === ')' ) {
			depth--;
		}
		if ( ch === ',' && depth === 0 ) {
			parts.push( current );
			current = '';
		} else {
			current += ch;
		}
	}
	parts.push( current );
	return parts;
}

/** True only when `selector` is `:where( ... )` in its entirety -- the
 * :where(...) opens at the very first character and its matching close is
 * the very last, so nothing trails outside it (which would carry its own,
 * non-zero specificity: `:where( .mhmui-front ) .x` is 0-1-0, not 0-0-0). */
function isFullyWrappedInWhere( selector ) {
	if ( ! selector.startsWith( ':where(' ) ) {
		return false;
	}
	let depth = 0;
	for ( let i = 6; i < selector.length; i++ ) {
		if ( selector[ i ] === '(' ) {
			depth++;
		} else if ( selector[ i ] === ')' ) {
			depth--;
			if ( depth === 0 ) {
				return i === selector.length - 1;
			}
		}
	}
	return false;
}

/** Gate for item 1 of the audit: every `:where(` skin selector in the
 * stylesheet must wrap its WHOLE selector, not just the `.mhmui-front`
 * ancestor -- `:where( .mhmui-front ) .x` is 0-1-0 per MDN's :where()
 * specificity rule, not the zero specificity the header comment promises. */
function allWhereSelectorsFullyWrapped( css ) {
	const code = css.replace( /\/\*[\s\S]*?\*\//g, '' );
	const ruleRe = /([^{}]+)\{[^}]*\}/g;
	let m;
	while ( ( m = ruleRe.exec( code ) ) !== null ) {
		for ( const raw of splitTopLevelCommas( m[ 1 ] ) ) {
			const sel = raw.trim();
			if ( sel.includes( ':where(' ) && ! isFullyWrappedInWhere( sel ) ) {
				return false;
			}
		}
	}
	return true;
}

describe( 'page layout standard (spec §3.5)', () => {
	const admin = read( 'admin.css' );
	const front = read( 'front.css' );

	test( 'admin page shell flows full width and is not a query container', () => {
		expect( adminPageFlows( admin ) ).toBe( true );
	} );

	test( 'front page shell centres, caps at page-max and is the mhmui-page container', () => {
		expect( frontPageCentresAndContains( front ) ).toBe( true );
	} );

	test( 'both stats grids wrap from the --mhmui-columns ceiling, not a fixed track list', () => {
		expect( gridWrapsFromColumns( admin, '.mhmui-stats-grid' ) ).toBe( true );
		expect( gridWrapsFromColumns( front, '.mhmui-front .mhmui-stats-grid' ) ).toBe( true );
	} );

	test( 'front.css declares no font-family and nothing !important', () => {
		const code = front.replace( /\/\*[\s\S]*?\*\//g, '' );
		expect( code ).not.toMatch( /font-family/ );
		expect( code ).not.toMatch( /!important/ );
	} );

	test( 'emphasis has a rule in BOTH stylesheets, and it is the rule that sets the accent colour', () => {
		expect( emphasisPlainRuleSetsAccent( admin ) ).toBe( true );
		expect( emphasisPlainRuleSetsAccent( front ) ).toBe( true );
	} );

	test( 'front.css skin rules wrap the WHOLE selector in :where(), not just the .mhmui-front ancestor', () => {
		expect( allWhereSelectorsFullyWrapped( front ) ).toBe( true );
	} );

	test( 'the checks are not vacuous: a capped admin shell and an uncontained front shell go red', () => {
		expect( adminPageFlows( '.mhmui-admin-page { max-width: 1200px; }' ) ).toBe( false );
		expect( adminPageFlows( '.mhmui-admin-page { max-width: none; container: x / inline-size; }' ) ).toBe( false );
		expect( frontPageCentresAndContains( '.mhmui-front-page { max-width: var(--mhmui-page-max); margin-inline: auto; }' ) ).toBe( false );

		// Only the tone-override selector present (no plain emphasis rule) --
		// this contains the same ".mhmui-stat-card--emphasis .mhmui-stat-card__value"
		// substring, which is exactly what made the old substring-regex test vacuous.
		expect( emphasisPlainRuleSetsAccent(
			':is( .mhmui-stat-card--info ).mhmui-stat-card--emphasis .mhmui-stat-card__value { color: inherit; }'
		) ).toBe( false );

		// Grid rule present but fixed tracks, not auto-fit -- would still wrap
		// nothing, since it never yields to the container's available space.
		expect( gridWrapsFromColumns(
			'.mhmui-stats-grid { grid-template-columns: repeat( 4, 1fr ); }',
			'.mhmui-stats-grid'
		) ).toBe( false );

		// Only the .mhmui-front ancestor is zeroed; the descendant compound
		// trails outside the :where(...) and still carries its own
		// specificity (0-1-0) -- exactly the bug this gate exists to catch.
		expect( allWhereSelectorsFullyWrapped(
			':where( .mhmui-front ) .x { }'
		) ).toBe( false );
	} );
} );
