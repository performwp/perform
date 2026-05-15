<?php
/**
 * Perform - Assets Manager.
 *
 * @since 1.1.0
 *
 * @package Perform
 * @subpackage Modules/AssetsManager
 * @author PerformWP <hello@performwp.com>
 */

namespace Perform\Modules\Assets;

use Perform\Includes\Helpers;
use Perform\Modules\ModuleInterface;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AssetsManager implements ModuleInterface {

	/**
	 * Loaded Assets.
	 *
	 * @since  1.1.0
	 * @access public
	 *
	 * @var array
	 */
	public $loaded_assets = [];

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
	 * Constructor
	 *
	 * @since  1.1.0
	 * @access public
	 *
	 * @return void
	 */
	/**
	 * Determine whether this module should be loaded.
	 *
	 * @return bool
	 */
	public function should_load(): bool {
		$settings = Helpers::get_settings();
		return isset( $settings['enable_assets_manager'] ) && ! empty( $settings['enable_assets_manager'] );
	}

	/**
	 * Register hooks and filters for this module.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->selected_options = get_option( 'perform_assets_manager_options' );

		add_action( 'template_redirect', [ $this, 'save_assets_manager_settings' ], 10, 2 );

		add_filter( 'script_loader_src', [ $this, 'dequeue_assets' ], 1000, 2 );
		add_filter( 'style_loader_src', [ $this, 'dequeue_assets' ], 1000, 2 );

		// Only add HTML if accessed by admin with perform param.
		if ( isset( $_GET['perform'] ) && current_user_can( 'manage_options' ) ) {
			add_action( 'wp_footer', [ $this, 'assets_manager_html' ], 1000 );
		}
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
		$summary     = $this->get_assets_summary( $assets_list );
		$current_url = get_permalink();
		if ( empty( $current_url ) ) {
			$current_url = home_url( add_query_arg( [] ) );
		}
		?>
		<div id="perform-assets-manager" class="perform-assets-manager" role="dialog" aria-modal="true" aria-labelledby="perform-assets-manager-title">
			<form id="perform-assets-manager--form" method="POST">
				<?php wp_nonce_field( 'perform_assets_manager_save', 'perform_assets_manager_nonce' ); ?>
				<div class="perform-assets-manager--header">
					<div class="perform-assets-manager--brand">
						<img src="<?php echo esc_url( PERFORM_PLUGIN_URL . 'assets/dist/images/logo.png' ); ?>" alt="<?php esc_attr_e( 'Perform', 'perform' ); ?>" />
						<div>
							<span><?php esc_html_e( 'Page asset scanner', 'perform' ); ?></span>
							<strong><?php esc_html_e( 'Perform Assets Manager', 'perform' ); ?></strong>
						</div>
					</div>
					<div class="perform-assets-manager-header-actions">
						<a class="perform-assets-manager-button perform-assets-manager-button--secondary" href="<?php echo esc_url( remove_query_arg( 'perform' ) ); ?>"><?php esc_html_e( 'Close', 'perform' ); ?></a>
						<input class="perform-assets-manager-button perform-assets-manager-button--primary" type="submit" name="perform_assets_manager" value="<?php esc_attr_e( 'Save changes', 'perform' ); ?>" />
					</div>
				</div>
				<div id="perform-assets-manager--main">
					<div class='perform-assets-manager--title'>
						<p class="perform-assets-manager--eyebrow">
							<?php esc_html_e( 'Current page', 'perform' ); ?>
						</p>
						<h2 id="perform-assets-manager-title">
							<?php esc_html_e( 'Assets Manager', 'perform' ); ?>
						</h2>
						<p>
							<?php esc_html_e( 'Review scripts and styles detected on this page, then disable only the assets you have verified are not needed.', 'perform' ); ?>
						</p>
						<code><?php echo esc_html( $current_url ); ?></code>
					</div>
					<div class="perform-assets-manager--scanner" aria-label="<?php esc_attr_e( 'Asset scan summary', 'perform' ); ?>">
						<div class="perform-assets-manager--summary-grid">
							<div class="perform-assets-manager--summary-card">
								<span><?php esc_html_e( 'Detected assets', 'perform' ); ?></span>
								<strong><?php echo esc_html( (string) $summary['total'] ); ?></strong>
							</div>
							<div class="perform-assets-manager--summary-card">
								<span><?php esc_html_e( 'JavaScript', 'perform' ); ?></span>
								<strong><?php echo esc_html( (string) $summary['js'] ); ?></strong>
							</div>
							<div class="perform-assets-manager--summary-card">
								<span><?php esc_html_e( 'CSS', 'perform' ); ?></span>
								<strong><?php echo esc_html( (string) $summary['css'] ); ?></strong>
							</div>
							<div class="perform-assets-manager--summary-card">
								<span><?php esc_html_e( 'Disabled rules', 'perform' ); ?></span>
								<strong><?php echo esc_html( (string) $summary['disabled'] ); ?></strong>
							</div>
							<div class="perform-assets-manager--summary-card">
								<span><?php esc_html_e( 'Known file size', 'perform' ); ?></span>
								<strong><?php echo esc_html( $this->format_asset_size( $summary['size'] ) ); ?></strong>
							</div>
						</div>
						<div class="perform-assets-manager--toolbar">
							<label class="screen-reader-text" for="perform-assets-manager-search"><?php esc_html_e( 'Search detected assets', 'perform' ); ?></label>
							<input id="perform-assets-manager-search" type="search" placeholder="<?php esc_attr_e( 'Search by handle, source, or file URL', 'perform' ); ?>" />
							<div class="perform-assets-manager--filters" aria-label="<?php esc_attr_e( 'Filter detected assets', 'perform' ); ?>">
								<button type="button" class="is-active" data-perform-filter="all" aria-pressed="true"><?php esc_html_e( 'All', 'perform' ); ?></button>
								<button type="button" data-perform-filter="plugins" aria-pressed="false"><?php esc_html_e( 'Plugins', 'perform' ); ?></button>
								<button type="button" data-perform-filter="themes" aria-pressed="false"><?php esc_html_e( 'Themes', 'perform' ); ?></button>
								<button type="button" data-perform-filter="misc" aria-pressed="false"><?php esc_html_e( 'Other', 'perform' ); ?></button>
								<button type="button" data-perform-filter="js" aria-pressed="false"><?php esc_html_e( 'JS', 'perform' ); ?></button>
								<button type="button" data-perform-filter="css" aria-pressed="false"><?php esc_html_e( 'CSS', 'perform' ); ?></button>
								<button type="button" data-perform-filter="disabled" aria-pressed="false"><?php esc_html_e( 'Disabled', 'perform' ); ?></button>
							</div>
						</div>
					</div>
						<?php
						foreach ( $assets_list as $category => $groups ) {
							if ( ! empty( $groups ) ) {
								?>
								<div class="perform-assets-manager--section" data-perform-section="<?php echo esc_attr( $category ); ?>">
									<h3><?php echo esc_html( ucwords( $category ) ); ?></h3>
									<?php
										if ( 'misc' !== $category ) {
											foreach ( $groups as $group => $details ) {
												$this->print_assets_manager_group( $category, $group, $details );
											}
										} else {
											$group   = 'misc';
											$details = [
												'assets' => $groups,
											];
										$this->print_assets_manager_group( $category, $group, $details );
									}

									?>
								</div>
								<?php
							}
						}
						?>
						<p class="perform-assets-manager--no-results" hidden><?php esc_html_e( 'No assets match the current scan filter.', 'perform' ); ?></p>
				</div>
				<div id="perform-assets-manager--footer">
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

		$assets_data = [
			'plugins' => [],
			'themes'  => [],
			'misc'    => [],
		];

		$all_assets = [
			'js'  => [
				'title'   => 'JS',
				'scripts' => $wp_scripts,
			],
			'css' => [
				'title'   => 'CSS',
				'scripts' => $wp_styles,
			],
		];

		$this->loaded_assets = $all_assets;

		$loaded_plugins  = [];
		$loaded_themes   = [];
		$content_dirname = Helpers::get_content_dir_name();

		foreach ( $all_assets as $type => $data ) {
			// List all the assets of the WordPress site.
			$assets = $data['scripts']->done;

			if ( is_array( $assets ) && count( $assets ) ) {
				foreach ( $assets as $key => $handle ) {
					$url = $all_assets[ $type ]['scripts']->registered[ $handle ]->src;

					// Add handles which we don't want to show under Assets Manager.
					$incompatible_handles = [
						'perform',
						'admin-bar',
						'query-monitor',
					];

					// Don't show incompatible handles for Assets Manager.
					if ( in_array( $handle, $incompatible_handles, true ) ) {
						continue;
					}

					if ( strpos( $url, "/{$content_dirname}/plugins/" ) !== false ) {

						$url_split   = explode( "/{$content_dirname}/plugins/", $url );
						$plugin_slug = strtok( $url_split[1], '/' );

						if ( ! array_key_exists( $plugin_slug, $loaded_plugins ) ) {
							$plugin_details                         = get_plugins( "/{$plugin_slug}" );
							$loaded_plugins[ $plugin_slug ]         = $plugin_details;
							$assets_data['plugins'][ $plugin_slug ] = [
								'name' => $plugin_details[ key( $plugin_details ) ]['Name'],
							];
						} else {
							$plugin_details = $loaded_plugins[ $plugin_slug ];
						}

						$assets_data['plugins'][ $plugin_slug ]['assets'][] = [
							'type'   => $type,
							'handle' => $handle,
						];

					} elseif ( strpos( $url, "/{$content_dirname}/themes/" ) !== false ) {

						$url_split   = explode( "/{$content_dirname}/themes/", $url );
						$plugin_slug = strtok( $url_split[1], '/' );

						if ( ! array_key_exists( $plugin_slug, $loaded_themes ) ) {

							$theme_details                         = wp_get_theme( '/' . $plugin_slug );
							$loaded_themes[ $plugin_slug ]         = $theme_details;
							$assets_data['themes'][ $plugin_slug ] = [
								'name' => $theme_details->get( 'Name' ),
							];

						} else {
							$theme_details = $loaded_themes[ $plugin_slug ];
						}

						$assets_data['themes'][ $plugin_slug ]['assets'][] = [
							'type'   => $type,
							'handle' => $handle,
						];
					} else {
						$assets_data['misc'][] = [
							'type'   => $type,
							'handle' => $handle,
						];
					}
				}
			}
		}

		return $assets_data;
	}

	/**
	 * Build scan summary data for the current page.
	 *
	 * @param array $assets_list Grouped assets list.
	 *
	 * @return array
	 */
	private function get_assets_summary( $assets_list ) {
		$summary = [
			'total'    => 0,
			'js'       => 0,
			'css'      => 0,
			'disabled' => 0,
			'size'     => 0,
		];

		foreach ( $assets_list as $category => $groups ) {
			$groups = 'misc' === $category ? [ 'misc' => [ 'assets' => $groups ] ] : $groups;

			foreach ( $groups as $group => $details ) {
				if ( empty( $details['assets'] ) || ! is_array( $details['assets'] ) ) {
					continue;
				}

				foreach ( $details['assets'] as $asset ) {
					if ( empty( $asset['type'] ) || empty( $asset['handle'] ) ) {
						continue;
					}

					$type   = $asset['type'];
					$handle = $asset['handle'];
					$src    = $this->get_asset_src( $type, $handle );

					if ( empty( $src ) ) {
						continue;
					}

					$summary['total']++;
					if ( isset( $summary[ $type ] ) ) {
						$summary[ $type ]++;
					}

					if ( $this->is_rule_disabled( $type, $handle ) || ( 'misc' !== $category && $this->is_rule_disabled( $category, $group ) ) ) {
						$summary['disabled']++;
					}

					$summary['size'] += $this->get_asset_size( $src );
				}
			}
		}

		return $summary;
	}

	/**
	 * Format bytes for scanner display.
	 *
	 * @param int $bytes File size in bytes.
	 *
	 * @return string
	 */
	private function format_asset_size( $bytes ) {
		$bytes = (int) $bytes;
		if ( $bytes <= 0 ) {
			return '0 KB';
		}

		if ( $bytes >= 1048576 ) {
			return round( $bytes / 1048576, 1 ) . ' MB';
		}

		return round( $bytes / 1024, 1 ) . ' KB';
	}

	/**
	 * Get registered asset URL.
	 *
	 * @param string $type   Asset type.
	 * @param string $handle Asset handle.
	 *
	 * @return string
	 */
	private function get_asset_src( $type, $handle ) {
		if ( empty( $this->loaded_assets[ $type ]['scripts']->registered[ $handle ]->src ) ) {
			return '';
		}

		return (string) $this->loaded_assets[ $type ]['scripts']->registered[ $handle ]->src;
	}

	/**
	 * Get local file size for a registered asset when it can be resolved.
	 *
	 * @param string $src Asset source URL.
	 *
	 * @return int
	 */
	private function get_asset_size( $src ) {
		$site_url = site_url( '/' );
		if ( 0 !== strpos( $src, $site_url ) ) {
			return 0;
		}

		$asset_path = ABSPATH . ltrim( str_replace( $site_url, '', $src ), '/' );
		if ( ! file_exists( $asset_path ) ) {
			return 0;
		}

		return (int) filesize( $asset_path );
	}

	/**
	 * Check whether an asset or group has a disabled rule.
	 *
	 * @param string $type   Asset type or source category.
	 * @param string $handle Asset handle or source group.
	 *
	 * @return bool
	 */
	private function is_rule_disabled( $type, $handle ) {
		return ! empty( $this->selected_options['disabled'][ $type ][ $handle ] )
			&& is_array( $this->selected_options['disabled'][ $type ][ $handle ] );
	}

	/**
	 * This function is used to print section for assets manager.
	 *
	 * @param $category
	 * @param $group
	 * @param array    $asset_details List of all assets.
	 *
	 * @since  1.1.0
	 * @access public
	 *
	 * @return mixed
	 */
	public function print_assets_manager_group( $category, $group, $asset_details ) {
		$asset_count = ! empty( $asset_details['assets'] ) && is_array( $asset_details['assets'] ) ? count( $asset_details['assets'] ) : 0;
		?>
		<div class="perform-assets-manager--group" data-perform-group data-perform-source="<?php echo esc_attr( $category ); ?>">
			<?php
			if ( 'misc' !== $category ) {
				?>
				<div class="perform-assets-manager-group--title">
					<div>
						<span class="perform-assets-manager--source-label"><?php echo esc_html( ucwords( $category ) ); ?></span>
						<h4><?php echo esc_html( $asset_details['name'] ); ?></h4>
						<p>
							<?php
							printf(
								/* translators: %d: number of assets. */
								esc_html( _n( '%d detected asset', '%d detected assets', $asset_count, 'perform' ) ),
								(int) $asset_count
							);
							?>
						</p>
					</div>
					<div class='perform-assets-manager-group--status'>
						<?php $this->print_assets_manager_status( $category, $group ); ?>
					</div>
				</div>
				<?php
			} else {
				?>
				<div class="perform-assets-manager-group--title perform-assets-manager-group--title-misc">
					<div>
						<span class="perform-assets-manager--source-label"><?php esc_html_e( 'Other', 'perform' ); ?></span>
						<h4><?php esc_html_e( 'WordPress core, CDN, and uncategorized assets', 'perform' ); ?></h4>
						<p>
							<?php
							printf(
								/* translators: %d: number of assets. */
								esc_html( _n( '%d detected asset', '%d detected assets', $asset_count, 'perform' ) ),
								(int) $asset_count
							);
							?>
						</p>
					</div>
				</div>
				<?php
			}
			?>
			<table cellspacing="0" cellpadding="0" class="perform-assets-manager--assets-table">
				<thead>
					<tr>
						<th>
							<?php echo esc_html__( 'Handle', 'perform' ); ?>
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
						<th>
							<?php echo esc_html__( 'Actions', 'perform' ); ?>
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
	 * Print Assets Manager Script.
	 *
	 * @param $category
	 * @param $group
	 * @param $script
	 * @param $type
	 *
	 * @since  1.1.0
	 * @access public
	 *
	 * @return void
	 */
	public function print_assets_manager_script( $category, $group, $script, $type ) {
			$data   = $this->loaded_assets[ $type ];
			$handle = $data['scripts']->registered[ $script ]->handle;
			$src    = $this->get_asset_src( $type, $script );

		if ( ! empty( $src ) ) {
			$asset_size   = $this->get_asset_size( $src );
			$is_disabled  = $this->is_rule_disabled( $type, $handle ) || ( 'misc' !== $category && $this->is_rule_disabled( $category, $group ) );
			$asset_status = $is_disabled ? 'disabled' : 'enabled';
			?>
				<tr data-perform-asset-row data-perform-asset-type="<?php echo esc_attr( $type ); ?>" data-perform-asset-source="<?php echo esc_attr( $category ); ?>" data-perform-asset-status="<?php echo esc_attr( $asset_status ); ?>" data-perform-asset-handle="<?php echo esc_attr( $handle ); ?>">
					<td class="perform-assets-manager--handle">
						<strong><?php echo esc_html( $handle ); ?></strong>
						<code><?php echo esc_html( $src ); ?></code>
					</td>
					<td class="perform-assets-manager--type">
					<?php
					if ( ! empty( $type ) ) {
						printf(
							'<span class="perform-assets-manager--type-badge perform-assets-manager--type-badge-%1$s">%2$s</span>',
							esc_attr( $type ),
							esc_html( strtoupper( $type ) )
						);
					}
					?>
					</td>
					<td class='perform-assets-manager--size'>
					<?php
					echo $asset_size > 0 ? esc_html( $this->format_asset_size( $asset_size ) ) : esc_html__( 'External', 'perform' );
					?>
					</td>
					<td class='perform-assets-manager--status'>
					<?php $this->print_assets_manager_status( $type, $handle ); ?>
						<?php $this->disable_single_asset_html( $type, $handle ); ?>
					</td>
					<td class="perform-assets-manager--url">
						<a href="<?php echo esc_url( $src ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View File', 'perform' ); ?></a>
							<input type="hidden" name="<?php echo esc_attr( "relations[{$type}][{$handle}][category]" ); ?>" value="<?php echo esc_attr( $category ); ?>" />
							<input type="hidden" name="<?php echo esc_attr( "relations[{$type}][{$handle}][group]" ); ?>" value="<?php echo esc_attr( $group ); ?>" />
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
		?>
		<div class="perform-assets-manager-disable-single-asset perform-assets-manager-disable-assets" <?php echo empty( $is_selected ) ? 'hidden' : ''; ?>>
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
		<div class="perform-assets-manager-disable-group-assets perform-assets-manager-disable-assets" hidden>
			<?php $this->disable_assets_html( $type, $handle ); ?>
			<p>
				<?php esc_html_e( 'All assets in this group have been disabled. Enable the group again to manage individual assets.', 'perform' ); ?>
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
		$radio_inputs = [
			'current'    => esc_html__( 'Current URL', 'perform' ),
			'everywhere' => esc_html__( 'Everywhere', 'perform' ),
		];
		?>
		<fieldset class="perform-assets-manager-disable-asset-option-selection">
			<legend>
				<?php esc_html_e( 'Disable on', 'perform' ); ?>
			</legend>
			<div class="perform-assets-manager--radio-group">
			<?php
			foreach ( $radio_inputs as $key => $value ) {
				$is_disabled_key = isset( $this->selected_options['disabled'][ $type ][ $handle ][ $key ] ) ? $this->selected_options['disabled'][ $type ][ $handle ][ $key ] : false;

				if (
					empty( $is_checked ) &&
					is_array( $is_disabled_key ) &&
					in_array( $current_id, $is_disabled_key, true )
				) {
					$is_checked = " checked='checked'";
				} else {
					$is_checked = checked( $is_disabled_key, 1, false );
				}
				?>
				<label for="<?php echo esc_attr( "disabled-{$type}-{$handle}-{$key}" ); ?>">
							<input type="radio" name="disabled[<?php echo esc_attr( $type ); ?>][<?php echo esc_attr( $handle ); ?>]" id="<?php echo esc_attr( "disabled-{$type}-{$handle}-{$key}" ); ?>" class="perform-disable-assets" value="<?php echo esc_attr( $key ); ?>"<?php echo $is_checked; ?>/>
							<?php echo esc_html( $value ); ?>
				</label>
				<?php
			}
			?>
			</div>
			<?php $this->print_assets_manager_exceptions( $type, $handle ); ?>
		</fieldset>
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
		<select name="status[<?php echo esc_attr( $type ); ?>][<?php echo esc_attr( $handle ); ?>]" class="perform-status-select <?php echo esc_attr( $disable_class ); ?>" aria-label="<?php esc_attr_e( 'Asset loading status', 'perform' ); ?>">
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
		$current_exception   = isset( $this->selected_options['enabled'][ $type ][ $handle ]['current'] ) ? $this->selected_options['enabled'][ $type ][ $handle ]['current'] : false;
		$is_current_checked  = ( is_array( $current_exception ) && in_array( $current_id, $current_exception, true ) ) ? ' checked="checked"' : '';
		?>
		<div class="perform-assets-manager--exceptions" <?php echo empty( $is_selected ) ? 'hidden' : ''; ?>>
			<div class="perform-assets-manager-exceptions--title">
				<?php esc_html_e( 'Exceptions', 'perform' ); ?>
			</div>

			<div class="perform-assets-manager-exception--options">
							<input type="hidden" name="enabled[<?php echo esc_attr( $type ); ?>][<?php echo esc_attr( $handle ); ?>][current]" value="" />
							<label for="<?php echo esc_attr( "{$type}-{$handle}-enable-current" ); ?>">
								<input type="checkbox" name="enabled[<?php echo esc_attr( $type ); ?>][<?php echo esc_attr( $handle ); ?>][current]" id="<?php echo esc_attr( "{$type}-{$handle}-enable-current" ); ?>" value="<?php echo esc_attr( $current_id ); ?>" <?php echo $is_current_checked; ?>/>
					<?php esc_html_e( 'Current URL', 'perform' ); ?>
				</label>

				<span style='display: block; font-size: 10px; font-weight: bold; margin: 0px;'>Post Types</span>
				<?php
				$post_types = get_post_types(
					[
						'public' => true,
					],
					'objects',
					'and'
				);
				if ( ! empty( $post_types ) ) {
					if ( isset( $post_types['attachment'] ) ) {
						unset( $post_types['attachment'] );
					}
					?>
							<input type="hidden" name="enabled[<?php echo esc_attr( $type ); ?>][<?php echo esc_attr( $handle ); ?>][post_types]" value="" />
					<?php

					foreach ( $post_types as $key => $value ) {
						$is_post_type_selected = ( is_array( $selected_post_types ) && in_array( $key, $selected_post_types, true ) ) ? ' checked="checked"' : '';
						?>
						<label for="<?php echo esc_attr( "{$type}-{$handle}-enable-{$key}" ); ?>">
								<input type="checkbox" name="enabled[<?php echo esc_attr( $type ); ?>][<?php echo esc_attr( $handle ); ?>][post_types][]" id="<?php echo esc_attr( "{$type}-{$handle}-enable-{$key}" ); ?>" value="<?php echo esc_attr( $key ); ?>" <?php echo $is_post_type_selected; ?> />
								<?php echo esc_html( $value->label ); ?>
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
	public function save_assets_manager_settings() {
 		// Capability check.
 		if ( ! current_user_can( 'manage_options' ) ) {
 			return;
 		}

 		// Require the expected nonce and verify it to protect against CSRF.
 		if ( ! isset( $_POST['perform_assets_manager_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['perform_assets_manager_nonce'] ), 'perform_assets_manager_save' ) ) {
 			return;
 		}

 		$post_data = Helpers::clean( filter_input_array( INPUT_POST ) );
 		$get_data  = Helpers::clean( filter_input_array( INPUT_GET ) );

		if (
			isset( $get_data['perform'] ) &&
			! empty( $post_data['perform_assets_manager'] )
		) {

			$current_id = get_queried_object_id();
			$filters    = [ 'js', 'css', 'plugins', 'themes' ];
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
									$options['disabled'][ $type ][ $handle ]['current'] = [];
								}

								if ( ! in_array( $current_id, $options['disabled'][ $type ][ $handle ]['current'], true ) ) {
									array_push( $options['disabled'][ $type ][ $handle ]['current'], $current_id );
								}
							}
						} else {
							unset( $options['disabled'][ $type ][ $handle ]['everywhere'] );

							if ( isset( $options['disabled'][ $type ][ $handle ]['current'] ) ) {

								$current_key = array_search( $current_id, $options['disabled'][ $type ][ $handle ]['current'], true );

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
								$options['enabled'][ $type ][ $handle ]['current'] = [];
							}

							if ( ! in_array( $value['current'], $options['enabled'][ $type ][ $handle ]['current'], true ) ) {
								array_push( $options['enabled'][ $type ][ $handle ]['current'], $value['current'] );
							}
						} else {
							if ( isset( $options['enabled'][ $type ][ $handle ]['current'] ) ) {
								$current_key = array_search( $current_id, $options['enabled'][ $type ][ $handle ]['current'], true );

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
							$options['enabled'][ $type ][ $handle ]['post_types'] = [];

							foreach ( $value['post_types'] as $key => $post_type ) {
								if ( isset( $options['enabled'][ $type ][ $handle ]['post_types'] ) ) {
									if ( ! in_array( $post_type, $options['enabled'][ $type ][ $handle ]['post_types'], true ) ) {
										array_push( $options['enabled'][ $type ][ $handle ]['post_types'], $post_type );
									}
								}
							}
						} else {
							unset( $options['enabled'][ $type ][ $handle ]['post_types'] );
						}

						// Filter out empty child arrays.
						if ( ! empty( $settings['separate_archives'] ) && $settings['separate_archives'] == '1' ) {
							$value['archives'] = array_filter( $value['archives'] );

							if (
								! $group_disabled &&
								'disabled' === $post_data['status'][ $type ][ $handle ] &&
								! empty( $value['archives'] )
							) {
								$archives = [ 'wp', 'taxonomies', 'post_types' ];

								foreach ( $archives as $archive_type ) {
									if ( ! empty( $value['archives'][ $archive_type ] ) ) {
										$options['enabled'][ $type ][ $handle ]['archives'][ $archive_type ] = [];

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

						if ( empty( $options['enabled'][ $type ][ $handle ] ) ) {
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
	 * @return bool|string
	 */
	public function dequeue_assets( $src, $handle ) {

		if ( is_admin() ) {
			return $src;
		}

		$get_data = Helpers::clean( filter_input_array( INPUT_GET ) );

		// Get assets type.
		$type = current_filter() === 'script_loader_src' ? 'js' : 'css';

		// Load Assets Manager settings.
		$options         = get_option( 'perform_assets_manager_options' );
		$current_id      = get_queried_object_id();
		$content_dirname = Helpers::get_content_dir_name();

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
				in_array( $current_id, $options['disabled'][ $type ][ $handle ]['current'], true )
			)
		) {

			if ( ! empty( $options['enabled'][ $type ][ $handle ]['current'] ) && in_array( $current_id, $options['enabled'][ $type ][ $handle ]['current'], true ) ) {
				return $src;
			}

			if ( is_front_page() || is_home() ) {
				if (
					'page' === get_option( 'show_on_front' ) &&
					! empty( $options['enabled'][ $type ][ $handle ]['post_types'] ) &&
					in_array( 'page', $options['enabled'][ $type ][ $handle ]['post_types'], true )
				) {
					return $src;
				}
			} else {
				if (
					! empty( $options['enabled'][ $type ][ $handle ]['post_types'] ) &&
					in_array( get_post_type(), $options['enabled'][ $type ][ $handle ]['post_types'], true )
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
