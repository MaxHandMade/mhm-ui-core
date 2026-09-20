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

/** Every UNWRAPPED rule (selector text, after stripping comments, matched
 * EXACTLY as one top-level item of a possibly comma-separated selector list)
 * whose selector equals `selector` -- ALL of them, in source order, not just
 * the first. Reused by `hierarchyDeclaredExactlyOnce` below; see that
 * function's comment for why "just the first" was the bug. */
function unwrappedRuleBodiesForSelector( css, selector ) {
	const code = css.replace( /\/\*[\s\S]*?\*\//g, '' );
	const ruleRe = /([^{}]+)\{([^}]*)\}/g;
	const bodies = [];
	let m;
	while ( ( m = ruleRe.exec( code ) ) !== null ) {
		for ( const raw of splitTopLevelCommas( m[ 1 ] ) ) {
			if ( raw.trim() === selector ) {
				bodies.push( m[ 2 ] );
			}
		}
	}
	return bodies;
}

/** L2 (2026-09-18), fix round 1 / F1 (reviewer-found regression in the gate
 * itself, 2026-09-18): the front-end hierarchy typography -- the value
 * dominating its label, the thing that makes a KPI card a KPI card -- must
 * live OUTSIDE :where(), at normal specificity (0-2-0), not inside the
 * zero-specificity skin block. Measured on a real page (Astra, WooCommerce
 * My Account): a same-page CSS reset at 0-0-1 (Astra's own `main.min.css`,
 * `address, blockquote, body, dd, …, p, … { font-size: 100%; font-weight:
 * inherit }`) beats a 0-0-0 :where() rule every time, so
 * .mhmui-stat-card__value rendered 16px / weight 400 -- identical to its
 * label -- until the declarations moved here.
 *
 * The first version of this check used `ruleBody()`, which relies on
 * `String.match()` WITHOUT the `g` flag -- it only ever sees the FIRST rule
 * with a given selector. A SECOND, later
 * `.mhmui-front .mhmui-stat-card__value { font-size: 1rem; font-weight: 400; }`
 * -- a plausible bad-merge / bad-rebase reintroduction of the original bug --
 * left that version green, because the first (correct) rule still matched.
 * But a browser resolves an equal-specificity tie by SOURCE ORDER: the LATER
 * rule wins, and the value renders identically to its label again. This
 * version collects EVERY unwrapped rule for the selector and requires the
 * one that declares the hierarchy properties to appear EXACTLY ONCE --
 * zero means the declaration is missing (or still inside :where()), two or
 * more means a later rule can silently overrule the first regardless of what
 * it says. A rule that merely GROUPS this selector for something unrelated
 * -- the margin reset `.mhmui-front .mhmui-stat-card__label, … .mhmui-stat-
 * card__delta { margin: 0; }` -- does not count: it declares none of
 * `properties`, so it is filtered out before counting (exercised below). */
function hierarchyDeclaredExactlyOnce( css, selector, properties ) {
	const bodies = unwrappedRuleBodiesForSelector( css, selector );
	const declaring = bodies.filter( ( body ) => properties.some( ( prop ) => new RegExp( `${ prop }\\s*:` ).test( body ) ) );
	return declaring.length === 1 && properties.every( ( prop ) => new RegExp( `${ prop }\\s*:` ).test( declaring[ 0 ] ) );
}

