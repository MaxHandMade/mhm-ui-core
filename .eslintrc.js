module.exports = {
	extends: [ 'plugin:@wordpress/eslint-plugin/recommended' ],
	overrides: [
		{
			// wp-scripts' default config gives jest globals to **/*.test.js but not to
			// .test.jsx, so a JSX test file fails on `describe` being undefined.
			files: [ '**/*.test.js', '**/*.test.jsx' ],
			env: { jest: true },
		},
	],
};
