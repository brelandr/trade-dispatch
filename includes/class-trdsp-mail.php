<?php
/**
 * Native WordPress email notifications.
 *
 * @package Trade_Dispatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends job booked / completed emails via wp_mail.
 */
class TRDSP_Mail {

	/**
	 * Hook listeners.
	 */
	public static function hooks() {
		add_action( 'trdsp_job_booked', array( __CLASS__, 'on_booked' ), 10, 2 );
		add_action( 'trdsp_job_completed', array( __CLASS__, 'on_completed' ), 10, 2 );
		add_action( 'trdsp_job_assigned', array( __CLASS__, 'on_assigned' ), 10, 3 );
		add_action( 'trdsp_estimate_sent', array( __CLASS__, 'on_estimate_sent' ), 10, 2 );
		add_action( 'trdsp_estimate_reminded', array( __CLASS__, 'on_estimate_reminded' ), 10, 2 );
		add_action( 'trdsp_portal_reschedule_requested', array( __CLASS__, 'on_reschedule_requested' ), 10, 4 );
		add_action( 'trdsp_portal_estimate_requested', array( __CLASS__, 'on_estimate_requested' ), 10, 2 );
		add_action( 'trdsp_estimate_accepted', array( __CLASS__, 'on_estimate_accepted' ), 10, 2 );
		add_action( 'trdsp_job_confirmed', array( __CLASS__, 'on_confirmed' ), 10, 2 );
		add_action( 'trdsp_job_preferred_applied', array( __CLASS__, 'on_preferred_applied' ), 10, 3 );
		add_action( 'trdsp_job_preferred_declined', array( __CLASS__, 'on_preferred_declined' ), 10, 3 );
		add_action( 'trdsp_estimate_request_approved', array( __CLASS__, 'on_estimate_request_approved' ), 10, 2 );
		add_action( 'trdsp_estimate_request_declined', array( __CLASS__, 'on_estimate_request_declined' ), 10, 2 );
		add_action( 'trdsp_job_booking_declined', array( __CLASS__, 'on_booking_declined' ), 10, 2 );
	}

	/**
	 * Admin notification address.
	 *
	 * @return string
	 */
	public static function admin_email() {
		$settings = get_option( 'trdsp_settings', array() );
		$email    = isset( $settings['notify_email'] ) ? sanitize_email( (string) $settings['notify_email'] ) : '';
		if ( '' === $email ) {
			$email = sanitize_email( (string) get_option( 'admin_email' ) );
		}
		return $email;
	}

	/**
	 * Business name on outbound mail (settings, else site title).
	 *
	 * @return string
	 */
	public static function company_name() {
		$settings = get_option( 'trdsp_settings', array() );
		$name     = isset( $settings['business_name'] ) ? sanitize_text_field( (string) $settings['business_name'] ) : '';
		if ( '' === $name ) {
			$name = (string) get_bloginfo( 'name' );
		}
		return $name;
	}

	/**
	 * Job booked: customer + office.
	 *
	 * @param int                  $job_id Job ID.
	 * @param array<string,mixed> $job    Job row.
	 */
	public static function on_booked( $job_id, $job ) {
		unset( $job_id );
		$customer = TRDSP_Customers::get( absint( $job['customer_id'] ?? 0 ) );
		$vars     = self::job_vars( $job, $customer );
		self::send_pair( 'booked_customer', 'booked_office', $vars, $customer );
	}

	/**
	 * Customer: booking request was confirmed.
	 *
	 * @param int                  $job_id Job ID.
	 * @param array<string,mixed> $job    Job row.
	 */
	public static function on_confirmed( $job_id, $job ) {
		unset( $job_id );
		$customer = TRDSP_Customers::get( absint( $job['customer_id'] ?? 0 ) );
		self::send_pair( 'confirmed_customer', 'confirmed_office', self::job_vars( $job, $customer ), $customer );
	}

