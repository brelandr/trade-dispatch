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
		add_action( 'trdsp_portal_reschedule_requested', array( __CLASS__, 'on_reschedule_requested' ), 10, 4 );
		add_action( 'trdsp_portal_estimate_requested', array( __CLASS__, 'on_estimate_requested' ), 10, 2 );
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
		$customer = TRDSP_Customers::get( absint( $job['customer_id'] ?? 0 ) );
		$subject  = sprintf(
			/* translators: %s job title */
			__( 'Booking received: %s', 'trade-dispatch' ),
			(string) ( $job['title'] ?? '' )
		);
		$body = self::job_body(
			__( 'We received your service request. The office will confirm a time shortly.', 'trade-dispatch' ),
			$job,
			$customer
		);
		self::send_to_customer( $customer, $subject, $body );
		self::send_to_office(
			$subject,
			self::job_body( __( 'A new booking was submitted on the website.', 'trade-dispatch' ), $job, $customer )
		);
	}

	/**
	 * Job completed: customer + office.
	 *
	 * @param int                  $job_id Job ID.
	 * @param array<string,mixed> $job    Job row.
	 */
	public static function on_completed( $job_id, $job ) {
		$customer = TRDSP_Customers::get( absint( $job['customer_id'] ?? 0 ) );
		$subject  = sprintf(
			/* translators: %s job title */
			__( 'Job complete: %s', 'trade-dispatch' ),
			(string) ( $job['title'] ?? '' )
		);
		$body = self::job_body(
			__( 'The scheduled service has been marked complete.', 'trade-dispatch' ),
			$job,
			$customer
		);
		self::send_to_customer( $customer, $subject, $body );
		self::send_to_office( $subject, $body );
	}

	/**
	 * Notify the assigned crew member and the office.
	 *
	 * @param int                  $job_id   Job ID.
	 * @param array<string,mixed> $job      Job row.
	 * @param int                  $user_id  Assignee.
	 */
	public static function on_assigned( $job_id, $job, $user_id ) {
		$user = get_userdata( absint( $user_id ) );
		if ( ! $user || ! is_email( $user->user_email ) ) {
			return;
		}
		$customer = TRDSP_Customers::get( absint( $job['customer_id'] ?? 0 ) );
		$subject  = sprintf(
			/* translators: %s job title */
			__( 'Job assigned: %s', 'trade-dispatch' ),
			(string) ( $job['title'] ?? '' )
		);
		$body = self::job_body(
			__( 'A job was assigned to you in Trade Dispatch.', 'trade-dispatch' ),
			$job,
			$customer
		);
		wp_mail( $user->user_email, $subject, $body );
		self::send_to_office( $subject, $body );
	}

	/**
	 * Email an estimate record to the customer and office.
	 *
	 * @param int                  $estimate_id Estimate ID.
	 * @param array<string,mixed> $estimate    Estimate row.
	 */
	public static function on_estimate_sent( $estimate_id, $estimate ) {
		$customer = TRDSP_Customers::get( absint( $estimate['customer_id'] ?? 0 ) );
		$subject  = sprintf(
			/* translators: %s estimate title */
			__( 'Estimate: %s', 'trade-dispatch' ),
			(string) ( $estimate['title'] ?? '' )
		);
		$amount = number_format_i18n( (float) ( $estimate['amount'] ?? 0 ), 2 );
		$intro  = sprintf(
			/* translators: %s formatted amount */
			__( 'Here is your estimate for %s. This is a quote only and is not a charge.', 'trade-dispatch' ),
			$amount
		);
		$lines   = array( $intro, '' );
		$lines[] = __( 'Estimate', 'trade-dispatch' ) . ': ' . (string) ( $estimate['title'] ?? '' );
		$lines[] = __( 'Amount', 'trade-dispatch' ) . ': ' . $amount;
		if ( $customer ) {
			$lines[] = __( 'Customer', 'trade-dispatch' ) . ': ' . (string) $customer['name'];
		}
		$lines[] = '';
		$lines[] = self::company_name();
		$body    = implode( "\n", $lines );
		self::send_to_customer( $customer, $subject, $body );
		self::send_to_office( $subject, $body );
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
		$customer = TRDSP_Customers::get( absint( $job['customer_id'] ?? 0 ) );
		$subject  = sprintf(
			/* translators: %s job title */
			__( 'Reschedule requested: %s', 'trade-dispatch' ),
			(string) ( $job['title'] ?? '' )
		);
		$intro = __( 'A customer requested a new visit time from the portal.', 'trade-dispatch' );
		if ( '' !== $preferred ) {
			$intro .= ' ' . __( 'Preferred', 'trade-dispatch' ) . ': ' . sanitize_text_field( $preferred );
		}
		if ( '' !== $message ) {
			$intro .= "\n" . sanitize_textarea_field( $message );
		}
		self::send_to_office( $subject, self::job_body( $intro, $job, $customer ) );
	}

	/**
	 * Office notice when a customer wants to schedule a sent estimate.
	 *
	 * @param int                  $estimate_id Estimate ID.
	 * @param array<string,mixed> $estimate    Estimate row.
	 */
	public static function on_estimate_requested( $estimate_id, $estimate ) {
		$customer = TRDSP_Customers::get( absint( $estimate['customer_id'] ?? 0 ) );
		$subject  = sprintf(
			/* translators: %s estimate title */
			__( 'Customer wants to schedule: %s', 'trade-dispatch' ),
			(string) ( $estimate['title'] ?? '' )
		);
		$amount = number_format_i18n( (float) ( $estimate['amount'] ?? 0 ), 2 );
		$lines  = array(
			__( 'A customer asked to schedule this estimate from the portal. This is not a payment.', 'trade-dispatch' ),
			'',
			__( 'Estimate', 'trade-dispatch' ) . ': ' . (string) ( $estimate['title'] ?? '' ),
			__( 'Amount', 'trade-dispatch' ) . ': ' . $amount,
		);
		if ( $customer ) {
			$lines[] = __( 'Customer', 'trade-dispatch' ) . ': ' . (string) $customer['name'];
		}
		$edit = add_query_arg(
			array(
				'page'        => 'trade-dispatch-estimates',
				'trdsp_view'  => 'edit',
				'estimate_id' => absint( $estimate_id ),
			),
			admin_url( 'admin.php' )
		);
		$lines[] = __( 'Open in admin', 'trade-dispatch' ) . ': ' . $edit;
		$lines[] = '';
		$lines[] = self::company_name();
		self::send_to_office( $subject, implode( "\n", $lines ) );
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
		$lines[] = '';
		$lines[] = self::company_name();
		return implode( "\n", $lines );
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
