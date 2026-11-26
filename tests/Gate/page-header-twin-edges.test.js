/**
 * @jest-environment node
 */
import { createElement } from '@wordpress/element';
import { renderToStaticMarkup } from 'react-dom/server';
import PageHeader from '../../src-react/components/PageHeader';
import edges from '../Fixtures/page-header-twin-edges.json';

// The same corpus tests/Kit/PageHeaderTest.php draws with the PHP twin: an
// edge either twin handles differently turns one of the two suites red
// (0.27.2). Server markup, not innerHTML, so a no-break space stays the
// character PHP's esc_html() leaves it as.
describe( 'PageHeader -- edge props draw what the PHP twin draws', () => {
	test.each( edges.cases.map( ( c ) => [ c.name, c ] ) )(
		'%s',
		( name, { props, html } ) => {
			expect(
				renderToStaticMarkup( createElement( PageHeader, props ) )
			).toBe( html );
		}
	);
} );
