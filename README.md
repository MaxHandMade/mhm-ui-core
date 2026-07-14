# mhm/ui-core

Shared WordPress UI infrastructure for MHM plugins.

Three responsibilities:

1. **Component factory** — one component contract produces a shortcode, a
   Gutenberg block and an Elementor widget from a single renderer.
2. **React admin kit** — shared enqueue helper, design tokens and JSX components.
3. **Tier seam** — the extension points a WordPress.org-compliant free core
   exposes for a licensed Pro add-on to bind to.

This package contains no business logic, no licensing code and no external
HTTP calls. Public and GPL, like every WordPress plugin it ships inside.

Design: `rentiva-dev/docs/superpowers/specs/2026-07-14-mhm-ui-core-design.md`
