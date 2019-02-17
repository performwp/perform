<?php
/**
 * Perform - Assets Manager.
 *
 * @since 1.0.0
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
 * @since 1.0.0
 */
class Perform_Assets_Manager {
	
	/**
	 *
	 */
	public $loaded_assets = array();
 
	/**
     * Check whether the assets manager is enabled?
     *
     * @since  1.0.0
     * @access public
     *
	 * @var bool
	 */
    public $is_assets_manager_enabled = false;
    
	/**
	 * Perform_Assets_Manager Constructor.
	 *
	 * @since  1.0.0
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
	 * @since  1.0.0
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

		$href = add_query_arg(
			str_replace( array( '&perform', 'perform' ), '', $_SERVER['QUERY_STRING'] ),
			'',
			home_url( $wp->request )
		);

		if ( ! isset( $_GET['perform'] ) ) {
			$href .= ! empty( $_SERVER['QUERY_STRING'] ) ? '&perform' : '?perform';
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
			<div id="perform-assets-manager">
				<div id="perform-assets-manager--header">
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
                        <h2><?php echo __('Assets Manager', 'perform'); ?></h2>
                        <p>
                            <?php echo __('Optimise loading of assets on this page.', 'perform'); ?>
                        </p>
                    </div>
                    <form class="perform-assets-manager--form" method='POST'>
                        <?php
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
                    </form>
                </div>
                <div id="perform-assets-manager--footer">
                    <input type="submit" name="perform_assets_manager" value="<?php echo esc_html__( 'Save', 'perform' ); ?>" />
                </div>
            </div>
        </div>
		<?php
	}

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

		$loaded_plugins = array();
		$loaded_themes  = array();

		foreach ( $all_assets as $type => $data ) {
			
		    // List all the assets of the WordPress site.
		    $assets = $data['scripts']->done;
			
			if ( is_array( $assets ) && count( $assets ) ) {
				
			

//				uasort( $plug_org_scripts, function( $a, $b ) use ( $type ) {
//
//					if ( $all_assets[ $type ]['scripts']->registered[$a]->src == $all_assets[$type]['scripts']->registered[$b]->src ) {
//						return 0;
//					}
//					return ($all_assets[$type]['scripts']->registered[$a]->src < $all_assets[$type]['scripts']->registered[$b]->src ) ? -1 : 1;
//				});

				foreach ( $assets as $key => $handle ) {
					
				    $url = $all_assets[$type]['scripts']->registered[$handle]->src;
					
					if ( strpos( $url, '/wp-content/plugins/' ) !== false ) {
					 
						$url_split   = explode( '/wp-content/plugins/', $url );
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
						
						$assets_data['plugins'][$plugin_slug]['assets'][] = array(
                            'type' => $type,
                            'handle' => $handle,
                        );
					
					} else if ( strpos( $url, '/wp-content/themes/' )  !== false ) {
					
						$url_split   = explode( '/wp-content/themes/', $url );
						$plugin_slug = strtok( $url_split, '/' );
						
						if ( ! array_key_exists( $plugin_slug, $loaded_themes ) ) {
							
						    $theme_details = wp_get_theme('/' . $plugin_slug);
							$loaded_themes[$plugin_slug] = $theme_details;
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
                 <?php $this->print_assets_manager_status($type, $handle); ?>
                </td>
                
			</tr>
			<?php
		}
	}
	
	/**
	 * @param $type
	 * @param $handle
	 */
	function print_assets_manager_status($type, $handle) {
//		?>
<!--			<select name='status[--><?php //echo $type; ?><!--][--><?php //echo $handle; ?><!--]' class='perform-status-select'>-->
<!--			<option value='enabled' class='perform-option-enabled'>--><?php //echo __('ON', 'perform'); ?><!--</option>-->
<!--			<option value='disabled' class='perform-option-everywhere'>--><?php //echo __('OFF', 'perform'); ?><!--</option>-->
<!--			</select>-->
<!--	    --><?php
	}
	
	
	function print_assets_manager_disable($type, $handle) {
		
		echo "<div class='perform-script-manager-disable'>";
		echo "<div style='font-size: 16px;'>" . __('Disabled', 'perfmatters') . "</div>";
		echo "<label for='disabled-" . $type . "-" . $handle . "-everywhere'>";
		echo "<input type='radio' name='disabled[" . $type . "][" . $handle . "]' id='disabled-" . $type . "-" . $handle . "-everywhere' class='perform-disable-select' value='everywhere' ";
		echo (!empty($options['disabled'][$type][$handle]['everywhere']) ? "checked" : "");
		echo " />";
		echo __('Everywhere', 'perfmatters');
		echo "</label>";
		
		echo "<label for='disabled-" . $type . "-" . $handle . "-current'>";
		echo "<input type='radio' name='disabled[" . $type . "][" . $handle . "]' id='disabled-" . $type . "-" . $handle . "-current' class='perform-disable-select' value='current' ";
		echo (isset($options['disabled'][$type][$handle]['current']) && in_array($currentID, $options['disabled'][$type][$handle]['current']) ? "checked" : "");
		echo " />";
		echo __('Current URL', 'perfmatters');
		echo "</label>";
		echo "</div>";
	}
	