	/**
	 * Customer: office applied a portal-requested time.
	 *
	 * @param int                  $job_id    Job ID.
	 * @param array<string,mixed> $job       Job row.
	 * @param string               $preferred Preferred datetime.
	 */
	public static function on_preferred_applied( $job_id, $job, $preferred ) {
		$fresh = TRDSP_Jobs::get( absint( $job_id ) );
		if ( $fresh ) {
			$job = $fresh;
		}
		$customer = TRDSP_Customers::get( absint( $job['customer_id'] ?? 0 ) );
		$vars     = self::job_vars(
			$job,
			$customer,
			array(
				'preferred_time' => self::format_when( $preferred ),
			)
		);
		self::send_pair( 'preferred_applied_customer', 'preferred_applied_office', $vars, $customer );
	}

	/**
	 * Customer + office: portal time request declined.
	 *
	 * @param int                  $job_id    Job ID.
	 * @param array<string,mixed> $job       Job row.
	 * @param string               $preferred Declined datetime.
	 */
	public static function on_preferred_declined( $job_id, $job, $preferred ) {
		unset( $job_id );
		$customer = TRDSP_Customers::get( absint( $job['customer_id'] ?? 0 ) );
		$vars     = self::job_vars(
			$job,
			$customer,
			array(
				'preferred_time' => self::format_when( $preferred ),
			)
		);
		self::send_pair( 'preferred_declined_customer', 'preferred_declined_office', $vars, $customer );
	}

	/**
	 * Customer + office: booking request declined.
	 *
	 * @param int                  $job_id Job ID.
	 * @param array<string,mixed> $job    Job row.
	 */
	public static function on_booking_declined( $job_id, $job ) {
		unset( $job_id );
		$customer = TRDSP_Customers::get( absint( $job['customer_id'] ?? 0 ) );
		self::send_pair( 'booking_declined_customer', 'booking_declined_office', self::job_vars( $job, $customer ), $customer );
	}

	/**
	 * Job completed: customer + office.
	 *
	 * @param int                  $job_id Job ID.
	 * @param array<string,mixed> $job    Job row.
	 */
	public static function on_completed( $job_id, $job ) {
		unset( $job_id );
		$customer = TRDSP_Customers::get( absint( $job['customer_id'] ?? 0 ) );
		self::send_pair( 'completed_customer', 'completed_office', self::job_vars( $job, $customer ), $customer );
	}

	/**
	 * Notify the assigned crew member and the office.
	 *
	 * @param int                  $job_id   Job ID.
	 * @param array<string,mixed> $job      Job row.
	 * @param int                  $user_id  Assignee.
	 */
	public static function on_assigned( $job_id, $job, $user_id ) {
		unset( $job_id );
		$user = get_userdata( absint( $user_id ) );
		if ( ! $user || ! is_email( $user->user_email ) ) {
			return;
		}
		$customer = TRDSP_Customers::get( absint( $job['customer_id'] ?? 0 ) );
		$vars     = self::job_vars(
			$job,
			$customer,
			array(
				'crew_name' => $user->display_name,
			)
		);
		if ( class_exists( 'TRDSP_Email_Templates' ) ) {
			$crew = TRDSP_Email_Templates::render( 'assigned_crew', $vars );
			wp_mail( $user->user_email, $crew['subject'], $crew['body'] );
			$office = TRDSP_Email_Templates::render( 'assigned_office', $vars );
			self::send_to_office( $office['subject'], $office['body'] );
			return;
		}
		$body = self::job_body( __( 'A job was assigned to you in Trade Dispatch.', 'trade-dispatch' ), $job, $customer );
		wp_mail( $user->user_email, sprintf( __( 'Job assigned: %s', 'trade-dispatch' ), (string) ( $job['title'] ?? '' ) ), $body );
		self::send_to_office( sprintf( __( 'Job assigned: %s', 'trade-dispatch' ), (string) ( $job['title'] ?? '' ) ), $body );
	}

	/**
	 * Email an estimate record to the customer and office.
	 *
	 * @param int                  $estimate_id Estimate ID.
	 * @param array<string,mixed> $estimate    Estimate row.
	 */
	public static function on_estimate_sent( $estimate_id, $estimate ) {
		unset( $estimate_id );
		$customer = TRDSP_Customers::get( absint( $estimate['customer_id'] ?? 0 ) );
		self::send_pair( 'estimate_sent_customer', 'estimate_sent_office', self::estimate_vars( $estimate, $customer ), $customer );
	}

