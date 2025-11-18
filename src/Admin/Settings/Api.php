<?php
/**
 * Perform - Admin Settings API
 * Optimized for better performance and maintainability.
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
			<input id="perform-save-settings" type="button" class="button button-primary" value="<?php echo esc_attr( $save_text ); ?>" data-default-text="<?php echo esc_attr( $save_text ); ?>" data-saving-text="<?php esc_attr_e( 'Saving...', 'perform' ); ?>"/>
			<div class="perform-admin-settings--save-notices">
			</div>
		</div>
		<?php
		echo wp_kses_post( ob_get_clean() );
	}

	/**
	 * Render Admin Fields.
	 *
	 * This function loops through the admin settings fields for all the tabs.
	 *
	 * @param array $all_fields List of fields in a multi-dimensional array format for all tabs.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function render_fields( $all_fields = [] ) {
		$current_tab = Helpers::get_current_tab();
		$fields      = $all_fields[ $current_tab ] ?? [];

		if ( empty( $fields ) ) {
			return;
		}

		?>
		<form id="perform-admin-settings-form" action="POST">
			<table class="form-table" role="presentation">
				<tbody>
					<?php foreach ( $fields as $field ) : ?>
						<tr>
							<th scope="row">
								<?php echo esc_html( $field['name'] ); ?>
								<?php echo wp_kses_post( $this->render_help_link( $field['help_link'] ) ); ?>
							</th>
							<td>
								<?php echo wp_kses_post( $this->render_field( $field ) ); ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php $this->render_action(); ?>
		</form>
		<?php
	}

	/**
	 * Render a field based on its type.
	 *
	 * @param array $field Field configuration.
	 *
	 * @return string
	 */
	private function render_field( $field ) {
		$type = $field['type'] ?? 'text';
		$method = "render_{$type}_field";

		if ( method_exists( $this, $method ) ) {
			return $this->$method( $field );
		}

		return '';
	}

	/**
	 * Render Help Link.
	 *
	 * @param string $url URL for the help link.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return string
	 */
	public function render_help_link( $url ) {
		if ( empty( $url ) ) {
			return '';
		}

		return sprintf(
			'<a href="%s" class="perform-tooltip" target="_blank" title="%s">?</a>',
			esc_url( $url ),
			esc_attr__( 'Learn more', 'perform' )
		);
	}

	/**
	 * Render Checkbox field.
	 *
	 * @param array $field List of admin field parameters.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return string
	 */
	public function render_checkbox_field( $field ) {
		$settings   = Helpers::get_settings();
		$is_checked = ! empty( $settings[ $field['id'] ] ) ? 'checked' : '';

		return sprintf(
			'<input type="hidden" name="%s" value="0"/>
			<input type="checkbox" name="%s" value="1" %s/> %s',
			esc_attr( $field['id'] ),
			esc_attr( $field['id'] ),
			$is_checked,
			esc_html( $field['desc'] )
		);
	}

	/**
	 * Render Select field.
	 *
	 * @param array $field List of admin field parameters.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return string
	 */
	public function render_select_field( $field ) {
		$settings = Helpers::get_settings();
		$options  = '';

		foreach ( $field['options'] as $option_slug => $option_value ) {
			$is_selected = ! empty( $settings[ $field['id'] ] ) && $settings[ $field['id'] ] === $option_slug ? 'selected' : '';
			$options    .= sprintf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $option_slug ),
				$is_selected,
				esc_html( $option_value )
			);
		}

		return sprintf(
			'<select name="%s">%s</select>',
			esc_attr( $field['id'] ),
			$options
		);
	}

	/**
	 * Render URL field.
	 *
	 * @param array $field List of admin field parameters.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return string
	 */
	public function render_url_field( $field ) {
		$settings    = Helpers::get_settings();
		$placeholder = ! empty( $field['placeholder'] ) ? $field['placeholder'] : '';
		$value       = ! empty( $settings[ $field['id'] ] ) ? $settings[ $field['id'] ] : '';

		return sprintf(
			'<input type="url" name="%s" placeholder="%s" value="%s"/> %s',
			esc_attr( $field['id'] ),
			esc_attr( $placeholder ),
			esc_attr( $value ),
			esc_html( $field['desc'] )
		);
	}

	/**
	 * Render Text field.
	 *
	 * @param array $field List of admin field parameters.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return string
	 */
	public function render_text_field( $field ) {
		$settings    = Helpers::get_settings();
		$placeholder = $field['placeholder'] ?? '';
		$value       = $settings[ $field['id'] ] ?? '';

		return sprintf(
			'<input type="text" name="%s" placeholder="%s" value="%s"/> %s',
			esc_attr( $field['id'] ),
			esc_attr( $placeholder ),
			esc_attr( $value ),
			esc_html( $field['desc'] )
		);
	}

	/**
	 * Render Textarea field.
	 *
	 * @param array $field List of admin field parameters.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return string
	 */
	public function render_textarea_field( $field ) {
		$settings    = Helpers::get_settings();
		$placeholder = $field['placeholder'] ?? '';
		$value       = ! empty( $settings[ $field['id'] ] ) ? implode( "\n", $settings[ $field['id'] ] ) : '';

		return sprintf(
			'<textarea name="%s" placeholder="%s">%s</textarea><p class="description">%s</p>',
			esc_attr( $field['id'] ),
			esc_attr( $placeholder ),
			esc_textarea( $value ),
			esc_html( $field['desc'] )
		);
	}
}
