<?php
/**
 * Perform - Assets Manager Functions.
 *
 * @since 1.1.0
 *
 * @package    Perform
 * @subpackage Assets Manager/Functions
 * @author     Mehul Gohil
 */

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load Assets Manager Class.
 *
 * @since 1.1.0
 *
 * @return void
 */
function perform_load_assets_manager() {
	require_once PERFORM_PLUGIN_DIR . 'includes/modules/assets-manager/class-perform-assets-manager.php';
}

add_action( 'wp_footer', 'perform_load_assets_manager', 1000 );

/**
 * Saves Assets Manager Optimization Settings.
 *
 * @since  1.1.0
 * @access public
 *
 * @return array
 */
function perform_update_assets_manager() {

	$post_data = perform_clean( filter_input_array( INPUT_POST ) );
	$get_data  = perform_clean( filter_input_array( INPUT_GET ) );

	if (
		isset( $get_data['perform'] ) &&
		! empty( $post_data['perform_assets_manager'] )
	) {

		$current_id = get_queried_object_id();
		$filters    = array( 'js', 'css', 'plugins', 'themes' );
		$options    = get_option( 'perform_assets_manager_options' );
		$settings   = get_option( 'perform_assets_manager_settings' );

		foreach ( $filters as $type ) {

			if ( isset( $post_data['disabled'][ $type ] ) ) {

				foreach ( $post_data['disabled'][ $type ] as $handle => $value ) {

					$group_disabled = false;

					if ( isset( $post_data['relations'][ $type ][ $handle ] ) ) {

						$relation_info = $post_data['relations'][ $type ][ $handle ];

						if (
							'disabled' === $post_data['status'][ $relation_info['category'] ][ $relation_info['group'] ] &&
							isset( $post_data['disabled'][ $relation_info['category'] ][ $relation_info['group'] ] )
						) {
							$group_disabled = true;
						}
					}

					if (
						! $group_disabled &&
						'disabled' === $post_data['status'][ $type ][ $handle ] &&
						! empty( $value )
					) {
						if ( 'everywhere' === $value ) {
							$options['disabled'][ $type ][ $handle ]['everywhere'] = 1;

							if ( ! empty( $options['disabled'][ $type ][ $handle ]['current'] ) ) {
								unset( $options['disabled'][ $type ][ $handle ]['current'] );
							}
						} elseif ( 'current' === $value ) {

							if ( isset( $options['disabled'][ $type ][ $handle ]['everywhere'] ) ) {
								unset( $options['disabled'][ $type ][ $handle ]['everywhere'] );
							}

							if ( ! is_array( $options['disabled'][ $type ][ $handle ]['current'] ) ) {
								$options['disabled'][ $type ][ $handle ]['current'] = array();
							}

							if ( ! in_array( $current_id, $options['disabled'][ $type ][ $handle ]['current'] ) ) {
								array_push( $options['disabled'][ $type ][ $handle ]['current'], $current_id );
							}
						}
					} else {
						unset( $options['disabled'][ $type ][ $handle ]['everywhere'] );

						if ( isset( $options['disabled'][ $type ][ $handle ]['current'] ) ) {

							$current_key = array_search( $current_id, $options['disabled'][ $type ][ $handle ]['current'] );

							if ( false !== $current_key ) {
								unset( $options['disabled'][ $type ][ $handle ]['current'][ $current_key ] );

								if ( empty( $options['disabled'][ $type ][ $handle ]['current'] ) ) {
									unset( $options['disabled'][ $type ][ $handle ]['current'] );
								}
							}
						}
					}

					if ( empty( $options['disabled'][ $type ][ $handle ] ) ) {
						unset( $options['disabled'][ $type ][ $handle ] );

						if ( empty( $options['disabled'][ $type ] ) ) {
							unset( $options['disabled'][ $type ] );

							if ( empty( $options['disabled'] ) ) {
								unset( $options['disabled'] );
							}
						}
					}
				}
			}

			if ( isset( $post_data['enabled'][ $type ] ) ) {

				foreach ( $post_data['enabled'][ $type ] as $handle => $value ) {

					$group_disabled = false;

					if ( isset( $post_data['relations'][ $type ][ $handle ] ) ) {
						$relation_info = $post_data['relations'][ $type ][ $handle ];

						if (
							isset( $post_data['disabled'][ $relation_info['category'] ][ $relation_info['group'] ] ) &&
							'disabled' === $post_data['status'][ $relation_info['category'] ][ $relation_info['group'] ]
						) {
							$group_disabled = true;
						}
					}

					if (
						! $group_disabled &&
						'disabled' === $post_data['status'][ $type ][ $handle ] &&
						(
							! empty( $value['current'] ) ||
							0 === $value['current']
						)
					) {
						if ( ! is_array( $options['enabled'][ $type ][ $handle ]['current'] ) ) {
							$options['enabled'][ $type ][ $handle ]['current'] = array();
						}

						if ( ! in_array( $value['current'], $options['enabled'][ $type ][ $handle ]['current'] ) ) {
							array_push( $options['enabled'][ $type ][ $handle ]['current'], $value['current'] );
						}
					} else {
						if ( isset( $options['enabled'][ $type ][ $handle ]['current'] ) ) {
							$current_key = array_search( $current_id, $options['enabled'][ $type ][ $handle ]['current'] );

							if ( false !== $current_key ) {
								unset( $options['enabled'][ $type ][ $handle ]['current'][ $current_key ] );

								if ( empty( $options['enabled'][ $type ][ $handle ]['current'] ) ) {
									unset( $options['enabled'][ $type ][ $handle ]['current'] );
								}
							}
						}
					}

					if (
						! $group_disabled &&
						'disabled' === $post_data['status'][ $type ][ $handle ] &&
						! empty( $value['post_types'] )
					) {
						$options['enabled'][ $type ][ $handle ]['post_types'] = array();

						foreach ( $value['post_types'] as $key => $post_type ) {
							if ( isset( $options['enabled'][ $type ][ $handle ]['post_types'] ) ) {
								if ( ! in_array( $post_type, $options['enabled'][ $type ][ $handle ]['post_types'] ) ) {
									array_push( $options['enabled'][ $type ][ $handle ]['post_types'], $post_type );
								}
							}
						}
					} else {
						unset( $options['enabled'][ $type ][ $handle ]['post_types'] );
					}

					// Filter out empty child arrays.
					if ( ! empty( $settings['separate_archives'] ) && $settings['separate_archives'] == "1" ) {
						$value['archives'] = array_filter( $value['archives'] );

						if (
							! $group_disabled &&
							'disabled' === $post_data['status'][ $type ][ $handle ] &&
							! empty( $value['archives'] )
						) {
							$archives = array( 'wp', 'taxonomies', 'post_types' );

							foreach ( $archives as $archive_type ) {
								if ( ! empty( $value['archives'][ $archive_type ] ) ) {
									$options['enabled'][ $type ][ $handle ]['archives'][ $archive_type ] = array();

									foreach ( $value['archives'][ $archive_type ] as $key => $archive ) {
										if ( isset( $options['enabled'][ $type ][ $handle ]['archives'][ $archive_type ] ) ) {
											if ( ! in_array( $post_type, $options['enabled'][ $type ][ $handle ]['archives'][ $archive_type ] ) ) {
												array_push( $options['enabled'][ $type ][ $handle ]['archives'][ $archive_type ], $archive );
											}
										}
									}
								} else {
									unset( $options['enabled'][ $type ][ $handle ]['archives'][ $archive_type ] );
								}
							}
						} else {
							unset( $options['enabled'][ $type ][ $handle ]['archives'] );
						}
					}

					if ( empty( $options['enabled'][ $type ][ $handle] ) ) {
						unset( $options['enabled'][ $type ][ $handle ] );

						if ( empty( $options['enabled'][ $type ] ) ) {
							unset( $options['enabled'][ $type ] );

							if ( empty( $options['enabled'] ) ) {
								unset( $options['enabled'] );
							}
						}
					}
				}
			}
		}

		// Save assets manager settings to DB.
		update_option( 'perform_assets_manager_options', $options, false );
	}
}