	/**
	 * Reminder email for a sent estimate (quote only — no charge).
	 *
	 * @param int                  $estimate_id Estimate ID.
	 * @param array<string,mixed> $estimate    Estimate row.
	 */
	public static function on_estimate_reminded( $estimate_id, $estimate ) {
		unset( $estimate_id );
		$customer = TRDSP_Customers::get( absint( $estimate['customer_id'] ?? 0 ) );
		self::send_pair( 'estimate_reminded_customer', 'estimate_reminded_office', self::estimate_vars( $estimate, $customer ), $customer );
	}

	/**
	 * Customer + office: estimate schedule request approved.
	 *
	 * @param int                  $estimate_id Estimate ID.
	 * @param array<string,mixed> $estimate    Estimate row.
	 */
	public static function on_estimate_request_approved( $estimate_id, $estimate ) {
		unset( $estimate_id );
		$customer = TRDSP_Customers::get( absint( $estimate['customer_id'] ?? 0 ) );
		self::send_pair( 'estimate_request_approved_customer', 'estimate_request_approved_office', self::estimate_vars( $estimate, $customer ), $customer );
	}

	/**
	 * Customer + office: estimate schedule request declined.
	 *
	 * @param int                  $estimate_id Estimate ID.
	 * @param array<string,mixed> $estimate    Estimate row.
	 */
	public static function on_estimate_request_declined( $estimate_id, $estimate ) {
		unset( $estimate_id );
		$customer = TRDSP_Customers::get( absint( $estimate['customer_id'] ?? 0 ) );
		self::send_pair( 'estimate_request_declined_customer', 'estimate_request_declined_office', self::estimate_vars( $estimate, $customer ), $customer );
	}

	/**
	 * Office notice when a portal customer asks for a new time.
	 *
	 * @param int                  $job_id    Job ID.
	 * @param array<string,mixed> $job       Job row.
	 * @param string               $preferred Preferred datetime.
	 * @param string               $message   Customer note.
	 */
	public static function on_reschedule_requested( $job_id, $job, $preferred, $message ) {
		unset( $job_id );
		$customer = TRDSP_Customers::get( absint( $job['customer_id'] ?? 0 ) );
		$vars     = self::job_vars(
			$job,
			$customer,
			array(
				'preferred_time'   => self::format_when( $preferred ),
				'customer_message' => sanitize_textarea_field( (string) $message ),
			)
		);
		if ( class_exists( 'TRDSP_Email_Templates' ) ) {
			$mail = TRDSP_Email_Templates::render( 'reschedule_requested_office', $vars );
			self::send_to_office( $mail['subject'], $mail['body'] );
			return;
		}
		self::send_to_office(
			sprintf( __( 'Reschedule requested: %s', 'trade-dispatch' ), (string) ( $job['title'] ?? '' ) ),
			self::job_body( __( 'A customer requested a new visit time from the portal.', 'trade-dispatch' ), $job, $customer )
		);
	}

	/**
	 * Office notice when a customer wants to schedule a sent estimate.
	 *
	 * @param int                  $estimate_id Estimate ID.
	 * @param array<string,mixed> $estimate    Estimate row.
	 */
	public static function on_estimate_requested( $estimate_id, $estimate ) {
		$customer = TRDSP_Customers::get( absint( $estimate['customer_id'] ?? 0 ) );
		$vars     = self::estimate_vars( $estimate, $customer );
		if ( class_exists( 'TRDSP_Requests' ) ) {
			$vars['customer_message'] = TRDSP_Requests::estimate_request_message( (int) $estimate_id );
		}
		if ( class_exists( 'TRDSP_Email_Templates' ) ) {
			$mail = TRDSP_Email_Templates::render( 'estimate_requested_office', $vars );
			self::send_to_office( $mail['subject'], $mail['body'] );
			return;
		}
		self::send_to_office(
			sprintf( __( 'Customer wants to schedule: %s', 'trade-dispatch' ), (string) ( $estimate['title'] ?? '' ) ),
			self::job_body( __( 'A customer asked to schedule this estimate from the portal. This is not a payment.', 'trade-dispatch' ), array( 'title' => (string) ( $estimate['title'] ?? '' ) ), $customer )
		);
	}

