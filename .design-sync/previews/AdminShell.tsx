import { AdminShell, PageHeader, StatsGrid, Button, Widget, DetailList, Notice } from '@mhm/ui-core';

/** A whole page: `page` turns on the shell's vertical rhythm between the blocks. */
export const Page = () => (
	<AdminShell page>
		<PageHeader title="Bookings" meta="48 this month" level={ 1 } />
		<Notice tone="info">Two bookings start today.</Notice>
		<StatsGrid
			cards={ [
				{ label: 'Active', value: '12', tone: 'success', icon: 'yes-alt' },
				{ label: 'Pending', value: '3', tone: 'warning', icon: 'clock' },
				{ label: 'Revenue', value: '₺48,200', tone: 'info', icon: 'chart-bar' },
				{ label: 'Cancelled', value: '1', tone: 'danger', icon: 'dismiss' },
			] }
		/>
		<div className="mhmui-toolbar">
			<Button variant="primary">Add booking</Button>
			<Button variant="secondary">Export</Button>
		</div>
		<Widget title="Today" level={ 2 }>
			<DetailList emptyText="—" layout="inline" columns={ 1 } items={ [ { label: 'Pick-ups', value: '3' }, { label: 'Returns', value: '2' } ] } />
		</Widget>
	</AdminShell>
);

/** A single component: no `page`, so no rhythm -- just the wp-admin surface. */
export const OneComponent = () => (
	<AdminShell>
		<Button variant="primary">Save changes</Button>
	</AdminShell>
);
