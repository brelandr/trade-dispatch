<?php
/**
 * GDPR exporter and eraser.
 *
 * @package Trade_Dispatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Personal data tools for customers and jobs.
 */
class TRDSP_Privacy {

	/**
	 * Register privacy hooks.
	 */
	public static function hooks() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
	}

	/**
	 * Register exporter.
	 *
	 * @param array<string,array<string,mixed>> $exporters Exporters.
	 * @return array<string,array<string,mixed>>
	 */
	public static function register_exporter( $exporters ) {
		$exporters['trade-dispatch'] = array(
			'exporter_friendly_name' => __( 'Trade Dispatch Data', 'trade-dispatch' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Register eraser.
	 *
	 * @param array<string,array<string,mixed>> $erasers Erasers.
	 * @return array<string,array<string,mixed>>
	 */
	public static function register_eraser( $erasers ) {
		$erasers['trade-dispatch'] = array(
			'eraser_friendly_name' => __( 'Trade Dispatch Data', 'trade-dispatch' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Export customer + job records for an email.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page number.
	 * @return array<string,mixed>
	 */
	public static function export( $email, $page = 1 ) {
		unset( $page );
		$customer = TRDSP_Customers::get_by_email( $email );
		$data     = array();
		if ( $customer ) {
			$data[] = array(
				'group_id'    => 'trdsp-customer',
				'group_label' => __( 'Trade Dispatch Customer', 'trade-dispatch' ),
				'item_id'     => 'customer-' . (int) $customer['id'],
				'data'        => array(
					array(
						'name'  => __( 'Name', 'trade-dispatch' ),
						'value' => (string) $customer['name'],
					),
					array(
						'name'  => __( 'Email', 'trade-dispatch' ),
						'value' => (string) $customer['email'],
					),
					array(
						'name'  => __( 'Phone', 'trade-dispatch' ),
						'value' => (string) $customer['phone'],
					),
					array(
						'name'  => __( 'Address', 'trade-dispatch' ),
						'value' => trim( implode( ', ', array_filter( array( $customer['address_1'], $customer['city'], $customer['state'], $customer['postcode'] ) ) ) ),
					),
				),
			);
			$jobs = TRDSP_Jobs::query(
				array(
					'customer_id' => (int) $customer['id'],
					'limit'       => 100,
				)
			);
			foreach ( $jobs as $job ) {
				$data[] = array(
					'group_id'    => 'trdsp-jobs',
					'group_label' => __( 'Trade Dispatch Jobs', 'trade-dispatch' ),
					'item_id'     => 'job-' . (int) $job['id'],
					'data'        => array(
						array(
							'name'  => __( 'Title', 'trade-dispatch' ),
							'value' => (string) $job['title'],
						),
						array(
							'name'  => __( 'Status', 'trade-dispatch' ),
							'value' => (string) $job['status'],
						),
						array(
							'name'  => __( 'Scheduled', 'trade-dispatch' ),
							'value' => (string) $job['scheduled_at'],
						),
					),
				);
			}
		}
		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * Erase customer + their jobs for an email.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page number.
	 * @return array<string,mixed>
	 */
	public static function erase( $email, $page = 1 ) {
		unset( $page );
		$customer = TRDSP_Customers::get_by_email( $email );
		$removed  = 0;
		$retained = false;
		if ( $customer ) {
			$jobs = TRDSP_Jobs::query(
				array(
					'customer_id' => (int) $customer['id'],
					'limit'       => 200,
				)
			);
			foreach ( $jobs as $job ) {
				TRDSP_Jobs::delete( (int) $job['id'] );
				++$removed;
			}
			TRDSP_Customers::delete( (int) $customer['id'] );
			++$removed;
		}
		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => $retained,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
