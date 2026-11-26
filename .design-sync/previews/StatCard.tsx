import { StatCard } from '@mhm/ui-core';

const grid = { display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: 12 };

/** Tones, each with an icon: a Dashicons name or a semantic concept (revenue, pending...). */
export const Tones = () => (
	<div style={ grid }>
		<StatCard label="Bookings" value="42" tone="success" icon="calendar-alt" sub="this month" />
		<StatCard label="Pending" value="3" tone="warning" icon="pending" sub="awaiting payment" />
		<StatCard label="Cancelled" value="1" tone="danger" icon="dismiss" />
		<StatCard label="Revenue" value="₺48,200" tone="info" icon="revenue" />
		<StatCard label="Drafts" value="0" tone="neutral" icon="edit" />
	</div>
);

/** Change against the last period: up, down, flat. */
export const Deltas = () => (
	<div style={ grid }>
		<StatCard label="Bookings" value="45" icon="time" delta={ { direction: 'up', text: '3 this month', label: 'increase' } } />
		<StatCard label="Customers" value="38" icon="customers" delta={ { direction: 'down', text: '2 this month', label: 'decrease' } } />
		<StatCard label="Vehicles" value="7" icon="items" sub="12 total" delta={ { direction: 'flat', text: 'no change' } } />
	</div>
);

/** Emphasis: the one figure the page is about. */
export const Emphasis = () => (
	<div style={ { maxWidth: 280 } }>
		<StatCard label="Balance" value="₺12,450.00" emphasis sub="next payout 30/11" />
	</div>
);
