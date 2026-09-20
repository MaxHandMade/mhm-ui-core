import StatsGrid from '../../../vendor/mhm/ui-core/src-react/components/StatsGrid';

export default function Row() {
	const cards = [ { label: 'A', value: '1', icon: 'money-alt' } ];
	// data-icon is a DOM attribute, not the kit's `icon` prop: the lookbehind
	// ( ( ?<![\w-] ) in js_icons() ) must keep it out of every bucket.
	return <StatsGrid cards={ cards } columns={ 2 } data-icon="not-a-kit-icon-prop" />;
}
