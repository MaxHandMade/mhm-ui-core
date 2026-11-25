/**
 * @jest-environment node
 */
import { createElement } from '@wordpress/element';
import { renderToString } from 'react-dom/server';
import DataTable from '../../src-react/components/DataTable';
import SelectAll from '../../src-react/components/SelectAll';
import Widget from '../../src-react/components/Widget';

/**
 * Rendered on a server, the kit's layout effects stay silent. Before 0.26.0
 * each logged React 18's "useLayoutEffect does nothing on the server"
 * (@wordpress/jest-console turns an unexpected console.error into a failure).
 */
describe( 'server rendering', () => {
	test( 'there is no window here (the node environment)', () => {
		expect( typeof window ).toBe( 'undefined' );
	} );

	test.each( [
		[
			'DataTable (stack)',
			() =>
				createElement(
					DataTable,
					{ caption: 'C', layout: 'stack' },
					createElement( 'tbody' )
				),
		],
		[
			'SelectAll',
			() =>
				createElement( SelectAll, {
					state: 'some',
					onChange: () => {},
					label: 'All',
				} ),
		],
		[
			'Widget (collapsible)',
			() =>
				createElement(
					Widget,
					{ title: 'W', collapsible: true },
					'body'
				),
		],
	] )( '%s renders to a string without a warning', ( name, element ) => {
		const html = renderToString( element() );
		expect( html ).toContain( 'mhmui-' );
		expect( console ).not.toHaveErrored();
	} );
} );