	function print_assets_manager_enable($type, $handle) {
		global $perfmatters_script_manager_settings;
		global $perfmatters_script_manager_options;
		global $currentID;
		$options = $perfmatters_script_manager_options;
		
		echo "<div class='perform-script-manager-enable'"; if(empty($options['disabled'][$type][$handle]['everywhere'])) { echo " style='display: none;'"; } echo">";
		
		echo "<div style='font-size: 16px;'>" . __('Exceptions', 'perfmatters') . "</div>";
		
		//Current URL
		echo "<input type='hidden' name='enabled[" . $type . "][" . $handle . "][current]' value='' />";
		echo "<label for='" . $type . "-" . $handle . "-enable-current'>";
		echo "<input type='checkbox' name='enabled[" . $type . "][" . $handle . "][current]' id='" . $type . "-" . $handle . "-enable-current' value='" . $currentID ."' ";
		if(isset($options['enabled'][$type][$handle]['current'])) {
			if(in_array($currentID, $options['enabled'][$type][$handle]['current'])) {
				echo "checked";
			}
		}
		echo " />" . __('Current URL', 'perfmatters');
		echo "</label>";
		
		//Post Types
		echo "<span style='display: block; font-size: 10px; font-weight: bold; margin: 0px;'>Post Types:</span>";
		$post_types = get_post_types(array('public' => true), 'objects', 'and');
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
	
	
	function update_assets_manager() {
		
		if ( isset( $_GET['perform'] ) && ! empty( $_POST['perform_assets_manager'] ) ) {
			
			$currentID = get_queried_object_id();
			
			$perfmatters_filters = array("js", "css", "plugins", "themes");
			
			$options = get_option('perfmatters_script_manager');
			$settings = get_option('perfmatters_script_manager_settings');
			
			foreach($perfmatters_filters as $type) {
				
				if(isset($_POST['disabled'][$type])) {
					
					foreach($_POST['disabled'][$type] as $handle => $value) {
						
						$groupDisabled = false;
						if(isset($_POST['relations'][$type][$handle])) {
							$relationInfo = $_POST['relations'][$type][$handle];
							if($_POST['status'][$relationInfo['category']][$relationInfo['group']] == "disabled" && isset($_POST['disabled'][$relationInfo['category']][$relationInfo['group']])) {
								$groupDisabled = true;
							}
						}
						
						if(!$groupDisabled && $_POST['status'][$type][$handle] == 'disabled' && !empty($value)) {
							if($value == "everywhere") {
								$options['disabled'][$type][$handle]['everywhere'] = 1;
								if(!empty($options['disabled'][$type][$handle]['current'])) {
									unset($options['disabled'][$type][$handle]['current']);
								}
							}
                            elseif($value == "current") {
								if(isset($options['disabled'][$type][$handle]['everywhere'])) {
									unset($options['disabled'][$type][$handle]['everywhere']);
								}
								if(!is_array($options['disabled'][$type][$handle]['current'])) {
									$options['disabled'][$type][$handle]['current'] = array();
								}
								if(!in_array($currentID, $options['disabled'][$type][$handle]['current'])) {
									array_push($options['disabled'][$type][$handle]['current'], $currentID);
								}
							}
						}
						else {
							unset($options['disabled'][$type][$handle]['everywhere']);
							if(isset($options['disabled'][$type][$handle]['current'])) {
								$current_key = array_search($currentID, $options['disabled'][$type][$handle]['current']);
								if($current_key !== false) {
									unset($options['disabled'][$type][$handle]['current'][$current_key]);
									if(empty($options['disabled'][$type][$handle]['current'])) {
										unset($options['disabled'][$type][$handle]['current']);
									}
								}
							}
						}
						
						if(empty($options['disabled'][$type][$handle])) {
							unset($options['disabled'][$type][$handle]);
							if(empty($options['disabled'][$type])) {
								unset($options['disabled'][$type]);
								if(empty($options['disabled'])) {
									unset($options['disabled']);
								}
							}
						}
					}
				}
				
				if(isset($_POST['enabled'][$type])) {
					
					foreach($_POST['enabled'][$type] as $handle => $value) {
						
						$groupDisabled = false;
						if(isset($_POST['relations'][$type][$handle])) {
							$relationInfo = $_POST['relations'][$type][$handle];
							if($_POST['status'][$relationInfo['category']][$relationInfo['group']] == "disabled" && isset($_POST['disabled'][$relationInfo['category']][$relationInfo['group']])) {
								$groupDisabled = true;
							}
						}
						
						if(!$groupDisabled && $_POST['status'][$type][$handle] == 'disabled' && (!empty($value['current']) || $value['current'] === "0")) {
							if(!is_array($options['enabled'][$type][$handle]['current'])) {
								$options['enabled'][$type][$handle]['current'] = array();
							}
							if(!in_array($value['current'], $options['enabled'][$type][$handle]['current'])) {
								array_push($options['enabled'][$type][$handle]['current'], $value['current']);
							}
						}
						else {
							if(isset($options['enabled'][$type][$handle]['current'])) {
								$current_key = array_search($currentID, $options['enabled'][$type][$handle]['current']);
								if($current_key !== false) {
									unset($options['enabled'][$type][$handle]['current'][$current_key]);
									if(empty($options['enabled'][$type][$handle]['current'])) {
										unset($options['enabled'][$type][$handle]['current']);
									}
								}
							}
						}
						
						if(!$groupDisabled && $_POST['status'][$type][$handle] == 'disabled' && !empty($value['post_types'])) {
							$options['enabled'][$type][$handle]['post_types'] = array();
							foreach($value['post_types'] as $key => $post_type) {
								if(isset($options['enabled'][$type][$handle]['post_types'])) {
									if(!in_array($post_type, $options['enabled'][$type][$handle]['post_types'])) {
										array_push($options['enabled'][$type][$handle]['post_types'], $post_type);
									}
								}
							}
						}
						else {
							unset($options['enabled'][$type][$handle]['post_types']);
						}
						
						//filter out empty child arrays
						if(!empty($settings['separate_archives']) && $settings['separate_archives'] == "1") {
							$value['archives'] = array_filter($value['archives']);
							if(!$groupDisabled && $_POST['status'][$type][$handle] == 'disabled' && !empty($value['archives'])) {
								$script_manager_archives = array('wp', 'taxonomies', 'post_types');
								foreach($script_manager_archives as $archive_type) {
									if(!empty($value['archives'][$archive_type])) {
										$options['enabled'][$type][$handle]['archives'][$archive_type] = array();
										foreach($value['archives'][$archive_type] as $key => $archive) {
											if(isset($options['enabled'][$type][$handle]['archives'][$archive_type])) {
												if(!in_array($post_type, $options['enabled'][$type][$handle]['archives'][$archive_type])) {
													array_push($options['enabled'][$type][$handle]['archives'][$archive_type], $archive);
												}
											}
										}
									}
									else {
										unset($options['enabled'][$type][$handle]['archives'][$archive_type]);
									}
								}
							}
							else {
								unset($options['enabled'][$type][$handle]['archives']);
							}
						}
						
						if(empty($options['enabled'][$type][$handle])) {
							unset($options['enabled'][$type][$handle]);
							if(empty($options['enabled'][$type])) {
								unset($options['enabled'][$type]);
								if(empty($options['enabled'])) {
									unset($options['enabled']);
								}
							}
						}
					}
				}
			}
			update_option('perfmatters_script_manager', $options);
		}
	}
	
	function dequeue_assets($src, $handle) {
		if(is_admin()) {
			return $src;
		}
		
		//get script type
		$type = current_filter() == 'script_loader_src' ? "js" : "css";
		
		//load options
		$options = get_option('perfmatters_script_manager');
		$currentID = get_queried_object_id();
		
		//get category + group from src
		preg_match('/\/wp-content\/(.*?\/.*?)\//', $src, $match);
		if(!empty($match[1])) {
			$match = explode("/", $match[1]);
			$category = $match[0];
			$group = $match[1];
		}
		
		//check for group disable settings and override
		if(!empty($category) && !empty($group) && isset($options['disabled'][$category][$group])) {
			$type = $category;
			$handle = $group;
		}
		
		//disable is set, check options
		if((!empty($options['disabled'][$type][$handle]['everywhere']) && $options['disabled'][$type][$handle]['everywhere'] == 1) || (!empty($options['disabled'][$type][$handle]['current']) && in_array($currentID, $options['disabled'][$type][$handle]['current']))) {
			
			if(!empty($options['enabled'][$type][$handle]['current']) && in_array($currentID, $options['enabled'][$type][$handle]['current'])) {
				return $src;
			}
			
			if(is_front_page() || is_home()) {
				if(get_option('show_on_front') == 'page' && !empty($options['enabled'][$type][$handle]['post_types']) && in_array('page', $options['enabled'][$type][$handle]['post_types'])) {
					return $src;
				}
			}
			else {
				if(!empty($options['enabled'][$type][$handle]['post_types']) && in_array(get_post_type(), $options['enabled'][$type][$handle]['post_types'])) {
					return $src;
				}
			}
			
			if($handle == 'jquery-core' && $type == 'js' && isset($_GET['perform']) && current_user_can('manage_options')) {
				global $pmsm_jquery_disabled;
				$pmsm_jquery_disabled = true;
				return $src;
			}
			
			return false;
		}
		
		//original script src
		return $src;
	}
}
