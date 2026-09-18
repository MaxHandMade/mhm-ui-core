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

/** No `width` declaration on the admin shell: it sits on the SAME element as
 * WP core's own `.wrap` (margin: 10px 20px 0 2px), and a block box already
 * fills its container's content box on its own while respecting its own
 * margins -- that IS "flows full width". Forcing `width: 100%` on top of
 * that margin pushes the box past its container by exactly the horizontal
 * margins (measured 2026-09-18, Chrome 1905px: .wrap.mhmui-admin.mhmui-admin-page
 * grew to 1907, the fourth KPI card clipped, page grew a horizontal
 * scrollbar). The lookbehind excludes `max-width` (and would exclude
 * `min-width`), whose hyphen sits directly before "width:". */
function adminPageFlows( css ) {
	const body = ruleBody( css, '.mhmui-admin-page' );
	return body !== null
		&& /max-width:\s*none/.test( body )
		&& ! /container/.test( body )
		&& ! /(?<!-)width:/.test( body );
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

/** The stat-card dashicon carries the accent colour -- the defect this gate
 * exists to catch (admin.css:311 had no colour at all before the fix, so the
 * icon silently inherited --mhmui-text instead of standing out in the
 * accent). */
function statCardIconIsAccent( css ) {
	const body = ruleBody( css, '.mhmui-stat-card .dashicons' );
	return body !== null && /color:\s*var\(\s*--mhmui-accent\s*\)/.test( body );
}

/** The delta line is green climbing, red falling. The non-colour cue WCAG
 * 1.4.1 requires no longer lives in the stylesheet -- it lives in the
 * consumer's own delta text (an arrow or a sign, per the `delta` docblock /
 * README), so the falling rule must NOT reintroduce an underline (or any
 * other text-decoration) on top of that. */
function statCardDeltaHasDirectionColour( css ) {
	const up = ruleBody( css, '.mhmui-stat-card__delta--up' );
	const down = ruleBody( css, '.mhmui-stat-card__delta--down' );
	return up !== null && down !== null
		&& /color:\s*var\(\s*--mhmui-success-strong\s*\)/.test( up )
		&& /color:\s*var\(\s*--mhmui-danger-strong\s*\)/.test( down )
		&& ! /text-decoration/.test( down );
}

/** The direction mark (StatCard's aria-hidden ↑/↓) inherits the delta line's
 * colour and keeps a small gap from the text -- it must not disappear
 * (display:none / visibility:hidden) or lose its colour, either of which
 * would silently drop the non-colour cue WCAG 1.4.1 needs even though the
 * coloured delta line still renders fine. */
function deltaMarkInheritsColourAndHasSpacing( css, selector ) {
	const body = ruleBody( css, selector );
	return body !== null
		&& /color:\s*inherit/.test( body )
		&& /margin/.test( body )
		&& ! /display:\s*none/.test( body )
		&& ! /visibility:\s*hidden/.test( body );
}

/** The delta line's accessible-name span (StatCard's optional `delta.label`,
 * 0.13.0+) must be visually hidden but stay IN the accessibility tree: the
 * standard clip-to-1px pattern (position:absolute, 1x1px, clipped, no
 * wrapping), never display:none / visibility:hidden -- either of those
 * removes it from the accessibility tree too, which defeats the entire
 * point of adding it (P1: direction invisible to assistive technology). */
function deltaSrIsVisuallyHidden( css, selector ) {
	const body = ruleBody( css, selector );
	return body !== null
		&& /position:\s*absolute/.test( body )
		&& /width:\s*1px/.test( body )
		&& /height:\s*1px/.test( body )
		&& /overflow:\s*hidden/.test( body )
		&& /clip(-path)?:/.test( body )
		&& ! /display:\s*none/.test( body )
		&& ! /visibility:\s*hidden/.test( body );
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
 * specificity rule, not the zero specificity the header comment promises.
 *
 * `minCount` guards the check from passing vacuously: a stylesheet with zero
 * `:where(` selectors used to read as "every one of them is fully wrapped"
 * (true over an empty set) even though the six skin rules this gate exists
 * to police had silently gone missing. Default 1 rejects that empty case for
 * any caller; the real front.css assertion below raises it to 6, the actual
 * count of skin rules (measured 2026-09-17). */
function allWhereSelectorsFullyWrapped( css, minCount = 1 ) {
	const code = css.replace( /\/\*[\s\S]*?\*\//g, '' );
	const ruleRe = /([^{}]+)\{[^}]*\}/g;
	let m;
	let found = 0;
	while ( ( m = ruleRe.exec( code ) ) !== null ) {
		for ( const raw of splitTopLevelCommas( m[ 1 ] ) ) {
			const sel = raw.trim();
			if ( ! sel.includes( ':where(' ) ) {
				continue;
			}
			found++;
			if ( ! isFullyWrappedInWhere( sel ) ) {
				return false;
			}
		}
	}
	return found >= minCount;
}

/** L2 (2026-09-18): the front-end hierarchy typography -- the value
 * dominating its label, the thing that makes a KPI card a KPI card -- must
 * live OUTSIDE :where(), at normal specificity (0-2-0), not inside the
 * zero-specificity skin block. Measured on a real page (Astra, WooCommerce
 * My Account): a same-page CSS reset at 0-0-1 (Astra's own `main.min.css`,
 * `address, blockquote, body, dd, …, p, … { font-size: 100%; font-weight:
 * inherit }`) beats a 0-0-0 :where() rule every time, so
 * .mhmui-stat-card__value rendered 16px / weight 400 -- identical to its
 * label -- until the declarations moved here.
 *
 * `ruleBody` only matches a rule whose selector text is EXACTLY the string
 * given, so `:where( .mhmui-front .mhmui-stat-card__value )` (a different
 * selector string) is invisible to this check -- putting the declaration
 * back inside :where() makes the UNWRAPPED selector's rule disappear (or,
 * if a stray unwrapped rule with no properties were left behind, its body
 * would no longer contain font-size/font-weight), so this must go red. */
function frontHierarchyOutsideWhere( css ) {
	const value = ruleBody( css, '.mhmui-front .mhmui-stat-card__value' );
	const label = ruleBody( css, '.mhmui-front .mhmui-stat-card__label' );
	return value !== null && /font-size\s*:/.test( value ) && /font-weight\s*:/.test( value )
		&& label !== null && /font-size\s*:/.test( label );
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

	test( 'admin.css stat-card icon is the accent colour and the delta line is coloured by direction', () => {
		expect( statCardIconIsAccent( admin ) ).toBe( true );
		expect( statCardDeltaHasDirectionColour( admin ) ).toBe( true );
	} );

	test( 'the delta direction mark inherits the delta colour and keeps a gap from the text, in both stylesheets', () => {
		expect( deltaMarkInheritsColourAndHasSpacing( admin, '.mhmui-stat-card__delta-mark' ) ).toBe( true );
		expect( deltaMarkInheritsColourAndHasSpacing( front, '.mhmui-front .mhmui-stat-card__delta-mark' ) ).toBe( true );
	} );

	test( 'the delta accessible-name span is visually hidden but stays in the accessibility tree, in both stylesheets', () => {
		expect( deltaSrIsVisuallyHidden( admin, '.mhmui-stat-card__delta-sr' ) ).toBe( true );
		expect( deltaSrIsVisuallyHidden( front, '.mhmui-front .mhmui-stat-card__delta-sr' ) ).toBe( true );
	} );

	test( 'front.css skin rules wrap the WHOLE selector in :where(), not just the .mhmui-front ancestor', () => {
		// minCount 6: the actual count of skin rules (measured 2026-09-17) --
		// not just "every :where( selector found is fully wrapped", which is
		// vacuously true if the six rules themselves went missing.
		expect( allWhereSelectorsFullyWrapped( front, 6 ) ).toBe( true );
	} );

	test( 'front.css hierarchy declarations (value font-size/font-weight, label font-size) live OUTSIDE :where() at 0-2-0', () => {
		expect( frontHierarchyOutsideWhere( front ) ).toBe( true );
	} );

	test( 'the checks are not vacuous: a capped admin shell and an uncontained front shell go red', () => {
		expect( adminPageFlows( '.mhmui-admin-page { max-width: 1200px; }' ) ).toBe( false );
		expect( adminPageFlows( '.mhmui-admin-page { max-width: none; container: x / inline-size; }' ) ).toBe( false );
		// The regression this gate was added for (2026-09-18): `width: 100%`
		// on the SAME element as WP core's own `.wrap` (non-zero horizontal
		// margin) overflows the container by exactly that margin.
		expect( adminPageFlows( '.mhmui-admin-page { width: 100%; max-width: none; box-sizing: border-box; }' ) ).toBe( false );
		expect( frontPageCentresAndContains( '.mhmui-front-page { max-width: var(--mhmui-page-max); margin-inline: auto; }' ) ).toBe( false );

		// Only the tone-override selector present (no plain emphasis rule) --
		// this contains the same ".mhmui-stat-card--emphasis .mhmui-stat-card__value"
		// substring, which is exactly what made the old substring-regex test vacuous.
		expect( emphasisPlainRuleSetsAccent(
			':is( .mhmui-stat-card--info ).mhmui-stat-card--emphasis .mhmui-stat-card__value { color: inherit; }'
		) ).toBe( false );

		// Icon rule present but the colour got dropped -- would silently
		// go back to inheriting page text instead of the accent.
		expect( statCardIconIsAccent( '.mhmui-stat-card .dashicons { font-size: 28px; }' ) ).toBe( false );

		// Neither delta direction has a colour at all.
		expect( statCardDeltaHasDirectionColour(
			'.mhmui-stat-card__delta--up { } .mhmui-stat-card__delta--down { }'
		) ).toBe( false );

		// Up has its colour, but down lost its -- still fails, both directions
		// are required.
		expect( statCardDeltaHasDirectionColour(
			'.mhmui-stat-card__delta--up { color: var( --mhmui-success-strong ); } .mhmui-stat-card__delta--down { }'
		) ).toBe( false );

		// Both colours present, but an underline crept back onto --down --
		// the non-colour cue now lives in the consumer's delta text (an arrow
		// or sign), not the stylesheet, so a reintroduced text-decoration
		// must fail this check.
		expect( statCardDeltaHasDirectionColour(
			'.mhmui-stat-card__delta--up { color: var( --mhmui-success-strong ); } .mhmui-stat-card__delta--down { color: var( --mhmui-danger-strong ); text-decoration: underline; }'
		) ).toBe( false );

		// Direction mark rule present but the colour dropped -- would silently
		// lose the WCAG 1.4.1 cue even though the coloured delta line itself
		// still renders fine.
		expect( deltaMarkInheritsColourAndHasSpacing(
			'.mhmui-stat-card__delta-mark { margin-right: 4px; }',
			'.mhmui-stat-card__delta-mark'
		) ).toBe( false );

		// Colour present but the mark is hidden -- it must never be able to
		// disappear, that IS the non-colour cue.
		expect( deltaMarkInheritsColourAndHasSpacing(
			'.mhmui-stat-card__delta-mark { color: inherit; margin-right: 4px; display: none; }',
			'.mhmui-stat-card__delta-mark'
		) ).toBe( false );
		expect( deltaMarkInheritsColourAndHasSpacing(
			'.mhmui-stat-card__delta-mark { color: inherit; margin-right: 4px; visibility: hidden; }',
			'.mhmui-stat-card__delta-mark'
		) ).toBe( false );

		// The rule for the mark itself is missing entirely -- only the delta
		// line's own colour rule is present.
		expect( deltaMarkInheritsColourAndHasSpacing(
			'.mhmui-stat-card__delta { color: red; }',
			'.mhmui-stat-card__delta-mark'
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

		// No :where( selector at all -- "every one of them is fully wrapped"
		// used to be true over an empty set, so a stylesheet that lost all
		// six skin rules would have passed this gate silently.
		expect( allWhereSelectorsFullyWrapped(
			'.mhmui-front .mhmui-stat-card__label { color: red; }'
		) ).toBe( false );

		// L2 regression, mutated: the value's font-size put back inside
		// :where() only, leaving nothing but font-weight on the unwrapped
		// rule -- the reset that motivated this gate would beat font-size
		// again, so this must go red.
		expect( frontHierarchyOutsideWhere(
			'.mhmui-front .mhmui-stat-card__value { font-weight: 600; } '
			+ ':where( .mhmui-front .mhmui-stat-card__value ) { font-size: 1.75rem; } '
			+ '.mhmui-front .mhmui-stat-card__label { font-size: 0.8125rem; }'
		) ).toBe( false );

		// The rule is missing entirely.
		expect( deltaSrIsVisuallyHidden(
			'.mhmui-stat-card__delta { color: red; }',
			'.mhmui-stat-card__delta-sr'
		) ).toBe( false );

		// Not clipped/positioned at all -- would render as plain visible text.
		expect( deltaSrIsVisuallyHidden(
			'.mhmui-stat-card__delta-sr { color: red; }',
			'.mhmui-stat-card__delta-sr'
		) ).toBe( false );

		// display:none -- hides it from the accessibility tree too, defeating
		// the entire point of an accessible-name span.
		expect( deltaSrIsVisuallyHidden(
			'.mhmui-stat-card__delta-sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); display: none; }',
			'.mhmui-stat-card__delta-sr'
		) ).toBe( false );

		// visibility:hidden -- same defect as display:none, via a different
		// property.
		expect( deltaSrIsVisuallyHidden(
			'.mhmui-stat-card__delta-sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); visibility: hidden; }',
			'.mhmui-stat-card__delta-sr'
		) ).toBe( false );
	} );
} );