	/**
	 * Office: customer accepted an estimate (not a payment).
	 *
	 * @param int                  $estimate_id Estimate ID.
	 * @param array<string,mixed> $estimate    Estimate row.
	 */
	public static function on_estimate_accepted( $estimate_id, $estimate ) {
		unset( $estimate_id );
		$customer = TRDSP_Customers::get( absint( $estimate['customer_id'] ?? 0 ) );
		$vars     = self::estimate_vars( $estimate, $customer );
		if ( class_exists( 'TRDSP_Email_Templates' ) ) {
			$mail = TRDSP_Email_Templates::render( 'estimate_accepted_office', $vars );
			self::send_to_office( $mail['subject'], $mail['body'] );
			return;
		}
		self::send_to_office(
			sprintf( __( 'Estimate accepted: %s', 'trade-dispatch' ), (string) ( $estimate['title'] ?? '' ) ),
			self::job_body( __( 'A customer accepted this estimate from the portal. This is not a payment.', 'trade-dispatch' ), array( 'title' => (string) ( $estimate['title'] ?? '' ) ), $customer )
		);
	}

	/**
	 * Placeholder values for a job email.
	 *
	 * @param array<string,mixed>      $job      Job row.
	 * @param array<string,mixed>|null $customer Customer.
	 * @param array<string,string>     $extra    Extra placeholders.
	 * @return array<string,string>
	 */
	public static function job_vars( $job, $customer, $extra = array() ) {
		$addr = trim( implode( ', ', array_filter( array( $job['address_1'] ?? '', $job['city'] ?? '', $job['state'] ?? '', $job['postcode'] ?? '' ) ) ) );
		$portal = class_exists( 'TRDSP_Portal' ) ? TRDSP_Portal::url_if_set() : '';
		$note   = class_exists( 'TRDSP_Requests' ) ? TRDSP_Requests::peek_office_note() : '';
		$crew   = '';
		if ( ! empty( $job['assigned_user_id'] ) ) {
			$user = get_userdata( (int) $job['assigned_user_id'] );
			$crew = $user ? $user->display_name : '';
		}
		$vars = array(
			'customer_name'    => $customer ? (string) $customer['name'] : '',
			'job_title'        => (string) ( $job['title'] ?? '' ),
			'status'           => (string) ( $job['status'] ?? '' ),
			'scheduled'        => self::format_when( (string) ( $job['scheduled_at'] ?? '' ) ),
			'preferred_time'   => class_exists( 'TRDSP_Jobs' ) ? TRDSP_Jobs::format_preferred_label( (int) ( $job['id'] ?? 0 ) ) : '',
			'address'          => $addr,
			'company_name'     => self::company_name(),
			'portal_url'       => $portal,
			'portal_line'      => '' !== $portal ? __( 'Your portal', 'trade-dispatch' ) . ': ' . $portal : '',
			'office_note'      => $note,
			'crew_name'        => $crew,
			'customer_message' => '',
			'estimate_title'   => '',
			'amount'           => '',
			'job_list'         => '',
			'digest_date'      => '',
			'photos_line'      => '',
			'invoice_line'     => '',
			'time_line'        => '',
		);
		foreach ( $extra as $key => $value ) {
			$vars[ sanitize_key( (string) $key ) ] = (string) $value;
		}
		/**
		 * Job email placeholders. Add-ons may set photos_line, invoice_line, and time_line.
		 *
		 * @param array<string,string>     $vars     Placeholders.
		 * @param array<string,mixed>      $job      Job row.
		 * @param array<string,mixed>|null $customer Customer.
		 */
		return apply_filters( 'trdsp_mail_job_vars', $vars, $job, $customer );
	}

