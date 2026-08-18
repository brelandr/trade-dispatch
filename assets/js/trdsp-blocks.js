( function ( blocks, element, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;

	blocks.registerBlockType( 'trade-dispatch/booking', {
		title: __( 'Trade Dispatch Booking', 'trade-dispatch' ),
		icon: 'calendar-alt',
		category: 'widgets',
		edit: function () {
			return el(
				'p',
				{ className: 'trdsp-block-preview' },
				__( 'Trade Dispatch booking form (shown on the front end).', 'trade-dispatch' )
			);
		},
		save: function () {
			return null;
		}
	} );

	blocks.registerBlockType( 'trade-dispatch/portal', {
		title: __( 'Trade Dispatch Portal', 'trade-dispatch' ),
		icon: 'id',
		category: 'widgets',
		edit: function () {
			return el(
				'p',
				{ className: 'trdsp-block-preview' },
				__( 'Trade Dispatch customer portal (shown on the front end).', 'trade-dispatch' )
			);
		},
		save: function () {
			return null;
		}
	} );
}( window.wp.blocks, window.wp.element, window.wp.i18n ) );
