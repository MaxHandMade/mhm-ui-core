import { DetailLayout, DetailList, Widget, ConfirmButton } from '@mhm/ui-core';

const done = () => Promise.resolve();

/** Main column + a sticky decision aside: the approval screen layout. */
export const WithDecisionAside = () => (
	<DetailLayout
		asideLabel="Decision"
		aside={
			<Widget title="Decision" level={ 3 }>
				<p style={ { marginTop: 0 } }>Documents are complete. Approving publishes the vendor listing and its 7 vehicles.</p>
				<ConfirmButton label="Approve vendor" confirmText="Approve Marmaris Cars?" confirmLabel="Yes, approve" cancelLabel="Cancel" busyText="Approving…" onConfirm={ done } variant="primary" />
			</Widget>
		}
	>
		<Widget title="Company" level={ 3 }>
			<DetailList emptyText="Not entered" items={ [ { label: 'Company', value: 'Marmaris Cars Ltd.' }, { label: 'City', value: 'Muğla' }, { label: 'Tax office', value: 'Marmaris' }, { label: 'Tax number', value: '' } ] } />
		</Widget>
	</DetailLayout>
);

/** No aside: the main column takes the width. */
export const MainOnly = () => (
	<DetailLayout>
		<Widget title="Booking notes" level={ 3 }>
			<p style={ { margin: 0 } }>Customer asked for a child seat and a 10:00 pick-up at Dalaman airport.</p>
		</Widget>
	</DetailLayout>
);
