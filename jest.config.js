const preset = require( '@wordpress/jest-preset-default/jest-preset' );
const babelTransform = require.resolve( '@wordpress/scripts/config/babel-transform' );

module.exports = {
	...preset,
	transform: {
		'\\.[jt]sx?$': babelTransform,
	},
	setupFilesAfterEnv: [
		...preset.setupFilesAfterEnv,
		'<rootDir>/jest.setup.js',
	],
};
