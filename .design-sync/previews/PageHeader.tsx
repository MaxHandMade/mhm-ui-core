import { PageHeader, Button } from '@mhm/ui-core';

/** A detail sub-page: back link to its list, title, status, meta, actions. */
export const DetailView = () => (
	<PageHeader
		back={ { label: 'All bookings', href: '#bookings' } }
		title="Booking #9292 — Marmaris Cars"
		badge={ { text: 'Pending', tone: 'warning' } }
		meta="Created 23/09/2026 07:26 · 3 days · Fiat Egea"
		actions={
			<>
				<Button variant="secondary">Edit</Button>
				<Button variant="primary">Confirm booking</Button>
			</>
		}
		level={ 1 }
	/>
);

/** badge + badges share one wrapper, which drops to its own row when narrow. */
export const SeveralBadges = () => (
	<PageHeader
		back={ { label: 'Back to messages', href: '#messages' } }
		title="Deposit refund has not reached my account"
		badge={ { text: 'Pending', tone: 'warning' } }
		badges={ [ { text: 'Urgent', tone: 'danger' }, { text: 'Payment', tone: 'info' } ] }
		meta="#3702 · 3 messages · last reply 2 hours ago"
		level={ 1 }
	/>
);

/** A section heading inside a page: no back link, default h2. */
export const SectionHeading = () => <PageHeader title="Payout settings" meta="Applies to every vendor from the next payout." />;
