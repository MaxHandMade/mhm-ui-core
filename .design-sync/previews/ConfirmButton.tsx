import { ConfirmButton } from '@mhm/ui-core';

const row = { display: 'flex', gap: 12, flexWrap: 'wrap' as const, alignItems: 'flex-start' };
const wait = () => new Promise< void >( ( done ) => setTimeout( done, 800 ) );

/** A decision that asks once before it runs: click to see the confirm step. */
export const ApproveOrReject = () => (
	<div style={ row }>
		<ConfirmButton label="Approve vendor" confirmText="Approve Marmaris Cars as a vendor?" confirmLabel="Yes, approve" cancelLabel="Cancel" busyText="Approving…" onConfirm={ wait } variant="primary" />
		<ConfirmButton label="Reject" confirmText="Reject this application? The applicant is emailed." confirmLabel="Yes, reject" cancelLabel="Keep it" busyText="Rejecting…" onConfirm={ wait } variant="danger" />
	</div>
);

/** Compact size for table rows, and a locked confirm. */
export const CompactAndDisabled = () => (
	<div style={ row }>
		<ConfirmButton label="Refund deposit" confirmText="Refund ₺2,000 to the customer?" confirmLabel="Refund" cancelLabel="Cancel" busyText="Refunding…" onConfirm={ wait } variant="secondary" size="compact" />
		<ConfirmButton label="Delete booking" confirmText="Delete booking #9292?" confirmLabel="Delete" cancelLabel="Cancel" busyText="Deleting…" onConfirm={ wait } variant="danger" disabled />
	</div>
);
