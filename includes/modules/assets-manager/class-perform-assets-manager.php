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

		// Don't proceed, if Assets Manager is not enabled.
		if ( ! isset( $_GET['perform'] ) ) {
		    return;
		}

		$this->selected_options = get_option( 'perform_assets_manager_options' );

		$this->assets_manager_html();

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

		$is_disabled_type   = isset( $this->selected_options['disabled'][ $type ] );
		$is_disabled_handle = $is_disabled_type && isset( $this->selected_options['disabled'][ $type ][ $handle ] );
		$is_selected        = $is_disabled_handle && is_array( $this->selected_options['disabled'][ $type ][ $handle ] ) ? 'selected="selected"' : '';
		$show_options       = $is_selected ? 'display: block;' : 'display: none;';
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
		$current_id   = get_the_ID();
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

				$is_disabled_key = isset( $this->selected_options['disabled'][ $type ][ $handle ][ $key ] ) ? $this->selected_options['disabled'][ $type ][ $handle ][ $key ] : false;

				if (
					empty( $is_checked ) &&
					is_array( $is_disabled_key ) &&
					in_array( $current_id, $is_disabled_key )
				) {
					$is_checked = " checked='checked'";
				} else {
					$is_checked = checked( $is_disabled_key, 1, false );
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

		$is_disabled_type   = isset( $this->selected_options['disabled'][ $type ] );
		$is_disabled_handle = $is_disabled_type && isset( $this->selected_options['disabled'][ $type ][ $handle ] );
		$is_selected        = ( $is_disabled_handle && is_array( $this->selected_options['disabled'][ $type ][ $handle ] ) ) ? 'selected="selected"' : '';
		$disable_class      = ! empty( $is_selected ) ? 'disabled' : '';
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
		$is_selected         = isset( $this->selected_options['disabled'][ $type ][ $handle ]['everywhere'] ) ? selected( $this->selected_options['disabled'][ $type ][ $handle ]['everywhere'], 1, false ) : '';
		$show_options        = $is_selected ? 'display: block;' : 'display: none;';
		$current_exception   = isset( $this->selected_options['enabled'][ $type ][ $handle ]['current'] ) ? $this->selected_options['enabled'][ $type ][ $handle ]['current'] : false;
		$is_current_checked  = ( is_array( $current_exception ) && in_array( $current_id, $current_exception ) ) ? ' checked="checked"' : '';
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

						$is_post_type_selected = ( is_array( $selected_post_types ) && in_array( $key, $selected_post_types ) ) ? ' checked="checked"' : '';
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
}

new Perform_Assets_Manager();
