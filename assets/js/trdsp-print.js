( function () {
	var buttons = document.querySelectorAll( '[id^="trdsp-print-"]' );
	if ( ! buttons.length ) {
		return;
	}
	Array.prototype.forEach.call( buttons, function ( button ) {
		button.addEventListener( 'click', function () {
			window.print();
		} );
	} );
}() );
