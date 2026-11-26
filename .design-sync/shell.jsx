/**
 * The wp-admin surface the kit is drawn on, for previews and designs built
 * outside WordPress. Not part of the package: a design-sync wrapper only.
 *
 * In a real admin screen WordPress supplies `wp-core-ui` (core's button and
 * notice styles), Dashicons and the body font; the kit's tokens live on
 * `.mhmui-admin`. AdminShell supplies all four, so a component renders as it
 * does in wp-admin. Wrap a whole page once; add `mhmui-admin-page` (page="true")
 * to get the kit's vertical rhythm between blocks.
 */
import './.cache/tokens-root.css'; // gen-api.mjs (buildCmd) writes it; run that first.
import './wp/wp-chrome.css';
import './wp/dashicons.css';
import '../assets/react/pro.css';

/**
 * The wp-admin screen a design is drawn on: WordPress core's button and
 * notice styles, Dashicons, the admin body font and the kit's tokens. Wrap a
 * whole design in it once. Design-sync only -- a real plugin screen gets the
 * same surface from WordPress and its own `mhmui-admin` wrapper.
 *
 * @param {Object}    props
 * @param {ReactNode} props.children The page or component being designed.
 * @param {boolean}   [props.page]   true for a full page: adds `mhmui-admin-page`,
 *                                   the shell that spaces its direct children
 *                                   (PageHeader, StatsGrid, Widget...) at the
 *                                   kit's vertical rhythm. Leave it off for a
 *                                   single component.
 */
export function AdminShell( { children, page = false } ) {
	return (
		<div className={ page ? 'wp-core-ui mhmui-admin mhmui-admin-page' : 'wp-core-ui mhmui-admin' }>
			{ children }
		</div>
	);
}
