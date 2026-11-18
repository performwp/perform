#!/usr/bin/env node

const MINIMUM_VERSION = '24.22.1';
const [ major, minor, patch ] = process.versions.node.split( '.' ).map( Number );
const supported = 24 === major && ( minor > 22 || ( 22 === minor && patch >= 1 ) );

if ( ! supported ) {
	console.error(
		`Perform builds require Node.js >=${ MINIMUM_VERSION } <25.0.0; current runtime is ${ process.versions.node }.`
	);
	process.exit( 1 );
}
