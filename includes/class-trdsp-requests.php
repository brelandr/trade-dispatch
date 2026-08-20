<?php
/**
 * Pending customer request inbox.
 *
 * @package Trade_Dispatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Booking, portal time, and estimate-schedule requests.
 */
class TRDSP_Requests {

	const OPTION          = 'trdsp_estimate_requests';
	const COUNT_TRANSIENT = 'trdsp_requests_count';

	/**
	 * Request-count memo for the current PHP request.
	 *
	 * @var int|null
	 */
	protected static $count_memo = null;

	/**
	 * Optional note appended to the next customer email.
	 *
	 * @var string
	 */
	protected static $office_note = '';

	/**
	 * Register hooks.
	 */
	public static function hooks() {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, array(), '', 'no' );
		}
		add_action( 'admin_post_trdsp_review_request', array( __CLASS__, 'handle_review' ) );
		add_action( 'trdsp_after_job_save', array( __CLASS__, 'invalidate_count' ) );
	}

	/**
	 * Drop the inbox count cache after a write.
	 */
	public static function invalidate_count() {
		self::$count_memo = null;
		delete_transient( self::COUNT_TRANSIENT );
	}

	/**
	 * Store a pending estimate schedule request.
	 *
	 * @param int    $estimate_id Estimate ID.
	 * @param string $message     Optional customer note.
	 */
	public static function set_estimate_request( $estimate_id, $message = '' ) {
		$estimate_id = absint( $estimate_id );
		if ( $estimate_id < 1 ) {
			return;
		}
		$all = get_option( self::OPTION, array() );
		if ( ! is_array( $all ) ) {
			$all = array();
		}
		$all[ (string) $estimate_id ] = array(
			'at'      => gmdate( 'Y-m-d H:i:s' ),
			'message' => sanitize_textarea_field( (string) $message ),
		);
		update_option( self::OPTION, $all, false );
		self::invalidate_count();
	}

	/**
	 * Clear a pending estimate schedule request.
	 *
	 * @param int $estimate_id Estimate ID.
	 */
	public static function clear_estimate_request( $estimate_id ) {
		$estimate_id = absint( $estimate_id );
		if ( $estimate_id < 1 ) {
			return;
		}
		$all = get_option( self::OPTION, array() );
		if ( ! is_array( $all ) ) {
			return;
		}
		unset( $all[ (string) $estimate_id ], $all[ $estimate_id ] );
		update_option( self::OPTION, $all, false );
		self::invalidate_count();
	}

	/**
	 * Estimate IDs waiting for office review.
	 *
	 * @return array<int,int>
	 */
	public static function list_estimate_request_ids() {
		$all = get_option( self::OPTION, array() );
		if ( ! is_array( $all ) ) {
			return array();
		}
		$ids = array();
		foreach ( $all as $key => $value ) {
			if ( is_array( $value ) ) {
				if ( '' === sanitize_text_field( (string) ( $value['at'] ?? '' ) ) ) {
					continue;
				}
			} elseif ( '' === sanitize_text_field( (string) $value ) ) {
				continue;
			}
			$id = absint( $key );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * When the estimate schedule request was stored.
	 *
	 * @param int $estimate_id Estimate ID.
	 * @return string
	 */
	public static function estimate_requested_at( $estimate_id ) {
		$all = get_option( self::OPTION, array() );
		if ( ! is_array( $all ) ) {
			return '';
		}
		$key = (string) absint( $estimate_id );
		$raw = null;
		if ( isset( $all[ $key ] ) ) {
			$raw = $all[ $key ];
		} elseif ( isset( $all[ absint( $estimate_id ) ] ) ) {
			$raw = $all[ absint( $estimate_id ) ];
		}
		if ( is_array( $raw ) ) {
			return sanitize_text_field( (string) ( $raw['at'] ?? '' ) );
		}
		return sanitize_text_field( (string) $raw );
	}

	/**
	 * Customer note stored with an estimate schedule request.
	 *
	 * @param int $estimate_id Estimate ID.
	 * @return string
	 */
	public static function estimate_request_message( $estimate_id ) {
		$all = get_option( self::OPTION, array() );
		if ( ! is_array( $all ) ) {
			return '';
		}
		$key = (string) absint( $estimate_id );
		$raw = null;
		if ( isset( $all[ $key ] ) ) {
			$raw = $all[ $key ];
		} elseif ( isset( $all[ absint( $estimate_id ) ] ) ) {
			$raw = $all[ absint( $estimate_id ) ];
		}
		if ( ! is_array( $raw ) ) {
			return '';
		}
		return sanitize_textarea_field( (string) ( $raw['message'] ?? '' ) );
	}

	/**
	 * Latest portal time-request note from the customer, if any.
	 *
	 * @param int $job_id Job ID.
	 * @return string
	 */
	public static function job_portal_message( $job_id ) {
		if ( ! class_exists( 'TRDSP_Notes' ) ) {
			return '';
		}
		$prefix = __( 'Customer requested a new visit time from the portal.', 'trade-dispatch' );
		$pref   = __( 'Preferred', 'trade-dispatch' );
		foreach ( TRDSP_Notes::for_job( absint( $job_id ) ) as $row ) {
			$note = (string) ( $row['note'] ?? '' );
			if ( 0 !== strpos( $note, $prefix ) ) {
				continue;
			}
			$parts = preg_split( '/\r\n|\r|\n/', $note );
			$kept  = array();
			foreach ( $parts as $i => $line ) {
				if ( 0 === $i ) {
					continue;
				}
				$line = trim( (string) $line );
				if ( '' === $line || 0 === strpos( $line, $pref ) ) {
					continue;
				}
				$kept[] = $line;
			}
			return implode( ' ', $kept );
		}
		return '';
	}

	/**
	 * Pending inbox count for office users.
	 *
	 * @return int
	 */
	public static function count() {
		if ( null !== self::$count_memo ) {
			return self::$count_memo;
		}
		$cached = get_transient( self::COUNT_TRANSIENT );
		if ( false !== $cached && is_numeric( $cached ) ) {
			self::$count_memo = (int) $cached;
			return self::$count_memo;
		}
		self::$count_memo = count( self::list_items() );
		set_transient( self::COUNT_TRANSIENT, self::$count_memo, MINUTE_IN_SECONDS );
		return self::$count_memo;
	}

	/**
	 * Red count HTML for admin menu titles, or empty.
	 *
	 * @return string
	 */
	public static function badge_html() {
		if ( ! class_exists( 'TRDSP_Roles' ) || ! TRDSP_Roles::can_manage_office() ) {
			return '';
		}
		$n = self::count();
		if ( $n < 1 ) {
			return '';
		}
		return ' <span class="awaiting-mod"><span class="pending-count">' . esc_html( (string) $n ) . '</span></span>';
	}

	/**
	 * Unified inbox rows (booking, time, estimate). Requested jobs are not also listed as time rows.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function list_items() {
		$items       = array();
		$booking_ids = array();
		if ( class_exists( 'TRDSP_Jobs' ) ) {
			foreach ( TRDSP_Jobs::query( array( 'status' => 'requested', 'limit' => 100 ) ) as $job ) {
				$booking_ids[] = (int) $job['id'];
				$customer      = class_exists( 'TRDSP_Customers' ) ? TRDSP_Customers::get( (int) $job['customer_id'] ) : null;
				$items[]       = array(
					'type'     => 'booking',
					'id'       => (int) $job['id'],
					'kind'     => __( 'Booking request', 'trade-dispatch' ),
					'title'    => (string) $job['title'],
					'customer' => $customer ? (string) $customer['name'] : '—',
					'detail'   => ! empty( $job['scheduled_at'] ) ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( (string) $job['scheduled_at'] ) ) : __( 'No time on the job yet.', 'trade-dispatch' ),
					'message'  => $customer ? sanitize_textarea_field( (string) ( $customer['notes'] ?? '' ) ) : '',
					'job'      => $job,
				);
			}
			foreach ( TRDSP_Jobs::list_preferred_job_ids() as $job_id ) {
				if ( in_array( (int) $job_id, $booking_ids, true ) ) {
					continue;
				}
				$job = TRDSP_Jobs::get( $job_id );
				if ( ! $job ) {
					continue;
				}
				$customer = class_exists( 'TRDSP_Customers' ) ? TRDSP_Customers::get( (int) $job['customer_id'] ) : null;
				$current  = ! empty( $job['scheduled_at'] ) ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( (string) $job['scheduled_at'] ) ) : '—';
				$items[]  = array(
					'type'     => 'time',
					'id'       => (int) $job_id,
					'kind'     => __( 'Time request', 'trade-dispatch' ),
					'title'    => (string) $job['title'],
					'customer' => $customer ? (string) $customer['name'] : '—',
					'detail'   => sprintf(
						/* translators: 1: current time, 2: requested time */
						__( 'Current: %1$s. Requested: %2$s', 'trade-dispatch' ),
						$current,
						TRDSP_Jobs::format_preferred_label( $job_id )
					),
					'message'  => self::job_portal_message( $job_id ),
					'job'      => $job,
				);
			}
		}
		if ( class_exists( 'TRDSP_Estimates' ) ) {
			foreach ( self::list_estimate_request_ids() as $estimate_id ) {
				$estimate = TRDSP_Estimates::get( $estimate_id );
				if ( ! $estimate ) {
					self::clear_estimate_request( $estimate_id );
					continue;
				}
				$customer = class_exists( 'TRDSP_Customers' ) ? TRDSP_Customers::get( (int) $estimate['customer_id'] ) : null;
				$when     = self::estimate_requested_at( $estimate_id );
				$items[]  = array(
					'type'     => 'estimate',
					'id'       => (int) $estimate_id,
					'kind'     => __( 'Estimate schedule', 'trade-dispatch' ),
					'title'    => (string) $estimate['title'],
					'customer' => $customer ? (string) $customer['name'] : '—',
					'detail'   => sprintf(
						/* translators: 1: amount, 2: when requested */
						__( 'Amount: %1$s. Requested: %2$s', 'trade-dispatch' ),
						number_format_i18n( (float) $estimate['amount'], 2 ),
						$when ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $when ) ) : '—'
					),
					'message'  => self::estimate_request_message( $estimate_id ),
					'estimate' => $estimate,
				);
			}
		}
		return $items;
	}

	/**
	 * Set the reply note for the next outbound template.
	 *
	 * @param string $note Note.
	 */
	public static function set_office_note( $note ) {
		self::$office_note = sanitize_textarea_field( (string) $note );
	}

	/**
	 * Current reply note (does not clear).
	 *
	 * @return string
	 */
	public static function peek_office_note() {
		return self::$office_note;
	}

	/**
	 * Clear the reply note after mail is sent.
	 */
	public static function clear_office_note() {
		self::$office_note = '';
	}

	/**
	 * Approve or decline a request.
	 */
	public static function handle_review() {
		if ( ! isset( $_POST['trdsp_review_request_nonce'] ) ) {
			wp_die( esc_html__( 'Security check failed.', 'trade-dispatch' ) );
		}
		$type = isset( $_POST['trdsp_request_type'] ) ? sanitize_key( wp_unslash( $_POST['trdsp_request_type'] ) ) : '';
		$id   = isset( $_POST['trdsp_request_id'] ) ? absint( wp_unslash( $_POST['trdsp_request_id'] ) ) : 0;
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['trdsp_review_request_nonce'] ) ), 'trdsp_review_request_' . $type . '_' . $id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'trade-dispatch' ) );
		}
		if ( 'estimate' === $type ) {
			if ( ! class_exists( 'TRDSP_Roles' ) || ! TRDSP_Roles::can_manage_estimates() ) {
				wp_die( esc_html__( 'Unauthorized.', 'trade-dispatch' ) );
			}
		} elseif ( ! class_exists( 'TRDSP_Roles' ) || ! TRDSP_Roles::can_manage_office() ) {
			wp_die( esc_html__( 'Unauthorized.', 'trade-dispatch' ) );
		}
		if ( ! in_array( $type, array( 'time', 'estimate', 'booking' ), true ) || $id < 1 ) {
			wp_safe_redirect( esc_url_raw( self::inbox_url( 'error' ) ) );
			exit;
		}
		$note = isset( $_POST['trdsp_office_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['trdsp_office_note'] ) ) : '';
		self::set_office_note( $note );
		$approve = isset( $_POST['trdsp_decision_approve'] );
		$decline = isset( $_POST['trdsp_decision_decline'] );
		if ( $approve ) {
			self::approve( $type, $id );
			return;
		}
		if ( $decline ) {
			self::decline( $type, $id );
			return;
		}
		self::clear_office_note();
		wp_safe_redirect( esc_url_raw( self::inbox_url( 'error' ) ) );
		exit;
	}

	/**
	 * Approve or decline without redirecting (companion REST).
	 *
	 * @param string $type     time|estimate|booking.
	 * @param int    $id       Record ID.
	 * @param string $decision approve|decline.
	 * @param string $note     Optional office note for the customer email.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function decide( $type, $id, $decision, $note = '' ) {
		$type     = sanitize_key( $type );
		$id       = absint( $id );
		$decision = sanitize_key( $decision );
		if ( ! in_array( $type, array( 'time', 'estimate', 'booking' ), true ) || $id < 1 || ! in_array( $decision, array( 'approve', 'decline' ), true ) ) {
			return new WP_Error( 'trdsp_request_invalid', __( 'That request could not be reviewed.', 'trade-dispatch' ), array( 'status' => 400 ) );
		}
		if ( '' !== $note ) {
			self::set_office_note( $note );
		}
		if ( 'approve' === $decision ) {
			return self::decide_approve( $type, $id );
		}
		return self::decide_decline( $type, $id );
	}

	/**
	 * Approve a pending item.
	 *
	 * @param string $type time|estimate|booking.
	 * @param int    $id   Record ID.
	 */
	protected static function approve( $type, $id ) {
		self::redirect_after_decide( $id, 'approve', self::decide( $type, $id, 'approve' ) );
	}

	/**
	 * Decline a pending item.
	 *
	 * @param string $type time|estimate|booking.
	 * @param int    $id   Record ID.
	 */
	protected static function decline( $type, $id ) {
		self::redirect_after_decide( $id, 'decline', self::decide( $type, $id, 'decline' ) );
	}

	/**
	 * Apply a portal preferred time.
	 *
	 * @param int $id Job ID.
	 */
	protected static function approve_time( $id ) {
		self::redirect_after_decide( $id, 'approve', self::decide( 'time', $id, 'approve' ) );
	}

	/**
	 * Confirm a booking request.
	 *
	 * @param int $id Job ID.
	 */
	protected static function approve_booking( $id ) {
		self::redirect_after_decide( $id, 'approve', self::decide( 'booking', $id, 'approve' ) );
	}

	/**
	 * Approve without redirect.
	 *
	 * @param string $type time|estimate|booking.
	 * @param int    $id   Record ID.
	 * @return array<string,mixed>|\WP_Error
	 */
	protected static function decide_approve( $type, $id ) {
		if ( 'time' === $type ) {
			$job       = class_exists( 'TRDSP_Jobs' ) ? TRDSP_Jobs::get( $id ) : null;
			$preferred = $job ? TRDSP_Jobs::get_preferred_at( $id ) : '';
			if ( ! $job || '' === $preferred ) {
				self::clear_office_note();
				return new WP_Error( 'trdsp_request_missing', __( 'That time request is no longer waiting.', 'trade-dispatch' ), array( 'status' => 404 ) );
			}
			$job['scheduled_at'] = $preferred;
			$result              = TRDSP_Jobs::save( $job );
			if ( is_wp_error( $result ) ) {
				self::clear_office_note();
				return $result;
			}
			TRDSP_Jobs::set_preferred_at( $id, '' );
			do_action( 'trdsp_job_preferred_applied', $id, $job, $preferred );
			self::clear_office_note();
			return array(
				'overlap' => TRDSP_Jobs::overlaps_for_assignee( (int) $job['assigned_user_id'], (string) $job['scheduled_at'], $id ),
			);
		}
		if ( 'booking' === $type ) {
			$job = class_exists( 'TRDSP_Jobs' ) ? TRDSP_Jobs::get( $id ) : null;
			if ( ! $job || 'requested' !== (string) $job['status'] ) {
				self::clear_office_note();
				return new WP_Error( 'trdsp_request_missing', __( 'That booking request is no longer waiting.', 'trade-dispatch' ), array( 'status' => 404 ) );
			}
			if ( empty( $job['scheduled_at'] ) ) {
				$preferred = TRDSP_Jobs::get_preferred_at( $id );
				if ( '' !== $preferred ) {
					$job['scheduled_at'] = $preferred;
				}
			}
			if ( empty( $job['scheduled_at'] ) ) {
				self::clear_office_note();
				return new WP_Error( 'trdsp_booking_need_time', __( 'Set a visit time before approving this booking.', 'trade-dispatch' ), array( 'status' => 400 ) );
			}
			$job['status'] = 'scheduled';
			$result        = TRDSP_Jobs::save( $job );
			if ( is_wp_error( $result ) ) {
				self::clear_office_note();
				return $result;
			}
			TRDSP_Jobs::set_preferred_at( $id, '' );
			self::clear_office_note();
			return array(
				'overlap' => TRDSP_Jobs::overlaps_for_assignee( (int) $job['assigned_user_id'], (string) $job['scheduled_at'], $id ),
			);
		}
		$estimate = class_exists( 'TRDSP_Estimates' ) ? TRDSP_Estimates::get( $id ) : null;
		if ( ! $estimate ) {
			self::clear_office_note();
			return new WP_Error( 'trdsp_request_missing', __( 'That estimate request is no longer waiting.', 'trade-dispatch' ), array( 'status' => 404 ) );
		}
		self::clear_estimate_request( $id );
		/**
		 * After the office approves a portal estimate-schedule request.
		 *
		 * @param int                  $id       Estimate ID.
		 * @param array<string,mixed> $estimate Estimate row.
		 */
		do_action( 'trdsp_estimate_request_approved', $id, $estimate );
		self::clear_office_note();
		return array( 'overlap' => false );
	}

	/**
	 * Decline without redirect.
	 *
	 * @param string $type time|estimate|booking.
	 * @param int    $id   Record ID.
	 * @return array<string,mixed>|\WP_Error
	 */
	protected static function decide_decline( $type, $id ) {
		if ( 'time' === $type ) {
			$job       = class_exists( 'TRDSP_Jobs' ) ? TRDSP_Jobs::get( $id ) : null;
			$preferred = $job ? TRDSP_Jobs::get_preferred_at( $id ) : '';
			if ( ! $job || '' === $preferred ) {
				self::clear_office_note();
				return new WP_Error( 'trdsp_request_missing', __( 'That time request is no longer waiting.', 'trade-dispatch' ), array( 'status' => 404 ) );
			}
			TRDSP_Jobs::set_preferred_at( $id, '' );
			/**
			 * After the office declines a portal time request. Schedule is unchanged.
			 *
			 * @param int                  $id        Job ID.
			 * @param array<string,mixed> $job       Job row.
			 * @param string               $preferred Declined datetime.
			 */
			do_action( 'trdsp_job_preferred_declined', $id, $job, $preferred );
			self::clear_office_note();
			return array( 'overlap' => false );
		}
		if ( 'booking' === $type ) {
			$job = class_exists( 'TRDSP_Jobs' ) ? TRDSP_Jobs::get( $id ) : null;
			if ( ! $job || 'requested' !== (string) $job['status'] ) {
				self::clear_office_note();
				return new WP_Error( 'trdsp_request_missing', __( 'That booking request is no longer waiting.', 'trade-dispatch' ), array( 'status' => 404 ) );
			}
			$job['status'] = 'cancelled';
			$result        = TRDSP_Jobs::save( $job );
			if ( is_wp_error( $result ) ) {
				self::clear_office_note();
				return $result;
			}
			TRDSP_Jobs::set_preferred_at( $id, '' );
			/**
			 * After the office declines a public booking request (job cancelled).
			 *
			 * @param int                  $id  Job ID.
			 * @param array<string,mixed> $job Job row.
			 */
			do_action( 'trdsp_job_booking_declined', $id, $job );
			self::clear_office_note();
			return array( 'overlap' => false );
		}
		$estimate = class_exists( 'TRDSP_Estimates' ) ? TRDSP_Estimates::get( $id ) : null;
		if ( ! $estimate ) {
			self::clear_office_note();
			return new WP_Error( 'trdsp_request_missing', __( 'That estimate request is no longer waiting.', 'trade-dispatch' ), array( 'status' => 404 ) );
		}
		self::clear_estimate_request( $id );
		/**
		 * After the office declines a portal estimate-schedule request.
		 *
		 * @param int                  $id       Estimate ID.
		 * @param array<string,mixed> $estimate Estimate row.
		 */
		do_action( 'trdsp_estimate_request_declined', $id, $estimate );
		self::clear_office_note();
		return array( 'overlap' => false );
	}

	/**
	 * Admin redirect after a review.
	 *
	 * @param int                       $id       Record ID.
	 * @param string                    $decision approve|decline.
	 * @param array<string,mixed>|\WP_Error $result Decide result.
	 */
	protected static function redirect_after_decide( $id, $decision, $result ) {
		if ( is_wp_error( $result ) ) {
			if ( 'trdsp_booking_need_time' === $result->get_error_code() ) {
				wp_safe_redirect(
					esc_url_raw(
						add_query_arg(
							array(
								'page'         => 'trade-dispatch',
								'trdsp_view'   => 'edit',
								'job_id'       => $id,
								'trdsp_notice' => 'booking_need_time',
							),
							admin_url( 'admin.php' )
						)
					)
				);
				exit;
			}
			wp_safe_redirect( esc_url_raw( self::inbox_url( 'error' ) ) );
			exit;
		}
		$args = array(
			'page'         => 'trade-dispatch-requests',
			'trdsp_notice' => 'approve' === $decision ? 'request_approved' : 'request_declined',
		);
		if ( ! empty( $result['overlap'] ) ) {
			$args['trdsp_overlap'] = '1';
		}
		wp_safe_redirect( esc_url_raw( add_query_arg( $args, admin_url( 'admin.php' ) ) ) );
		exit;
	}

	/**
	 * Inbox admin URL.
	 *
	 * @param string $notice Notice key.
	 * @return string
	 */
	protected static function inbox_url( $notice ) {
		return add_query_arg(
			array(
				'page'         => 'trade-dispatch-requests',
				'trdsp_notice' => $notice,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Requests admin screen.
	 */
	public static function render_page() {
		if ( ! class_exists( 'TRDSP_Roles' ) || ! TRDSP_Roles::can_manage_office() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'trade-dispatch' ) );
		}
		$items = self::list_items();
		echo '<div class="wrap trdsp-wrap">';
		echo '<h1>' . esc_html__( 'Requests', 'trade-dispatch' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Approve or decline customer booking, time-change, and estimate-schedule requests. An optional reply note is added to the customer email.', 'trade-dispatch' ) . '</p>';
		if ( empty( $items ) ) {
			echo '<p>' . esc_html__( 'No customer requests waiting.', 'trade-dispatch' ) . '</p></div>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Type', 'trade-dispatch' ) . '</th>';
		echo '<th>' . esc_html__( 'Customer', 'trade-dispatch' ) . '</th>';
		echo '<th>' . esc_html__( 'Item', 'trade-dispatch' ) . '</th>';
		echo '<th>' . esc_html__( 'Details', 'trade-dispatch' ) . '</th>';
		echo '<th>' . esc_html__( 'Reply and decide', 'trade-dispatch' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $items as $item ) {
			$type = (string) $item['type'];
			$id   = (int) $item['id'];
			echo '<tr>';
			echo '<td>' . esc_html( (string) $item['kind'] ) . '</td>';
			echo '<td>' . esc_html( (string) $item['customer'] ) . '</td>';
			echo '<td>' . esc_html( (string) $item['title'] ) . '</td>';
			echo '<td>' . esc_html( (string) $item['detail'] );
			if ( ! empty( $item['message'] ) ) {
				echo '<p class="description">' . esc_html( (string) $item['message'] ) . '</p>';
			}
			echo '</td>';
			echo '<td>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo '<input type="hidden" name="action" value="trdsp_review_request" />';
			echo '<input type="hidden" name="trdsp_request_type" value="' . esc_attr( $type ) . '" />';
			echo '<input type="hidden" name="trdsp_request_id" value="' . esc_attr( (string) $id ) . '" />';
			wp_nonce_field( 'trdsp_review_request_' . $type . '_' . $id, 'trdsp_review_request_nonce' );
			echo '<p><label class="screen-reader-text" for="trdsp_office_note_' . esc_attr( $type . '_' . $id ) . '">' . esc_html__( 'Reply note', 'trade-dispatch' ) . '</label>';
			echo '<textarea class="large-text" rows="2" id="trdsp_office_note_' . esc_attr( $type . '_' . $id ) . '" name="trdsp_office_note" placeholder="' . esc_attr__( 'Optional note on the customer email', 'trade-dispatch' ) . '"></textarea></p>';
			submit_button( __( 'Approve', 'trade-dispatch' ), 'primary', 'trdsp_decision_approve', false );
			echo ' ';
			if ( 'booking' === $type ) {
				echo '<button type="submit" name="trdsp_decision_decline" class="button" value="1" data-trdsp-confirm="' . esc_attr( __( 'Decline this booking and mark the job cancelled?', 'trade-dispatch' ) ) . '">' . esc_html__( 'Decline and cancel', 'trade-dispatch' ) . '</button>';
			} else {
				submit_button( __( 'Decline', 'trade-dispatch' ), 'secondary', 'trdsp_decision_decline', false );
			}
			echo '</form>';
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
	}
}
