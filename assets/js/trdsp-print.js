( function () {
	var button = document.getElementById( 'trdsp-print-work-order' );
	if ( ! button ) {
		return;
	}
	button.addEventListener( 'click', function () {
		window.print();
	} );
}() );
