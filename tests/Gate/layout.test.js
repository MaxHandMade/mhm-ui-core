const { readFileSync } = require( 'node:fs' );
const { join } = require( 'node:path' );
const postcss = require( 'postcss' );
const { ruleBody, mediaBlock } = require( './helpers' );

const ROOT = join( __dirname, '..', '..' );
const read = ( f ) => readFileSync( join( ROOT, 'assets', 'react', f ), 'utf8' );

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
 * count of skin rules (measured 2026-09-17).
 * Reads through rules() (B-3), so a rule inside an at-rule is seen too. */
function allWhereSelectorsFullyWrapped( css, minCount = 1 ) {
	let found = 0;
	for ( const [ sel ] of rules( css ) ) {
		if ( ! sel.includes( ':where(' ) ) {
			continue;
		}
		found++;
		if ( ! isFullyWrappedInWhere( sel ) ) {
			return false;
		}
	}
	return found >= minCount;
}

/** Every UNWRAPPED rule (selector text, after stripping comments, matched
 * EXACTLY as one top-level item of a possibly comma-separated selector list)
 * whose selector equals `selector` -- ALL of them, in source order, not just
 * the first. Reused by `hierarchyDeclaredExactlyOnce` below; see that
 * function's comment for why "just the first" was the bug.
 * Reads through rules() (B-3), so a rule inside an at-rule is seen too. */
function unwrappedRuleBodiesForSelector( css, selector ) {
	return rules( css )
		.filter( ( [ sel ] ) => sel === selector )
		.map( ( [ , body ] ) => body );
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

	const RHYTHM =
		'> * + :is( .mhmui-stats-grid, .mhmui-widget, .mhmui-pagination, .mhmui-notice, .mhmui-tabs, .mhmui-page-header, .mhmui-detail-layout )';

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

	/** Branch audit 2026-09-23 (M-5, re-graded Important): a sticky aside
	 * taller than the viewport stays pinned at its top, so its bottom -- the
	 * decision buttons under an open reason field -- sits off screen until the
	 * main column ends, and keyboard focus landing there is not scrolled into
	 * view. The aside is capped at the viewport and scrolls on its own. */
	test( 'the sticky detail aside never outgrows the viewport', () => {
		const body = ruleBody( admin, '.mhmui-detail-layout__aside' );
		expect( body ).not.toBeNull();
		expect( body ).toMatch( /position:\s*sticky/ );
		expect( body ).toMatch( /max-height:\s*calc\(\s*100vh\s*-/ );
		expect( body ).toMatch( /overflow-y:\s*auto/ );
	} );

	test( 'ConfirmButton targets are at least 44px (WCAG 2.2 2.5.8 asks 24; the kit asks 44)', () => {
		const body = ruleBody(
			admin,
			'.mhmui-confirm:is( .mhmui-confirm--primary, .mhmui-confirm--secondary, .mhmui-confirm--danger ) button[type="button"].mhmui-confirm__trigger,\n.mhmui-confirm:is( .mhmui-confirm--primary, .mhmui-confirm--secondary, .mhmui-confirm--danger ) button[type="button"].mhmui-confirm__confirm,\n.mhmui-confirm:is( .mhmui-confirm--primary, .mhmui-confirm--secondary, .mhmui-confirm--danger ) button[type="button"].mhmui-confirm__cancel'
		);
		expect( body ).not.toBeNull();
		expect( body ).toMatch( /min-height:\s*44px/ );
	} );

	/** Finding 7 (2026-09-20, final fix wave): the OLD check only banned the
	 * literal shape `> * + *`, so `.mhmui-admin-page > * + p` or
	 * `> *:not(h1) + *` still targeted a core-owned element (any `p`, any
	 * unwrapped `*`) while staying green -- the gate's name promised more than
	 * its regex measured. Narrowed the name, widened the ban: whatever the
	 * LAST combinator in a shell-prefixed selector introduces must be a
	 * `:is(...)` group -- exactly the shape the real rhythm rule already uses
	 * (`.mhmui-admin-page > * + :is( .mhmui-stats-grid, … )`). Comments are
	 * stripped first (ruleBody()'s own habit, and kit-parity.test.js's
	 * classUniverse()) so a docblock describing the ban is never mistaken for
	 * a violation of it.
	 * Reads through rules() (B-3), so a rule inside an at-rule is seen too. */
	function rhythmTargetsAreWrapped( css ) {
		let found = 0;
		for ( const [ sel ] of rules( css ) ) {
			const shellMatch = sel.match( /mhmui-(?:admin|front)-page\s*>\s*[^+{}]*\+\s*(.*)$/ );
			if ( ! shellMatch ) {
				continue;
			}
			found++;
			if ( ! shellMatch[ 1 ].startsWith( ':is(' ) ) {
				return false;
			}
		}
		return found >= 1;
	}

	test( 'the rhythm never targets core-owned elements', () => {
		// The real rule wraps its target in :is(...) -- this must stay green
		// for both shipped stylesheets.
		for ( const css of [ admin, front ] ) {
			expect( rhythmTargetsAreWrapped( css ) ).toBe( true );
		}
	} );

	test( 'M15 mutation: `> * + p` (an unwrapped tag, the bypass the finding named) goes red', () => {
		expect( rhythmTargetsAreWrapped(
			'.mhmui-admin-page > * + p { margin-block-start: var( --mhmui-space-3 ); }'
		) ).toBe( false );
	} );

	test( 'M15 mutation: `> * + *` (the OLD literal ban, still unwrapped) goes red', () => {
		expect( rhythmTargetsAreWrapped(
			'.mhmui-admin-page > * + * { margin-block-start: var( --mhmui-space-3 ); }'
		) ).toBe( false );
	} );

	test( 'M15 baseline: the shipped shape (`+ :is(...)`) is green', () => {
		expect( rhythmTargetsAreWrapped(
			'.mhmui-admin-page > * + :is( .mhmui-stats-grid, .mhmui-widget ) { margin-block-start: var( --mhmui-space-3 ); }'
		) ).toBe( true );
	} );

	test( 'the check is not vacuous: ruleBody finds nothing for an absent selector', () => {
		expect( ruleBody( admin, '.mhmui-admin-page > * + * + *' ) ).toBeNull();
	} );

	test( 'rhythmTargetsAreWrapped is not vacuous: no shell-prefixed combinator selector at all', () => {
		expect( rhythmTargetsAreWrapped( '.mhmui-stats-grid { margin: 0; }' ) ).toBe( false );
	} );
} );

