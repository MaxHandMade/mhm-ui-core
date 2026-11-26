import { StatsGrid } from '@mhm/ui-core';

/** A dashboard band of four: 4 across, 2+2 when narrow, never 3+1. */
export const FourAcross = () => (
	<StatsGrid
		columns={ 4 }
		cards={ [
			{ label: 'Bookings', value: '42', tone: 'success', icon: 'calendar-alt', delta: { direction: 'up', text: '3 this month' } },
			{ label: 'Revenue', value: '₺48,200', tone: 'info', icon: 'revenue' },
			{ label: 'Pending', value: '3', tone: 'warning', icon: 'pending' },
			{ label: 'Customers', value: '128', icon: 'customers', sub: '9 new' },
		] }
	/>
);

/** Three figures with a ceiling of 3. */
export const Three = () => (
	<StatsGrid
		columns={ 3 }
		cards={ [
			{ label: 'Vehicles', value: '7', icon: 'items', sub: '12 total' },
			{ label: 'Occupancy', value: '64%', icon: 'rate' },
			{ label: 'Avg. rental', value: '3.2 days', icon: 'time' },
		] }
	/>
);
