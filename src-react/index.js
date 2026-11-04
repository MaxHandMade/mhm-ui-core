export { createFormatter } from './format';
export { createApiClient } from './api/createApiClient';
export { useApi } from './hooks/useApi';
export { usePersistentOpen } from './hooks/usePersistentOpen';
export { useContainerWidth } from './hooks/useContainerWidth';
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
export { default as PageHeader } from './components/PageHeader';
export { default as DetailList } from './components/DetailList';
export { default as DetailLayout } from './components/DetailLayout';
export { default as ConfirmButton } from './components/ConfirmButton';
export { default as Button } from './components/Button';
export { default as DataTable } from './components/DataTable';
export { default as SortHeader } from './components/SortHeader';
export { default as SelectAll } from './components/SelectAll';
export { default as RowSelect } from './components/RowSelect';

// Table helpers (0.19.0). NAMED exports on purpose, like registerIcons below:
// the manifest gate counts `export { default as X }` to find components, and a
// hook and a pure sort helper are not components.
export { useTableSelection } from './hooks/useTableSelection';
export { nextSort } from './components/SortHeader';

// Server list helpers (0.20.0). NAMED exports for the same reason: hooks and a
// pure range helper are not components. buildParams / normalizeTotals stay
// module-internal (exported from their file for tests only).
export { useServerList, pageRange } from './hooks/useServerList';
export { useDebouncedValue } from './hooks/useDebouncedValue';

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
