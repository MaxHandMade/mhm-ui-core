import { ErrorBoundary, Notice } from '@mhm/ui-core';

function BrokenChart(): null {
	throw new Error( 'chart data missing' );
}

/** A child that throws: the boundary draws its fallback instead of a blank screen. */
export const CaughtError = () => (
	<ErrorBoundary fallback={ <Notice tone="danger">This report could not be drawn. Reload the page or try a shorter date range.</Notice> }>
		<BrokenChart />
	</ErrorBoundary>
);

/** No error: children render as normal. */
export const Healthy = () => (
	<ErrorBoundary fallback={ <Notice tone="danger">Something went wrong.</Notice> }>
		<p style={ { margin: 0 } }>Revenue this month: ₺48,200</p>
	</ErrorBoundary>
);