	/**
	 * Placeholder values for an estimate email.
	 *
	 * @param array<string,mixed>      $estimate Estimate row.
	 * @param array<string,mixed>|null $customer Customer.
	 * @return array<string,string>
	 */
	public static function estimate_vars( $estimate, $customer ) {
		return self::job_vars(
			array(
				'title'  => (string) ( $estimate['title'] ?? '' ),
				'status' => (string) ( $estimate['status'] ?? '' ),
			),
			$customer,
			array(
				'estimate_title' => (string) ( $estimate['title'] ?? '' ),
				'amount'         => number_format_i18n( (float) ( $estimate['amount'] ?? 0 ), 2 ),
			)
		);
	}

	/**
	 * Format a stored datetime for mail.
	 *
	 * @param string $value Raw datetime.
	 * @return string
	 */
	protected static function format_when( $value ) {
		$value = sanitize_text_field( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		$ts = strtotime( str_replace( 'T', ' ', $value ) );
		if ( false === $ts ) {
			return $value;
		}
		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts );
	}

	/**
	 * Send a customer template and an office template.
	 *
	 * @param string                   $customer_key Customer template key.
	 * @param string                   $office_key   Office template key.
	 * @param array<string,string>     $vars         Placeholders.
	 * @param array<string,mixed>|null $customer     Customer.
	 */
	protected static function send_pair( $customer_key, $office_key, $vars, $customer ) {
		if ( class_exists( 'TRDSP_Email_Templates' ) ) {
			$cust  = TRDSP_Email_Templates::render( $customer_key, $vars );
			$office = TRDSP_Email_Templates::render( $office_key, $vars );
			self::send_to_customer( $customer, $cust['subject'], $cust['body'] );
			self::send_to_office( $office['subject'], $office['body'] );
			return;
		}
		self::send_to_customer( $customer, (string) ( $vars['job_title'] ?? '' ), (string) ( $vars['company_name'] ?? '' ) );
		self::send_to_office( (string) ( $vars['job_title'] ?? '' ), (string) ( $vars['company_name'] ?? '' ) );
	}