function frontHierarchyOutsideWhere( css ) {
	return hierarchyDeclaredExactlyOnce( css, '.mhmui-front .mhmui-stat-card__value', [ 'font-size', 'font-weight' ] )
		&& hierarchyDeclaredExactlyOnce( css, '.mhmui-front .mhmui-stat-card__label', [ 'font-size' ] )
		&& hierarchyDeclaredExactlyOnce( css, '.mhmui-front .mhmui-stat-card__sub', [ 'font-size' ] )
		&& hierarchyDeclaredExactlyOnce( css, '.mhmui-front .mhmui-stat-card__delta', [ 'font-size' ] );
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

	test( 'front.css hierarchy declarations (value font-size/font-weight, label/sub/delta font-size) live OUTSIDE :where() at 0-2-0, EXACTLY ONCE each', () => {
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
		// again, so this must go red. (label/sub/delta given valid rules so
		// the failure is attributable to the value mutation specifically.)
		expect( frontHierarchyOutsideWhere(
			'.mhmui-front .mhmui-stat-card__value { font-weight: 600; } '
			+ ':where( .mhmui-front .mhmui-stat-card__value ) { font-size: 1.75rem; } '
			+ '.mhmui-front .mhmui-stat-card__label { font-size: 0.8125rem; } '
			+ '.mhmui-front .mhmui-stat-card__sub, .mhmui-front .mhmui-stat-card__delta { font-size: 0.75rem; }'
		) ).toBe( false );

		// F1 (reviewer-found, 2026-09-18): a SECOND, LATER value rule --
		// e.g. a bad merge/rebase reintroducing the pre-fix declaration --
		// appended after the correct one. The old ruleBody()-based check
		// (String.match without the `g` flag) only ever saw the first match
		// and stayed green here; a browser resolves the equal-specificity
		// tie by source order and renders the LATER rule, so the value goes
		// back to looking identical to its label. This must go red.
		expect( frontHierarchyOutsideWhere(
			'.mhmui-front .mhmui-stat-card__value { font-size: 1.75rem; font-weight: 600; } '
			+ '.mhmui-front .mhmui-stat-card__label { font-size: 0.8125rem; } '
			+ '.mhmui-front .mhmui-stat-card__sub, .mhmui-front .mhmui-stat-card__delta { font-size: 0.75rem; } '
			+ '.mhmui-front .mhmui-stat-card__value { font-size: 1rem; font-weight: 400; }'
		) ).toBe( false );

		// The margin-reset rule groups all four part selectors together for
		// an UNRELATED property -- it must not be mistaken for a second
		// hierarchy-declaring rule. A single, correct hierarchy rule
		// alongside it still reads as "exactly once" and must pass.
		expect( frontHierarchyOutsideWhere(
			'.mhmui-front .mhmui-stat-card__label, .mhmui-front .mhmui-stat-card__value, .mhmui-front .mhmui-stat-card__sub, .mhmui-front .mhmui-stat-card__delta { margin: 0; } '
			+ '.mhmui-front .mhmui-stat-card__value { font-size: 1.75rem; font-weight: 600; } '
			+ '.mhmui-front .mhmui-stat-card__label { font-size: 0.8125rem; } '
			+ '.mhmui-front .mhmui-stat-card__sub, .mhmui-front .mhmui-stat-card__delta { font-size: 0.75rem; }'
		) ).toBe( true );

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

describe( 'vertical rhythm is owned by the page shell (0.14.0)', () => {
	const admin = read( 'admin.css' );
	const front = read( 'front.css' );

	const RHYTHM = '> * + :is( .mhmui-stats-grid, .mhmui-widget, .mhmui-pagination, .mhmui-notice )';

	test( 'the stats grid no longer carries its own outer margin', () => {
		const body = ruleBody( admin, '.mhmui-stats-grid' );
		expect( body ).not.toBeNull();
		expect( body ).not.toMatch( /margin(-top|-block-start)?\s*:/ );
	} );

	test( 'both shells space their kit-member children with --mhmui-space-3', () => {
		for ( const [ name, css, shell ] of [
			[ 'admin.css', admin, '.mhmui-admin-page' ],
			[ 'front.css', front, '.mhmui-front-page' ],
		] ) {
			const body = ruleBody( css, `${ shell } ${ RHYTHM }` );
			expect( [ name, body ] ).not.toEqual( [ name, null ] );
			expect( body ).toMatch( /margin-block-start:\s*var\(\s*--mhmui-space-3\s*\)/ );
		}
	} );

	test( 'the rhythm never targets core-owned elements', () => {
		// Ritim h1/p/.notice'e uygulanirsa core'un bosluklariyla yarisir ve
		// tuketici ekraninda gorunmeyen bir catisma dogar.
		for ( const css of [ admin, front ] ) {
			expect( css ).not.toMatch( /mhmui-(admin|front)-page\s*>\s*\*\s*\+\s*\*/ );
		}
	} );

	test( 'the check is not vacuous: ruleBody finds nothing for an absent selector', () => {
		expect( ruleBody( admin, '.mhmui-admin-page > * + * + *' ) ).toBeNull();
	} );
} );
