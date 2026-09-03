export { createFormatter } from './format';
export { createApiClient } from './api/createApiClient';
export { useApi } from './hooks/useApi';
export { default as ErrorBoundary } from './components/ErrorBoundary';

// Visual kit -- every string is a prop; the package has no text domain.
export { default as StatCard } from './components/StatCard';
export { default as StatsGrid } from './components/StatsGrid';
export { default as KpiBox } from './components/KpiBox';
export { default as StatusBadge } from './components/StatusBadge';
export { default as Pagination } from './components/Pagination';
export { default as ProLock } from './components/ProLock';
export { default as Notice } from './components/Notice';
export { default as Widget } from './components/Widget';

// The single token source, for consumers that need the raw values in JS
// (charts, inline styles). The CSS custom properties are generated from it.
export { default as tokens } from './tokens.json';
