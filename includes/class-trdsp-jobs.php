<?php
/**
 * Job repository.
 *
 * @package Trade_Dispatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for wp_trdsp_jobs.
 */
class TRDSP_Jobs {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'trdsp_jobs';
	}

	/**
	 * Allowed statuses.
	 *
	 * @return array<string,string>
	 */
	public static function statuses() {
		return array(
			'requested'   => __( 'Requested', 'trade-dispatch' ),
			'scheduled'   => __( 'Scheduled', 'trade-dispatch' ),
			'in_progress' => __( 'In progress', 'trade-dispatch' ),
			'completed'   => __( 'Completed', 'trade-dispatch' ),
			'cancelled'   => __( 'Cancelled', 'trade-dispatch' ),
		);
	}

	/**
	 * Recurrence options.
	 *
	 * @return array<string,string>
	 */
	public static function recurrences() {
		return array(
			''         => __( 'None', 'trade-dispatch' ),
			'weekly'   => __( 'Weekly', 'trade-dispatch' ),
			'biweekly' => __( 'Every two weeks', 'trade-dispatch' ),
			'monthly'  => __( 'Monthly', 'trade-dispatch' ),
		);
	}

	/**
	 * Get one job.
	 *
	 * @param int $id Job ID.
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
	 * List jobs.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return array<int,array<string,mixed>>
	 */
	public static function query( $args = array() ) {
		global $wpdb;
		$args  = wp_parse_args(
			$args,
			array(
				'customer_id'      => 0,
				'assigned_user_id' => 0,
				'status'           => '',
				'from'             => '',
				'to'               => '',
				'has_recurrence'   => false,
				'search'           => '',
				'limit'            => 50,
				'offset'           => 0,
			)
		);
		$table   = self::table();
		$where   = array( '1=1' );
		$params  = array();
		$cust    = absint( $args['customer_id'] );
		$assignee = absint( $args['assigned_user_id'] );
		$status  = sanitize_key( (string) $args['status'] );
		if ( $cust > 0 ) {
			$where[]  = 'customer_id = %d';
			$params[] = $cust;
		}
		if ( $assignee > 0 ) {
			$where[]  = 'assigned_user_id = %d';
			$params[] = $assignee;
		}
		if ( '' !== $status && isset( self::statuses()[ $status ] ) ) {
			$where[]  = 'status = %s';
			$params[] = $status;
		}
		if ( ! empty( $args['from'] ) ) {
			$where[]  = 'scheduled_at >= %s';
			$params[] = sanitize_text_field( (string) $args['from'] );
		}
		if ( ! empty( $args['to'] ) ) {
			$where[]  = 'scheduled_at <= %s';
			$params[] = sanitize_text_field( (string) $args['to'] );
		}
		if ( ! empty( $args['has_recurrence'] ) ) {
			$where[] = "recurrence <> ''";
		}
		$search = sanitize_text_field( (string) $args['search'] );
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(title LIKE %s OR address_1 LIKE %s OR city LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		$limit  = min( 200, max( 1, absint( $args['limit'] ) ) );
		$offset = max( 0, absint( $args['offset'] ) );
		$sql    = 'SELECT * FROM ' . $table . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY scheduled_at ASC, id DESC LIMIT %d OFFSET %d';
		$params[] = $limit;
		$params[] = $offset;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Dynamic WHERE with prepare.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Insert or update a job.
	 *
	 * @param array<string,mixed> $data Job data.
	 * @return int|\WP_Error
	 */
	public static function save( $data ) {
		global $wpdb;
		$now    = gmdate( 'Y-m-d H:i:s' );
		$id     = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
		$status = sanitize_key( (string) ( $data['status'] ?? 'scheduled' ) );
		if ( ! isset( self::statuses()[ $status ] ) ) {
			$status = 'scheduled';
		}
		$recurrence = sanitize_key( (string) ( $data['recurrence'] ?? '' ) );
		if ( ! isset( self::recurrences()[ $recurrence ] ) ) {
			$recurrence = '';
		}

		$scheduled_raw = isset( $data['scheduled_at'] ) ? sanitize_text_field( (string) $data['scheduled_at'] ) : '';
		$scheduled_at  = self::normalize_datetime( $scheduled_raw );

		$row = array(
			'customer_id'      => absint( $data['customer_id'] ?? 0 ),
			'assigned_user_id' => absint( $data['assigned_user_id'] ?? 0 ),
			'title'            => sanitize_text_field( (string) ( $data['title'] ?? '' ) ),
			'status'           => $status,
			'scheduled_at'     => $scheduled_at,
			'address_1'        => sanitize_text_field( (string) ( $data['address_1'] ?? '' ) ),
			'city'             => sanitize_text_field( (string) ( $data['city'] ?? '' ) ),
			'state'            => sanitize_text_field( (string) ( $data['state'] ?? '' ) ),
			'postcode'         => sanitize_text_field( (string) ( $data['postcode'] ?? '' ) ),
			'gate_notes'       => sanitize_textarea_field( (string) ( $data['gate_notes'] ?? '' ) ),
			'hazard_notes'     => sanitize_textarea_field( (string) ( $data['hazard_notes'] ?? '' ) ),
			'recurrence'       => $recurrence,
			'updated_at'       => $now,
		);

		if ( '' === $row['title'] ) {
			return new WP_Error( 'trdsp_job_title', __( 'Job title is required.', 'trade-dispatch' ) );
		}

		$previous = $id > 0 ? self::get( $id ) : null;

		if ( $id < 1 && $row['customer_id'] > 0 && '' === $row['address_1'] ) {
			$customer = TRDSP_Customers::get( $row['customer_id'] );
			if ( $customer ) {
				$row['address_1'] = (string) $customer['address_1'];
				$row['city']      = (string) $customer['city'];
				$row['state']     = (string) $customer['state'];
				$row['postcode']  = (string) $customer['postcode'];
			}
		}

		/**
		 * Filter job data before save.
		 *
		 * @param array $row Job row.
		 */
		$row = apply_filters( 'trdsp_job_data_before_save', $row );

		if ( $id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update.
			$updated = $wpdb->update(
				self::table(),
				$row,
				array( 'id' => $id ),
				array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
			if ( false === $updated ) {
				return new WP_Error( 'trdsp_job_update', __( 'Could not update job.', 'trade-dispatch' ) );
			}
		} else {
			$row['created_at'] = $now;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert.
			$inserted = $wpdb->insert(
				self::table(),
				$row,
				array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
			if ( false === $inserted ) {
				return new WP_Error( 'trdsp_job_insert', __( 'Could not create job.', 'trade-dispatch' ) );
			}
			$id = (int) $wpdb->insert_id;
		}

		/**
		 * Fires after a job is saved.
		 *
		 * @param int   $id  Job ID.
		 * @param array $row Saved row.
		 */
		do_action( 'trdsp_after_job_save', $id, $row );

		$prev_assignee = $previous ? (int) $previous['assigned_user_id'] : 0;
		$new_assignee  = (int) $row['assigned_user_id'];
		if ( $new_assignee > 0 && $new_assignee !== $prev_assignee ) {
			/**
			 * Fires when a job is assigned (or reassigned) to a crew member.
			 *
			 * @param int                  $id           Job ID.
			 * @param array<string,mixed> $row          Saved row.
			 * @param int                  $new_assignee User ID.
			 */
			do_action( 'trdsp_job_assigned', $id, $row, $new_assignee );
		}

		$became_completed = ( 'completed' === $row['status'] && ( ! $previous || 'completed' !== $previous['status'] ) );
		if ( $became_completed ) {
			do_action( 'trdsp_job_completed', $id, $row );
			if ( '' !== $row['recurrence'] && ! empty( $row['scheduled_at'] ) ) {
				self::create_next_occurrence( $id, $row );
			}
		}

		return $id;
	}

	/**
	 * Delete a job.
	 *
	 * @param int $id Job ID.
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
		if ( false !== $deleted ) {
			TRDSP_Notes::delete_for_job( $id );
		}
		return false !== $deleted;
	}

	/**
	 * Normalize a datetime string to Y-m-d H:i:s or empty.
	 *
	 * @param string $value Raw datetime.
	 * @return string
	 */
	public static function normalize_datetime( $value ) {
		$value = trim( str_replace( 'T', ' ', (string) $value ) );
		if ( '' === $value ) {
			return '';
		}
		$ts = strtotime( $value );
		if ( false === $ts ) {
			return '';
		}
		return gmdate( 'Y-m-d H:i:s', $ts );
	}

	/**
	 * Next occurrence datetime.
	 *
	 * @param string $scheduled_at Current GMT datetime.
	 * @param string $recurrence   Recurrence key.
	 * @return string
	 */
	public static function next_datetime( $scheduled_at, $recurrence ) {
		$ts = strtotime( (string) $scheduled_at );
		if ( false === $ts ) {
			return '';
		}
		if ( 'weekly' === $recurrence ) {
			$ts = strtotime( '+1 week', $ts );
		} elseif ( 'biweekly' === $recurrence ) {
			$ts = strtotime( '+2 weeks', $ts );
		} elseif ( 'monthly' === $recurrence ) {
			$ts = strtotime( '+1 month', $ts );
		} else {
			return '';
		}
		return false === $ts ? '' : gmdate( 'Y-m-d H:i:s', $ts );
	}

	/**
	 * Whether a job already exists at that slot.
	 *
	 * @param int    $customer_id  Customer ID.
	 * @param string $title        Title.
	 * @param string $scheduled_at Datetime.
	 * @return bool
	 */
	public static function exists_at( $customer_id, $title, $scheduled_at ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Duplicate check.
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE customer_id = %d AND title = %s AND scheduled_at = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table from prefix + fixed slug.
				absint( $customer_id ),
				$title,
				$scheduled_at
			)
		);
		return ! empty( $found );
	}

	/**
	 * Create the next recurring job.
	 *
	 * @param int                  $source_id Source job ID.
	 * @param array<string,mixed> $row       Source row.
	 * @return int
	 */
	public static function create_next_occurrence( $source_id, $row ) {
		$next = self::next_datetime( (string) $row['scheduled_at'], (string) $row['recurrence'] );
		if ( '' === $next ) {
			return 0;
		}
		if ( self::exists_at( (int) $row['customer_id'], (string) $row['title'], $next ) ) {
			return 0;
		}
		$copy                   = $row;
		unset( $copy['created_at'] );
		$copy['id']             = 0;
		$copy['status']         = 'scheduled';
		$copy['scheduled_at']   = $next;
		$result                 = self::save( $copy );
		return is_wp_error( $result ) ? 0 : (int) $result;
	}

	/**
	 * Daily cron: spawn missing future occurrences for due recurring jobs.
	 */
	public static function generate_due_occurrences() {
		$jobs = self::query(
			array(
				'has_recurrence' => true,
				'limit'          => 100,
			)
		);
		$now = time();
		foreach ( $jobs as $job ) {
			if ( empty( $job['scheduled_at'] ) || empty( $job['recurrence'] ) ) {
				continue;
			}
			if ( strtotime( (string) $job['scheduled_at'] ) >= $now ) {
				continue;
			}
			self::create_next_occurrence( (int) $job['id'], $job );
		}
	}
}
