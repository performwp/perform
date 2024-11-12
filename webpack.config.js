const path = require('path');
const CopyWebpackPlugin = require( 'copy-webpack-plugin' );
const ImageminPlugin = require( 'imagemin-webpack-plugin' ).default;
const { CleanWebpackPlugin } = require( 'clean-webpack-plugin' );
const MiniCSSExtractPlugin = require( 'mini-css-extract-plugin' );
const WebpackRTLPlugin = require( 'webpack-rtl-plugin' );
const wpPot = require( 'wp-pot' );

const inProduction = ( 'production' === process.env.NODE_ENV );
const mode = inProduction ? 'production' : 'development';

const config = {
	mode,
	entry: {
		perform: [ './assets/src/js/frontend/main.js', './assets/src/scss/frontend/main.scss'],
		admin: [ './assets/src/js/admin/main.js', './assets/src/scss/admin/main.scss' ],
	},
	output: {
		path: path.join(__dirname, 'assets/dist/'),
		filename: 'js/[name].min.js',
	},
	module: {
		rules: [
			// Create RTL styles.
			{
				test: /\.css$/,
				use: [
					'style-loader',
					'css-loader',
				],
			},

			// SASS to CSS.
			{
				test: /\.scss$/,
				use: [
					MiniCSSExtractPlugin.loader,
					{
						loader: 'css-loader',
						options: {
							sourceMap: true,
						},
					},
					{
						loader: 'sass-loader',
						options: {
							sassOptions: {
								sourceMap: true,
								style: ( inProduction ? 'compressed' : 'nested' ),
							},
						},
					} ],
			},

			// Font files.
			{
				test: /\.(ttf|otf|eot|woff(2)?)(\?[a-z0-9]+)?$/,
				use: [
					{
						loader: 'file-loader',
						options: {
							name: 'fonts/[name].[ext]',
							publicPath: '../',
						},
					},
				],
			},

			// Image files.
			{
				test: /\.(png|jpe?g|gif|svg)$/,
				use: [
					{
						loader: 'file-loader',
						options: {
							name: 'images/[name].[ext]',
							publicPath: '../',
						},
					},
				],
			},
		],
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
	// Create RTL css.
	config.plugins.push( new WebpackRTLPlugin( {
		suffix: '-rtl',
		minify: true,
	} ) );

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
