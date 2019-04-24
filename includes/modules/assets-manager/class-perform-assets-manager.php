<?php
/**
 * Perform - Assets Manager.
 *
 * @since 1.1.0
 *
 * @package    Perform
 * @subpackage Assets Manager
 * @author     Mehul Gohil
 */

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Perform Assets Manager Class.
 *
 * @since 1.1.0
 */
class Perform_Assets_Manager {

	/**
	 * Loaded Assets.
	 *
	 * @since  1.1.0
	 * @access public
	 *
	 * @var array
	 */
	public $loaded_assets = array();

	/**
     * Check whether the assets manager is enabled?
     *
     * @since  1.1.0
     * @access public
     *
	 * @var bool
	 */
	public $is_assets_manager_enabled = false;

	/**
	 * Selected Assets Manager Options.
	 *
	 * @since  1.1.0
	 * @access public
	 *
	 * @var array
	 */
	public $selected_options;

	/**
	 * Perform_Assets_Manager Constructor.
	 *
	 * @since  1.1.0
	 * @access public
	 */
	public function __construct() {

		$this->is_assets_manager_enabled = Perform()->settings->get_option( 'enable_assets_manager', 'perform_advanced' );
		$this->selected_options          = get_option( 'perform_assets_manager_options' );

		// Bailout, if assets manager is not enabled.
		if ( ! $this->is_assets_manager_enabled ) {
		    return;
		}

		add_action( 'admin_bar_menu', array( $this, 'add_assets_manager_admin_bar' ), 1000, 1 );
		add_filter( 'script_loader_src', array( $this, 'dequeue_assets' ), 1000, 2 );
        add_filter( 'style_loader_src', array( $this, 'dequeue_assets' ), 1000, 2 );

		// Don't proceed, if Assets Manager is not enabled.
		if ( ! isset( $_GET['perform'] ) ) {
		    return;
        }

		add_action( 'wp_footer', array( $this, 'assets_manager_html' ), 1000 );
        add_action( 'template_redirect', array( $this, 'update_assets_manager' ), 10, 2 );

	}

