import { Component } from '@wordpress/element';

/**
 * Catches a render error in its subtree and shows the host's fallback instead.
 *
 * The wording is a prop, not a built-in string: ui-core is a package, not a plugin, so it
 * has no text domain of its own and cannot ship a translatable message. Passing the
 * fallback in also keeps @wordpress/components out of this package's dependency surface.
 */
class ErrorBoundary extends Component {
	state = { hasError: false };

	static getDerivedStateFromError() {
		return { hasError: true };
	}

	render() {
		if ( this.state.hasError ) {
			return this.props.fallback ?? null;
		}

		return this.props.children;
	}
}

export default ErrorBoundary;
