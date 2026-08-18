<?php
/**
 * Public/portal REST routes (web only).
 *
 * @package Trade_Dispatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * trade-dispatch/v1 portal endpoints.
 */
class TRDSP_REST {

	/**
	 * Register routes.
	 */
	public static function hooks() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public static function register_routes() {
		register_rest_route(
			'trade-dispatch/v1',
			'/portal/jobs',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'portal_jobs' ),
				'permission_callback' => array( __CLASS__, 'portal_permission' ),
			)
		);
	}

	/**
	 * Logged-in users may read their own portal jobs.
	 *
	 * @return bool
	 */
	public static function portal_permission() {
		return is_user_logged_in();
	}

	/**
	 * Jobs for the customer matching the current user's email.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function portal_jobs( $request ) {
		unset( $request );
		$user     = wp_get_current_user();
		$customer = TRDSP_Customers::get_by_email( (string) $user->user_email );
		if ( ! $customer ) {
			return rest_ensure_response( array() );
		}
		$jobs   = TRDSP_Jobs::query(
			array(
				'customer_id' => (int) $customer['id'],
				'limit'       => 100,
			)
		);
		$output = array();
		foreach ( $jobs as $job ) {
			$output[] = array(
				'id'           => (int) $job['id'],
				'title'        => (string) $job['title'],
				'status'       => (string) $job['status'],
				'scheduled_at' => (string) $job['scheduled_at'],
				'address_1'    => (string) $job['address_1'],
				'city'         => (string) $job['city'],
				'state'        => (string) $job['state'],
				'postcode'     => (string) $job['postcode'],
			);
		}
		return rest_ensure_response( $output );
	}
}
