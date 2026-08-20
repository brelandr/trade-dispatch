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
 * trade-dispatch/v1 portal endpoints (logged-in customer or office).
 *
 * Field-tech mobile routes live in Trade Dispatch Pro (`trade-dispatch-pro/v1`).
 */
class TRDSP_REST {

	/**
	 * Hook rest_api_init.
	 *
	 * @return void
	 */
	public static function hooks() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register GET /portal/jobs and GET /portal/estimates.
	 *
	 * @return void
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
		register_rest_route(
			'trade-dispatch/v1',
			'/portal/estimates',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'portal_estimates' ),
				'permission_callback' => array( __CLASS__, 'portal_permission' ),
			)
		);
	}

	/**
	 * Cookie or Application Password session with portal or office capability.
	 *
	 * @return bool
	 */
	public static function portal_permission() {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		return current_user_can( 'trdsp_portal' ) || current_user_can( 'trdsp_access' );
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
				'upcoming'     => ( 'completed' !== $job['status'] && 'cancelled' !== $job['status'] ),
			);
		}
		return rest_ensure_response( $output );
	}

	/**
	 * Estimates for the customer matching the current user's email.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function portal_estimates( $request ) {
		unset( $request );
		$user     = wp_get_current_user();
		$customer = TRDSP_Customers::get_by_email( (string) $user->user_email );
		if ( ! $customer ) {
			return rest_ensure_response( array() );
		}
		$estimates = TRDSP_Estimates::query(
			array(
				'customer_id' => (int) $customer['id'],
				'limit'       => 100,
			)
		);
		$output    = array();
		foreach ( $estimates as $estimate ) {
			$output[] = array(
				'id'     => (int) $estimate['id'],
				'title'  => (string) $estimate['title'],
				'amount' => (string) $estimate['amount'],
				'status' => (string) $estimate['status'],
				'job_id' => (int) $estimate['job_id'],
			);
		}
		return rest_ensure_response( $output );
	}
}
