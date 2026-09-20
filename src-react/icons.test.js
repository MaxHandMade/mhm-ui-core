import { SEED, resolveIcon, registerIcons, resetIcons } from './icons';

describe( 'icon concepts (JSX twin of src/Kit/Icons.php)', () => {
	beforeEach( () => resetIcons() );
	afterEach( () => resetIcons() );

	test( 'a seed concept resolves to its dashicon suffix', () => {
		expect( resolveIcon( 'revenue' ) ).toBe( 'money-alt' );
		expect( resolveIcon( 'place' ) ).toBe( 'location-alt' );
	} );

	test( 'a raw suffix passes through unchanged', () => {
		expect( resolveIcon( 'money-alt' ) ).toBe( 'money-alt' );
		expect( resolveIcon( 'fuel' ) ).toBe( 'fuel' );
	} );

	test( 'the retired key location is NOT a concept', () => {
		expect( resolveIcon( 'location' ) ).toBe( 'location' );
	} );

	test( 'an empty value stays empty', () => {
		expect( resolveIcon( '' ) ).toBe( '' );
	} );

	test( 'a registered concept resolves and wins over the seed', () => {
		registerIcons( { vehicles: 'car', revenue: 'chart-pie' } );

		expect( resolveIcon( 'vehicles' ) ).toBe( 'car' );
		expect( resolveIcon( 'revenue' ) ).toBe( 'chart-pie' );
	} );

	test( 'a malformed entry never becomes a concept', () => {
		registerIcons( { ok: '', bad: [ 'car' ], worse: 7 } );

		expect( resolveIcon( 'ok' ) ).toBe( 'ok' );
		expect( resolveIcon( 'bad' ) ).toBe( 'bad' );
		expect( resolveIcon( 'worse' ) ).toBe( 'worse' );
	} );

	test( 'INHERITED KEYS: a prototype name is not a concept', () => {
		// Plain-object lookup would answer Object.prototype here and the two
		// twins would diverge: JSX printed dashicons-functionObject..., PHP
		// printed dashicons-constructor. The package already knows this class
		// -- StatCard.jsx guards delta.direction with hasOwnProperty for the
		// same reason.
		expect( resolveIcon( 'constructor' ) ).toBe( 'constructor' );
		expect( resolveIcon( 'toString' ) ).toBe( 'toString' );
		expect( resolveIcon( '__proto__' ) ).toBe( '__proto__' );
	} );

	test( 'a non-string value is never resolved and never throws', () => {
		expect( resolveIcon( undefined ) ).toBe( '' );
		expect( resolveIcon( null ) ).toBe( '' );
		expect( resolveIcon( 7 ) ).toBe( '7' );
	} );

	test( 'EMPTY-SET guard: the seed table is not empty', () => {
		expect( Object.keys( SEED ).length ).toBeGreaterThanOrEqual( 12 );
		expect( SEED.revenue ).toBe( 'money-alt' );
	} );
} );
