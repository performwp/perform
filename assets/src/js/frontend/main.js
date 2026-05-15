( function () {
	function setHidden( element, hidden ) {
		if ( ! element ) {
			return;
		}

		element.hidden = hidden;
	}

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

	function setupScannerFilters() {
		const manager = document.getElementById( 'perform-assets-manager' );

		if ( ! manager ) {
			return;
		}

		const searchInput = manager.querySelector( '#perform-assets-manager-search' );
		const filterButtons = manager.querySelectorAll( '[data-perform-filter]' );
		const noResults = manager.querySelector( '.perform-assets-manager--no-results' );
		const state = {
			filter: 'all',
			query: '',
		};

		function rowMatchesFilter( row ) {
			if ( 'all' === state.filter ) {
				return true;
			}

			if ( 'disabled' === state.filter ) {
				return 'disabled' === row.dataset.performAssetStatus;
			}

			return state.filter === row.dataset.performAssetType || state.filter === row.dataset.performAssetSource;
		}

		function rowMatchesSearch( row ) {
			if ( '' === state.query ) {
				return true;
			}

			return row.textContent.toLowerCase().indexOf( state.query ) !== -1;
		}

		function applyFilters() {
			let visibleRows = 0;

			manager.querySelectorAll( '[data-perform-asset-row]' ).forEach( function ( row ) {
				const isVisible = rowMatchesFilter( row ) && rowMatchesSearch( row );
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

		if ( searchInput ) {
			searchInput.addEventListener( 'input', function () {
				state.query = searchInput.value.trim().toLowerCase();
				applyFilters();
			} );
		}

		filterButtons.forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				state.filter = button.dataset.performFilter;

				filterButtons.forEach( function ( filterButton ) {
					const isActive = filterButton === button;
					filterButton.classList.toggle( 'is-active', isActive );
					filterButton.setAttribute( 'aria-pressed', isActive ? 'true' : 'false' );
				} );

				applyFilters();
			} );
		} );

		document.addEventListener( 'performAssetsManagerFilterRefresh', applyFilters );
		applyFilters();
	}

	document.addEventListener( 'DOMContentLoaded', function () {
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

		setupScannerFilters();
	} );
} )();
