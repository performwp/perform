import { Button, Spinner } from '@wordpress/components';
import { CheckCircleIcon, ClockIcon, ExclamationTriangleIcon } from '@heroicons/react/24/outline';
import { __ } from '@wordpress/i18n';

const Footer = ( { dirty, saving, uncertain, message, onRetry } ) => {
	let status = __( 'All changes saved', 'perform' );
	let Icon = CheckCircleIcon;
	let state = 'saved';
	if ( dirty ) {
		status = __( 'Changes will save automatically…', 'perform' );
		Icon = ClockIcon;
		state = 'pending';
	}
	if ( 'error' === message?.type ) {
		status = uncertain ? __( 'Save not confirmed', 'perform' ) : __( 'Settings not saved', 'perform' );
		Icon = ExclamationTriangleIcon;
		state = 'error';
	}
	if ( saving ) {
		status = __( 'Saving settings…', 'perform' );
		state = 'saving';
	}

	return (
		<div className="perform-savebar">
			<div className="perform-savebar__state" data-state={ state } role="status" aria-live="polite">
				{ saving ? <Spinner aria-hidden="true" /> : <Icon aria-hidden="true" /> }
				<span>{ status }</span>
			</div>
			{ 'error' === message?.type && (
				<div className="perform-savebar__message" data-status="error" role="alert">
					{ message.text }
				</div>
			) }
			{ 'error' === message?.type && dirty && (
				<Button variant="primary" onClick={ onRetry } disabled={ saving }>
					{ __( 'Retry save', 'perform' ) }
				</Button>
			) }
		</div>
	);
};

export default Footer;
