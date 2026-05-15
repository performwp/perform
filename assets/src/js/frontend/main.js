import {Button, SearchControl} from '@wordpress/components';
import {render, useEffect, useMemo, useState} from '@wordpress/element';

const DEFAULT_CONTROL_LABELS = {
	search: 'Search detected assets',
	searchPlaceholder: 'Search by handle, source, or file URL',
	filters: 'Filter detected assets',
	all: 'All',
	plugins: 'Plugins',
	themes: 'Themes',
	misc: 'Other',
	js: 'JS',
	css: 'CSS',
	disabled: 'Disabled',
};

const SCANNER_FILTERS = [
	{value: 'all', labelKey: 'all'},
	{value: 'plugins', labelKey: 'plugins'},
	{value: 'themes', labelKey: 'themes'},
	{value: 'misc', labelKey: 'misc'},
	{value: 'js', labelKey: 'js'},
	{value: 'css', labelKey: 'css'},
	{value: 'disabled', labelKey: 'disabled'},
];

function setHidden( element, hidden ) {
	if ( ! element ) {
		return;
	}

	element.hidden = hidden;
}

function getControlLabels( root ) {
	if ( ! root || ! root.dataset.labels ) {
		return DEFAULT_CONTROL_LABELS;
	}

	try {
		return {
			...DEFAULT_CONTROL_LABELS,
			...JSON.parse( root.dataset.labels ),
		};
	} catch ( error ) {
		return DEFAULT_CONTROL_LABELS;
	}
}

function rowMatchesFilter( row, filter ) {
	if ( 'all' === filter ) {
		return true;
	}

	if ( 'disabled' === filter ) {
		return 'disabled' === row.dataset.performAssetStatus;
	}

	return filter === row.dataset.performAssetType || filter === row.dataset.performAssetSource;
}

function rowMatchesSearch( row, query ) {
	if ( '' === query ) {
		return true;
	}

	return row.textContent.toLowerCase().indexOf( query ) !== -1;
}

function applyScannerFilters( manager, filter, query ) {
	const noResults = manager.querySelector( '.perform-assets-manager--no-results' );
	const normalizedQuery = query.trim().toLowerCase();
	let visibleRows = 0;

	manager.querySelectorAll( '[data-perform-asset-row]' ).forEach( function ( row ) {
		const isVisible = rowMatchesFilter( row, filter ) && rowMatchesSearch( row, normalizedQuery );
		setHidden( row, ! isVisible );

		if ( isVisible ) {
			visibleRows++;
		}
	} );

	manager.querySelectorAll( '[data-perform-group]' ).forEach( function ( group ) {
		const visibleGroupRows = group.querySelectorAll( '[data-perform-asset-row]:not([hidden])' ).length;
		setHidden( group, 0 === visibleGroupRows );
	} );

	manager.querySelectorAll( '[data-perform-section]' ).forEach( function ( section ) {
		const visibleGroups = section.querySelectorAll( '[data-perform-group]:not([hidden])' ).length;
		setHidden( section, 0 === visibleGroups );
	} );

	setHidden( noResults, visibleRows > 0 );
}

function AssetsManagerControls( {manager, labels} ) {
	const [ filter, setFilter ] = useState( 'all' );
	const [ query, setQuery ] = useState( '' );
	const filters = useMemo(
		() =>
			SCANNER_FILTERS.map( ( item ) => ( {
				...item,
				label: labels[ item.labelKey ],
			} ) ),
		[ labels ]
	);

	useEffect( () => {
		const refreshFilters = () => applyScannerFilters( manager, filter, query );

		refreshFilters();
		document.addEventListener( 'performAssetsManagerFilterRefresh', refreshFilters );

		return () => {
			document.removeEventListener( 'performAssetsManagerFilterRefresh', refreshFilters );
		};
	}, [ manager, filter, query ] );

	return (
		<div className="perform-assets-manager--controls">
			<SearchControl
				className="perform-assets-manager--search"
				hideLabelFromVision
				label={ labels.search }
				onChange={ ( nextQuery = '' ) => setQuery( nextQuery ) }
				placeholder={ labels.searchPlaceholder }
				value={ query }
			/>
			<div className="perform-assets-manager--filters" aria-label={ labels.filters }>
				{ filters.map( ( {value, label} ) => {
					const isActive = value === filter;

					return (
						<Button
							key={ value }
							aria-pressed={ isActive }
							className="perform-assets-manager--filter"
							onClick={ () => setFilter( value ) }
							variant={ isActive ? 'primary' : 'secondary' }
						>
							{ label }
						</Button>
					);
				} ) }
			</div>
		</div>
	);
}

