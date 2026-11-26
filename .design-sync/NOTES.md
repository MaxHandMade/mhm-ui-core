# design-sync notes — mhm-ui-core

Target: the Design System artifact in `.design-sync/artifact.json`. First sync 2026-11-26.

## How this repo is shaped for the converter

- No dist/, no .d.ts: consumers import raw JSX from `src-react/`. `entry` is `./src-react/index.js`
  (the real public entry), `tsconfig` = `.design-sync/tsconfig.json` (`jsx: react-jsx`, the
  automatic runtime the package's babel preset uses).
- Every component is `export default function X`; the converter's JSDoc reader skips that shape
  and finds no props. `buildCmd` = `node .design-sync/gen-api.mjs` regenerates `dtsPropsFor` (in
  config.json) and `.design-sync/.cache/docs/<Name>.md` (`docsDir`) from `src-react/components.json`
  + each component's JSDoc. RUN IT BEFORE EVERY BUILD. Manifest fixtures are NOT used as examples:
  many are deliberate bad inputs for the package's gates (`tone: "purple"`, `icon: "%41"`).
- `componentSrcMap` lists the 18 components by path (no .d.ts to discover them from). A new
  component in `src-react/index.js` must be added there.
- Tokens live on `.mhmui-admin`, and Button/ConfirmButton/Pagination ride on WordPress core's
  `.button`. `.design-sync/shell.jsx` (extraEntries) exports `AdminShell` = `wp-core-ui mhmui-admin`
  (+ `mhmui-admin-page` with `page`), used as `provider`, and imports:
  - `wp/wp-chrome.css`: core button/notice rules (WP 7.1.2 values, verbatim) + body font on the
    shell + core link colours. The only copy (bin/build-design-system.py retired 2026-11-26).
  - `wp/dashicons.css` + `wp/dashicons.woff2`: WordPress 6.5.5 core Dashicons (GPL-2.0+), copied
    from a local WP install; font-face repointed at the woff2.
  - `assets/react/pro.css` (ProLock). `cssEntry` = `assets/react/admin.css`.
- `AdminShell` is also a card (group `design-sync`, 2026-11-26): `componentSrcMap` points at
  `.design-sync/shell.jsx`, `previews/AdminShell.tsx` shows `page` vs no `page`. It is not in
  `components.json`, so its props live in gen-api.mjs's `LOCAL` table -- a prop added to the
  shell goes there too, or gen-api drops its type on the next run.
- `wp/wp-chrome.css` also quotes WP 7.1.5 forms.css's checkbox/radio rules and declares the
  default scheme's `--wp-admin-theme-color*` on the shell (forms.css reads it with no fallback;
  WordPress prints it inline). Before this the cards drew the browser's native checkbox, so the
  kit's SelectAll/RowSelect rules were never exercised (cross-engine audit, 2026-11-26).
- Link colour MUST stay at core's weight: `:where(.wp-core-ui.mhmui-admin) a`. A (0,2,1) version
  overrode a link-styled primary Button's white text (ProLock "Upgrade to Pro" went invisible).

## Known render warns

- `[RENDER_ERRORS] ErrorBoundary: Error: chart data missing` — intended: the CaughtError story
  throws on purpose to show the fallback.

## Re-sync risks

- `.ds-sync/` and `ds-bundle/` (gitignored scratch) make the package's own purity test
  (tests/Seam/PurityScannerTest, `composer check`) fail LOCALLY: it scans the working tree and
  finds the converter's import()/fetch code. CI never has them. Delete both after a sync, or
  move them out before `composer check`.

- `wp/wp-chrome.css` quotes WP 7.1.2 core; when the pinned core moves, re-read buttons.css/forms.css.
- Dashicons are 6.5.5's; newer glyphs added to core later are missing.
- `componentSrcMap` is a hand list: a component added to the barrel is not synced until listed.
- Previews use English Rentiva-like content; they import only public exports.
