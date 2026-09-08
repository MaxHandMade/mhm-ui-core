import { render, screen, fireEvent } from '@testing-library/react';
import {
	StatCard,
	StatsGrid,
	StatusBadge,
	Pagination,
	ProLock,
	Notice,
	Widget,
	tokens,
} from '../index';

describe( 'the visual kit renders only what it is given', () => {
	test( 'StatCard shows label, value and a delta line in the delta direction', () => {
		render(
			<StatCard
				label="Bookings"
				value="42"
				tone="green"
				delta={ { direction: 'up', text: '+3 this month' } }
			/>
		);
		expect( screen.getByText( 'Bookings' ) ).toBeTruthy();
		expect( screen.getByText( '42' ) ).toBeTruthy();
		const delta = screen.getByText( '+3 this month' );
		expect( delta.className ).toContain( 'mhmui-stat-card__delta--up' );
	} );

	test( 'StatCard falls back to the sub line when the delta is flat', () => {
		render(
			<StatCard
				label="Vehicles"
				value="7"
				sub="12 total"
				delta={ { direction: 'flat', text: 'ignored' } }
			/>
		);
		expect( screen.getByText( '12 total' ) ).toBeTruthy();
		expect( screen.queryByText( 'ignored' ) ).toBeNull();
	} );

	test( 'StatsGrid renders one card per entry', () => {
		const { container } = render(
			<StatsGrid
				cards={ [
					{ label: 'A', value: '1' },
					{ label: 'B', value: '2' },
				] }
				columns={ 2 }
			/>
		);
		expect( container.querySelectorAll( '.mhmui-stat-card' ) ).toHaveLength(
			2
		);
		expect( container.firstChild.style.gridTemplateColumns ).toBe(
			'repeat(2, 1fr)'
		);
	} );

	// StatCard is the kit's ONLY key-figure component. KpiBox was a second one
	// whose only difference was which props it refused (no icon, no sub/delta,
	// a different default tone) -- a fork that would have drifted screen to
	// screen. These two tests pin what the merge decided.
	test( 'StatCard is quiet and iconless until asked otherwise', () => {
		const { container } = render( <StatCard label="Open" value="9" /> );
		// No tone modifier at all: the base class is the quiet bordered box.
		// Colour is opt-in, so a screen cannot become coloured by default.
		expect( container.firstChild.className ).toBe( 'mhmui-stat-card' );
		expect( container.querySelector( '.dashicons' ) ).toBeNull();
	} );

	test( 'StatCard carries the tone and icon it is given', () => {
		const { container } = render(
			<StatCard label="Open" value="9" tone="amber" icon="calendar-alt" />
		);
		expect( container.firstChild.className ).toBe(
			'mhmui-stat-card mhmui-stat-card--amber'
		);
		expect(
			container.querySelector( '.dashicons-calendar-alt' )
		).toBeTruthy();
	} );

	test( 'StatusBadge maps a tone, not a domain status', () => {
		render( <StatusBadge tone="success">Confirmed</StatusBadge> );
		expect( screen.getByText( 'Confirmed' ).className ).toBe(
			'mhmui-status mhmui-status--success'
		);
	} );

	test( 'Pagination disables the edges and reports the new page', () => {
		const onChange = jest.fn();
		const labels = { previous: 'Prev', next: 'Next', of: 'of' };
		const { rerender } = render(
			<Pagination
				page={ 1 }
				totalPages={ 3 }
				onChange={ onChange }
				labels={ labels }
			/>
		);
		expect( screen.getByText( 'Prev' ).disabled ).toBe( true );
		fireEvent.click( screen.getByText( 'Next' ) );
		expect( onChange ).toHaveBeenCalledWith( 2 );

		rerender(
			<Pagination
				page={ 3 }
				totalPages={ 3 }
				onChange={ onChange }
				labels={ labels }
			/>
		);
		expect( screen.getByText( 'Next' ).disabled ).toBe( true );
	} );

	test( 'ProLock shows children only when unlocked, the fallback otherwise', () => {
		const { rerender } = render(
			<ProLock unlocked={ false } fallback="Pro only">
				<b>Secret</b>
			</ProLock>
		);
		expect( screen.queryByText( 'Secret' ) ).toBeNull();
		expect( screen.getByText( 'Pro only' ) ).toBeTruthy();

		rerender(
			<ProLock unlocked={ true } fallback="Pro only">
				<b>Secret</b>
			</ProLock>
		);
		expect( screen.getByText( 'Secret' ) ).toBeTruthy();
		expect( screen.queryByText( 'Pro only' ) ).toBeNull();
	} );

	test( 'Notice takes WordPress notice classes and dismisses through the callback', () => {
		const onDismiss = jest.fn();
		const { container } = render(
			<Notice tone="error" onDismiss={ onDismiss } dismissLabel="Dismiss">
				Broken
			</Notice>
		);
		expect( container.firstChild.className ).toContain( 'notice-error' );
		fireEvent.click( container.querySelector( '.notice-dismiss' ) );
		expect( onDismiss ).toHaveBeenCalled();
	} );

	test( 'Widget renders title, subtitle, actions and body', () => {
		render(
			<Widget
				title="Recent"
				subtitle="last 7 days"
				actions={ <a href="#x">All</a> }
			>
				<p>Body</p>
			</Widget>
		);
		expect( screen.getByText( 'Recent' ) ).toBeTruthy();
		expect( screen.getByText( 'last 7 days' ) ).toBeTruthy();
		expect( screen.getByText( 'All' ) ).toBeTruthy();
		expect( screen.getByText( 'Body' ) ).toBeTruthy();
	} );

	test( 'tokens are exported from the single source', () => {
		expect( tokens.tokens.blue ).toBe( '#2271b1' );
		expect( Object.keys( tokens.tokens ).length ).toBeGreaterThanOrEqual(
			15
		);
	} );
} );
