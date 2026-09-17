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
		for ( const css of [ admin, front ] ) {
			expect( css ).toMatch( /grid-template-columns:\s*repeat\(\s*auto-fit/ );
			expect( css ).toMatch( /var\(\s*--mhmui-columns\s*\)/ );
		}
	} );

	test( 'front.css declares no font-family and nothing !important', () => {
		const code = front.replace( /\/\*[\s\S]*?\*\//g, '' );
		expect( code ).not.toMatch( /font-family/ );
		expect( code ).not.toMatch( /!important/ );
	} );

	test( 'emphasis has a rule in BOTH stylesheets', () => {
		for ( const css of [ admin, front ] ) {
			expect( css ).toMatch( /\.mhmui-stat-card--emphasis\s+\.mhmui-stat-card__value/ );
		}
	} );

	test( 'the checks are not vacuous: a capped admin shell and an uncontained front shell go red', () => {
		expect( adminPageFlows( '.mhmui-admin-page { max-width: 1200px; }' ) ).toBe( false );
		expect( adminPageFlows( '.mhmui-admin-page { max-width: none; container: x / inline-size; }' ) ).toBe( false );
		expect( frontPageCentresAndContains( '.mhmui-front-page { max-width: var(--mhmui-page-max); margin-inline: auto; }' ) ).toBe( false );
	} );
} );
