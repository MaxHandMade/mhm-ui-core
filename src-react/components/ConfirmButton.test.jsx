import { render, screen, fireEvent, act } from '@testing-library/react';
import { useState } from '@wordpress/element';
import { ConfirmButton } from '../index';

const BASE = {
	label: 'Approve',
	confirmText: 'Approve this application?',
	confirmLabel: 'Yes, approve',
	cancelLabel: 'Cancel',
	busyText: 'Working…',
};

function deferred() {
	let resolve;
	let reject;
	const promise = new Promise( ( res, rej ) => {
		resolve = res;
		reject = rej;
	} );
	return { promise, resolve, reject };
}

const open = () =>
	fireEvent.click( screen.getByRole( 'button', { name: 'Approve' } ) );

describe( 'ConfirmButton -- two steps, in the page', () => {
	test( 'opening shows the question and moves focus to confirm', () => {
		render( <ConfirmButton { ...BASE } onConfirm={ jest.fn() } /> );
		open();
		const confirm = screen.getByRole( 'button', { name: 'Yes, approve' } );
		expect( document.activeElement ).toBe( confirm );
		expect( screen.getByText( 'Approve this application?' ) ).toBeTruthy();
		expect(
			screen.queryByRole( 'button', { name: 'Approve' } )
		).toBeNull();
	} );

	test( 'both prompt buttons are described by the question', () => {
		render( <ConfirmButton { ...BASE } onConfirm={ jest.fn() } /> );
		open();
		const textId = screen.getByText( 'Approve this application?' ).id;
		expect( textId ).not.toBe( '' );
		expect(
			screen
				.getByRole( 'button', { name: 'Yes, approve' } )
				.getAttribute( 'aria-describedby' )
		).toBe( textId );
		expect(
			screen
				.getByRole( 'button', { name: 'Cancel' } )
				.getAttribute( 'aria-describedby' )
		).toBe( textId );
	} );

	test( 'two instances get different ids', () => {
		render(
			<>
				<ConfirmButton { ...BASE } onConfirm={ jest.fn() } />
				<ConfirmButton
					{ ...BASE }
					label="Reject"
					onConfirm={ jest.fn() }
				/>
			</>
		);
		open();
		fireEvent.click( screen.getByRole( 'button', { name: 'Reject' } ) );
		const ids = screen
			.getAllByText( 'Approve this application?' )
			.map( ( p ) => p.id );
		expect( new Set( ids ).size ).toBe( 2 );
	} );

	test( 'cancel and Escape close it and return focus to the trigger', () => {
		render( <ConfirmButton { ...BASE } onConfirm={ jest.fn() } /> );
		open();
		fireEvent.click( screen.getByRole( 'button', { name: 'Cancel' } ) );
		expect( document.activeElement ).toBe(
			screen.getByRole( 'button', { name: 'Approve' } )
		);
		open();
		fireEvent.keyDown(
			screen.getByRole( 'button', { name: 'Yes, approve' } ),
			{ key: 'Escape' }
		);
		expect( document.activeElement ).toBe(
			screen.getByRole( 'button', { name: 'Approve' } )
		);
	} );

	test( 'with a body, focus goes to its first focusable element', () => {
		render(
			<ConfirmButton { ...BASE } onConfirm={ jest.fn() }>
				<label htmlFor="r">Reason</label>
				<textarea id="r" />
			</ConfirmButton>
		);
		open();
		expect( document.activeElement ).toBe(
			screen.getByLabelText( 'Reason' )
		);
	} );

	test( 'confirmDisabled: aria-disabled, click does not confirm and focuses the body', () => {
		const onConfirm = jest.fn();
		render(
			<ConfirmButton { ...BASE } confirmDisabled onConfirm={ onConfirm }>
				<textarea aria-label="Reason" />
			</ConfirmButton>
		);
		open();
		const confirm = screen.getByRole( 'button', { name: 'Yes, approve' } );
		expect( confirm.getAttribute( 'aria-disabled' ) ).toBe( 'true' );
		expect( confirm.disabled ).toBe( false );
		confirm.focus();
		fireEvent.click( confirm );
		expect( onConfirm ).not.toHaveBeenCalled();
		expect( document.activeElement ).toBe(
			screen.getByLabelText( 'Reason' )
		);
	} );

	test( 'while busy: both buttons aria-disabled, focus kept, Escape and cancel do nothing', async () => {
		const d = deferred();
		const onConfirm = jest.fn( () => d.promise );
		render( <ConfirmButton { ...BASE } onConfirm={ onConfirm } /> );
		open();
		const confirm = screen.getByRole( 'button', { name: 'Yes, approve' } );
		fireEvent.click( confirm );
		const busy = screen.getByRole( 'button', { name: 'Working…' } );
		expect( busy.getAttribute( 'aria-disabled' ) ).toBe( 'true' );
		expect(
			screen
				.getByRole( 'button', { name: 'Cancel' } )
				.getAttribute( 'aria-disabled' )
		).toBe( 'true' );
		expect( document.activeElement ).toBe( busy );
		fireEvent.keyDown( busy, { key: 'Escape' } );
		fireEvent.click( screen.getByRole( 'button', { name: 'Cancel' } ) );
		fireEvent.click( busy );
		expect(
			screen.queryByRole( 'button', { name: 'Approve' } )
		).toBeNull();
		expect( onConfirm ).toHaveBeenCalledTimes( 1 );
		await act( async () => {
			d.resolve();
			await d.promise;
		} );
		expect( document.activeElement ).toBe(
			screen.getByRole( 'button', { name: 'Approve' } )
		);
	} );

	// No Jest test for a REJECTED or THROWING onConfirm, on purpose. Spec
	// §3.5-5: settle is Promise.resolve( onConfirm() ).finally( reset ) with no
	// catch, so that path always leaves an unhandled rejection -- and Jest
	// fails any test that produces one, from the PARENT process: measured
	// 2026-09-23, the test's own `process` has 0 unhandledRejection listeners,
	// so a test cannot detach the trap. The lock releasing and the error
	// reaching the console are measured in the browser (spec §7.3, the REST
	// 500 row).

	test( 'unmounting while busy leaves focus to the consumer (Review Focus 3)', async () => {
		// React 18 dropped the "state update on an unmounted component" warning,
		// so a console spy proves nothing here. What a person gets is observable:
		// the row is gone, the consumer moved focus elsewhere, and the late
		// settle must not throw or pull focus.
		const d = deferred();
		const { unmount } = render(
			<>
				<button type="button">Elsewhere</button>
				<ConfirmButton { ...BASE } onConfirm={ () => d.promise } />
			</>
		);
		const elsewhere = screen.getByRole( 'button', { name: 'Elsewhere' } );
		open();
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Yes, approve' } )
		);
		unmount();
		document.body.appendChild( elsewhere );
		elsewhere.focus();
		await act( async () => {
			d.resolve();
			await d.promise;
		} );
		expect( document.activeElement ).toBe( elsewhere );
		elsewhere.remove();
	} );

	test( 'disabled arriving while open locks both prompt buttons -- a sibling in the same row', async () => {
		const d = deferred();
		function Row() {
			const [ busy, setBusy ] = useState( false );
			const run = () => {
				setBusy( true );
				return d.promise.finally( () => setBusy( false ) );
			};
			return (
				<>
					<ConfirmButton
						{ ...BASE }
						disabled={ busy }
						onConfirm={ run }
					/>
					<ConfirmButton
						{ ...BASE }
						label="Reject"
						confirmLabel="Yes, reject"
						disabled={ busy }
						onConfirm={ run }
					/>
				</>
			);
		}
		render( <Row /> );
		fireEvent.click( screen.getByRole( 'button', { name: 'Reject' } ) );
		open();
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Yes, approve' } )
		);
		const siblingConfirm = screen.getByRole( 'button', {
			name: 'Yes, reject',
		} );
		expect( siblingConfirm.getAttribute( 'aria-disabled' ) ).toBe( 'true' );
		expect(
			screen
				.getAllByRole( 'button', { name: 'Cancel' } )
				.every( ( b ) => b.getAttribute( 'aria-disabled' ) === 'true' )
		).toBe( true );
		fireEvent.click( siblingConfirm );
		await act( async () => {
			d.resolve();
			await d.promise;
		} );
		// The sibling never confirmed: its prompt is still open, and unlocked.
		expect(
			screen
				.getByRole( 'button', { name: 'Yes, reject' } )
				.hasAttribute( 'aria-disabled' )
		).toBe( false );
	} );

	test( 'disabled trigger stays focusable and does not open', () => {
		render(
			<ConfirmButton { ...BASE } disabled onConfirm={ jest.fn() } />
		);
		const trigger = screen.getByRole( 'button', { name: 'Approve' } );
		expect( trigger.getAttribute( 'aria-disabled' ) ).toBe( 'true' );
		expect( trigger.disabled ).toBe( false );
		fireEvent.click( trigger );
		expect( screen.queryByText( 'Approve this application?' ) ).toBeNull();
	} );

	test( 'describedBy lands on the trigger', () => {
		render(
			<ConfirmButton
				{ ...BASE }
				describedBy="hint-1"
				onConfirm={ jest.fn() }
			/>
		);
		expect(
			screen
				.getByRole( 'button', { name: 'Approve' } )
				.getAttribute( 'aria-describedby' )
		).toBe( 'hint-1' );
	} );

	test.each( [
		[ 'primary', 'mhmui-confirm mhmui-confirm--primary' ],
		[ 'danger', 'mhmui-confirm mhmui-confirm--danger' ],
		[ 'secondary', 'mhmui-confirm mhmui-confirm--secondary' ],
		[ 'bogus', 'mhmui-confirm mhmui-confirm--secondary' ],
	] )( 'variant %s -> %s', ( variant, cls ) => {
		const { container } = render(
			<ConfirmButton
				{ ...BASE }
				variant={ variant }
				onConfirm={ jest.fn() }
			/>
		);
		expect( container.firstChild.className ).toBe( cls );
	} );
} );