function mountScannerControls( manager ) {
	const root = manager.querySelector( '#perform-assets-manager-controls-root' );

	if ( ! root ) {
		return;
	}

	render( <AssetsManagerControls manager={ manager } labels={ getControlLabels( root ) } />, root );
}

( function () {
	function syncAssetStatus( selectElement ) {
		const isDisabled = 'disabled' === selectElement.value;
		const statusCell = selectElement.closest( '.perform-assets-manager--status' );
		const row = selectElement.closest( '[data-perform-asset-row]' );
		const singleOptions = statusCell
			? statusCell.querySelector( '.perform-assets-manager-disable-single-asset' )
			: null;

		selectElement.classList.toggle( 'disabled', isDisabled );
		setHidden( singleOptions, ! isDisabled );

		if ( row ) {
			row.dataset.performAssetStatus = isDisabled ? 'disabled' : 'enabled';
			row.dataset.performOwnStatus = row.dataset.performAssetStatus;
		}

		document.dispatchEvent( new Event( 'performAssetsManagerFilterRefresh' ) );
	}

	function syncGroupStatus( selectElement ) {
		const group = selectElement.closest( '[data-perform-group]' );

		if ( ! group ) {
			return;
		}

		const isDisabled = 'disabled' === selectElement.value;
		const groupOptions = group.querySelector( '.perform-assets-manager-disable-group-assets' );
		const assetTable = group.querySelector( '.perform-assets-manager--assets-table' );

		selectElement.classList.toggle( 'disabled', isDisabled );
		setHidden( groupOptions, ! isDisabled );
		setHidden( assetTable, isDisabled );

		group.querySelectorAll( '[data-perform-asset-row]' ).forEach( function ( row ) {
			row.dataset.performAssetStatus = isDisabled ? 'disabled' : row.dataset.performOwnStatus || 'enabled';
		} );

		document.dispatchEvent( new Event( 'performAssetsManagerFilterRefresh' ) );
	}

	function syncExceptions( inputElement ) {
		const options = inputElement.closest( '.perform-assets-manager-disable-assets' );
		const exceptions = options ? options.querySelector( '.perform-assets-manager--exceptions' ) : null;

		setHidden( exceptions, 'everywhere' !== inputElement.value || ! inputElement.checked );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		const manager = document.getElementById( 'perform-assets-manager' );

		document.querySelectorAll( '[data-perform-asset-row]' ).forEach( function ( row ) {
			row.dataset.performOwnStatus = row.dataset.performAssetStatus || 'enabled';
		} );

		document.querySelectorAll( '.perform-status-select' ).forEach( function ( selectElement ) {
			const isGroupSelect = Boolean( selectElement.closest( '.perform-assets-manager-group--status' ) );

			if ( isGroupSelect ) {
				syncGroupStatus( selectElement );
			} else {
				syncAssetStatus( selectElement );
			}

			selectElement.addEventListener( 'change', function () {
				if ( isGroupSelect ) {
					syncGroupStatus( selectElement );
				} else {
					syncAssetStatus( selectElement );
				}
			} );
		} );

		document.querySelectorAll( '.perform-disable-assets' ).forEach( function ( inputElement ) {
			if ( inputElement.checked ) {
				syncExceptions( inputElement );
			}

			inputElement.addEventListener( 'change', function () {
				syncExceptions( inputElement );
			} );
		} );

		if ( manager ) {
			mountScannerControls( manager );
		}
	} );
} )();
