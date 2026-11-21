/**
 * ESLint flat config. @wordpress/scripts 37 runs ESLint 10 and reads only an
 * eslint.config.* file; the old .eslintrc.js was ignored without an error, so
 * its two overrides below were silently gone (jest globals: 1108 no-undef;
 * the i18n restrictions: nothing at all). Base and Jest block follow the
 * scripts 37 docs/vitest-migration.md, "Keep Jest lint rules".
 *
 * package.json pins eslint and @wordpress/eslint-plugin to the versions
 * scripts 37 itself depends on, so the tree holds one copy of each: with a
 * second root copy, `wp-scripts lint-js` ran its own nested ESLint 10 while
 * the i18n gate ran the root 9. The gate now asserts they are one binary.
 */
const wpPlugin = require( '@wordpress/eslint-plugin' );
const jestPlugin = require( 'eslint-plugin-jest' );

const TESTS = [ '**/*.test.js', '**/*.test.jsx' ];

const I18N_MESSAGE =
	'mhm-ui-core has no text domain and no runtime globals: take the string as a prop.';

module.exports = [
	{ ignores: [ '**/build/**', '**/node_modules/**', '**/vendor/**' ] },
	...wpPlugin.configs.recommended,
	{
		languageOptions: {
			parserOptions: {
				requireConfigFile: false,
				babelOptions: {
					presets: [
						require.resolve( '@wordpress/babel-preset-default' ),
					],
				},
			},
		},
	},
	{
		// .test.jsx as well as .test.js: a JSX test file must not fail on
		// `describe` being undefined. flat/recommended brings the jest/* rules
		// too, not only the globals the old `env: { jest: true }` gave --
		// kept on purpose: they flag real test mistakes.
		...jestPlugin.configs[ 'flat/recommended' ],
		files: TESTS,
	},
	{
		// No text domain in this package: every string is a prop the consumer
		// translated (the PHP half is bin/check-no-i18n.php).
		// tests/Gate/no-i18n-jsx.test.js proves each rule fires here and only
		// here.
		files: [ 'src-react/**/*.js', 'src-react/**/*.jsx' ],
		ignores: TESTS,
		rules: {
			'no-restricted-imports': [
				'error',
				{
					paths: [
						{
							name: '@wordpress/i18n',
							message:
								'mhm-ui-core has no text domain: take the translated string as a prop.',
						},
					],
				},
			],
			'no-restricted-globals': [
				'error',
				{ name: 'wp', message: I18N_MESSAGE },
			],
			'no-restricted-properties': [
				'error',
				{ object: 'window', property: 'wp', message: I18N_MESSAGE },
			],
		},
	},
];
