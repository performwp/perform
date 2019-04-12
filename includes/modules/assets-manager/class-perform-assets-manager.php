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
	 * Perform_Assets_Manager Constructor.
	 *
	 * @since  1.1.0
	 * @access public
	 */
	public function __construct() {

		$this->is_assets_manager_enabled = Perform()->settings->get_option( 'enable_assets_manager', 'perform_advanced' );

		// Bailout, if assets manager is not enabled.
		if ( ! $this->is_assets_manager_enabled ) {
		    return;
		}

		add_action( 'admin_bar_menu', array( $this, 'add_assets_manager_admin_bar' ), 1000, 1 );

		if ( ! isset( $_GET['perform'] ) ) {
		    return;
        }

		add_action( 'wp_footer', array( $this, 'assets_manager_html' ), 1000 );
        add_action( 'script_loader_src', array( $this, 'dequeue_assets' ), 1000, 2 );
        add_action( 'style_loader_src', array( $this, 'dequeue_assets' ), 1000, 2 );
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

		$server_data = perform_clean( filter_input_array( 'INPUT_SERVER' ) );

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
							<h2>Perform</h2>
						</div>
						<ul class="perform-assets-manager--menu">
							<li class="perform-assets-manager--menu-item">
								<a href="">
									<?php echo __( 'Assets Manager', 'perform' ); ?>
								</a>
							</li>
							<li class="perform-assets-manager--menu-item">
								<a href="">
									<?php echo __( 'Statistics', 'perform' ); ?>
								</a>
							</li>
						</ul>
					</div>
					<div id="perform-assets-manager--main">
						<div class='perform-assets-manager--title'>
							<h2><?php echo __( 'Assets Manager', 'perform' ); ?></h2>
							<p>
								<?php echo __( 'Optimise loading of assets on this page.', 'perform' ); ?>
							</p>
						</div>
							<?php

							// echo "<pre>"; print_R($assets_list); echo "</pre>";
							foreach ( $assets_list as $category => $groups ) {
								if ( ! empty( $groups ) ) {
									?>
									<h3><?php echo ucwords( $category ); ?></h3>
									<?php
									if ( 'misc' !== $category ) {
										?>
										<div class="perform-assets-manager--section">
											<?php
											foreach ( $groups as $group => $details ) {
												if ( ! empty( $details['assets'] ) ) {
													?>
													<div class='perform-assets-manager--group'>
														<h3>
															<?php echo $details['name']; ?>
															<div class='perform-assets-manager-group--status' style='float: right;'>
																<?php $this->print_assets_manager_status($category, $group); ?>
															</div>
														</h3>
														<?php $this->print_assets_manager_section($category, $group, $details['assets']); ?>
													</div>
													<?php
												}
											}
											?>
										</div>
										<?php
									} else {
										if ( ! empty( $groups ) ) {
											$this->print_assets_manager_section( $category, $category, $groups );
										}
									}
								}
							}
							?>
					</div>
					<div id="perform-assets-manager--footer">
						<input type="submit" name="perform_assets_manager" value="<?php echo esc_html__( 'Save', 'perform' ); ?>" />
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
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
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
		$content_dirname = defined( 'WP_CONTENT_FOLDERNAME' ) ? WP_CONTENT_FOLDERNAME : 'wp-content';

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
                                'name' => $plugin_details[ key($plugin_details) ]['Name'],
                            );
						} else {
							$plugin_details = $loaded_plugins[ $plugin_slug ];
						}

						$assets_data['plugins'][ $plugin_slug ]['assets'][] = array(
                            'type' => $type,
                            'handle' => $handle,
                        );

					} elseif ( strpos( $url, "/{$content_dirname}/themes/" ) !== false ) {

						$url_split   = explode( "/{$content_dirname}/themes/", $url );
						$plugin_slug = strtok( $url_split[1], '/' );

						if ( ! array_key_exists( $plugin_slug, $loaded_themes ) ) {

						    $theme_details = wp_get_theme( '/' . $plugin_slug );
							$loaded_themes[ $plugin_slug ] = $theme_details;
							$assets_data['themes'][$plugin_slug] = array(
                                'name' => $theme_details->get('Name'),
                            );

						} else {
							$theme_details = $loaded_themes[$plugin_slug];
						}

						$assets_data['themes'][$plugin_slug]['assets'][] = array(
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
	 * @param array $assets List of all assets.
     *
     * @since 1.0.0
     *
     * @return mixed
	 */
	function print_assets_manager_section( $category, $group, $assets ) {
		global $currentID;

		?>
		<div class='perform-assets-manager--section'>
			<div class='perform-assets-manager--group'>
				<div class='perform-assets-manager--description'>
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
							foreach ( $assets as $key => $details ) {
								$this->print_assets_manager_script( $category, $group, $details['handle'], $details['type'] );
							}
						?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
    <?php
	}

	/**
	 * @param $category
	 * @param $group
	 * @param $script
	 * @param $type
	 */
	function print_assets_manager_script($category, $group, $script, $type) {

	        $data = $this->loaded_assets[$type];

            if( ! empty( $data["scripts"]->registered[$script]->src ) ) {


//			if(!empty($perfmatters_disables)) {
//				foreach($perfmatters_disables as $key => $val) {
//					if(strpos($data["scripts"]->registered[$script]->src, $val) !== false) {
//						//continue 2;
//						return;
//					}
//				}
//			}

			$handle = $data["scripts"]->registered[$script]->handle;
			?>
			<tr>

                <td class="perform-assets-manager--handle">
                    <?php echo $handle; ?>
                </td>
                <td class='perform-assets-manager--url'>
                    <a href='<?php echo $data["scripts"]->registered[$script]->src; ?>' target='_blank'><?php echo str_replace(get_home_url(), '', $data["scripts"]->registered[$script]->src); ?></a>
                </td>
                <td class='perform-assets-manager--type'>
                    <?php
                    if ( ! empty( $type ) ) {
                        echo $type;
                    }
                    ?>
                </td>
                <td class='perform-assets-manager--size'>
                    <?php
                    if( file_exists( ABSPATH . str_replace( get_home_url(), '', $data["scripts"]->registered[$script]->src ) ) ) {
                        echo round(filesize(ABSPATH . str_replace(get_home_url(), '', $data["scripts"]->registered[$script]->src)) / 1024, 1 ) . ' KB';
                    }
                    ?>
                </td>
                <td class='perform-assets-manager--status'>
				 <?php $this->print_assets_manager_status( $type, $handle ); ?>
				 <?php $this->disable_assets_html( $type, $handle ); ?>
                </td>

			</tr>
			<?php
		}
	}

	public function disable_assets_html( $type, $handle ) {
		?>
		<div class="perform-assets-manager--disable-options">
			<label for="<?php echo esc_html( "disabled-{$type}-{$handle}-current" ); ?>">
				<input type="radio" name="disabled[<?php echo esc_html( $type ); ?>][<?php echo esc_html( $handle ); ?>]" id="<?php echo esc_html( "disabled-{$type}-{$handle}-current" ); ?>" class="perform-disable-assets" value="current"/>
				<?php esc_html_e( 'Current URL', 'perform' ); ?>
			</label>
			<label for="<?php echo esc_html( "disabled-{$type}-{$handle}-everywhere" ); ?>">
				<input type="radio" name="disabled[<?php echo esc_html( $type ); ?>][<?php echo esc_html( $handle ); ?>]" id="<?php echo esc_html( "disabled-{$type}-{$handle}-everywhere" ); ?>" class="perform-disable-assets" value="everywhere"/>
				<?php esc_html_e( 'Everywhere', 'perform' ); ?>
			</label>
		</div>
		<?php

		$this->print_assets_manager_enable( $type, $handle );
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
		?>
		<select name="<?php echo esc_html( "perform_{$type}_{$handle}" ); ?>" class='perform-status-select'>
			<option value='enabled' class='perform-option-enabled'>
				<?php echo esc_attr__( 'ON', 'perform' ); ?>
			</option>
			<option value='disabled' class='perform-option-everywhere'>
				<?php echo esc_attr__( 'OFF', 'perform' ); ?>
			</option>
		</select>
		<?php
	}


	public function print_assets_manager_disable( $type, $handle ) {
		?>
		<div class="perform-script-manager-disable">;
			<div>
				<?php echo esc_html__( 'Disabled', 'perform' ); ?>
			</div>
			<label for="<?php echo esc_html( "disabled-{$type}-{$handle}-everywhere" ); ?>">
		<input type="radio" name="disabled[<?php echo $type; ?>][<?php echo $handle; ?>]" id="" class='perform-disable-select' value='everywhere' <?php echo (!empty($options['disabled'][$type][$handle]['everywhere']) ? "checked" : ""); ?> />
		<?php echo __('Everywhere', 'perform'); ?>
		</label>

		<label for='disabled-" . $type . "-" . $handle . "-current'>
		<input type='radio' name='disabled[" . $type . "][" . $handle . "]' id='disabled-" . $type . "-" . $handle . "-current' class='perform-disable-select' value='current' <?php  echo (isset($options['disabled'][$type][$handle]['current']) && in_array($currentID, $options['disabled'][$type][$handle]['current']) ? "checked" : ""); ?> />
		<?php echo __('Current URL', 'perform'); ?>
		</label>
		</div>
		<?php
	}

	public function print_assets_manager_enable( $type, $handle ) {

		$options = array();
		?>
		<div class="perform-assets-manager--exceptions">
			<div class="perform-assets-manager--title"><?php esc_html_e( 'Exceptions', 'perform' ); ?></div>


		<input type="hidden" name="enabled[<?php echo $type; ?>][<?php echo $handle; ?>][current]" value="" />
		<label for="<?php echo "{$type}-{$handle}-enable-current"; ?>">
			<input type="checkbox" name="enabled[<?php echo $type; ?>][<?php echo $handle; ?>][current]" id="<?php echo "{$type}-{$handle}-enable-current"; ?>" value=""/>
		</label>

		<span style='display: block; font-size: 10px; font-weight: bold; margin: 0px;'>Post Types:</span>
		<?php
		$post_types = get_post_types(
			array(
				'public' => true,
			),
			'objects',
			'and'
		);
		if(!empty($post_types)) {
			if(isset($post_types['attachment'])) {
				unset($post_types['attachment']);
			}
			echo "<input type='hidden' name='enabled[" . $type . "][" . $handle . "][post_types]' value='' />";
			foreach($post_types as $key => $value) {
				echo "<label for='" . $type . "-" . $handle . "-enable-" . $key . "'>";
				echo "<input type='checkbox' name='enabled[" . $type . "][" . $handle . "][post_types][]' id='" . $type . "-" . $handle . "-enable-" . $key . "' value='" . $key ."' ";
				if(isset($options['enabled'][$type][$handle]['post_types'])) {
					if(in_array($key, $options['enabled'][$type][$handle]['post_types'])) {
						echo "checked";
					}
				}
				echo " />" . $value->label;
				echo "</label>";
			}
		}

		//Archives
		if(!empty($perfmatters_script_manager_settings['separate_archives']) && $perfmatters_script_manager_settings['separate_archives'] == "1") {
			echo "<span style='display: block; font-size: 10px; font-weight: bold;'>Archives:</span>";

			//Built-In Tax Archives
			$wp_archives = array('category' => 'Categories', 'tag' => 'Tags', 'author' => 'Authors', 'date' => 'Dates');
			echo "<input type='hidden' name='enabled[" . $type . "][" . $handle . "][archives][wp]' value='' />";
			foreach($wp_archives as $key => $value) {
				echo "<label for='" . $type . "-" . $handle . "-enable-archive-wp-" . $key . "' title='" . $key . " (WordPress Taxonomy Archive)'>";
				echo "<input type='checkbox' name='enabled[" . $type . "][" . $handle . "][archives][wp][]' id='" . $type . "-" . $handle . "-enable-archive-wp-" . $key . "' value='" . $key ."' ";
				if(isset($options['enabled'][$type][$handle]['archives']['wp'])) {
					if(in_array($key, $options['enabled'][$type][$handle]['archives']['wp'])) {
						echo "checked";
					}
				}
				echo " />" . $value;
				echo "</label>";
			}

			//Custom Tax Archives
			$taxonomies = get_taxonomies(array('public' => true, '_builtin' => false), 'objects', 'and');
			if(!empty($taxonomies)) {
				echo "<input type='hidden' name='enabled[" . $type . "][" . $handle . "][archives][taxonomies]' value='' />";
				foreach($taxonomies as $key => $value) {
					echo "<label for='" . $type . "-" . $handle . "-enable-archive-taxonomy-" . $key . "' title='" . $key . " (Custom Taxonomy Archive)'>";
					echo "<input type='checkbox' name='enabled[" . $type . "][" . $handle . "][archives][taxonomies][]' id='" . $type . "-" . $handle . "-enable-archive-taxonomy-" . $key . "' value='" . $key ."' ";
					if(isset($options['enabled'][$type][$handle]['archives']['taxonomies'])) {
						if(in_array($key, $options['enabled'][$type][$handle]['archives']['taxonomies'])) {
							echo "checked";
						}
					}
					echo " />" . $value->label;
					echo "</label>";
				}
			}

			//Post Type Archives
			if(!empty($post_types)) {
				echo "<input type='hidden' name='enabled[" . $type . "][" . $handle . "][archives][post_types]' value='' />";
				foreach($post_types as $key => $value) {
					echo "<label for='" . $type . "-" . $handle . "-enable-archive-post-type-" . $key . "' title='" . $key . " (Post Type Archive)'>";
					echo "<input type='checkbox' name='enabled[" . $type . "][" . $handle . "][archives][post_types][]' id='" . $type . "-" . $handle . "-enable-archive-post-type-" . $key . "' value='" . $key ."' ";
					if(isset($options['enabled'][$type][$handle]['archives']['post_types'])) {
						if(in_array($key, $options['enabled'][$type][$handle]['archives']['post_types'])) {
							echo "checked";
						}
					}
					echo " />" . $value->label;
					echo "</label>";
				}
			}
		}

		echo "</div>";
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
							'disabled' === ! $group_disabled && $post_data['status'][ $type ][ $handle ] &&
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
								'disabled' === $post_data['status'][ $relation_info['category'] ][ $relationInfo['group'] ] &&
								isset( $post_data['disabled'][ $relation_info['category'] ][ $relation_info['group'] ] )
							) {
								$group_disabled = true;
							}
						}

						if (
							! $group_disabled &&
							'disabled' === $post_data['status'][ $type ][ $handle ] &&
							(
								! empty( $value['current'] ) ||
								'0' === $value['current']
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
			update_option( 'perform_assets_manager_options', $options );
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

		// Get assets type.
		$type = current_filter() == 'script_loader_src' ? 'js' : 'css';

		// Load Assets Manager settings.
		$options    = get_option( 'perform_assets_manager_options' );
		$current_id = get_queried_object_id();

		// Get category + group from src.
		preg_match( '/\/wp-content\/(.*?\/.*?)\//', $src, $match );

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
