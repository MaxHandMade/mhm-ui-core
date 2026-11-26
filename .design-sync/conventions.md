# mhm-ui-core — how to build with it

The admin kit of the MHM WordPress plugins (Rentiva and others): React
components plus one stylesheet, drawn inside wp-admin. Every string is a prop;
the kit has no text of its own.

## Wrap every design in `AdminShell`

In WordPress the kit's tokens are declared on `.mhmui-admin` (here they are
on `:root` too), and `Button`, `ConfirmButton` and `Pagination` sit on
WordPress core's `.button` styles, which only apply under `wp-core-ui`.
Outside the wrapper buttons lose their shape and text falls back to the
browser font.

```jsx
const { AdminShell, PageHeader, StatsGrid, Button } = window.MHMUiCore;

<AdminShell page>
  <PageHeader title="Bookings" level={ 1 } />
  <StatsGrid cards={ [ { label: 'Bookings', value: '42', tone: 'success', icon: 'calendar-alt' } ] } />
  <div className="mhmui-toolbar">
    <Button variant="primary">Add booking</Button>
    <Button variant="secondary">Export</Button>
  </div>
</AdminShell>
```

`AdminShell` stands in for the wp-admin screen: it supplies `wp-core-ui`,
`.mhmui-admin`, Dashicons and the admin font. `page` adds `mhmui-admin-page`,
the page shell that owns vertical rhythm. In a real plugin the same shell is
`<div className="mhmui-admin mhmui-admin-page">` inside WordPress' `.wrap`.

## Layout: the shell spaces blocks, blocks carry no outer margin

A direct child of the page shell that wears a rhythm class gets
`--mhmui-space-3` (12px) above it. The members are the kit's own blocks
(`PageHeader`, `StatsGrid`, `Widget`, `Notice`, `Tabs`, `DetailLayout`,
`DataTable`, `Pagination`) and these layout classes:

| Class | Use |
|---|---|
| `mhmui-section` | any block of your own (filter bar, two-column grid, card). No styles of its own; never add `margin` to it |
| `mhmui-toolbar` | a row of buttons between two blocks: wrapping flex, 8px gap |
| `mhmui-tabs-bar` | `Tabs variant="underline"` plus a trailing link or control |
| `mhmui-measure` | caps text width for reading |

A sub-page (detail, edit, add) starts with `PageHeader` whose `back` points at
its list. A top-level page has no back link.

## Styling idiom

Style through props first (`variant`, `tone`, `size`, `level`, `emphasis`).
For your own glue use the tokens, never raw colours: `var(--mhmui-text)`,
`--mhmui-text-secondary`, `--mhmui-text-muted`, `--mhmui-surface`,
`--mhmui-surface-sunken`, `--mhmui-border`, `--mhmui-border-light`,
`--mhmui-accent`, `--mhmui-accent-strong`, `--mhmui-radius`,
`--mhmui-shadow-1`, `--mhmui-space-1` to `--mhmui-space-4`, and per tone
`--mhmui-<tone>`, `--mhmui-<tone>-soft`, `--mhmui-<tone>-strong`.

- Tones everywhere: `success | warning | danger | info | neutral`. The text
  carries the meaning; colour only backs it up.
- Icons: a Dashicons name without the prefix (`calendar-alt`, `download`) or
  a concept: `revenue total count rate customers items pending active new
  returning time place`.
- Tables: `DataTable` owns the card and caption; you write `thead`/`tbody`.
  Cell classes: `mhmui-table__select` (checkbox column with `SelectAll` /
  `RowSelect`), `mhmui-table__primary` (the row's name cell, a `th scope="row"`),
  `mhmui-table__num` (end-aligned numbers). With `layout="stack"` give every
  other cell a `data-label`. Sortable headers are `SortHeader` + `nextSort`.
- Headings: the page `h1` is WordPress'. Use `PageHeader level={ 1 }` only as
  a page's first block; inside a page use the default `h2`/`h3`.

## Where the truth lives

`components/bundle.css` (every kit rule), `tokens.css` and `tokens.json`
(values), `components/index.d.ts` (props), and
`components/<Name>/README.md` (usage and examples) for each component.
