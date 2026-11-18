import { Button, Spinner } from '@wordpress/components';
import { CheckCircleIcon } from '@heroicons/react/24/outline';
import { __ } from '@wordpress/i18n';

const Footer = ( { dirty, saving, uncertain, message, onRetry } ) => {
	let status = __( 'All changes saved', 'perform' );
	if ( dirty ) {
		status = __( 'Changes will save automatically…', 'perform' );
	}
	if ( 'error' === message?.type ) {
		status = uncertain ? __( 'Save not confirmed', 'perform' ) : __( 'Settings not saved', 'perform' );
	}
	if ( saving ) {
		status = __( 'Saving settings…', 'perform' );
	}

	return (
		<div className="perform-savebar">
			<div className="perform-savebar__state" data-dirty={ dirty } role="status" aria-live="polite">
				<CheckCircleIcon aria-hidden="true" />
				<span>{ status }</span>
			</div>
			{ 'error' === message?.type && (
				<div className="perform-savebar__message" data-status="error" role="alert">
					{ message.text }
				</div>
			) }
			{ saving && <Spinner /> }
			{ 'error' === message?.type && dirty && (
				<Button variant="primary" onClick={ onRetry } disabled={ saving }>
					{ __( 'Retry save', 'perform' ) }
				</Button>
			) }
		</div>
	);
};

export default Footer;
