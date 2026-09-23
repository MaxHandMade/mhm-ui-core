module.exports = {
	extends: [ 'plugin:@wordpress/eslint-plugin/recommended' ],
	overrides: [
		{
			// wp-scripts' default config gives jest globals to **/*.test.js but not to
			// .test.jsx, so a JSX test file fails on `describe` being undefined.
			files: [ '**/*.test.js', '**/*.test.jsx' ],
			env: { jest: true },
		},
		{
			// No text domain in this package: every string is a prop the
			// consumer translated (the PHP half is bin/check-no-i18n.php).
			// tests/Gate/no-i18n-jsx.test.js proves each rule fires here and
			// only here.
			files: [ 'src-react/**/*.js', 'src-react/**/*.jsx' ],
			excludedFiles: [ '**/*.test.js', '**/*.test.jsx' ],
			rules: {
				'no-restricted-imports': [
					'error',
					{
						paths: [
							{
								name: '@wordpress/i18n',
								message: 'mhm-ui-core has no text domain: take the translated string as a prop.',
							},
						],
					},
				],
				'no-restricted-globals': [
					'error',
					{
						name: 'wp',
						message: 'mhm-ui-core has no text domain and no runtime globals: take the string as a prop.',
					},
				],
				'no-restricted-properties': [
					'error',
					{
						object: 'window',
						property: 'wp',
						message: 'mhm-ui-core has no text domain and no runtime globals: take the string as a prop.',
					},
				],
			},
		},
	],
};