	/**
	 * This function is used to add assets manager button in admin bar.
	 *
	 * @param array $wp_admin_bar List of items on admin bar.
	 *
	 * @since  1.1.0
	 * @access public
	 *
	 * @return void
	 */
	public function add_assets_manager_admin_bar( $wp_admin_bar ) {

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

	/**
	 * This function is used to load HTML of Assets Manager module.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return mixed
	 */
	public function assets_manager_html() {

		$assets_list = $this->prepare_assets_list();
		?>
		<div id="perform-assets-manager-wrap" class="perform-assets-manager-wrap">
			<form class="perform-assets-manager--form" method='POST'>
				<div class="perform-assets-manager">
					<div class="perform-assets-manager--header">
						<div class="perform-assets-manager--logo">
							<?php esc_html_e( 'Perform', 'perform' ); ?>
						</div>
						<?php /*
						<ul class="perform-assets-manager--menu">
							<li class="perform-assets-manager--menu-item">
								<a href="">
									<?php esc_html_e( 'Assets Manager', 'perform' ); ?>
								</a>
							</li>
							<li class="perform-assets-manager--menu-item">
								<a href="">
									<?php esc_html_e( 'Statistics', 'perform' ); ?>
								</a>
							</li>
						</ul>
						*/ ?>
						<div class="perform-assets-manager-header-actions">
							<input type="submit" name="perform_assets_manager" value="<?php esc_html_e( 'Save', 'perform' ); ?>" />
						</div>
					</div>
					<div id="perform-assets-manager--main">
						<div class='perform-assets-manager--title'>
							<h3>
								<?php esc_html_e( 'Assets Manager', 'perform' ); ?>
							</h3>
							<p>
								<?php esc_html_e( 'Optimise loading of assets on this page.', 'perform' ); ?>
							</p>
						</div>
							<?php
							foreach ( $assets_list as $category => $groups ) {
								if ( ! empty( $groups ) ) {
									?>
									<div class="perform-assets-manager--section">
										<h3><?php echo ucwords( $category ); ?></h3>
										<?php
										if ( 'misc' !== $category ) {
											foreach ( $groups as $group => $details ) {
												$this->print_assets_manager_group( $category, $group, $details );
											}
										} else {
											$details = array(
												'assets' => $groups,
											);
											$this->print_assets_manager_group( $category, $group, $details );
										}

										?>
									</div>
									<?php
								}
							}
							?>
					</div>
					<div id="perform-assets-manager--footer">
					</div>
				</div>
			</form>
        </div>
		<?php
	}

	/**
	 * This function will be used to prepare assets list.
	 *
	 * @since  1.1.0
	 * @access public
	 *
	 * @return array
	 */
	public function prepare_assets_list() {

		// Load the `get_plugins` function, if it is not loaded.
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Define required global variables.
		global $wp_scripts, $wp_styles;

		$assets_data = array(
			'plugins' => array(),
			'themes'  => array(),
			'misc'    => array(),
		);

		$all_assets = array(
			'js'  => array(
				'title'   => 'JS',
				'scripts' => $wp_scripts,
			),
			'css' => array(
				'title'   => 'CSS',
				'scripts' => $wp_styles,
			),
		);

		$this->loaded_assets = $all_assets;

		$loaded_plugins  = array();
		$loaded_themes   = array();
		$content_dirname = perform_get_content_dir_name();

		foreach ( $all_assets as $type => $data ) {

		    // List all the assets of the WordPress site.
		    $assets = $data['scripts']->done;

			if ( is_array( $assets ) && count( $assets ) ) {

				foreach ( $assets as $key => $handle ) {

				    $url = $all_assets[ $type ]['scripts']->registered[ $handle ]->src;

					if ( strpos( $url, "/{$content_dirname}/plugins/" ) !== false ) {

						$url_split   = explode( "/{$content_dirname}/plugins/", $url );
						$plugin_slug = strtok( $url_split[1], '/' );

						if ( ! array_key_exists( $plugin_slug, $loaded_plugins ) ) {
							$plugin_details = get_plugins( "/{$plugin_slug}" );
							$loaded_plugins[ $plugin_slug ] = $plugin_details;
							$assets_data['plugins'][ $plugin_slug ] = array(
                                'name' => $plugin_details[ key( $plugin_details ) ]['Name'],
                            );
						} else {
							$plugin_details = $loaded_plugins[ $plugin_slug ];
						}

						$assets_data['plugins'][ $plugin_slug ]['assets'][] = array(
                            'type'   => $type,
                            'handle' => $handle,
                        );

					} elseif ( strpos( $url, "/{$content_dirname}/themes/" ) !== false ) {

						$url_split   = explode( "/{$content_dirname}/themes/", $url );
						$plugin_slug = strtok( $url_split[1], '/' );

						if ( ! array_key_exists( $plugin_slug, $loaded_themes ) ) {

						    $theme_details = wp_get_theme( '/' . $plugin_slug );
							$loaded_themes[ $plugin_slug ] = $theme_details;
							$assets_data['themes'][ $plugin_slug ] = array(
                                'name' => $theme_details->get( 'Name' ),
                            );

						} else {
							$theme_details = $loaded_themes[ $plugin_slug ];
						}

						$assets_data['themes'][ $plugin_slug ]['assets'][] = array(
                            'type'   => $type,
                            'handle' => $handle,
                        );
					} else {
						$assets_data['misc'][] = array('type' => $type, 'handle' => $handle);
					}
				}
			}
		}

		return $assets_data;
	}

	/**
     * This function is used to print section for assets manager.
     *
	 * @param $category
	 * @param $group
	 * @param array $asset_details List of all assets.
     *
     * @since  1.1.0
	 * @access public
     *
     * @return mixed
	 */
	public function print_assets_manager_group( $category, $group, $asset_details ) {
		?>
		<div class="perform-assets-manager--group">
			<?php
			if ( 'misc' !== $category ) {
				?>
				<div class="perform-assets-manager-group--title">
					<h4><?php echo esc_html( $asset_details['name'] ); ?></h4>
					<div class='perform-assets-manager-group--status' style='float: right;'>
						<?php $this->print_assets_manager_status( $category, $group ); ?>
					</div>
				</div>
				<?php
			}
			?>
			<table>
				<thead>
					<tr>
						<th style=''>
							<?php echo esc_html__( 'Handle', 'perform' ); ?>
						</th>
						<th style=''>
							<?php echo esc_html__( 'Assets URL', 'perform' ); ?>
						</th>
						<th>
							<?php echo esc_html__( 'Type', 'perform' ); ?>
						</th>
						<th>
							<?php echo esc_html__( 'Size', 'perform' ); ?>
						</th>
						<th>
							<?php echo esc_html__( 'Status', 'perform' ); ?>
						</th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $asset_details['assets'] as $key => $details ) {
						$this->print_assets_manager_script( $category, $group, $details['handle'], $details['type'] );
					}
				?>
				</tbody>
			</table>
			<?php $this->disable_group_assets_html( $category, $group ); ?>
		</div>
    <?php
	}

