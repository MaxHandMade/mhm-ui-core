import { Button } from '@mhm/ui-core';

const row = { display: 'flex', gap: 8, flexWrap: 'wrap' as const, alignItems: 'center' };

/** Every variant at the default size. One primary per area. */
export const Variants = () => (
	<div style={ row }>
		<Button variant="primary">Save changes</Button>
		<Button variant="secondary">Preview</Button>
		<Button variant="neutral">Export CSV</Button>
		<Button variant="danger">Delete vehicle</Button>
		<Button variant="plain">Cancel</Button>
	</div>
);

/** Small size, a link button (href renders an <a>), an icon, and disabled. */
export const SizesAndStates = () => (
	<div style={ row }>
		<Button variant="primary" size="sm">Reply</Button>
		<Button variant="secondary" size="sm" href="#docs">Documentation</Button>
		<Button variant="secondary" icon={ <span className="dashicons dashicons-download" aria-hidden="true" /> }>Download invoice</Button>
		<Button variant="primary" disabled>Saving…</Button>
	</div>
);