/**
 * Specificity [ids, classes, types] of one complex selector. :where() counts
 * zero; :is()/:not()/:has() count their most specific argument; any other
 * pseudo-class counts as a class; pseudo-elements count as a type.
 */
function specificity( selector ) {
	let s = selector;
	const score = [ 0, 0, 0 ];
	const add = ( v ) => {
		score[ 0 ] += v[ 0 ];
		score[ 1 ] += v[ 1 ];
		score[ 2 ] += v[ 2 ];
	};
	const re = /:(where|is|not|has)\(/;
	let m;
	while ( ( m = re.exec( s ) ) !== null ) {
		let depth = 1;
		let i = m.index + m[ 0 ].length;
		for ( ; i < s.length && depth > 0; i++ ) {
			depth += s[ i ] === '(' ? 1 : s[ i ] === ')' ? -1 : 0;
		}
		const inner = s.slice( m.index + m[ 0 ].length, i - 1 );
		if ( m[ 1 ] !== 'where' ) {
			const best = splitTopLevelCommas( inner )
				.map( ( a ) => specificity( a.trim() ) )
				.sort( compareSpecificity )
				.pop();
			add( best );
		}
		s = s.slice( 0, m.index ) + ' ' + s.slice( i );
	}
	s = s.replace( /\[[^\]]*\]/g, () => {
		score[ 1 ]++;
		return ' ';
	} );
	s = s.replace( /::[\w-]+/g, () => {
		score[ 2 ]++;
		return ' ';
	} );
	score[ 0 ] += ( s.match( /#[\w-]+/g ) || [] ).length;
	score[ 1 ] += ( s.match( /\.[\w-]+/g ) || [] ).length;
	score[ 1 ] += ( s.match( /:[\w-]+/g ) || [] ).length;
	score[ 2 ] += ( s.replace( /[#.:][\w-]+/g, ' ' ).match( /(^|[\s>+~])[a-z][\w-]*/gi ) || [] ).length;
	return score;
}

function compareSpecificity( a, b ) {
	return a[ 0 ] - b[ 0 ] || a[ 1 ] - b[ 1 ] || a[ 2 ] - b[ 2 ];
}

/**
 * Every [ selector, body, atRules ] triple in a stylesheet, in source order.
 *
 * Reads through postcss (audit of #36, B-3; plan audit Codex F2): a rule
 * inside @media / @container / @supports / @keyframes comes back with the
 * at-rule chain in `atRules`, outermost first; an unconditional rule has
 * `[]`. `body` is the rule's declarations as `prop: value;`, one space apart,
 * `!important` kept. CSS postcss cannot parse, and a style rule nested in
 * another (CSS nesting, which this package does not write), THROW: a gate
 * that cannot read its input fails loudly instead of measuring nothing.
 */
function rules( css ) {
	let root;
	try {
		root = postcss.parse( css );
	} catch ( error ) {
		throw new Error( `rules(): ${ error.message }` );
	}
	const out = [];
	root.walkRules( ( rule ) => {
		const atRules = [];
		for ( let p = rule.parent; p && p.type !== 'root'; p = p.parent ) {
			if ( p.type === 'rule' ) {
				throw new Error(
					`rules(): nested style rule under "${ p.selector }" is not supported`
				);
			}
			atRules.unshift( `@${ p.name } ${ p.params }`.trim() );
		}
		const body = rule.nodes
			.filter( ( n ) => n.type === 'decl' )
			.map(
				( d ) =>
					`${ d.prop }: ${ d.value }${ d.important ? ' !important' : '' };`
			)
			.join( ' ' );
		for ( const sel of rule.selectors ) {
			out.push( [ sel.trim(), body, atRules ] );
		}
	} );
	return out;
}

/**
 * ConfirmButton renders WordPress's `.button` and lives in wp-admin, so every
 * rule the kit sets on it competes with core's buttons.css. Measured on WP
 * 7.1.2 (wp-includes/css/buttons.css): `.wp-core-ui .button` (0,2,0) sets
 * min-height 40px and a transparent background; `.wp-core-ui .button:hover`,
 * `:focus`, `:active` (0,3,0) set background, border and colour. The 0.15.0
 * kit rules were (0,1,0) and (0,2,0): in the browser the targets measured 40px
 * and a focused primary confirm lost its fill (Rentiva consumer check,
 * 2026-09-24). The layout test above read the CSS TEXT and stayed green.
 * These tests read what decides the cascade: specificity.
 */
describe( 'ConfirmButton wins the cascade against WordPress core buttons', () => {
	const admin = read( 'admin.css' );
	const all = rules( admin );
	const CORE_STATE = [ 0, 3, 0 ]; // .wp-core-ui .button:focus (and :hover, :active)
	// The heaviest rule core sizes a .button with, measured on WP 7.1.2:
	// `.wp-core-ui .button-group.button-{compact,small,large,hero} .button`
	// (buttons.css:74-104) at (0,4,0) -- the small group sets 24px. Below
	// that: `.wp-core-ui .tablenav .button` (forms.css:564, 32px; 40px under
	// 782px, :1774) and `.wp-core-ui .button.button-small` (buttons.css:82)
	// at (0,3,0). A ConfirmButton in a list table's tablenav fell to 32px at
	// (0,2,1) (audit of #36, B-6; plan audit, Codex F1).
	const CORE_CONTEXT = [ 0, 4, 0 ];

	test( 'the specificity helper agrees with the spec on known selectors', () => {
		expect( specificity( '.wp-core-ui .button' ) ).toEqual( [ 0, 2, 0 ] );
		expect( specificity( '.wp-core-ui .button:focus' ) ).toEqual( [ 0, 3, 0 ] );
		expect( specificity( '.a :is( .b, #c )' ) ).toEqual( [ 1, 1, 0 ] );
		expect( specificity( ':where( .a ) .b' ) ).toEqual( [ 0, 1, 0 ] );
		expect( specificity( 'input#publish' ) ).toEqual( [ 1, 0, 1 ] );
		expect( specificity( '.wp-core-ui .tablenav .button' ) ).toEqual( [ 0, 3, 0 ] );
		expect( specificity( '.wp-core-ui .button-group.button-small .button' ) ).toEqual( [ 0, 4, 0 ] );
		expect(
			specificity(
				'.mhmui-confirm:is( .mhmui-confirm--primary, .mhmui-confirm--danger ) button[type="button"].mhmui-confirm__trigger'
			)
		).toEqual( [ 0, 4, 1 ] );
	} );

	// A rule inside @media/@container wins only under its condition, so it
	// never counts as the rule that beats core everywhere (B-3).
	test( 'every 44px target outranks core, in every context core sizes a .button', () => {
		const targets = all.filter(
			( [ sel, body, at ] ) =>
				at.length === 0 &&
				/min-height:\s*44px/.test( body ) &&
				/mhmui-confirm__/.test( sel )
		);
		for ( const part of [
			'mhmui-confirm__trigger',
			'mhmui-confirm__confirm',
			'mhmui-confirm__cancel',
		] ) {
			expect( [ part, targets.some( ( [ sel ] ) => sel.includes( part ) ) ] ).toEqual( [ part, true ] );
		}
		for ( const [ sel ] of targets ) {
			expect( [ sel, compareSpecificity( specificity( sel ), CORE_CONTEXT ) > 0 ] ).toEqual( [ sel, true ] );
		}
	} );

	test.each( [
		[ 'primary', 'background' ],
		[ 'primary', 'border-color' ],
		[ 'danger', 'color' ],
		[ 'danger', 'border-color' ],
		// Core tints a hovered/active .button with the theme colour; a danger
		// button must not turn blue-ish (audit of #36, B-2).
		[ 'danger', 'background' ],
	] )( 'the %s variant keeps its %s on :focus, :hover and :active', ( variant, prop ) => {
		for ( const state of [ 'focus', 'hover', 'active' ] ) {
			const winners = all.filter(
				( [ sel, body, at ] ) =>
					at.length === 0 &&
					sel.includes( `mhmui-confirm--${ variant }` ) &&
					sel.includes( 'mhmui-confirm__confirm' ) &&
					sel.includes( `:${ state }` ) &&
					new RegExp( `(^|[;\\s])${ prop }\\s*:` ).test( body ) &&
					compareSpecificity( specificity( sel ), CORE_STATE ) > 0
			);
			expect( [ variant, state, winners.length > 0 ] ).toEqual( [ variant, state, true ] );
		}
	} );

	// The ring is the visible half of focus: core's :focus box-shadow (0,3,0)
	// must not win (audit of #36, B-4).
	test.each( [ [ 'primary' ], [ 'danger' ] ] )( 'the %s focus ring outranks core', ( variant ) => {
		const ring = all.filter(
			( [ sel, body, at ] ) =>
				at.length === 0 &&
				sel.includes( `mhmui-confirm--${ variant }` ) &&
				sel.includes( 'mhmui-confirm__confirm' ) &&
				sel.includes( ':focus' ) &&
				/(^|[;\s])box-shadow\s*:/.test( body ) &&
				compareSpecificity( specificity( sel ), CORE_STATE ) > 0
		);
		expect( [ variant, ring.length > 0 ] ).toEqual( [ variant, true ] );
	} );

	/**
	 * Busy and locked buttons are aria-disabled, focusable, and show busyText.
	 * Core forces their text to #8a8a8a !important (buttons.css:224-228) --
	 * a !important the kit cannot and must not fight. So the fill has to move
	 * instead, as core does for .button-primary[disabled]: grey text on the
	 * accent fill measured 1.50:1, and 1.26:1 under the old opacity (audit of
	 * #36, B-1/B-5).
	 */
	test( 'an aria-disabled button carries no opacity', () => {
		const faded = all.filter( ( [ sel, body ] ) => sel.includes( 'aria-disabled' ) && /(^|[;\s])opacity\s*:/.test( body ) );
		expect( faded.map( ( [ sel ] ) => sel ) ).toEqual( [] );
	} );

	test( 'a locked primary drops its fill, and that rule wins over the primary state rules', () => {
		const order = all.map( ( [ sel ] ) => sel );
		const stateRules = all.filter(
			( [ sel, body ] ) => sel.includes( 'mhmui-confirm--primary' ) && ! sel.includes( 'aria-disabled' ) && /(^|[;\s])background\s*:/.test( body )
		);
		const locked = all.filter(
			( [ sel, body, at ] ) =>
				at.length === 0 &&
				sel.includes( 'mhmui-confirm--primary' ) &&
				sel.includes( 'aria-disabled="true"' ) &&
				/(^|[;\s])background\s*:\s*var\(\s*--mhmui-surface\s*\)/.test( body )
		);
		expect( locked.length ).toBeGreaterThan( 0 );
		expect( stateRules.length ).toBeGreaterThan( 0 );
		const [ lockedSel ] = locked[ locked.length - 1 ];
		for ( const [ sel ] of stateRules ) {
			const cmp = compareSpecificity( specificity( lockedSel ), specificity( sel ) );
			const later = order.lastIndexOf( lockedSel ) > order.indexOf( sel );
			expect( [ sel, cmp > 0 || ( cmp === 0 && later ) ] ).toEqual( [ sel, true ] );
		}
	} );

	/**
	 * A locked danger confirm kept the danger border and differed from the
	 * live one by text colour alone (core's #8a8a8a). The border goes neutral
	 * too, so the lock has a second cue (audit of #36 round 2, N-2). The
	 * danger state rules are (0,4,1) as well, so the locked rule must come
	 * later in the source.
	 */
	test( 'a locked danger drops its border colour, and that rule wins over the danger state rules', () => {
		const order = all.map( ( [ sel ] ) => sel );
		const stateRules = all.filter(
			( [ sel, body, at ] ) =>
				at.length === 0 &&
				sel.includes( 'mhmui-confirm--danger' ) &&
				! sel.includes( 'aria-disabled' ) &&
				/(^|[;\s])border-color\s*:/.test( body )
		);
		const locked = all.filter(
			( [ sel, body, at ] ) =>
				at.length === 0 &&
				sel.includes( 'mhmui-confirm--danger' ) &&
				sel.includes( 'mhmui-confirm__confirm' ) &&
				sel.includes( 'aria-disabled="true"' ) &&
				/(^|[;\s])border-color\s*:\s*var\(\s*--mhmui-border\s*\)/.test( body )
		);
		expect( locked.length ).toBeGreaterThan( 0 );
		expect( stateRules.length ).toBeGreaterThan( 0 );
		const [ lockedSel ] = locked[ locked.length - 1 ];
		for ( const [ sel ] of stateRules ) {
			const cmp = compareSpecificity( specificity( lockedSel ), specificity( sel ) );
			const later = order.lastIndexOf( lockedSel ) > order.indexOf( sel );
			expect( [ sel, cmp > 0 || ( cmp === 0 && later ) ] ).toEqual( [ sel, true ] );
		}
	} );
} );

describe( 'ConfirmButton compact size (0.16.0)', () => {
	const all = rules( read( 'admin.css' ) );
	const MOBILE = '@media ( max-width: 782px )';
	const PARTS = [ 'trigger', 'confirm', 'cancel' ];
	const minHeightRows = ( part, at ) =>
		all.filter(
			( [ sel, body, a ] ) =>
				a.join( '|' ) === at.join( '|' ) &&
				sel.includes( 'mhmui-confirm--compact' ) &&
				sel.includes( 'mhmui-confirm__' + part ) &&
				/min-height:/.test( body )
		);

	test( 'compact confirm targets are 36px on desktop and 44px at 782px and below, and outrank core', () => {
		for ( const part of PARTS ) {
			const desk = minHeightRows( part, [] );
			const mob = minHeightRows( part, [ MOBILE ] );
			expect( [ part, desk.length > 0, mob.length > 0 ] ).toEqual( [ part, true, true ] );
			for ( const [ sel, body ] of desk ) {
				expect( /min-height:\s*36px/.test( body ) ).toBe( true );
				expect( [ sel, compareSpecificity( specificity( sel ), [ 0, 4, 1 ] ) >= 0 ] ).toEqual( [ sel, true ] );
			}
			for ( const [ sel, body ] of mob ) {
				expect( /min-height:\s*44px/.test( body ) ).toBe( true );
				expect( [ sel, compareSpecificity( specificity( sel ), [ 0, 4, 1 ] ) >= 0 ] ).toEqual( [ sel, true ] );
			}
		}
	} );

	test( 'compact desktop rules outrank the 44px base rules, so 36px wins', () => {
		const base = all.filter(
			( [ sel, body, at ] ) => at.length === 0 && ! sel.includes( '--compact' ) && /min-height:\s*44px/.test( body )
		);
		for ( const part of PARTS ) {
			const [ [ csel ] ] = minHeightRows( part, [] );
			for ( const [ bsel ] of base.filter( ( [ s ] ) => s.includes( 'mhmui-confirm__' + part ) ) ) {
				expect( [ bsel, compareSpecificity( specificity( csel ), specificity( bsel ) ) > 0 ] ).toEqual( [ bsel, true ] );
			}
		}
	} );

	test( 'compact root aligns to the start and the prompt is a box', () => {
		const root = all.find( ( [ s, , a ] ) => a.length === 0 && s === '.mhmui-confirm.mhmui-confirm--compact' );
		expect( root && /align-items:\s*flex-start/.test( root[ 1 ] ) ).toBe( true );
		const box = all.find( ( [ s, , a ] ) => a.length === 0 && s.includes( '--compact' ) && s.includes( 'mhmui-confirm__prompt' ) );
		expect( box ).toBeTruthy();
		expect( box[ 1 ] ).toMatch( /padding:\s*12px/ );
		expect( box[ 1 ] ).toMatch( /background:\s*var\(\s*--mhmui-neutral-soft\s*\)/ );
		expect( box[ 1 ] ).toMatch( /border:\s*1px solid var\(\s*--mhmui-border-divider\s*\)/ );
	} );

	test( 'the compact prompt box stretches while the root keeps the trigger at the start', () => {
		const box = all.find( ( [ s, , a ] ) => a.length === 0 && s.includes( '--compact' ) && s.includes( 'mhmui-confirm__prompt' ) );
		expect( box[ 1 ] ).toMatch( /align-self:\s*stretch/ );
	} );

	test( 'a focused filled danger confirm has a ring that is not the fill colour alone', () => {
		const focus = all.filter(
			( [ s, b, a ] ) =>
				a.length === 0 &&
				s.includes( 'mhmui-confirm--compact' ) &&
				s.includes( 'mhmui-confirm--danger' ) &&
				s.includes( 'mhmui-confirm__confirm' ) &&
				/:focus$/.test( s ) &&
				/box-shadow:\s*0 0 0 2px var\(\s*--mhmui-surface\s*\),\s*0 0 0 4px var\(\s*--mhmui-danger-ink\s*\)/.test( b )
		);
		expect( focus.length ).toBeGreaterThan( 0 );
		const rival = all.filter(
			( [ s, b, a ] ) =>
				a.length === 0 &&
				s.includes( 'mhmui-confirm--danger' ) &&
				! s.includes( '--compact' ) &&
				s.includes( ':focus' ) &&
				/box-shadow/.test( b )
		);
		const order = all.map( ( [ s ] ) => s );
		for ( const [ r ] of rival ) {
			const cmp = compareSpecificity( specificity( focus[ 0 ][ 0 ] ), specificity( r ) );
			expect( [ r, cmp > 0 || ( cmp === 0 && order.indexOf( focus[ 0 ][ 0 ] ) > order.indexOf( r ) ) ] ).toEqual( [ r, true ] );
		}
	} );
} );

describe( 'Button sizes (0.16.0)', () => {
	const all = rules( read( 'admin.css' ) );
	const MOBILE = '@media ( max-width: 782px )';
	const minHeight = ( sel, at ) => {
		const row = all.find( ( [ s, b, a ] ) => s === sel && a.join( '|' ) === at.join( '|' ) && /min-height:/.test( b ) );
		return row ? /min-height:\s*([\d.]+px)/.exec( row[ 1 ] )[ 1 ] : null;
	};

	test( 'button sizes: md 36px, sm 32px, both 44px at 782px and below', () => {
		expect( minHeight( '.mhmui-button--md', [] ) ).toBe( '36px' );
		expect( minHeight( '.mhmui-button--sm', [] ) ).toBe( '32px' );
		expect( minHeight( '.mhmui-button--md', [ MOBILE ] ) ).toBe( '44px' );
		expect( minHeight( '.mhmui-button--sm', [ MOBILE ] ) ).toBe( '44px' );
	} );

	test( 'a natively disabled button is drawn like an aria-disabled one', () => {
		for ( const state of [ ':disabled', '[aria-disabled="true"]' ] ) {
			const row = all.find( ( [ s, , a ] ) => a.length === 0 && s.includes( 'mhmui-button--secondary' ) && s.includes( state ) );
			expect( [ state, !! row ] ).toEqual( [ state, true ] );
		}
	} );
} );


/**
 * The parser every cascade check below stands on (audit of #36, B-3). The
 * first version matched `sel { body }` with one flat regex: a rule inside
 * @media/@container came back as the "selector" `@media ( … )` with a torn
 * body, and the rule itself vanished from every check -- measured 2026-09-26
 * on the two legacy .mhm-stats-grid rules in admin.css. A hand-counted
 * replacement was then measured wrong on braces inside strings and a stray
 * `}` (plan audit, Codex F2 / Fable m-1), so this reads through postcss.
 */
describe( 'rules() reads the stylesheet the way the cascade does', () => {
	test( 'a rule inside an at-rule comes back, with the at-rule prelude', () => {
		const css =
			'.a { color: red; }\n@container ( min-width: 1px ) {\n\t.b, .c { min-height: 44px; }\n}\n.d { color: blue; }';
		expect( rules( css ) ).toEqual( [
			[ '.a', 'color: red;', [] ],
			[ '.b', 'min-height: 44px;', [ '@container ( min-width: 1px )' ] ],
			[ '.c', 'min-height: 44px;', [ '@container ( min-width: 1px )' ] ],
			[ '.d', 'color: blue;', [] ],
		] );
	} );

	test( 'nested at-rules stack outermost first', () => {
		expect(
			rules(
				'@media print { @supports ( display: grid ) { .a { x: y; } } }'
			).map( ( [ sel, , at ] ) => [ sel, at ] )
		).toEqual( [
			[ '.a', [ '@media print', '@supports ( display: grid )' ] ],
		] );
	} );

	test( 'a statement at-rule before a rule is not part of its selector', () => {
		expect(
			rules( '@import url( x.css );\n.a { color: red; }' ).map(
				( [ sel ] ) => sel
			)
		).toEqual( [ '.a' ] );
	} );

	test( 'a brace inside a string is text, not structure', () => {
		expect(
			rules( '.a { content: "}"; min-height: 44px; }\n.b { content: "{"; }' )
		).toEqual( [
			[ '.a', 'content: "}"; min-height: 44px;', [] ],
			[ '.b', 'content: "{";', [] ],
		] );
	} );

	test( '@keyframes steps come back inside their at-rule, so they never count as unconditional', () => {
		expect(
			rules( '@keyframes spin { from { x: 0; } to { x: 1; } }' ).map(
				( [ sel, , at ] ) => [ sel, at ]
			)
		).toEqual( [
			[ 'from', [ '@keyframes spin' ] ],
			[ 'to', [ '@keyframes spin' ] ],
		] );
	} );

	test( 'the shipped stylesheets: no at-rule is read as a selector', () => {
		for ( const f of [ 'admin.css', 'front.css', 'pro.css' ] ) {
			const torn = rules( read( f ) )
				.map( ( [ sel ] ) => sel )
				.filter( ( sel ) => sel.startsWith( '@' ) );
			expect( [ f, torn ] ).toEqual( [ f, [] ] );
		}
		const inMedia = rules( read( 'admin.css' ) )
			.filter( ( [ , , at ] ) => at.length > 0 )
			.map( ( [ sel, , at ] ) => [ sel, at ] );
		expect( inMedia ).toEqual(
			expect.arrayContaining( [
				[ '.mhm-stats-grid', [ '@media ( max-width: 782px )' ] ],
				[ '.mhm-stats-grid', [ '@media ( max-width: 480px )' ] ],
			] )
		);
	} );

	test.each( [
		[ 'an unclosed block', '.a { color: red;' ],
		[ 'a stray closing brace', '.a { color: red; } }' ],
		[ 'a nested style rule', '.a { color: red; .b { color: blue; } }' ],
	] )( '%s throws instead of measuring nothing', ( _name, css ) => {
		expect( () => rules( css ) ).toThrow( /rules\(\)/ );
	} );
} );

describe( 'mediaBlock() lets ruleBody() read inside an @media block', () => {
	const css =
		'.a { x: 1; }\n@media ( max-width: 782px ) {\n\t.b { min-height: 44px; }\n\t.c { y: 2; }\n}\n@media print { .b { z: 3; } }\n@media (max-width:782px) { .d { w: 4; } }';

	test( 'the first rule of a block is readable, and blocks with the same query are joined', () => {
		expect( ruleBody( css, '.b' ) ).toBeNull(); // the gap this helper closes
		const block = mediaBlock( css, '( max-width: 782px )' );
		expect( ruleBody( block, '.b' ).trim() ).toBe( 'min-height: 44px;' );
		expect( ruleBody( block, '.d' ).trim() ).toBe( 'w: 4;' );
		expect( ruleBody( block, '.a' ) ).toBeNull();
	} );

	test( 'an absent query is empty, not the whole sheet', () => {
		expect( mediaBlock( css, '( prefers-reduced-motion: reduce )' ) ).toBe( '' );
	} );
} );

describe( 'collapsible Widget (0.17.0)', () => {
	const admin = read( 'admin.css' );

	test( 'collapsible toggle is a 44px target at 782px and below', () => {
		const body = ruleBody(
			mediaBlock( admin, '( max-width: 782px )' ),
			'.mhmui-widget__toggle'
		);
		expect( body ).not.toBeNull();
		expect( body ).toMatch( /min-height:\s*44px/ );
	} );

	test( 'chevron turns without motion under prefers-reduced-motion', () => {
		// Not vacuous: the chevron does animate by default, and it does turn.
		expect( ruleBody( admin, '.mhmui-widget__chevron' ) ).toMatch(
			/transition:\s*transform/
		);
		expect(
			ruleBody(
				admin,
				'.mhmui-widget__toggle[aria-expanded="true"] .mhmui-widget__chevron'
			)
		).toMatch( /transform:\s*rotate\(\s*180deg\s*\)/ );
		const reduced = ruleBody(
			mediaBlock( admin, '( prefers-reduced-motion: reduce )' ),
			'.mhmui-widget__chevron'
		);
		expect( reduced ).not.toBeNull();
		expect( reduced ).toMatch( /transition:\s*none/ );
	} );

	test( 'a hidden widget body is not displayed', () => {
		const body = ruleBody( admin, '.mhmui-widget__body[hidden]' );
		expect( body ).not.toBeNull();
		expect( body ).toMatch( /display:\s*none/ );
		// It must out-rank every rule that gives the body a display value, or
		// a later card rule would show a closed body again.
		const all = rules( admin );
		const guard = all.findIndex(
			( [ sel, b, at ] ) =>
				sel === '.mhmui-widget__body[hidden]' &&
				at.length === 0 &&
				/display:\s*none/.test( b )
		);
		const rivals = all.filter(
			( [ sel, b ], i ) =>
				i !== guard &&
				/\.mhmui-widget__body(?![\w-])/.test( sel ) &&
				/(^|\s)display:/.test( b )
		);
		for ( const [ sel ] of rivals ) {
			const cmp = compareSpecificity(
				specificity( '.mhmui-widget__body[hidden]' ),
				specificity( sel )
			);
			const later = all.findIndex( ( [ s ] ) => s === sel ) > guard;
			expect( [ sel, cmp > 0 || ( cmp === 0 && ! later ) ] ).toEqual( [ sel, true ] );
		}
	} );
} );

describe( 'the whole collapsible header row toggles', () => {
	const admin = read( 'admin.css' );
	const all = rules( admin );

	test( 'the toggle stretches a ::after over a positioned header, and actions sit above it', () => {
		expect(
			ruleBody( admin, '.mhmui-widget--collapsible .mhmui-widget__header' )
		).toMatch( /position:\s*relative/ );
		const overlay = ruleBody(
			admin,
			'.mhmui-widget--collapsible .mhmui-widget__toggle::after'
		);
		expect( overlay ).toMatch( /content:\s*""/ );
		expect( overlay ).toMatch( /position:\s*absolute/ );
		expect( overlay ).toMatch( /inset:\s*0/ );
		const actions = ruleBody(
			admin,
			'.mhmui-widget--collapsible .mhmui-widget__actions'
		);
		expect( actions ).toMatch( /position:\s*relative/ );
		expect( actions ).toMatch( /z-index:\s*[1-9]/ );
	} );

	test( 'nothing between the header and the overlay is positioned (title, toggle)', () => {
		const positioned = all.filter(
			( [ sel, body ] ) =>
				/mhmui-widget__(title|toggle)(?![\w-])(?!::after)\s*$/.test( sel ) &&
				/(^|\s)position:\s*(relative|absolute|fixed|sticky)/.test( body )
		);
		expect( positioned.map( ( [ sel ] ) => sel ) ).toEqual( [] );
	} );
} );

describe( 'page header and underline tabs fit the admin screen', () => {
	const admin = read( 'admin.css' );

	test( 'page-level h1 cancels the core .wrap h1 padding', () => {
		// WP core common.css: `.wrap h1` (0,1,1) sets padding 9px 0 4px.
		// `h1.mhmui-page-header__title.mhmui-page-header__title` is (0,2,1) and wins.
		const body = ruleBody( admin, 'h1.mhmui-page-header__title.mhmui-page-header__title' );
		expect( body ).not.toBeNull();
		expect( body ).toMatch( /padding:\s*0\s*;/ );
	} );

	test( 'underline tabs scroll inside their bar on narrow containers', () => {
		const nav = ruleBody( admin, '.mhmui-tabs--underline' );
		expect( nav ).toMatch( /overflow-x:\s*auto/ );
		const tab = ruleBody( admin, '.mhmui-tabs--underline .mhmui-tabs__tab' );
		expect( tab ).toMatch( /flex-shrink:\s*0/ );
	} );

	test( 'no negative tab margin under the scroll box; the strip owns the bar-rule overlap', () => {
		const tab = ruleBody( admin, '.mhmui-tabs--underline .mhmui-tabs__tab' );
		expect( tab ).not.toMatch( /margin-bottom:/ );
		const nav = ruleBody( admin, '.mhmui-tabs--underline' );
		expect( nav ).toMatch( /margin-bottom:\s*-1px/ );
	} );

	test( 'the underline tab focus ring is inset so the scroll box cannot clip it', () => {
		const ring = ruleBody( admin, '.mhmui-tabs--underline .mhmui-tabs__tab:focus-visible' );
		expect( ring ).toMatch( /outline:\s*2px solid/ );
		expect( ring ).toMatch( /outline-offset:\s*-2px/ );
	} );
} );
