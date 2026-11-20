import { Button } from '@wordpress/components';
import { useEffect, useRef } from '@wordpress/element';
import { ArrowTopRightOnSquareIcon } from '@heroicons/react/24/outline';
import { __, sprintf } from '@wordpress/i18n';
import { getWorkspaceForTab, WORKSPACES } from './navigation';

const DOCS_URL = window.performwpSettings?.docsUrl || '#';
const VERSION = window.performwpSettings?.version || '';
const LOGO_URL = window.performwpSettings?.logoUrl || '';

const SettingsHeader = ( { activeTab, onTabChange } ) => {
	const activeWorkspace = getWorkspaceForTab( activeTab );
	const navRef = useRef( null );
	const getWorkspaceHref = ( workspaceName ) => {
		const url = new URL( window.location.href );
		if ( 'dashboard' === workspaceName ) {
			url.searchParams.delete( 'tab' );
		} else {
			url.searchParams.set( 'tab', workspaceName );
		}
		return url.toString();
	};
	const handleWorkspaceClick = ( event, workspaceName ) => {
		if (
			event.defaultPrevented ||
			event.button !== 0 ||
			event.metaKey ||
			event.ctrlKey ||
			event.shiftKey ||
			event.altKey
		) {
			return;
		}

		event.preventDefault();
		onTabChange( workspaceName );
	};

	useEffect( () => {
		const selectedTab = navRef.current?.querySelector( '[aria-current="page"]' );
		selectedTab?.scrollIntoView( { block: 'nearest', inline: 'nearest' } );
	}, [ activeWorkspace ] );

	return (
		<header className="perform-settings-header">
			<img src={ LOGO_URL } alt={ __( 'Perform', 'perform' ) } className="perform-settings-header__logo" />
			<nav className="perform-workspace-nav" aria-label={ __( 'Perform settings', 'perform' ) }>
				<ul ref={ navRef } className="perform-workspace-nav__items">
					{ WORKSPACES.map( ( workspace ) => (
						<li key={ workspace.name } className="perform-workspace-nav__list-item">
							<a
								aria-current={ workspace.name === activeWorkspace ? 'page' : undefined }
								className="perform-workspace-nav__item"
								href={ getWorkspaceHref( workspace.name ) }
								onClick={ ( event ) => handleWorkspaceClick( event, workspace.name ) }
							>
								{ workspace.title }
							</a>
						</li>
					) ) }
				</ul>
			</nav>
			<div className="perform-settings-header__actions">
				<Button
					variant="tertiary"
					href={ DOCS_URL }
					target="_blank"
					rel="noopener noreferrer"
					icon={ <ArrowTopRightOnSquareIcon className="perform-ui-icon" aria-hidden="true" /> }
					iconPosition="right"
				>
					<span className="perform-docs-label">{ __( 'Documentation', 'perform' ) }</span>
					<span className="perform-docs-label-short">{ __( 'Docs', 'perform' ) }</span>
				</Button>
				{ VERSION && (
					<div className="perform-plugin-version">
						{ sprintf(
							/* translators: %s: plugin version. */
							__( 'Version %s', 'perform' ),
							VERSION
						) }
					</div>
				) }
			</div>
		</header>
	);
};

export default SettingsHeader;
