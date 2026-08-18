<?php
/**
 * Estimate / invoice records.
 *
 * @package Trade_Dispatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for wp_trdsp_estimates.
 */
class TRDSP_Estimates {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'trdsp_estimates';
	}

	/**
	 * Statuses (records only — no payment processing).
	 *
	 * @return array<string,string>
	 */
	public static function statuses() {
		return array(
			'draft'    => __( 'Draft', 'trade-dispatch' ),
			'sent'     => __( 'Sent', 'trade-dispatch' ),
			'accepted' => __( 'Accepted', 'trade-dispatch' ),
			'paid'     => __( 'Paid', 'trade-dispatch' ),
			'void'     => __( 'Void', 'trade-dispatch' ),
		);
	}

	/**
	 * Get one estimate.
	 *
	 * @param int $id Estimate ID.
	 * @return array<string,mixed>|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$id = absint( $id );
		if ( $id < 1 ) {
			return null;
		}
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup.
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table from prefix + fixed slug.
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/**
	 * List estimates.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return array<int,array<string,mixed>>
	 */
	public static function query( $args = array() ) {
		global $wpdb;
		$args     = wp_parse_args(
			$args,
			array(
				'customer_id' => 0,
				'job_id'      => 0,
				'status'      => '',
				'limit'       => 50,
				'offset'      => 0,
			)
		);
		$table    = self::table();
		$where    = array( '1=1' );
		$params   = array();
		$cust     = absint( $args['customer_id'] );
		$job      = absint( $args['job_id'] );
		if ( $cust > 0 ) {
			$where[]  = 'customer_id = %d';
			$params[] = $cust;
		}
		if ( $job > 0 ) {
			$where[]  = 'job_id = %d';
			$params[] = $job;
		}
		$status = sanitize_key( (string) ( $args['status'] ?? '' ) );
		if ( '' !== $status && isset( self::statuses()[ $status ] ) ) {
			$where[]  = 'status = %s';
			$params[] = $status;
		}
		$limit    = min( 200, max( 1, absint( $args['limit'] ) ) );
		$offset   = max( 0, absint( $args['offset'] ) );
		$sql      = 'SELECT * FROM ' . $table . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT %d OFFSET %d';
		$params[] = $limit;
		$params[] = $offset;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Dynamic WHERE with prepare.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Insert or update.
	 *
	 * @param array<string,mixed> $data Estimate data.
	 * @return int|\WP_Error
	 */
	public static function save( $data ) {
		global $wpdb;
		$now    = gmdate( 'Y-m-d H:i:s' );
		$id     = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
		$status = sanitize_key( (string) ( $data['status'] ?? 'draft' ) );
		if ( ! isset( self::statuses()[ $status ] ) ) {
			$status = 'draft';
		}
		$amount = isset( $data['amount'] ) ? (float) $data['amount'] : 0.0;
		$row    = array(
			'customer_id' => absint( $data['customer_id'] ?? 0 ),
			'job_id'      => absint( $data['job_id'] ?? 0 ),
			'title'       => sanitize_text_field( (string) ( $data['title'] ?? '' ) ),
			'amount'      => number_format( $amount, 2, '.', '' ),
			'status'      => $status,
			'updated_at'  => $now,
		);
		if ( '' === $row['title'] ) {
			return new WP_Error( 'trdsp_estimate_title', __( 'Estimate title is required.', 'trade-dispatch' ) );
		}
		if ( $row['customer_id'] < 1 && $row['job_id'] > 0 ) {
			$job_row = TRDSP_Jobs::get( $row['job_id'] );
			if ( $job_row ) {
				$row['customer_id'] = (int) $job_row['customer_id'];
			}
		}

		/**
		 * Filter estimate data before save.
		 *
		 * @param array $row Estimate row.
		 */
		$row = apply_filters( 'trdsp_estimate_data_before_save', $row );

		if ( $id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update.
			$updated = $wpdb->update( self::table(), $row, array( 'id' => $id ), array( '%d', '%d', '%s', '%s', '%s', '%s' ), array( '%d' ) );
			if ( false === $updated ) {
				return new WP_Error( 'trdsp_estimate_update', __( 'Could not update estimate.', 'trade-dispatch' ) );
			}
		} else {
			$row['created_at'] = $now;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert.
			$inserted = $wpdb->insert( self::table(), $row, array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' ) );
			if ( false === $inserted ) {
				return new WP_Error( 'trdsp_estimate_insert', __( 'Could not create estimate.', 'trade-dispatch' ) );
			}
			$id = (int) $wpdb->insert_id;
		}

		/**
		 * Fires after an estimate is saved.
		 *
		 * @param int   $id  Estimate ID.
		 * @param array $row Saved row.
		 */
		do_action( 'trdsp_after_estimate_save', $id, $row );
		return $id;
	}

	/**
	 * Delete an estimate.
	 *
	 * @param int $id Estimate ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$id = absint( $id );
		if ( $id < 1 ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete.
		$deleted = $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
		return false !== $deleted;
	}

	/**
	 * Delete estimates for a customer.
	 *
	 * @param int $customer_id Customer ID.
	 */
	public static function delete_for_customer( $customer_id ) {
		global $wpdb;
		$customer_id = absint( $customer_id );
		if ( $customer_id < 1 ) {
			return;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete.
		$wpdb->delete( self::table(), array( 'customer_id' => $customer_id ), array( '%d' ) );
	}

	/**
	 * Delete estimates for a job.
	 *
	 * @param int $job_id Job ID.
	 */
	public static function delete_for_job( $job_id ) {
		global $wpdb;
		$job_id = absint( $job_id );
		if ( $job_id < 1 ) {
			return;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete.
		$wpdb->delete( self::table(), array( 'job_id' => $job_id ), array( '%d' ) );
	}
}
