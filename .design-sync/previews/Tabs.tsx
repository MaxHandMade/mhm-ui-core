import { useState } from 'react';
import { Tabs } from '@mhm/ui-core';

const ITEMS = [
	{ id: 'pending', label: 'Pending', href: '#pending', badge: 4, badgeLabel: '4 pending', badgeTone: 'warning' },
	{ id: 'approved', label: 'Approved', href: '#approved', badge: 18 },
	{ id: 'rejected', label: 'Rejected', href: '#rejected', badge: 0 },
	{ id: 'all', label: 'All', href: '#all' },
];

/** Pill tabs (default): page sections as real links, counts as chips. */
export const Pill = () => {
	const [ current, setCurrent ] = useState( 'pending' );
	return <Tabs label="Applications" current={ current } items={ ITEMS } onSelect={ setCurrent } />;
};

/** Underline variant inside a .mhmui-tabs-bar with a trailing control; showZero draws the 0 chip. */
export const UnderlineInBar = () => {
	const [ current, setCurrent ] = useState( 'approved' );
	return (
		<div className="mhmui-tabs-bar">
			<Tabs label="Applications" variant="underline" showZero current={ current } items={ ITEMS } onSelect={ setCurrent } />
			<a href="#export"><span className="dashicons dashicons-download" aria-hidden="true" /> Export</a>
		</div>
	);
};
