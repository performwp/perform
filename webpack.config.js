const path = require('path');
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const CopyWebpackPlugin = require( 'copy-webpack-plugin' );
const ImageminPlugin = require( 'imagemin-webpack-plugin' ).default;
const { CleanWebpackPlugin } = require( 'clean-webpack-plugin' );
const MiniCSSExtractPlugin = require( 'mini-css-extract-plugin' );
const WebpackRTLPlugin = require( 'webpack-rtl-plugin' );
const wpPot = require( 'wp-pot' );


const inProduction = ( 'production' === process.env.NODE_ENV );
const mode = inProduction ? 'production' : 'development';

const config = {
	...defaultConfig,
	mode,
	entry: {
		...defaultConfig.entry,
		//perform: [ './assets/src/js/frontend/main.js', './assets/src/css/frontend/main.css'],
		admin: [ './assets/src/css/admin/admin.css', './assets/src/js/admin/main.js' ],
	},
	output: {
		...defaultConfig.output,
		path: path.join(__dirname, 'assets/dist/'),
		filename: 'js/[name].min.js',
	},
	plugins: [
		// Removes the "dist" folder before building.
		new CleanWebpackPlugin({
			cleanOnceBeforeBuildPatterns: [ 'assets/dist' ]
		}),

		new MiniCSSExtractPlugin( {
			filename: 'css/[name].css',
		} ),

		new CopyWebpackPlugin( {
			patterns: [
				{
					from: 'assets/src/images',
					to: 'images',
				},
			],
		} ),
	],
};

if ( inProduction ) {
	// Minify images.
	// Must go after CopyWebpackPlugin above: https://github.com/Klathmon/imagemin-webpack-plugin#example-usage
	config.plugins.push( new ImageminPlugin( { test: /\.(jpe?g|png|gif|svg)$/i } ) );

	// POT file.
	wpPot( {
		package: 'Perform',
		domain: 'perform',
		destFile: 'languages/perform.pot',
		relativeTo: './',
		src: [ './**/*.php', '!./includes/libraries/**/*', '!./vendor/**/*' ],
		bugReport: 'https://github.com/mehul0810/perform/issues/new',
		team: 'PerformWP <hello@performwp.com>',
	} );
}

module.exports = config;
