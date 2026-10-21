import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';

const zip = process.argv[ 2 ] || 'perform.zip';
const entries = execFileSync( 'unzip', [ '-Z1', zip ], { encoding: 'utf8' } ).trim().split( '\n' );
const readEntry = ( path ) => execFileSync( 'unzip', [ '-p', zip, `perform/${ path }` ], { encoding: 'utf8' } );
const version = JSON.parse( readFileSync( 'package.json', 'utf8' ) ).version;

for ( const path of entries ) {
	assert.ok( path.startsWith( 'perform/' ) && ! path.split( '/' ).includes( '..' ), `Unexpected ZIP path: ${ path }` );
	assert.ok( ! /^perform\/(?:tests|node_modules|build|\.git|\.github|assets\/src|vendor\/(?:bin|phpunit|phpstan))\//.test( path ), `Development content: ${ path }` );
	assert.ok( ! /^perform\/\.wp-env/.test( path ), `Environment configuration: ${ path }` );
}
for ( const path of [
	'perform.php', 'readme.txt', 'config/constants.php', 'src/Plugin.php',
	'assets/dist/js/admin.min.js', 'assets/dist/js/perform.min.js',
	'assets/dist/css/admin.css', 'assets/dist/css/perform.css',
	'vendor/autoload.php', 'vendor/freemius/wordpress-sdk/start.php',
] ) {
	assert.ok( readEntry( path ).length > 0, `Missing or empty runtime file: ${ path }` );
}
assert.equal( readEntry( 'perform.php' ).match( /\* Version:\s*(\S+)/ )[ 1 ], version );
assert.equal( readEntry( 'config/constants.php' ).match( /'PERFORM_VERSION',\s*'([^']+)'/ )[ 1 ], version );
assert.equal( readEntry( 'readme.txt' ).match( /^Stable tag:\s*(\S+)/m )[ 1 ], version );
assert.equal( readEntry( 'readme.txt' ).match( /^Tested up to:\s*(\S+)/m )[ 1 ], '7.1' );
assert.equal( readEntry( 'readme.txt' ).match( /^Requires PHP:\s*(\S+)/m )[ 1 ], '7.4' );
const dependencies = JSON.parse( readEntry( 'vendor/composer/installed.json' ) );
assert.equal( dependencies.dev, false, 'Development Composer install was packaged' );
assert.deepEqual( dependencies.packages.map( ( dependency ) => dependency.name ).sort(), [ 'composer/installers', 'freemius/wordpress-sdk' ] );
assert.ok( ! entries.some( ( path ) => path.startsWith( 'perform/vendor/composer/installers/' ) ), 'Build-only installer files were packaged' );
console.log( JSON.stringify( { version, files: entries.length, sha256: createHash( 'sha256' ).update( readFileSync( zip ) ).digest( 'hex' ), result: 'pass' } ) );
