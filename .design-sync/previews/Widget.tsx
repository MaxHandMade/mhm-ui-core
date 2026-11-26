import { Widget, Button, DetailList } from '@mhm/ui-core';

/** A card with a title, icon, subtitle and header actions. */
export const Card = () => (
	<Widget title="Upcoming pick-ups" subtitle="Next 7 days" icon="calendar-alt" level={ 3 } actions={ <Button variant="secondary" size="sm">View all</Button> }>
		<DetailList emptyText="—" layout="inline" columns={ 1 } items={ [ { label: 'Today', value: '3 pick-ups' }, { label: 'Tomorrow', value: '5 pick-ups' }, { label: 'This week', value: '14 pick-ups' } ] } />
	</Widget>
);

/** Collapsible, open by default. */
export const Collapsible = () => (
	<Widget title="Advanced settings" level={ 3 } collapsible defaultOpen>
		<p style={ { margin: 0 } }>Buffer between rentals: 2 hours. Minimum driver age: 21.</p>
	</Widget>
);

/** Plain variant: a titled section without the card chrome. */
export const Plain = () => (
	<Widget title="Notes" variant="plain" level={ 3 }>
		<p style={ { margin: 0 } }>Customer prefers WhatsApp for pick-up details.</p>
	</Widget>
);
