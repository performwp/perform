import { render } from '@wordpress/element';
import SettingsApp from './SettingsApp';

document.addEventListener('DOMContentLoaded', () => {
  const el = document.getElementById('perform-settings-page');
  if (el) {
    render(
      <SettingsApp />,
      el
    );
  }
});


document.addEventListener( 'DOMContentLoaded', () => {
	const saveBtn = document.getElementById( 'perform-save-settings' );
	const formElement = document.getElementById( 'perform-admin-settings-form' );

	// Bailout, if `Save Settings` btn doesn't exists.
	if ( ! saveBtn ) {
		return;
	}

	saveBtn.addEventListener( 'click', ( event ) => {
		event.preventDefault();

		// Disable the save button for unnecesssary clicks.
		saveBtn.setAttribute( 'disabled', 'disabled' );

		// Change Save button label to `Saving...` for more clarity.
		saveBtn.value = saveBtn.getAttribute( 'data-saving-text' );

		const formData = new FormData( formElement );
		formData.append( 'action', 'perform_save_settings' );

		fetch(
			ajaxurl,
			{
				method: 'POST',
				body: formData,
			}
		).then( response => {
			if ( 200 === response.status ) {
				return response.json();
			}

			return false;
		} ).then( () => {
			setTimeout( () => {
				saveBtn.removeAttribute( 'disabled' );
				saveBtn.value = saveBtn.getAttribute( 'data-default-text' );
			}, 1000 );
		} );
	} );
} );
