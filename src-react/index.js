export { createFormatter } from './format';
export { createApiClient } from './api/createApiClient';
export { useApi } from './hooks/useApi';
export { default as ErrorBoundary } from './components/ErrorBoundary';

// Visual kit -- every string is a prop; the package has no text domain.
export { default as StatCard } from './components/StatCard';
export { default as StatsGrid } from './components/StatsGrid';
export { default as StatusBadge } from './components/StatusBadge';
export { default as Pagination } from './components/Pagination';
export { default as ProLock } from './components/ProLock';
export { default as Notice } from './components/Notice';
export { default as Widget } from './components/Widget';

// The single token source, for consumers that need the raw values in JS
// (charts, inline styles). The CSS custom properties are generated from it.
// `tokens.tokens` is the legacy flat view (the admin scope) and is kept for
// at least one minor: this file is a public export and readers depend on it.
const tokenDoc = require( './tokens.json' );
export const tokens = {
	...tokenDoc,
	tokens: tokenDoc.scopes[ '.mhmui-admin' ],
};
