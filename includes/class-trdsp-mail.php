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
		$lines[] = get_bloginfo( 'name' );
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
