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
		add_action( 'trdsp_job_confirmed', array( __CLASS__, 'on_job_confirmed' ), 5, 2 );
	}

	/**
	 * Enqueue booking JS with localized window strings (booking form and portal chips).
	 */
	public static function enqueue_public_script() {
		wp_enqueue_script( 'trdsp-booking' );
		$window = self::window_config();
		wp_localize_script(
			'trdsp-booking',
			'trdspBooking',
			array(
				/* translators: %d: Typical visit duration in minutes. */
				'minutesLabel' => __( 'Typical visit: about %d minutes', 'trade-dispatch' ),
				'outsideHours' => __( 'That time is outside posted hours. You can still send the request — the office will confirm.', 'trade-dispatch' ),
				'busyDay'      => __( 'Another visit is already on the calendar near that time. You can still send the request.', 'trade-dispatch' ),
				'open'         => $window['open'],
				'close'        => $window['close'],
				'days'         => $window['days'],
				'occupied'     => $window['occupied'],
			)
		);
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
		self::enqueue_public_script();
		$settings = get_option( 'trdsp_settings', array() );
		$hours    = isset( $settings['booking_hours_hint'] ) ? sanitize_textarea_field( (string) $settings['booking_hours_hint'] ) : '';
		$notice   = '';
		$flag = isset( $_GET['trdsp_booked'] ) ? sanitize_key( wp_unslash( $_GET['trdsp_booked'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public thank-you flag only.
		if ( '' !== $flag ) {
			if ( '1' === $flag ) {
				$notice = '<p class="trdsp-notice trdsp-notice-ok">' . esc_html__( 'Thanks — your booking request was sent. The office will confirm a visit time.', 'trade-dispatch' ) . '</p>';
			} elseif ( 'window' === $flag ) {
				$notice = '<p class="trdsp-notice trdsp-notice-ok">' . esc_html__( 'Thanks — your request was sent. That time is outside posted hours or already busy; the office will confirm a visit time.', 'trade-dispatch' ) . '</p>';
			} elseif ( 'limit' === $flag ) {
				$notice = '<p class="trdsp-notice trdsp-notice-err">' . esc_html__( 'Too many booking requests from this connection. Please wait a few minutes and try again.', 'trade-dispatch' ) . '</p>';
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
				echo '<option value="' . esc_attr( (string) $service['id'] ) . '" data-minutes="' . esc_attr( (string) $service['default_minutes'] ) . '">' . esc_html( (string) $service['name'] ) . '</option>';
			}
			echo '</select></p>';
			echo '<p id="trdsp_book_minutes_hint" class="trdsp-hint"></p>';
		}
		echo '<p><label for="trdsp_book_title">' . esc_html__( 'Service needed', 'trade-dispatch' ) . '</label> ';
		echo '<input id="trdsp_book_title" name="trdsp_title" type="text" /></p>';
		echo '<p><label for="trdsp_book_when">' . esc_html__( 'Preferred date and time', 'trade-dispatch' ) . '</label> ';
		echo '<input id="trdsp_book_when" name="trdsp_scheduled_at" type="datetime-local" /></p>';
		self::render_suggested_chips( 'trdsp_book_when', 'trdsp_book_slots' );
		if ( '' !== $hours ) {
			echo '<p class="trdsp-hint">' . esc_html( $hours ) . '</p>';
		}
		echo '<p id="trdsp_book_window_hint" class="trdsp-hint" hidden></p>';
		if ( ! empty( $window['occupied'] ) ) {
			echo '<p class="trdsp-hint">' . esc_html__( 'Times already on the calendar (no customer details):', 'trade-dispatch' ) . '</p><ul class="trdsp-busy">';
			foreach ( $window['occupied'] as $slot ) {
				echo '<li>' . esc_html( $slot['date'] . ' ' . $slot['time'] ) . '</li>';
			}
			echo '</ul>';
		}
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
		/*
		 * Public, unauthenticated form: a missing or expired nonce here is
		 * almost always a cached page or a timed-out form, not an attack, and
		 * no privileged action has run yet. Send the visitor back to the form
		 * with a friendly error rather than wp_die(). (The authenticated admin
		 * handlers use wp_die() instead, where a bad nonce signals tampering.)
		 */
		if ( ! isset( $_POST['trdsp_booking_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['trdsp_booking_nonce'] ) ), 'trdsp_submit_booking' ) ) {
			wp_safe_redirect( esc_url_raw( add_query_arg( 'trdsp_booked', 'error', $redirect ) ) );
			exit;
		}

		$honeypot = isset( $_POST['trdsp_website'] ) ? sanitize_text_field( wp_unslash( $_POST['trdsp_website'] ) ) : '';
		if ( '' !== $honeypot ) {
			wp_safe_redirect( esc_url_raw( add_query_arg( 'trdsp_booked', '1', $redirect ) ) );
			exit;
		}

		if ( self::is_rate_limited( 'ip', self::client_ip() ) ) {
			wp_safe_redirect( esc_url_raw( add_query_arg( 'trdsp_booked', 'limit', $redirect ) ) );
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

		if ( self::is_rate_limited( 'email', strtolower( $email ) ) ) {
			wp_safe_redirect( esc_url_raw( add_query_arg( 'trdsp_booked', 'limit', $redirect ) ) );
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
				'service_id'   => $service ? (int) $service['id'] : 0,
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

		$flag = '1';
		$when = isset( $_POST['trdsp_scheduled_at'] ) ? sanitize_text_field( wp_unslash( $_POST['trdsp_scheduled_at'] ) ) : '';
		if ( '' !== $when && self::is_outside_window( $when ) ) {
			$flag = 'window';
		}
		wp_safe_redirect( esc_url_raw( add_query_arg( 'trdsp_booked', $flag, $redirect ) ) );
		exit;
	}

	/**
	 * Booking window + occupied times for the public form.
	 *
	 * @return array<string,mixed>
	 */
	public static function window_config() {
		$settings = get_option( 'trdsp_settings', array() );
		$days     = isset( $settings['booking_days'] ) && is_array( $settings['booking_days'] ) ? array_map( 'absint', $settings['booking_days'] ) : array();
		$occupied = array();
		if ( class_exists( 'TRDSP_Jobs' ) ) {
			$occupied = TRDSP_Jobs::public_busy_times( gmdate( 'Y-m-d H:i:s' ), gmdate( 'Y-m-d H:i:s', time() + ( 14 * DAY_IN_SECONDS ) ) );
		}
		return array(
			'open'     => isset( $settings['booking_open'] ) ? (string) $settings['booking_open'] : '',
			'close'    => isset( $settings['booking_close'] ) ? (string) $settings['booking_close'] : '',
			'days'     => array_values( array_unique( $days ) ),
			'occupied' => $occupied,
		);
	}

	/**
	 * Whether a preferred datetime is outside posted hours or already busy.
	 *
	 * @param string $value Raw datetime.
	 * @return bool
	 */
	public static function is_outside_window( $value ) {
		$cfg = self::window_config();
		$ts  = strtotime( str_replace( 'T', ' ', (string) $value ) );
		if ( false === $ts ) {
			return false;
		}
		$day  = (int) wp_date( 'w', $ts );
		$hm   = wp_date( 'H:i', $ts );
		$date = wp_date( 'Y-m-d', $ts );
		if ( ! empty( $cfg['days'] ) && ! in_array( $day, $cfg['days'], true ) ) {
			return true;
		}
		if ( '' !== $cfg['open'] && $hm < $cfg['open'] ) {
			return true;
		}
		if ( '' !== $cfg['close'] && $hm > $cfg['close'] ) {
			return true;
		}
		foreach ( $cfg['occupied'] as $slot ) {
			if ( $slot['date'] === $date && $slot['time'] === $hm ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Suggested public times (not a reserved-slot engine).
	 *
	 * @param int $step Minutes between suggestions.
	 * @return array<int,array<string,string>>
	 */
	public static function suggested_slots( $step = 60 ) {
		$step = max( 30, min( 120, absint( $step ) ) );
		$cfg  = self::window_config();
		$open = '' !== $cfg['open'] ? $cfg['open'] : '08:00';
		$close = '' !== $cfg['close'] ? $cfg['close'] : '17:00';
		$busy  = array();
		foreach ( $cfg['occupied'] as $slot ) {
			$busy[ $slot['date'] . 'T' . $slot['time'] ] = true;
		}
		$tz  = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
		$now = new DateTimeImmutable( 'now', $tz );
		$out = array();
		for ( $d = 0; $d < 7 && count( $out ) < 18; $d++ ) {
			$day = $now->modify( '+' . $d . ' days' );
			$w   = (int) $day->format( 'w' );
			if ( ! empty( $cfg['days'] ) && ! in_array( $w, $cfg['days'], true ) ) {
				continue;
			}
			$start = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $day->format( 'Y-m-d' ) . ' ' . $open, $tz );
			$end   = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $day->format( 'Y-m-d' ) . ' ' . $close, $tz );
			if ( ! $start || ! $end ) {
				continue;
			}
			for ( $t = $start; $t <= $end && count( $out ) < 18; $t = $t->modify( '+' . $step . ' minutes' ) ) {
				if ( $t <= $now ) {
					continue;
				}
				$key = $t->format( 'Y-m-d' ) . 'T' . $t->format( 'H:i' );
				if ( isset( $busy[ $key ] ) ) {
					continue;
				}
				$out[] = array(
					'value' => $key,
					'label' => wp_date( 'D g:i a', $t->getTimestamp() ),
				);
			}
		}
		return $out;
	}

	/**
	 * Suggested days (fills the datetime with the open hour, or 9am).
	 *
	 * @param int $count Max days.
	 * @return array<int,array<string,string>>
	 */
	public static function suggested_days( $count = 7 ) {
		$count = max( 1, min( 14, absint( $count ) ) );
		$cfg   = self::window_config();
		$open  = '' !== $cfg['open'] ? $cfg['open'] : '09:00';
		$tz    = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
		$now   = new DateTimeImmutable( 'now', $tz );
		$out   = array();
		for ( $d = 0; $d < 21 && count( $out ) < $count; $d++ ) {
			$day = $now->modify( '+' . $d . ' days' );
			$w   = (int) $day->format( 'w' );
			if ( ! empty( $cfg['days'] ) && ! in_array( $w, $cfg['days'], true ) ) {
				continue;
			}
			$out[] = array(
				'value' => $day->format( 'Y-m-d' ) . 'T' . $open,
				'label' => wp_date( 'D, M j', $day->getTimestamp() ),
			);
		}
		return $out;
	}

	/**
	 * Time and day chips for a datetime-local input.
	 *
	 * @param string $input_id Input id.
	 * @param string $slots_id Optional wrap id for time chips.
	 */
	public static function render_suggested_chips( $input_id, $slots_id = '' ) {
		$input_id = sanitize_html_class( (string) $input_id );
		$slots    = self::suggested_slots();
		$days     = self::suggested_days();
		if ( ! empty( $slots ) ) {
			echo '<p class="trdsp-hint">' . esc_html__( 'Suggested times (you can still pick another). The office confirms the visit.', 'trade-dispatch' ) . '</p>';
			echo '<div';
			if ( '' !== $slots_id ) {
				echo ' id="' . esc_attr( sanitize_html_class( $slots_id ) ) . '"';
			}
			echo ' class="trdsp-slots" data-for="' . esc_attr( $input_id ) . '">';
			foreach ( $slots as $slot ) {
				echo '<button type="button" class="trdsp-slot" data-value="' . esc_attr( (string) $slot['value'] ) . '">' . esc_html( (string) $slot['label'] ) . '</button>';
			}
			echo '</div>';
		}
		if ( ! empty( $days ) ) {
			echo '<p class="trdsp-hint">' . esc_html__( 'Or pick a day (the office will confirm a time).', 'trade-dispatch' ) . '</p>';
			echo '<div class="trdsp-slots trdsp-slots-days" data-for="' . esc_attr( $input_id ) . '">';
			foreach ( $days as $day ) {
				echo '<button type="button" class="trdsp-slot" data-value="' . esc_attr( (string) $day['value'] ) . '">' . esc_html( (string) $day['label'] ) . '</button>';
			}
			echo '</div>';
		}
	}

	/**
	 * Create the portal account after the office confirms the booking (not on the public form).
	 *
	 * @param int                  $job_id Job ID.
	 * @param array<string,mixed> $job    Saved job row.
	 */
	public static function on_job_confirmed( $job_id, $job ) {
		unset( $job_id );
		$customer = class_exists( 'TRDSP_Customers' ) ? TRDSP_Customers::get( absint( $job['customer_id'] ?? 0 ) ) : null;
		if ( ! $customer ) {
			return;
		}
		self::maybe_create_portal_user( (string) $customer['name'], (string) $customer['email'] );
	}

	/**
	 * Client IP for booking rate limits (do not trust forwarded headers).
	 *
	 * @return string
	 */
	protected static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return '' !== $ip ? $ip : 'unknown';
	}

	/**
	 * Whether this IP or email has submitted too many public bookings.
	 *
	 * @param string $kind  ip|email.
	 * @param string $value IP or email.
	 * @return bool
	 */
	protected static function is_rate_limited( $kind, $value ) {
		$limits = array(
			'ip'    => array(
				'max'  => 5,
				'ttl'  => 15 * MINUTE_IN_SECONDS,
			),
			'email' => array(
				'max'  => 3,
				'ttl'  => HOUR_IN_SECONDS,
			),
		);
		if ( ! isset( $limits[ $kind ] ) ) {
			return false;
		}
		$key   = 'trdsp_book_' . $kind . '_' . md5( (string) $value );
		$count = (int) get_transient( $key );
		if ( $count >= (int) $limits[ $kind ]['max'] ) {
			return true;
		}
		set_transient( $key, $count + 1, (int) $limits[ $kind ]['ttl'] );
		return false;
	}

	/**
	 * Create a Customer-role account so they can open the portal.
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
				'role'         => 'trdsp_customer',
			)
		);
		if ( ! is_wp_error( $user_id ) ) {
			wp_new_user_notification( $user_id, null, 'user' );
		}
	}
}
