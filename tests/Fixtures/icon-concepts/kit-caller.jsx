import StatsGrid from '../../../vendor/mhm/ui-core/src-react/components/StatsGrid';

export default function Row() {
	const cards = [ { label: 'A', value: '1', icon: 'money-alt' } ];
	return <StatsGrid cards={ cards } columns={ 2 } />;
}
