import { DetailList } from '@mhm/ui-core';

/** Label/value pairs in two columns; an empty value shows emptyText. */
export const Stacked = () => (
	<DetailList
		emptyText="Not entered"
		items={ [
			{ label: 'Full name', value: 'Ayşe Yılmaz' },
			{ label: 'Email', value: 'ayse@example.com' },
			{ label: 'Phone', value: '+90 532 000 00 00' },
			{ label: 'City', value: 'Muğla' },
			{ label: 'Tax office', value: '' },
			{ label: 'Customer since', value: 'March 2025' },
		] }
	/>
);

/** Inline layout, one column, with toned values. */
export const InlineWithTone = () => (
	<DetailList
		emptyText="Not entered"
		layout="inline"
		columns={ 1 }
		items={ [
			{ label: 'Documents', value: '2 missing', tone: 'warning' },
			{ label: 'IBAN', value: 'Verified', tone: 'success' },
			{ label: 'Payout', value: 'Every Monday' },
		] }
	/>
);
