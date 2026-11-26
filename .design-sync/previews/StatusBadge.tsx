import { StatusBadge } from '@mhm/ui-core';

const row = { display: 'flex', gap: 8, flexWrap: 'wrap' as const, alignItems: 'center' };

/** Every tone; the text carries the meaning, the colour only backs it up. */
export const Tones = () => (
	<div style={ row }>
		<StatusBadge tone="success">Confirmed</StatusBadge>
		<StatusBadge tone="warning">Pending payment</StatusBadge>
		<StatusBadge tone="danger">Cancelled</StatusBadge>
		<StatusBadge tone="info">In progress</StatusBadge>
		<StatusBadge tone="neutral">Draft</StatusBadge>
	</div>
);

/** No tone given: neutral. */
export const Default = () => <StatusBadge>Archived</StatusBadge>;
