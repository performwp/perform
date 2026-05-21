import { spawn } from 'node:child_process';

const manageWpEnv = process.env.PERFORM_E2E_MANAGE_WP_ENV !== '0';

function run( command, args ) {
	return new Promise( ( resolve, reject ) => {
		const child = spawn( command, args, {
			stdio: 'inherit',
			shell: process.platform === 'win32',
		} );

		child.on( 'error', reject );
		child.on( 'exit', ( code ) => {
			if ( code === 0 ) {
				resolve();
				return;
			}

			const error = new Error( `${ command } ${ args.join( ' ' ) } exited with code ${ code }` );
			error.code = code || 1;
			reject( error );
		} );
	} );
}

let exitCode = 0;

try {
	if ( manageWpEnv ) {
		await run( 'npm', [ 'run', 'wp-env', '--', 'start' ] );
		await run( 'npm', [ 'run', 'wp-env', '--', 'run', 'cli', 'wp', 'plugin', 'activate', 'perform' ] );
		await run( 'npm', [
			'run',
			'wp-env',
			'--',
			'run',
			'cli',
			'wp',
			'eval',
			'if ( class_exists( "Freemius" ) ) { $perform_fs = Freemius::get_instance_by_id( 18658 ); if ( $perform_fs ) { $perform_fs->skip_connection(); } }',
		] );
	}

	await run( 'npx', [ 'playwright', 'test', '--reporter=line' ] );
} catch ( error ) {
	exitCode = error.code || 1;
} finally {
	if ( manageWpEnv ) {
		try {
			await run( 'npm', [ 'run', 'wp-env', '--', 'stop' ] );
		} catch ( error ) {
			exitCode = exitCode || error.code || 1;
		}
	}
}

process.exit( exitCode );
