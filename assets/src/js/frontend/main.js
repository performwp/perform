document.addEventListener( 'DOMContentLoaded', function() {
	const allStatusDropdown = document.querySelectorAll( '.perform-status-select' );
	const allStatusDropdownWrap = document.querySelectorAll( '.perform-assets-manager--status' );
	const allDisableAssetsWrap = document.querySelectorAll( '.perform-assets-manager-disable-assets' );
	const allAssetsGroup = document.querySelectorAll( '.perform-assets-manager--group' );

	Array.prototype.forEach.call( allStatusDropdown, function( element ) {
		element.addEventListener( 'change', function( e ) {
			if ( this.classList.contains( 'disabled' ) ) {
				this.classList.remove( 'disabled' );
			} else {
				this.classList.add( 'disabled' );
			}
		} );
	} );

	Array.prototype.forEach.call( allStatusDropdownWrap, function( element ) {
		element.querySelector( '.perform-status-select' ).addEventListener( 'change', function( e ) {
			const disablePages = element.querySelector( '.perform-assets-manager-disable-single-asset' );
			if ( this.classList.contains( 'disabled' ) ) {
				disablePages.style.display = 'block';
			} else {
				disablePages.style.display = 'none';
			}
		} );
	} );

	Array.prototype.forEach.call( allDisableAssetsWrap, function( mainElement ) {
		Array.prototype.forEach.call( mainElement.querySelectorAll( '.perform-disable-assets' ), function( inputElement ) {
			inputElement.addEventListener( 'change', function() {
				const showExceptions = mainElement.querySelector( '.perform-assets-manager--exceptions' );

				if ( 'everywhere' === this.value ) {
					showExceptions.style.display = 'block';
				} else {
					showExceptions.style.display = 'none';
				}
			} );
		} );
	} );

	Array.prototype.forEach.call( allAssetsGroup, function( mainElement ) {
		const titleElement = mainElement.querySelector( '.perform-assets-manager-group--title' );

		if ( null !== titleElement ) {
			const selectElement = titleElement.querySelector( '.perform-assets-manager-group--status' ).querySelector( '.perform-status-select' );
			const assetOptionsElement = mainElement.querySelector( '.perform-assets-manager-disable-group-assets' );
			const assetTableElement = mainElement.querySelector( 'table' );

			if ( 'disabled' === selectElement.value ) {
				assetOptionsElement.style.display = 'block';
				assetTableElement.style.display = 'none';
			}

			selectElement.addEventListener( 'change', function() {
				if ( 'enabled' === this.value ) {
					assetOptionsElement.style.display = 'none';
					assetTableElement.style.display = 'table';
				} else {
					assetOptionsElement.style.display = 'block';
					assetTableElement.style.display = 'none';
				}
			} );
		}
	} );
} );
