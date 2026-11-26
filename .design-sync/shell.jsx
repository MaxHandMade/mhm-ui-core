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

export function AdminShell( { children, page = false } ) {
	return (
		<div className={ page ? 'wp-core-ui mhmui-admin mhmui-admin-page' : 'wp-core-ui mhmui-admin' }>
			{ children }
		</div>
	);
}
