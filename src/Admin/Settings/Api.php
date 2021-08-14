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
		$this->render_help_link( $field['help_link'] );
		?>
		<input type="checkbox" name="<?php echo esc_attr( $field['id'] ); ?>"/> <?php echo $field['desc']; ?>
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
		?>
		<select name="<?php echo esc_attr( $field['id'] ); ?>">
			<?php
			foreach ( $field['options'] as $option_slug => $option_value ) {
				?>
				<option value="<?php echo $option_slug; ?>"><?php echo $option_value; ?></option>
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
		$this->render_help_link( $field['help_link'] );
		?>
		<input type="url" name="<?php echo esc_attr( $field['id'] ); ?>"/> <?php echo $field['desc']; ?>
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
		$this->render_help_link( $field['help_link'] );
		?>
		<input type="text" name="<?php echo esc_attr( $field['id'] ); ?>"/> <?php echo $field['desc']; ?>
		<?php
		return ob_get_clean();
	}
}
