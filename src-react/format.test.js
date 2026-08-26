/**
 * The formatter is the module with the most consumers (7 in Lite, 11 in Pro) and the
 * only one whose output reaches money on screen, so its edges are locked here.
 */
import { createFormatter } from './format';

describe( 'createFormatter', () => {
	describe( 'defaults when the host passes nothing', () => {
		const { fmtAmount, fmtMoney } = createFormatter();

		it( 'falls back to comma decimals, dot thousands and two places', () => {
			expect( fmtAmount( 1234.5 ) ).toBe( '1.234,50' );
		} );

		it( 'falls back to right_space placement, matching CurrencyHelper', () => {
			expect( fmtMoney( 12, '₺' ) ).toBe( '12,00 ₺' );
		} );
	} );

	describe( 'fmtAmount', () => {
		const { fmtAmount } = createFormatter( {
			decimalSep: '.',
			thousandSep: ',',
			numDecimals: 2,
		} );

		it( 'groups thousands', () => {
			expect( fmtAmount( 1234567.89 ) ).toBe( '1,234,567.89' );
		} );

		it( 'leaves values below a thousand ungrouped', () => {
			expect( fmtAmount( 999 ) ).toBe( '999.00' );
		} );

		it( 'groups exactly at the thousand boundary', () => {
			expect( fmtAmount( 1000 ) ).toBe( '1,000.00' );
		} );

		it( 'treats null and undefined as zero rather than printing NaN', () => {
			expect( fmtAmount( null ) ).toBe( '0.00' );
			expect( fmtAmount( undefined ) ).toBe( '0.00' );
		} );

		it( 'drops the decimal separator entirely when decimals are zero', () => {
			expect( fmtAmount( 1500, 0 ) ).toBe( '1,500' );
		} );

		it( 'lets an explicit decimals argument override the configured count', () => {
			expect( fmtAmount( 1.005, 3 ) ).toBe( '1.005' );
		} );

		it( 'accepts a zero decimals override, which must not read as "no argument"', () => {
			expect( fmtAmount( 7.6, 0 ) ).toBe( '8' );
		} );

		it( 'formats negative values without breaking the grouping', () => {
			expect( fmtAmount( -1234.5 ) ).toBe( '-1,234.50' );
		} );
	} );

	describe( 'fmtMoney placement', () => {
		const build = ( currencyPosition ) =>
			createFormatter( {
				decimalSep: ',',
				thousandSep: '.',
				numDecimals: 2,
				currencyPosition,
			} );

		it.each( [
			[ 'left', '₺1.234,50' ],
			[ 'left_space', '₺ 1.234,50' ],
			[ 'right', '1.234,50₺' ],
			[ 'right_space', '1.234,50 ₺' ],
		] )( 'places the symbol for %s', ( position, expected ) => {
			expect( build( position ).fmtMoney( 1234.5, '₺' ) ).toBe(
				expected
			);
		} );

		it( 'falls back to right_space for an unknown position', () => {
			expect( build( 'sideways' ).fmtMoney( 1234.5, '₺' ) ).toBe(
				'1.234,50 ₺'
			);
		} );
	} );

	it( 'does not read any global — two formatters stay independent', () => {
		const tr = createFormatter( {
			decimalSep: ',',
			thousandSep: '.',
			numDecimals: 2,
		} );
		const us = createFormatter( {
			decimalSep: '.',
			thousandSep: ',',
			numDecimals: 2,
		} );

		expect( tr.fmtAmount( 1234.5 ) ).toBe( '1.234,50' );
		expect( us.fmtAmount( 1234.5 ) ).toBe( '1,234.50' );
	} );
} );
