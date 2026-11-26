import { Notice } from '@mhm/ui-core';

const col = { display: 'grid', gap: 8 };

/** The four tones. */
export const Tones = () => (
	<div style={ col }>
		<Notice tone="success">Booking #9292 confirmed. The customer has been emailed.</Notice>
		<Notice tone="info">Prices are shown in your store currency (TRY).</Notice>
		<Notice tone="warning">2 vendor documents expire this week.</Notice>
		<Notice tone="danger">The payment gateway did not answer. Nothing was charged.</Notice>
	</div>
);

/** Dismissible. */
export const Dismissible = () => (
	<Notice tone="success" onDismiss={ () => {} } dismissLabel="Dismiss this notice">Settings saved.</Notice>
);
