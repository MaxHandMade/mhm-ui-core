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
				tone="success"
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

	test( 'StatCard rol adini modifier olarak tasir, renk adini degil', () => {
		const { container } = render(
			<StatCard
				label="Open"
				value="9"
				tone="success"
				icon="calendar-alt"
			/>
		);
		expect( container.firstChild.className ).toBe(
			'mhmui-stat-card mhmui-stat-card--success'
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
			<Notice
				tone="danger"
				onDismiss={ onDismiss }
				dismissLabel="Dismiss"
			>
				Broken
			</Notice>
		);
		expect( container.firstChild.className ).toContain( 'notice-error' );
		fireEvent.click( container.querySelector( '.notice-dismiss' ) );
		expect( onDismiss ).toHaveBeenCalled();
	} );

	test( 'Notice danger der, error demez -- tek rol sozlugu', () => {
		const { container } = render( <Notice tone="danger">Broken</Notice> );
		expect( container.firstChild.className ).toContain(
			'mhmui-notice--danger'
		);
		// WordPress'in kendi bildirim sinifi KORUNUR: notice-error WP sozlesmesidir.
		expect( container.firstChild.className ).toContain( 'notice-error' );
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
		expect( tokens.tokens.success ).toBe( '#00a32a' );
		expect( Object.keys( tokens.tokens ).length ).toBeGreaterThanOrEqual(
			15
		);
	} );

	test( 'StatsGrid gives CSS a column ceiling, not a track list it cannot override', () => {
		const { container } = render(
			<StatsGrid cards={ [ { label: 'A', value: '1' } ] } columns={ 2 } />
		);
		expect( container.firstChild.style.gridTemplateColumns ).toBe( '' );
		expect(
			container.firstChild.style.getPropertyValue( '--mhmui-columns' )
		).toBe( '2' );
	} );

	test( 'StatsGrid floors the ceiling at one and defaults a non-number to four', () => {
		const zero = render( <StatsGrid cards={ [] } columns={ 0 } /> );
		expect(
			zero.container.firstChild.style.getPropertyValue(
				'--mhmui-columns'
			)
		).toBe( '1' );
		const junk = render( <StatsGrid cards={ [] } columns="wide" /> );
		expect(
			junk.container.firstChild.style.getPropertyValue(
				'--mhmui-columns'
			)
		).toBe( '4' );
	} );

	test( 'StatCard drops a tone or direction outside the vocabulary, like the PHP renderer', () => {
		const { container } = render(
			<StatCard
				label="L"
				value="1"
				tone="purple"
				sub="fallback"
				delta={ { direction: 'sideways', text: 'x' } }
			/>
		);
		expect( container.firstChild.className ).toBe( 'mhmui-stat-card' );
		expect(
			container.querySelector( '.mhmui-stat-card__sub' ).textContent
		).toBe( 'fallback' );
		expect( container.querySelector( '[class*="__delta"]' ) ).toBeNull();
	} );

	test( 'StatCard emphasis is a modifier, and only for literal true', () => {
		const on = render( <StatCard label="L" value="1" emphasis /> );
		expect( on.container.firstChild.className ).toBe(
			'mhmui-stat-card mhmui-stat-card--emphasis'
		);
		const truthy = render(
			<StatCard label="L" value="1" emphasis="yes" />
		);
		expect( truthy.container.firstChild.className ).toBe(
			'mhmui-stat-card'
		);
	} );

	test( 'StatCard data becomes data-* attributes, invalid keys are skipped', () => {
		const { container } = render(
			<StatCard
				label="L"
				value="1"
				data={ {
					stat: 'active_addons',
					'Bad Key': 'x',
					'on-click': 'y',
				} }
			/>
		);
		expect( container.firstChild.getAttribute( 'data-stat' ) ).toBe(
			'active_addons'
		);
		expect( container.firstChild.getAttribute( 'data-on-click' ) ).toBe(
			'y'
		);
		expect( container.firstChild.hasAttribute( 'data-Bad Key' ) ).toBe(
			false
		);
	} );

	test( 'StatCard treats "0" icon/sub as present, like the PHP twin', () => {
		const { container } = render(
			<StatCard label="L" value="1" sub="0" icon="0" />
		);
		const subEl = container.querySelector( '.mhmui-stat-card__sub' );
		expect( subEl ).not.toBeNull();
		expect( subEl.textContent ).toBe( '0' );
		expect( container.querySelector( '.dashicons-0' ) ).not.toBeNull();
	} );
} );
