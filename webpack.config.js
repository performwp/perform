const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const CopyWebpackPlugin = require( 'copy-webpack-plugin' );
const { CleanWebpackPlugin } = require( 'clean-webpack-plugin' );
const MiniCSSExtractPlugin = require( 'mini-css-extract-plugin' );
const sharp = require( 'sharp' );
const { optimize } = require( 'svgo' );
const TerserPlugin = require( 'terser-webpack-plugin' );

const inProduction = 'production' === process.env.NODE_ENV;
const mode = inProduction ? 'production' : 'development';

const config = {
	...defaultConfig,
	mode,
	entry: {
		...defaultConfig.entry,
		perform: [ './assets/src/js/frontend/main.js', './assets/src/css/frontend/main.css' ],
		admin: [ './assets/src/css/admin/admin.css', './assets/src/js/admin/main.js' ],
	},
	externals: {
		...( defaultConfig.externals || {} ),
		'@wordpress/components': 'wp.components',
		'@wordpress/element': 'wp.element',
		'@wordpress/i18n': 'wp.i18n',
	},
	output: {
		...defaultConfig.output,
		path: path.join( __dirname, 'assets/dist/' ),
		// WordPress.org skips .min.js during language-pack extraction.
		filename: ( { chunk } ) => ( 'admin' === chunk.name ? 'js/perform-settings.js' : 'js/[name].min.js' ),
	},
	optimization: {
		...defaultConfig.optimization,
		minimizer: [
			new TerserPlugin( {
				include: /js\/perform-settings\.js$/,
				terserOptions: {
					// Keep conditional gettext calls separate so each msgid remains a literal.
					compress: { passes: 2, conditionals: false },
					mangle: { reserved: [ '__', '_n', '_nx', '_x' ] },
					format: { comments: /translators:/i },
				},
				extractComments: false,
			} ),
			new TerserPlugin( {
				exclude: /js\/perform-settings\.js$/,
				terserOptions: {
					compress: { passes: 2 },
					mangle: { reserved: [ '__', '_n', '_nx', '_x' ] },
					format: { comments: /translators:/i },
				},
				extractComments: false,
			} ),
		],
	},
	plugins: [
		// Removes the "dist" folder before building.
		new CleanWebpackPlugin( {
			cleanOnceBeforeBuildPatterns: [ 'assets/dist' ],
		} ),

		new MiniCSSExtractPlugin( {
			filename: 'css/[name].css',
		} ),

		new CopyWebpackPlugin( {
			patterns: [
				{
					from: 'assets/src/images',
					to: 'images',
					// The current source contains PNG/SVG only. JPEG and GIF remain unchanged if added later.
					transform: inProduction
						? {
								async transformer( content, filename ) {
									if ( /\.png$/i.test( filename ) ) {
										return sharp( content )
											.png( {
												compressionLevel: 9,
												effort: 10,
												palette: false,
											} )
											.toBuffer();
									}

									if ( /\.svg$/i.test( filename ) ) {
										return Buffer.from(
											optimize( content.toString(), {
												multipass: true,
											} ).data
										);
									}

									return content;
								},
							}
						: undefined,
				},
			],
		} ),
	],
};

module.exports = config;
