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
			'theme',
			'activate',
			'perform-classic-theme',
		] );
		await run( 'npm', [
			'run',
			'wp-env',
			'--',
			'run',
			'cli',
			'wp',
			'eval',
			'$menu = wp_get_nav_menu_object( "Primary" ); $menu_id = $menu ? (int) $menu->term_id : wp_create_nav_menu( "Primary" ); if ( is_wp_error( $menu_id ) ) { throw new RuntimeException( $menu_id->get_error_message() ); } if ( ! wp_get_nav_menu_items( $menu_id ) ) { wp_update_nav_menu_item( $menu_id, 0, [ "menu-item-title" => "Home", "menu-item-url" => home_url( "/" ), "menu-item-status" => "publish" ] ); } set_theme_mod( "nav_menu_locations", [ "primary" => $menu_id ] );',
		] );
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
		await run( 'npm', [
			'run',
			'wp-env',
			'--',
			'run',
			'cli',
			'wp',
			'eval',
			'$user = get_user_by( "login", "perform-editor" ); if ( ! $user ) { $user_id = wp_create_user( "perform-editor", "password", "perform-editor@example.com" ); if ( is_wp_error( $user_id ) ) { throw new RuntimeException( $user_id->get_error_message() ); } $user = get_user_by( "id", $user_id ); } wp_set_password( "password", $user->ID ); $user->set_role( "editor" );',
		] );
		await run( 'npm', [
			'run',
			'wp-env',
			'--',
			'run',
			'cli',
			'wp',
			'option',
			'update',
			'perform_cache_stats',
			'{"hits":120,"stale_hits":8,"misses":22,"bypasses":4,"top_misses":{"/sample-page/":3}}',
			'--format=json',
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