	/**
	 * Build a plain-text job summary.
	 *
	 * @param string                    $intro    Intro sentence.
	 * @param array<string,mixed>      $job      Job row.
	 * @param array<string,mixed>|null $customer Customer row.
	 * @return string
	 */
	protected static function job_body( $intro, $job, $customer ) {
		$lines   = array( $intro, '' );
		$lines[] = __( 'Job', 'trade-dispatch' ) . ': ' . (string) ( $job['title'] ?? '' );
		$lines[] = __( 'Status', 'trade-dispatch' ) . ': ' . (string) ( $job['status'] ?? '' );
		if ( ! empty( $job['scheduled_at'] ) ) {
			$lines[] = __( 'Scheduled', 'trade-dispatch' ) . ': ' . wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( (string) $job['scheduled_at'] ) );
		}
		$addr = trim( implode( ', ', array_filter( array( $job['address_1'] ?? '', $job['city'] ?? '', $job['state'] ?? '', $job['postcode'] ?? '' ) ) ) );
		if ( '' !== $addr ) {
			$lines[] = __( 'Address', 'trade-dispatch' ) . ': ' . $addr;
		}
		if ( $customer ) {
			$lines[] = __( 'Customer', 'trade-dispatch' ) . ': ' . (string) $customer['name'];
		}
		$portal = self::portal_line();
		if ( '' !== $portal ) {
			$lines[] = $portal;
		}
		$lines[] = '';
		$lines[] = self::company_name();
		return implode( "\n", $lines );
	}

	/**
	 * Portal URL line when a page is saved in settings.
	 *
	 * @return string
	 */
	protected static function portal_line() {
		if ( ! class_exists( 'TRDSP_Portal' ) ) {
			return '';
		}
		$url = TRDSP_Portal::url_if_set();
		if ( '' === $url ) {
			return '';
		}
		return __( 'Your portal', 'trade-dispatch' ) . ': ' . $url;
	}

	/**
	 * Email assigned crew their jobs for tomorrow (same daily cron as recurrence).
	 */
	public static function send_tomorrow_crew_digests() {
		if ( ! class_exists( 'TRDSP_Jobs' ) ) {
			return;
		}
		$tz  = wp_timezone();
		$day = ( new DateTimeImmutable( 'tomorrow', $tz ) )->format( 'Y-m-d' );
		$jobs = TRDSP_Jobs::query(
			array(
				'from'  => $day . ' 00:00:00',
				'to'    => $day . ' 23:59:59',
				'limit' => 200,
			)
		);
		$by_user = array();
		foreach ( $jobs as $job ) {
			$uid    = absint( $job['assigned_user_id'] ?? 0 );
			$status = sanitize_key( (string) ( $job['status'] ?? '' ) );
			if ( $uid < 1 || in_array( $status, array( 'cancelled', 'completed' ), true ) ) {
				continue;
			}
			if ( ! isset( $by_user[ $uid ] ) ) {
				$by_user[ $uid ] = array();
			}
			$by_user[ $uid ][] = $job;
		}
		foreach ( $by_user as $uid => $rows ) {
			$key = 'trdsp_crew_digest_' . absint( $uid ) . '_' . $day;
			if ( get_transient( $key ) ) {
				continue;
			}
			$user = get_userdata( absint( $uid ) );
			if ( ! $user || ! is_email( $user->user_email ) ) {
				continue;
			}
			$list_lines = array();
			foreach ( $rows as $job ) {
				$when = ! empty( $job['scheduled_at'] ) ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( (string) $job['scheduled_at'] ) ) : '';
				$addr = trim( implode( ', ', array_filter( array( $job['address_1'] ?? '', $job['city'] ?? '', $job['state'] ?? '', $job['postcode'] ?? '' ) ) ) );
				$block = array( (string) ( $job['title'] ?? '' ) . ( '' !== $when ? ' — ' . $when : '' ) );
				if ( '' !== $addr ) {
					$block[] = $addr;
				}
				/**
				 * Extra lines on a crew tomorrow-digest job (checklist, photos, parts).
				 *
				 * @param array<int,string>       $block Job lines.
				 * @param array<string,mixed>     $job   Job row.
				 */
				$block = apply_filters( 'trdsp_crew_digest_job_lines', $block, $job );
				if ( is_array( $block ) ) {
					foreach ( $block as $line ) {
						$list_lines[] = (string) $line;
					}
				}
				$list_lines[] = '';
			}
			$vars = array(
				'company_name' => self::company_name(),
				'digest_date'  => $day,
				'job_list'     => implode( "\n", $list_lines ),
				'crew_name'    => $user->display_name,
			);
			if ( class_exists( 'TRDSP_Email_Templates' ) ) {
				$mail    = TRDSP_Email_Templates::render( 'crew_tomorrow_digest', $vars );
				$subject = $mail['subject'];
				$body    = $mail['body'];
			} else {
				$subject = sprintf(
					/* translators: %s Y-m-d date */
					__( 'Tomorrow\'s jobs — %s', 'trade-dispatch' ),
					$day
				);
				$body = implode( "\n", array_merge( $list_lines, array( self::company_name() ) ) );
			}
			wp_mail( $user->user_email, $subject, $body );
			set_transient( $key, 1, DAY_IN_SECONDS + HOUR_IN_SECONDS );
			/**
			 * After a crew tomorrow digest is emailed.
			 *
			 * @param int                            $uid  User ID.
			 * @param array<int,array<string,mixed>> $rows Jobs.
			 * @param string                         $day  Y-m-d.
			 */
			do_action( 'trdsp_crew_tomorrow_digest_sent', absint( $uid ), $rows, $day );
		}
	}

	/**
	 * Email the customer when they have an address.
	 *
	 * @param array<string,mixed>|null $customer Customer.
	 * @param string                   $subject  Subject.
	 * @param string                   $body     Body.
	 */
	protected static function send_to_customer( $customer, $subject, $body ) {
		if ( ! $customer || empty( $customer['email'] ) || ! is_email( $customer['email'] ) ) {
			return;
		}
		wp_mail( $customer['email'], $subject, $body );
	}

	/**
	 * Email the office.
	 *
	 * @param string $subject Subject.
	 * @param string $body    Body.
	 */
	protected static function send_to_office( $subject, $body ) {
		$to = self::admin_email();
		if ( '' === $to || ! is_email( $to ) ) {
			return;
		}
		wp_mail( $to, $subject, $body );
	}
}
