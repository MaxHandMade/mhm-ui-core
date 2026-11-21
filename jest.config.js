/**
 * Jest, kept on purpose after @wordpress/scripts 37 moved `test-unit-js` to
 * Vitest. 37 no longer ships config/jest-unit.config.js or a bundled Jest;
 * this is the "tested legacy combination" from its docs/vitest-migration.md
 * (Keep an existing Jest suite): the published WordPress preset plus
 * babel-jest with the WordPress Babel preset, which together replace the
 * removed defaults. The package.json pins match that document.
 */
module.exports = {
	preset: '@wordpress/jest-preset-default',
	transform: {
		'\\.[jt]sx?$': [
			'babel-jest',
			{ presets: [ '@wordpress/babel-preset-default' ] },
		],
	},
	reporters: [ 'default', 'github-actions' ],
	rootDir: __dirname,
	testMatch: [
		'<rootDir>/src-react/**/*.test.js',
		'<rootDir>/src-react/**/*.test.jsx',
		'<rootDir>/tests/Gate/**/*.test.js',
	],
};
