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
export { default as Tabs } from './components/Tabs';

// The semantic icon vocabulary (0.14.0+). A NAMED export on purpose: the
// manifest gate counts `export { default as X }` to find components, and a
// vocabulary is not a component. resetIcons stays out -- it is a test seam.
export { registerIcons } from './icons';

// The single token source, for consumers that need the raw values in JS
// (charts, inline styles). The CSS custom properties are generated from it.
// Use named export, not default; a later task counts export { default as X } to
// check components, and tokens is not a component -- the export const form
// distinguishes it deliberately.
import tokenDoc from './tokens.json';
export const tokens = tokenDoc;
