const base = require( '@wordpress/scripts/config/jest-unit.config.js' );

module.exports = {
	...base,
	rootDir: __dirname,
	testMatch: [
		'<rootDir>/src-react/**/*.test.js',
		'<rootDir>/src-react/**/*.test.jsx',
		'<rootDir>/tests/gate/**/*.test.js',
	],
};