	/**
	 * @param $category
	 * @param $group
	 * @param $script
	 * @param $type
	 */
	public function print_assets_manager_script( $category, $group, $script, $type ) {

			$data   = $this->loaded_assets[ $type ];
			$handle = $data['scripts']->registered[ $script ]->handle;
			$src    = $data['scripts']->registered[ $script ]->src;

            if ( ! empty( $src ) ) {
				?>
				<tr>
					<td class="perform-assets-manager--handle">
						<?php echo esc_html( $handle ); ?>
					</td>
					<td class="perform-assets-manager--url">
						<a href="<?php echo esc_url( $src ); ?>" target="_blank"><?php echo str_replace( get_home_url(), '', $src ); ?></a>
						<input type="hidden" name="<?php echo esc_html( "relations[{$type}][{$handle}][category]" ); ?>" value="<?php echo $category; ?>" />
						<input type="hidden" name="<?php echo esc_html( "relations[{$type}][{$handle}][group]" ); ?>", value="<?php echo $group; ?>" />
					</td>
					<td class="perform-assets-manager--type">
						<?php
						if ( ! empty( $type ) ) {
							echo esc_html( $type );
						}
						?>
					</td>
					<td class='perform-assets-manager--size'>
						<?php
						$asset_path = ABSPATH . str_replace( site_url( '/' ), '', $data['scripts']->registered[ $script ]->src );
						if ( file_exists( $asset_path ) ) {
							echo esc_html( round( filesize( $asset_path ) / 1024, 1 ) . ' KB' );
						}
						?>
					</td>
					<td class='perform-assets-manager--status'>
						<?php $this->print_assets_manager_status( $type, $handle ); ?>
						<?php $this->disable_single_asset_html( $type, $handle ); ?>
					</td>
				</tr>
			<?php
		}
	}

	/**
	 * Disable Assets HTML markup for single/individual assets
	 *
	 * @param string $type   Asset type.
	 * @param string $handle Asset handle.
	 *
	 * @since  1.1.0
	 * @access public
	 *
	 * @return void
	 */
	public function disable_single_asset_html( $type, $handle ) {

		$is_selected  = is_array( $this->selected_options['disabled'][ $type ][ $handle ] ) ? 'selected="selected"' : '';
		$show_options = $is_selected ? 'display: block;' : 'display: none;';
		?>
		<div class="perform-assets-manager-disable-single-asset perform-assets-manager-disable-assets" style="<?php echo esc_html( $show_options ); ?>">
			<?php $this->disable_assets_html( $type, $handle ); ?>
		</div>
		<?php
	}

