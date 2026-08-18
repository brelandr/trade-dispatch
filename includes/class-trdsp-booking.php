<?php
/**
 * Public booking form shortcode.
 *
 * @package Trade_Dispatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [trdsp_booking] form and admin-post handler.
 */
class TRDSP_Booking {

	/**
	 * Register hooks.
	 */
	public static function hooks() {
		add_shortcode( 'trdsp_booking', array( __CLASS__, 'render' ) );
		add_action( 'admin_post_trdsp_submit_booking', array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_nopriv_trdsp_submit_booking', array( __CLASS__, 'handle' ) );
	}

	/**
	 * Shortcode output.
	 *
	 * @param array<string,string>|string $atts Attributes.
	 * @return string
	 */
	public static function render( $atts ) {
		unset( $atts );
		wp_enqueue_style( 'trdsp-public' );
		$notice = '';
		if ( isset( $_GET['trdsp_booked'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public thank-you flag only.
			$flag = sanitize_key( wp_unslash( $_GET['trdsp_booked'] ) );
			if ( '1' === $flag ) {
				$notice = '<p class="trdsp-notice trdsp-notice-ok">' . esc_html__( 'Thanks — your booking request was sent. If this is your first visit, check your email for a login to the customer portal.', 'trade-dispatch' ) . '</p>';
			} elseif ( 'error' === $flag ) {
				$notice = '<p class="trdsp-notice trdsp-notice-err">' . esc_html__( 'Please fill in your name, email, and a service description.', 'trade-dispatch' ) . '</p>';
			}
		}

		ob_start();
		echo '<div class="trdsp-booking">';
		echo wp_kses_post( $notice );
		echo '<form class="trdsp-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="trdsp_submit_booking" />';
		wp_nonce_field( 'trdsp_submit_booking', 'trdsp_booking_nonce' );
		echo '<p class="trdsp-hp" aria-hidden="true"><label>' . esc_html__( 'Website', 'trade-dispatch' ) . ' <input type="text" name="trdsp_website" value="" tabindex="-1" autocomplete="off" /></label></p>';
		echo '<p><label for="trdsp_book_name">' . esc_html__( 'Name', 'trade-dispatch' ) . '</label> ';
		echo '<input id="trdsp_book_name" name="trdsp_name" type="text" required /></p>';
		echo '<p><label for="trdsp_book_email">' . esc_html__( 'Email', 'trade-dispatch' ) . '</label> ';
		echo '<input id="trdsp_book_email" name="trdsp_email" type="email" required /></p>';
		echo '<p><label for="trdsp_book_phone">' . esc_html__( 'Phone', 'trade-dispatch' ) . '</label> ';
		echo '<input id="trdsp_book_phone" name="trdsp_phone" type="text" /></p>';
		$services = class_exists( 'TRDSP_Services' ) ? TRDSP_Services::query() : array();
		if ( ! empty( $services ) ) {
			echo '<p><label for="trdsp_book_service">' . esc_html__( 'Service', 'trade-dispatch' ) . '</label> ';
			echo '<select id="trdsp_book_service" name="trdsp_service_id">';
			echo '<option value="0">' . esc_html__( 'Other / describe below', 'trade-dispatch' ) . '</option>';
			foreach ( $services as $service ) {
				echo '<option value="' . esc_attr( (string) $service['id'] ) . '">' . esc_html( (string) $service['name'] ) . '</option>';
			}
			echo '</select></p>';
		}
		echo '<p><label for="trdsp_book_title">' . esc_html__( 'Service needed', 'trade-dispatch' ) . '</label> ';
		echo '<input id="trdsp_book_title" name="trdsp_title" type="text" /></p>';
		echo '<p><label for="trdsp_book_when">' . esc_html__( 'Preferred date and time', 'trade-dispatch' ) . '</label> ';
		echo '<input id="trdsp_book_when" name="trdsp_scheduled_at" type="datetime-local" /></p>';
		echo '<p><label for="trdsp_book_address">' . esc_html__( 'Address', 'trade-dispatch' ) . '</label> ';
		echo '<input id="trdsp_book_address" name="trdsp_address_1" type="text" /></p>';
		echo '<p><label for="trdsp_book_city">' . esc_html__( 'City', 'trade-dispatch' ) . '</label> ';
		echo '<input id="trdsp_book_city" name="trdsp_city" type="text" /></p>';
		echo '<p><label for="trdsp_book_state">' . esc_html__( 'State', 'trade-dispatch' ) . '</label> ';
		echo '<input id="trdsp_book_state" name="trdsp_state" type="text" /></p>';
		echo '<p><label for="trdsp_book_postcode">' . esc_html__( 'Postal code', 'trade-dispatch' ) . '</label> ';
		echo '<input id="trdsp_book_postcode" name="trdsp_postcode" type="text" /></p>';
		echo '<p><label for="trdsp_book_notes">' . esc_html__( 'Notes', 'trade-dispatch' ) . '</label> ';
		echo '<textarea id="trdsp_book_notes" name="trdsp_notes" rows="4"></textarea></p>';
		echo '<p><button type="submit" class="trdsp-submit">' . esc_html__( 'Request booking', 'trade-dispatch' ) . '</button></p>';
		echo '</form></div>';
		return (string) ob_get_clean();
	}

	/**
	 * Handle booking POST.
	 */
	public static function handle() {
		$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/' );
		if ( ! isset( $_POST['trdsp_booking_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['trdsp_booking_nonce'] ) ), 'trdsp_submit_booking' ) ) {
			wp_safe_redirect( esc_url_raw( add_query_arg( 'trdsp_booked', 'error', $redirect ) ) );
			exit;
		}

		$honeypot = isset( $_POST['trdsp_website'] ) ? sanitize_text_field( wp_unslash( $_POST['trdsp_website'] ) ) : '';
		if ( '' !== $honeypot ) {
			wp_safe_redirect( esc_url_raw( add_query_arg( 'trdsp_booked', '1', $redirect ) ) );
			exit;
		}

		$name  = isset( $_POST['trdsp_name'] ) ? sanitize_text_field( wp_unslash( $_POST['trdsp_name'] ) ) : '';
		$email = isset( $_POST['trdsp_email'] ) ? sanitize_email( wp_unslash( $_POST['trdsp_email'] ) ) : '';
		$title       = isset( $_POST['trdsp_title'] ) ? sanitize_text_field( wp_unslash( $_POST['trdsp_title'] ) ) : '';
		$service_id  = isset( $_POST['trdsp_service_id'] ) ? absint( wp_unslash( $_POST['trdsp_service_id'] ) ) : 0;
		$service     = $service_id > 0 && class_exists( 'TRDSP_Services' ) ? TRDSP_Services::get( $service_id ) : null;
		if ( $service && '' === $title ) {
			$title = (string) $service['name'];
		}
		if ( '' === $name || '' === $email || ! is_email( $email ) || '' === $title ) {
			wp_safe_redirect( esc_url_raw( add_query_arg( 'trdsp_booked', 'error', $redirect ) ) );
			exit;
		}

		$existing = TRDSP_Customers::get_by_email( $email );
		$cust_id  = $existing ? (int) $existing['id'] : 0;
		$cust_id  = TRDSP_Customers::save(
			array(
				'id'        => $cust_id,
				'name'      => $name,
				'email'     => $email,
				'phone'     => isset( $_POST['trdsp_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['trdsp_phone'] ) ) : '',
				'address_1' => isset( $_POST['trdsp_address_1'] ) ? sanitize_text_field( wp_unslash( $_POST['trdsp_address_1'] ) ) : '',
				'city'      => isset( $_POST['trdsp_city'] ) ? sanitize_text_field( wp_unslash( $_POST['trdsp_city'] ) ) : '',
				'state'     => isset( $_POST['trdsp_state'] ) ? sanitize_text_field( wp_unslash( $_POST['trdsp_state'] ) ) : '',
				'postcode'  => isset( $_POST['trdsp_postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['trdsp_postcode'] ) ) : '',
				'notes'     => isset( $_POST['trdsp_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['trdsp_notes'] ) ) : '',
			)
		);
		if ( is_wp_error( $cust_id ) ) {
			wp_safe_redirect( esc_url_raw( add_query_arg( 'trdsp_booked', 'error', $redirect ) ) );
			exit;
		}

		$job_id = TRDSP_Jobs::save(
			array(
				'customer_id'  => (int) $cust_id,
				'title'        => $title,
				'status'       => 'requested',
				'scheduled_at' => isset( $_POST['trdsp_scheduled_at'] ) ? sanitize_text_field( wp_unslash( $_POST['trdsp_scheduled_at'] ) ) : '',
				'address_1'    => isset( $_POST['trdsp_address_1'] ) ? sanitize_text_field( wp_unslash( $_POST['trdsp_address_1'] ) ) : '',
				'city'         => isset( $_POST['trdsp_city'] ) ? sanitize_text_field( wp_unslash( $_POST['trdsp_city'] ) ) : '',
				'state'        => isset( $_POST['trdsp_state'] ) ? sanitize_text_field( wp_unslash( $_POST['trdsp_state'] ) ) : '',
				'postcode'     => isset( $_POST['trdsp_postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['trdsp_postcode'] ) ) : '',
			)
		);
		if ( is_wp_error( $job_id ) ) {
			wp_safe_redirect( esc_url_raw( add_query_arg( 'trdsp_booked', 'error', $redirect ) ) );
			exit;
		}

		self::maybe_create_portal_user( $name, $email );

		$job = TRDSP_Jobs::get( (int) $job_id );
		if ( $job ) {
			/**
			 * Fires after a public booking is stored.
			 *
			 * @param int   $job_id Job ID.
			 * @param array $job    Job row.
			 */
			do_action( 'trdsp_job_booked', (int) $job_id, $job );
		}

		wp_safe_redirect( esc_url_raw( add_query_arg( 'trdsp_booked', '1', $redirect ) ) );
		exit;
	}

	/**
	 * Create a subscriber so the customer can open the portal.
	 *
	 * @param string $name  Display name.
	 * @param string $email Email address.
	 */
	protected static function maybe_create_portal_user( $name, $email ) {
		if ( email_exists( $email ) ) {
			return;
		}
		$base = sanitize_user( (string) strstr( $email, '@', true ), true );
		if ( strlen( $base ) < 2 ) {
			$base = 'customer';
		}
		$login = $base;
		$i     = 1;
		while ( username_exists( $login ) ) {
			$login = $base . $i;
			++$i;
			if ( $i > 50 ) {
				$login = $base . wp_generate_password( 6, false, false );
				break;
			}
		}
		$user_id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 24, true ),
				'display_name' => $name,
				'role'         => 'subscriber',
			)
		);
		if ( ! is_wp_error( $user_id ) ) {
			wp_new_user_notification( $user_id, null, 'user' );
		}
	}
}
