<?php
/**
 * Gutenberg blocks for booking and portal.
 *
 * @package Trade_Dispatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers dynamic blocks that render the shortcodes.
 */
class TRDSP_Blocks {

	/**
	 * Register hooks.
	 */
	public static function hooks() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register block types.
	 */
	public static function register() {
		$asset = array(
			'dependencies' => array( 'wp-blocks', 'wp-element', 'wp-i18n' ),
			'version'      => TRDSP_VERSION,
		);
		wp_register_script(
			'trdsp-blocks',
			TRDSP_PLUGIN_URL . 'assets/js/trdsp-blocks.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_set_script_translations( 'trdsp-blocks', 'trade-dispatch', TRDSP_PLUGIN_DIR . 'languages' );

		register_block_type(
			'trade-dispatch/booking',
			array(
				'api_version'     => 2,
				'title'           => __( 'Trade Dispatch Booking', 'trade-dispatch' ),
				'description'     => __( 'Public booking request form.', 'trade-dispatch' ),
				'category'        => 'widgets',
				'icon'            => 'calendar-alt',
				'editor_script'   => 'trdsp-blocks',
				'render_callback' => array( __CLASS__, 'render_booking' ),
			)
		);
		register_block_type(
			'trade-dispatch/portal',
			array(
				'api_version'     => 2,
				'title'           => __( 'Trade Dispatch Portal', 'trade-dispatch' ),
				'description'     => __( 'Customer portal for logged-in visitors.', 'trade-dispatch' ),
				'category'        => 'widgets',
				'icon'            => 'id',
				'editor_script'   => 'trdsp-blocks',
				'render_callback' => array( __CLASS__, 'render_portal' ),
			)
		);
	}

	/**
	 * Booking block.
	 *
	 * @return string
	 */
	public static function render_booking() {
		return TRDSP_Booking::render( array() );
	}

	/**
	 * Portal block.
	 *
	 * @return string
	 */
	public static function render_portal() {
		return TRDSP_Portal::render( array() );
	}
}