add_action( 'template_redirect', 'perform_update_assets_manager', 10, 2 );

/**
 * Dequeue Assets based on the Assets Manager Options.
 *
 * @param string $src    Source URL of the asset.
 * @param string $handle Handle of the asset.
 *
 * @since  1.1.0
 * @access public
 *
 * @return void
 */
function perform_dequeue_assets( $src, $handle ) {

	if ( is_admin() ) {
		return $src;
	}

	$get_data = perform_clean( filter_input_array( INPUT_GET ) );

	// Get assets type.
	$type = current_filter() == 'script_loader_src' ? 'js' : 'css';

	// Load Assets Manager settings.
	$options         = get_option( 'perform_assets_manager_options' );
	$current_id      = get_queried_object_id();
	$content_dirname = perform_get_content_dir_name();

	// Get category + group from src.
	preg_match( "/\/{$content_dirname}\/(.*?\/.*?)\//", $src, $match );

	if ( ! empty( $match[1] ) ) {
		$match    = explode( '/', $match[1] );
		$category = $match[0];
		$group    = $match[1];
	}

	// Check for group disable settings and override.
	if ( ! empty( $category ) && ! empty( $group ) && isset( $options['disabled'][ $category ][ $group ] ) ) {
		$type   = $category;
		$handle = $group;
	}

	// Disable is set, check options.
	if (
		(
			! empty( $options['disabled'][ $type ][ $handle ]['everywhere'] ) &&
			1 === $options['disabled'][ $type ][ $handle ]['everywhere']
		) ||
		(
			! empty( $options['disabled'][ $type ][ $handle ]['current'] ) &&
			in_array( $current_id, $options['disabled'][ $type ][ $handle ]['current'] )
		)
	) {

		if ( ! empty( $options['enabled'][ $type ][ $handle ]['current'] ) && in_array( $current_id, $options['enabled'][ $type ][ $handle ]['current'] ) ) {
			return $src;
		}

		if ( is_front_page() || is_home() ) {
			if (
				'page' === get_option( 'show_on_front' ) &&
				! empty( $options['enabled'][ $type ][ $handle ]['post_types'] ) &&
				in_array( 'page', $options['enabled'][ $type ][ $handle ]['post_types'] )
			) {
				return $src;
			}
		} else {
			if (
				! empty( $options['enabled'][ $type ][ $handle ]['post_types'] ) &&
				in_array( get_post_type(), $options['enabled'][ $type ][ $handle ]['post_types'] )
			) {
				return $src;
			}
		}

		if (
			'jquery-core' === $handle &&
			'js' === $type &&
			isset( $get_data['perform'] ) &&
			current_user_can( 'manage_options' )
		) {
			global $pmsm_jquery_disabled;
			$pmsm_jquery_disabled = true;
			return $src;
		}

		return false;
	}

	return $src;
}

