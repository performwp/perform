document.addEventListener( 'DOMContentLoaded', function() {
	const allStatusDropdown = document.querySelectorAll( '.perform-status-select' );
	const allStatusDropdownWrap = document.querySelectorAll( '.perform-assets-manager--status' );
	const allGroupStatusDropdownWrap = document.querySelectorAll( '.perform-assets-manager-group--status' );

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
			const disablePages = element.querySelector( '.perform-assets-manager-disable-asset-options' );
			if ( this.classList.contains( 'disabled' ) ) {
				disablePages.style.display = 'block';
			} else {
				disablePages.style.display = 'none';
			}
		} );
	} );
} );
