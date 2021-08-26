<?php
/**
 * Perform - Admin Settings API
 *
 * @since 1.0.0
 *
 * @package Perform
 * @subpackage Admin/Settings
 * @author Mehul Gohil <hello@mehulgohil.com>
 */

namespace Perform\Admin\Settings;

use Perform\Includes\Helpers;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API Class for admin settings.
 *
 * @since 2.0.0
 */
class Api {

	/**
	 * Set a prefix for the purpose of storing it in DB.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public $prefix = '';

	/**
	 * Render Action for admin settings field.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function render_action() {
		ob_start();
		$save_text = esc_html__( 'Save Settings', 'perform' );
		?>
		<div class="perform-admin-settings--save-wrap">
			<?php wp_nonce_field( 'perform-save-settings', 'perform_settings_barrier' ); ?>
			<input id="perform-save-settings" type="button" class="button button-primary" value="<?php echo $save_text; ?>" data-default-text="<?php echo $save_text; ?>" data-saving-text="<?php esc_html_e( 'Saving...', 'perform' ); ?>"/>
			<div class="perform-admin-settings--save-notices">
			</div>
		</div>
		<?php
		echo ob_get_clean();
	}

	/**
	 * Render Admin Fields.
	 *
	 * This function will loop through the admin settings fields for all the tabs.
	 *
	 * @param array $all_fields List of fields in a multi-dimensional array format for all tabs.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function render_fields( $all_fields = [] ) {
		ob_start();
		$current_tab = Helpers::get_current_tab();
		?>
		<form id="perform-admin-settings-form" action="POST">
			<table class="form-table" role="presentation">
				<tbody>
					<?php
					foreach ( $all_fields as $tab => $fields ) {
						// Skip this iteration, if the current tab doesn't match with the field tab.
						if ( $tab !== $current_tab ) {
							continue;
						}

						// Skip this iteration, if the `$fields` array is empty.
						if ( empty( $fields ) ) {
							continue;
						}

						foreach ( $fields as $field ) {
							$type = ! empty( $field['type'] ) ? $field['type'] : 'text';

							?>
							<tr>
								<th scope="row">
									<?php echo $field['name']; ?>
									<?php echo $this->render_help_link( $field['help_link'] ); ?>
								</th>
								<td>
									<?php
									// Dynamically render the required admin settings field.
									echo call_user_func( [ $this, "render_{$type}_field" ], $field );
									?>
								</td>
							</tr>
							<?php
						}
					}
					?>
				</tbody>
			</table>
			<?php $this->render_action(); ?>
		</form>
		<?php
		echo ob_get_clean();
	}

	/**
	 * Render Help Link.
	 *
	 * @param string $url URL for the help link.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return mixed
	 */
	public function render_help_link( $url ) {
		ob_start();
		?>
		<a href="<?php echo esc_url( $url ); ?>" class="perform-tooltip" target="_blank" title="<?php esc_html_e( 'Learn more', 'perform' ); ?>">?</a>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render Checkbox field.
	 *
	 * @param array $field List of admin field parameters.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return mixed
	 */
	public function render_checkbox_field( $field ) {
		ob_start();
		$settings   = Helpers::get_settings();
		$is_checked = ! empty( $settings[ $field['id'] ] ) ? checked( true, $settings[ $field['id'] ], false ) : '';
		?>
		<input type="hidden" name="<?php echo esc_attr( $field['id'] ); ?>" value="0"/>
		<input type="checkbox" name="<?php echo esc_attr( $field['id'] ); ?>" value="1" <?php echo $is_checked; ?>/> <?php echo $field['desc']; ?>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render Select field.
	 *
	 * @param array $field List of admin field parameters.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return mixed
	 */
	public function render_select_field( $field ) {
		ob_start();
		$settings = Helpers::get_settings();
		?>
		<select name="<?php echo esc_attr( $field['id'] ); ?>">
			<?php
			foreach ( $field['options'] as $option_slug => $option_value ) {
				$is_selected = ! empty( $settings[ $field['id'] ] ) ? selected( $option_slug, $settings[ $field['id'] ], false ) : '';
				?>
				<option <?php echo $is_selected; ?> value="<?php echo $option_slug; ?>"><?php echo $option_value; ?></option>
				<?php
			}
			?>
		</select>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render URL field.
	 *
	 * @param array $field List of admin field parameters.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return mixed
	 */
	public function render_url_field( $field ) {
		ob_start();
		$settings    = Helpers::get_settings();
		$placeholder = ! empty( $field['placeholder'] ) ? $field['placeholder'] : '';
		$value       = ! empty( $settings[ $field['id'] ] ) ? $settings[ $field['id'] ] : '';
		?>
		<input type="url" name="<?php echo esc_attr( $field['id'] ); ?>" placeholder="<?php echo $placeholder; ?>" value="<?php echo $value; ?>"/> <?php echo $field['desc']; ?>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render `Text` field.
	 *
	 * @param array $field List of admin field parameters.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return mixed
	 */
	public function render_text_field( $field ) {
		ob_start();
		$settings    = Helpers::get_settings();
		$placeholder = ! empty( $field['placeholder'] ) ? $field['placeholder'] : '';
		$value       = ! empty( $settings[ $field['id'] ] ) ? $settings[ $field['id'] ] : '';
		?>
		<input type="text" name="<?php echo esc_attr( $field['id'] ); ?>" placeholder="<?php echo $placeholder; ?>" value="<?php echo $value; ?>"/> <?php echo $field['desc']; ?>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render `Textarea` field.
	 *
	 * @param array $field List of admin field parameters.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return mixed
	 */
	public function render_textarea_field( $field ) {
		ob_start();
		$settings    = Helpers::get_settings();
		$placeholder = ! empty( $field['placeholder'] ) ? $field['placeholder'] : '';
		$value       = ! empty( $settings[ $field['id'] ] ) ? implode( ' ', $settings[ $field['id'] ] ) : '';
		?>
		<textarea name="<?php echo esc_attr( $field['id'] ); ?>" placeholder="<?php echo $placeholder; ?>"><?php echo $value; ?></textarea>
		<p class="description">
			<?php echo $field['desc']; ?>
		</p>
		<?php
		return ob_get_clean();
	}
}