add_filter( 'script_loader_src', 'perform_dequeue_assets', 1000, 2 );
add_filter( 'style_loader_src', 'perform_dequeue_assets', 1000, 2 );

/**
 * This function is used to add assets manager button in admin bar.
 *
 * @param array $wp_admin_bar List of items on admin bar.
 *
 * @since  1.1.1
 * @access public
 *
 * @return void
 */
function perform_add_assets_manager_admin_bar( $wp_admin_bar ) {

	// Bailout, if conditions below doesn't pass through.
	if ( ! current_user_can( 'manage_options' ) || is_admin() ) {
		return;
	}

	global $wp;

	$server_data = perform_clean( filter_input_array( INPUT_SERVER ) );

	$href = add_query_arg(
		str_replace( array( '&perform', 'perform' ), '', $server_data['QUERY_STRING'] ),
		'',
		home_url( $wp->request )
	);

	if ( ! isset( $_GET['perform'] ) ) {
		$href .= ! empty( $server_data['QUERY_STRING'] ) ? '&perform' : '?perform';
		$menu_text = __( 'Assets Manager', 'perform' );
	} else {
		$menu_text = __( 'Close Assets Manager', 'perform' );
	}

	$args = array(
		'id'    => 'perform_assets_manager',
		'title' => $menu_text,
		'href'  => $href,
	);

	$wp_admin_bar->add_node( $args );
}

add_action( 'admin_bar_menu', 'perform_add_assets_manager_admin_bar', 1000, 1 );
