import { Button, Spinner } from '@wordpress/components';

const Footer = ( { dirty, saving, message, onSave } ) => {
	return (
		<div className="perform-savebar">
			{ message && message.text && (
				<div
					className="perform-savebar__message"
					data-status={ message.type }
					role={ 'error' === message.type ? 'alert' : 'status' }
					aria-live="polite"
				>
					{ message.text }
				</div>
			) }
			<Button isPrimary onClick={ onSave } disabled={ ! dirty || saving }>
				{ saving ? (
					<>
						<Spinner /> Saving...
					</>
				) : (
					'Save Settings'
				) }
			</Button>
		</div>
	);
};

export default Footer;