	/**
	 * Disable Assets HTML markup for group assets.
	 *
	 * @param string $type   Asset type.
	 * @param string $handle Asset handle.
	 *
	 * @since  1.1.0
	 * @access public
	 *
	 * @return void
	 */
	public function disable_group_assets_html( $type, $handle ) {
		?>
		<div class="perform-assets-manager-disable-group-assets perform-assets-manager-disable-assets" style="display: none;">
			<?php $this->disable_assets_html( $type, $handle ); ?>
			<p>
				<?php esc_html_e( 'All assets in this group have been disabled. Please enable the group to individually manager assets.', 'perform' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Disable Assets HTML markup
	 *
	 * @param string $type   Asset type.
	 * @param string $handle Asset handle.
	 *
	 * @since  1.1.0
	 * @access public
	 *
	 * @return void
	 */
	public function disable_assets_html( $type, $handle ) {
		$is_checked   = '';
		$radio_inputs = array(
			'current'    => esc_html__( 'Current URL', 'perform' ),
			'everywhere' => esc_html__( 'Everywhere', 'perform' ),
		);
		?>
		<div class="perform-assets-manager-disable-asset-option-selection">
			<strong>
				<?php esc_html_e( 'Disable on', 'perform' ); ?>
			</strong>
			<?php
			foreach ( $radio_inputs as $key => $value ) {
				$is_checked = checked( $this->selected_options['disabled'][ $type ][ $handle ][ $key ], 1, false );

				if (
					empty( $is_checked ) &&
					get_the_ID() === $this->selected_options['disabled'][ $type ][ $handle ][ $key ][0]
				) {
					$is_checked = " checked='checked'";
				}
				?>
				<label for="<?php echo esc_html( "disabled-{$type}-{$handle}-{$key}" ); ?>">
					<input type="radio" name="disabled[<?php echo $type; ?>][<?php echo $handle; ?>]" id="<?php echo esc_html( "disabled-{$type}-{$handle}-{$key}" ); ?>" class="perform-disable-assets" value="<?php echo $key; ?>"<?php echo $is_checked; ?>/>
					<?php echo $value; ?>
				</label>
				<?php
			}
			?>
			<?php $this->print_assets_manager_exceptions( $type, $handle ); ?>
		</div>
		<?php
	}

	/**
	 * This function will load assets manager status and field to change it.
	 *
	 * @param string $type   Type of asset.
	 * @param string $handle Handle of asset.
	 *
	 * @since 1.1.0
	 *
	 * @return mixed|void
	 */
	public function print_assets_manager_status( $type, $handle ) {

		$is_selected   = is_array( $this->selected_options['disabled'][ $type ][ $handle ] ) ? 'selected="selected"' : '';
		$disable_class = ! empty( $is_selected ) ? 'disabled' : '';
		?>
		<select name="status[<?php echo esc_html( $type ); ?>][<?php echo esc_html( $handle ); ?>]" class="perform-status-select <?php echo esc_html( $disable_class ); ?>">
			<option value='enabled' class='perform-option-enabled'>
				<?php echo esc_attr__( 'ON', 'perform' ); ?>
			</option>
			<option value='disabled' class='perform-option-everywhere' <?php echo $is_selected; ?>>
				<?php echo esc_attr__( 'OFF', 'perform' ); ?>
			</option>
		</select>
		<?php
	}

	/**
	 * This function is used to print exceptions HTML markup.
	 *
	 * @param string $type   Asset type.
	 * @param string $handle Asset handle.
	 *
	 * @since  1.1.0
	 * @access public
	 *
	 * @return void
	 */
	public function print_assets_manager_exceptions( $type, $handle ) {
		$current_id          = get_the_ID();
		$selected_post_types = isset( $this->selected_options['enabled'][ $type ][ $handle ]['post_types'] ) ? $this->selected_options['enabled'][ $type ][ $handle ]['post_types'] : false;
		$is_selected         = selected( $this->selected_options['disabled'][ $type ][ $handle ]['everywhere'], 1, false );
		$show_options        = $is_selected ? 'display: block;' : 'display: none;';
		$is_current_checked  = in_array( $current_id, $this->selected_options['enabled'][ $type ][ $handle ]['current'] ) ? ' checked="checked"' : '';
		?>
		<div class="perform-assets-manager--exceptions" style="<?php echo esc_html( $show_options ); ?>">
			<div class="perform-assets-manager-exceptions--title">
				<?php esc_html_e( 'Exceptions', 'perform' ); ?>
			</div>

			<div class="perform-assets-manager-exception--options">
				<input type="hidden" name="enabled[<?php echo $type; ?>][<?php echo $handle; ?>][current]" value="" />
				<label for="<?php echo "{$type}-{$handle}-enable-current"; ?>">
					<input type="checkbox" name="enabled[<?php echo $type; ?>][<?php echo $handle; ?>][current]" id="<?php echo "{$type}-{$handle}-enable-current"; ?>" value="<?php echo $current_id; ?>" <?php echo $is_current_checked; ?>/>
					<?php esc_html_e( 'Current URL', 'perform' ); ?>
				</label>

				<span style='display: block; font-size: 10px; font-weight: bold; margin: 0px;'>Post Types</span>
				<?php
				$post_types = get_post_types(
					array(
						'public' => true,
					),
					'objects',
					'and'
				);
				if ( ! empty( $post_types ) ) {

					if ( isset( $post_types['attachment'] ) ) {
						unset( $post_types['attachment'] );
					}
					?>
					<input type="hidden" name="enabled[<?php echo $type; ?>][<?php echo $handle; ?>][post_types]" value="" />
					<?php

					foreach ( $post_types as $key => $value ) {

						$is_post_type_selected = '';
						if ( in_array( $key, $selected_post_types ) ) {
							$is_post_type_selected = " checked='checked'";
						}
						?>
						<label for="<?php echo "{$type}-{$handle}-enable-{$key}"; ?>">
							<input type="checkbox" name="enabled[<?php echo $type; ?>][<?php echo $handle; ?>][post_types][]" id="<?php echo "{$type}-{$handle}-enable-{$key}"; ?>" value="<?php echo $key; ?>" <?php echo $is_post_type_selected; ?> />
							<?php echo $value->label; ?>
						</label>
						<?php
					}
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Saves Assets Manager Optimization Settings.
	 *
	 * @since  1.1.0
	 * @access public
	 *
	 * @return array
	 */
	public function update_assets_manager() {

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
								unset( $options['enabled'][ $type ][$handle ]['archives'] );
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
	public function dequeue_assets( $src, $handle ) {

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
		if ( ! empty( $category ) && ! empty( $group ) && isset( $options['disabled'][ $category ][ $group] ) ) {
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
}
