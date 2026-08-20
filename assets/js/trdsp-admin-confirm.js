( function () {
	document.addEventListener( 'click', function ( event ) {
		var el = event.target.closest ? event.target.closest( '[data-trdsp-confirm]' ) : null;
		var msg;
		if ( ! el ) {
			return;
		}
		msg = el.getAttribute( 'data-trdsp-confirm' );
		if ( ! msg ) {
			return;
		}
		if ( ! window.confirm( msg ) ) {
			event.preventDefault();
		}
	} );
}() );
