<?php
/**
 * Customer web portal shortcode.
 *
 * @package Trade_Dispatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [trdsp_portal] upcoming visits and history.
 */
class TRDSP_Portal {

	/**
	 * Register hooks.
	 */
	public static function hooks() {
		add_shortcode( 'trdsp_portal', array( __CLASS__, 'render' ) );
		add_action( 'admin_post_trdsp_portal_reschedule', array( __CLASS__, 'handle_reschedule' ) );
		add_action( 'admin_post_trdsp_portal_estimate_request', array( __CLASS__, 'handle_estimate_request' ) );
		add_action( 'admin_post_trdsp_portal_accept_estimate', array( __CLASS__, 'handle_accept_estimate' ) );
		add_action( 'admin_post_trdsp_portal_ics', array( __CLASS__, 'handle_ics' ) );
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
		if ( ! is_user_logged_in() ) {
			$login = wp_login_url( get_permalink() ? (string) get_permalink() : home_url( '/' ) );
			return '<div class="trdsp-portal"><p>' . esc_html__( 'Log in to view your scheduled visits and service history.', 'trade-dispatch' ) . '</p><p><a class="trdsp-submit" href="' . esc_url( $login ) . '">' . esc_html__( 'Log in', 'trade-dispatch' ) . '</a></p></div>';
		}

		$user     = wp_get_current_user();
		$customer = TRDSP_Customers::get_by_email( (string) $user->user_email );
		if ( ! $customer ) {
			return '<div class="trdsp-portal"><p>' . esc_html__( 'No customer record is linked to this account yet. Use the same email you booked with, or ask the office to add you.', 'trade-dispatch' ) . '</p></div>';
		}

		$jobs = TRDSP_Jobs::query(
			array(
				'customer_id' => (int) $customer['id'],
				'limit'       => 100,
			)
		);
		$open = array();
		$past = array();
		foreach ( $jobs as $job ) {
			if ( self::job_is_open( $job ) ) {
				$open[] = $job;
			} else {
				$past[] = $job;
			}
		}

		ob_start();
		echo '<div class="trdsp-portal">';
		self::render_notice();
		echo '<h2>' . esc_html__( 'Your visits', 'trade-dispatch' ) . '</h2>';
		echo '<p>' . esc_html( (string) $customer['name'] ) . '</p>';
		echo '<h3>' . esc_html__( 'Upcoming', 'trade-dispatch' ) . '</h3>';
		if ( empty( $open ) ) {
			echo '<p>' . esc_html__( 'No upcoming visits on file.', 'trade-dispatch' ) . '</p>';
		} else {
			self::render_jobs_table( $open, true );
		}
		echo '<h3>' . esc_html__( 'Past visits', 'trade-dispatch' ) . '</h3>';
		if ( empty( $past ) ) {
			echo '<p>' . esc_html__( 'No past visits on file yet.', 'trade-dispatch' ) . '</p>';
		} else {
			self::render_jobs_table( $past, false );
		}

		$estimates = TRDSP_Estimates::query(
			array(
				'customer_id' => (int) $customer['id'],
				'limit'       => 50,
			)
		);
		echo '<h2>' . esc_html__( 'Your estimates', 'trade-dispatch' ) . '</h2>';
		if ( empty( $estimates ) ) {
			echo '<p>' . esc_html__( 'No estimates on file yet.', 'trade-dispatch' ) . '</p>';
		} else {
			$est_statuses = TRDSP_Estimates::statuses();
			echo '<table class="trdsp-table"><thead><tr>';
			echo '<th>' . esc_html__( 'Title', 'trade-dispatch' ) . '</th>';
			echo '<th>' . esc_html__( 'Amount', 'trade-dispatch' ) . '</th>';
			echo '<th>' . esc_html__( 'Status', 'trade-dispatch' ) . '</th>';
			echo '<th>' . esc_html__( 'Related job', 'trade-dispatch' ) . '</th>';
			echo '<th>' . esc_html__( 'Next step', 'trade-dispatch' ) . '</th>';
			echo '</tr></thead><tbody>';
			foreach ( $estimates as $estimate ) {
				$status = isset( $est_statuses[ $estimate['status'] ] ) ? $est_statuses[ $estimate['status'] ] : (string) $estimate['status'];
				$linked = '';
				if ( (int) ( $estimate['job_id'] ?? 0 ) > 0 ) {
					$linked_job = TRDSP_Jobs::get( (int) $estimate['job_id'] );
					$linked     = $linked_job ? (string) $linked_job['title'] : '';
				}
				echo '<tr>';
				echo '<td>' . esc_html( (string) $estimate['title'] ) . '</td>';
				echo '<td>' . esc_html( number_format_i18n( (float) $estimate['amount'], 2 ) ) . '</td>';
				echo '<td>' . esc_html( $status ) . '</td>';
				echo '<td>' . esc_html( '' !== $linked ? $linked : '—' ) . '</td>';
				echo '<td>';
				if ( 'sent' === (string) $estimate['status'] ) {
					self::render_estimate_accept_form( $estimate );
					self::render_estimate_request_form( $estimate );
				} elseif ( 'accepted' === (string) $estimate['status'] ) {
					echo esc_html__( 'Accepted — the office will schedule.', 'trade-dispatch' );
				} else {
					echo '&mdash;';
				}
				echo '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}

		/**
		 * After portal estimates (Pro invoices, pay later, etc.).
		 *
		 * @param array<string,mixed> $customer Customer row.
		 */
		do_action( 'trdsp_portal_after_estimates', $customer );

		echo '</div>';
		return (string) ob_get_clean();
	}

	/**
	 * Portal flash notice after a reschedule request.
	 */
	protected static function render_notice() {
		if ( ! isset( $_GET['trdsp_portal'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display flag only.
			return;
		}
		$flag = sanitize_key( wp_unslash( $_GET['trdsp_portal'] ) );
		if ( 'rescheduled' === $flag ) {
			echo '<p class="trdsp-notice trdsp-notice-ok">' . esc_html__( 'Your reschedule request was sent to the office. They will confirm a new time.', 'trade-dispatch' ) . '</p>';
		} elseif ( 'estimate_requested' === $flag ) {
			echo '<p class="trdsp-notice trdsp-notice-ok">' . esc_html__( 'The office received your request to schedule this estimate.', 'trade-dispatch' ) . '</p>';
		} elseif ( 'estimate_accepted' === $flag ) {
			echo '<p class="trdsp-notice trdsp-notice-ok">' . esc_html__( 'You accepted this estimate. It is not a payment — the office will follow up.', 'trade-dispatch' ) . '</p>';
		} elseif ( 'error' === $flag ) {
			echo '<p class="trdsp-notice trdsp-notice-err">' . esc_html__( 'Could not send that request. Try again or contact the office.', 'trade-dispatch' ) . '</p>';
		}
	}

	/**
	 * Jobs table, optionally with a reschedule form.
	 *
	 * @param array<int,array<string,mixed>> $jobs   Jobs.
	 * @param bool                           $open   Whether these are still open.
	 */
	protected static function render_jobs_table( $jobs, $open ) {
		$statuses = TRDSP_Jobs::statuses();
		echo '<table class="trdsp-table"><thead><tr>';
		echo '<th>' . esc_html__( 'When', 'trade-dispatch' ) . '</th>';
		echo '<th>' . esc_html__( 'Service', 'trade-dispatch' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'trade-dispatch' ) . '</th>';
		echo '<th>' . esc_html__( 'Address', 'trade-dispatch' ) . '</th>';
		if ( $open ) {
			echo '<th>' . esc_html__( 'Request', 'trade-dispatch' ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $jobs as $job ) {
			$when = '';
			if ( ! empty( $job['scheduled_at'] ) ) {
				$when = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( (string) $job['scheduled_at'] ) );
			}
			$addr   = trim( implode( ', ', array_filter( array( $job['address_1'], $job['city'], $job['state'], $job['postcode'] ) ) ) );
			$status = isset( $statuses[ $job['status'] ] ) ? $statuses[ $job['status'] ] : (string) $job['status'];
			echo '<tr>';
			echo '<td>' . esc_html( $when ) . '</td>';
			echo '<td>' . esc_html( (string) $job['title'] ) . '</td>';
			echo '<td>' . esc_html( $status ) . '</td>';
			echo '<td>';
			if ( $open ) {
				self::render_visit_details( $job, $addr );
			} else {
				echo esc_html( $addr );
			}
			echo '</td>';
			if ( $open ) {
				echo '<td>';
				self::render_reschedule_form( $job );
				echo '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Address plus gate/hazard notes and calendar download.
	 *
	 * @param array<string,mixed> $job  Job row.
	 * @param string              $addr Formatted address.
	 */
	protected static function render_visit_details( $job, $addr ) {
		echo '<details class="trdsp-visit">';
		echo '<summary>' . esc_html( '' !== $addr ? $addr : __( 'Visit details', 'trade-dispatch' ) ) . '</summary>';
		if ( ! empty( $job['gate_notes'] ) ) {
			echo '<p>' . esc_html__( 'Gate / access', 'trade-dispatch' ) . ': ' . esc_html( (string) $job['gate_notes'] ) . '</p>';
		}
		if ( ! empty( $job['hazard_notes'] ) ) {
			echo '<p>' . esc_html__( 'Hazards', 'trade-dispatch' ) . ': ' . esc_html( (string) $job['hazard_notes'] ) . '</p>';
		}
		if ( ! empty( $job['scheduled_at'] ) ) {
			$ics = wp_nonce_url(
				add_query_arg(
					array(
						'action' => 'trdsp_portal_ics',
						'job_id' => (int) $job['id'],
					),
					admin_url( 'admin-post.php' )
				),
				'trdsp_portal_ics_' . (int) $job['id']
			);
			echo '<p><a href="' . esc_url( $ics ) . '">' . esc_html__( 'Add to calendar', 'trade-dispatch' ) . '</a></p>';
		}
		echo '</details>';
	}

	/**
	 * Accept a sent estimate (not a charge).
	 *
	 * @param array<string,mixed> $estimate Estimate row.
	 */
	protected static function render_estimate_accept_form( $estimate ) {
		$redirect = get_permalink() ? (string) get_permalink() : home_url( '/' );
		echo '<form class="trdsp-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-bottom:8px;">';
		echo '<input type="hidden" name="action" value="trdsp_portal_accept_estimate" />';
		echo '<input type="hidden" name="estimate_id" value="' . esc_attr( (string) (int) $estimate['id'] ) . '" />';
		echo '<input type="hidden" name="trdsp_redirect" value="' . esc_url( $redirect ) . '" />';
		wp_nonce_field( 'trdsp_portal_accept_estimate_' . (int) $estimate['id'], 'trdsp_portal_accept_estimate_nonce' );
		echo '<button type="submit" class="trdsp-submit">' . esc_html__( 'Accept estimate', 'trade-dispatch' ) . '</button>';
		echo '</form>';
	}

	/**
	 * Ask the office to schedule a sent estimate.
	 *
	 * @param array<string,mixed> $estimate Estimate row.
	 */
	protected static function render_estimate_request_form( $estimate ) {
		$redirect = get_permalink() ? (string) get_permalink() : home_url( '/' );
		echo '<form class="trdsp-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="trdsp_portal_estimate_request" />';
		echo '<input type="hidden" name="estimate_id" value="' . esc_attr( (string) (int) $estimate['id'] ) . '" />';
		echo '<input type="hidden" name="trdsp_redirect" value="' . esc_url( $redirect ) . '" />';
		wp_nonce_field( 'trdsp_portal_estimate_request_' . (int) $estimate['id'], 'trdsp_portal_estimate_request_nonce' );
		echo '<button type="submit" class="trdsp-submit">' . esc_html__( 'I\'d like to schedule this', 'trade-dispatch' ) . '</button>';
		echo '</form>';
	}

	/**
	 * Reschedule request form for one open job.
	 *
	 * @param array<string,mixed> $job Job row.
	 */
	protected static function render_reschedule_form( $job ) {
		$redirect = get_permalink() ? (string) get_permalink() : home_url( '/' );
		echo '<details class="trdsp-reschedule">';
		echo '<summary>' . esc_html__( 'Request a new time', 'trade-dispatch' ) . '</summary>';
		echo '<form class="trdsp-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="trdsp_portal_reschedule" />';
		echo '<input type="hidden" name="job_id" value="' . esc_attr( (string) (int) $job['id'] ) . '" />';
		echo '<input type="hidden" name="trdsp_redirect" value="' . esc_url( $redirect ) . '" />';
		wp_nonce_field( 'trdsp_portal_reschedule_' . (int) $job['id'], 'trdsp_portal_reschedule_nonce' );
		echo '<p><label for="trdsp_pref_' . esc_attr( (string) (int) $job['id'] ) . '">' . esc_html__( 'Preferred date and time', 'trade-dispatch' ) . '</label> ';
		echo '<input id="trdsp_pref_' . esc_attr( (string) (int) $job['id'] ) . '" name="trdsp_preferred_at" type="datetime-local" /></p>';
		echo '<p><label for="trdsp_rmsg_' . esc_attr( (string) (int) $job['id'] ) . '">' . esc_html__( 'Note for the office', 'trade-dispatch' ) . '</label> ';
		echo '<textarea id="trdsp_rmsg_' . esc_attr( (string) (int) $job['id'] ) . '" name="trdsp_message" rows="3"></textarea></p>';
		echo '<p><button type="submit" class="trdsp-submit">' . esc_html__( 'Send request', 'trade-dispatch' ) . '</button></p>';
		echo '</form></details>';
	}

	/**
	 * Open jobs can still be rescheduled.
	 *
	 * @param array<string,mixed> $job Job row.
	 * @return bool
	 */
	protected static function job_is_open( $job ) {
		$status = (string) ( $job['status'] ?? '' );
		return ( 'completed' !== $status && 'cancelled' !== $status );
	}

	/**
	 * Customer asked for a different visit time.
	 */
	public static function handle_reschedule() {
		$id = isset( $_POST['job_id'] ) ? absint( wp_unslash( $_POST['job_id'] ) ) : 0;
		if ( ! isset( $_POST['trdsp_portal_reschedule_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['trdsp_portal_reschedule_nonce'] ) ), 'trdsp_portal_reschedule_' . $id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'trade-dispatch' ) );
		}
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Unauthorized.', 'trade-dispatch' ) );
		}
		$redirect = isset( $_POST['trdsp_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['trdsp_redirect'] ) ) : home_url( '/' );
		$safe     = wp_validate_redirect( $redirect, home_url( '/' ) );
		$job      = TRDSP_Jobs::get( $id );
		$user     = wp_get_current_user();
		$customer = TRDSP_Customers::get_by_email( (string) $user->user_email );
		if ( ! $job || ! $customer || (int) $job['customer_id'] !== (int) $customer['id'] || ! self::job_is_open( $job ) ) {
			wp_safe_redirect( esc_url_raw( add_query_arg( 'trdsp_portal', 'error', $safe ) ) );
			exit;
		}
		$preferred = isset( $_POST['trdsp_preferred_at'] ) ? sanitize_text_field( wp_unslash( $_POST['trdsp_preferred_at'] ) ) : '';
		$message   = isset( $_POST['trdsp_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['trdsp_message'] ) ) : '';
		$lines     = array( __( 'Customer requested a new visit time from the portal.', 'trade-dispatch' ) );
		if ( '' !== $preferred ) {
			$lines[] = __( 'Preferred', 'trade-dispatch' ) . ': ' . $preferred;
		}
		if ( '' !== $message ) {
			$lines[] = $message;
		}
		$note = TRDSP_Notes::add( $id, implode( "\n", $lines ), get_current_user_id() );
		if ( is_wp_error( $note ) ) {
			wp_safe_redirect( esc_url_raw( add_query_arg( 'trdsp_portal', 'error', $safe ) ) );
			exit;
		}
		/**
		 * After a portal customer requests a reschedule.
		 *
		 * @param int                  $id        Job ID.
		 * @param array<string,mixed> $job       Job row.
		 * @param string               $preferred Preferred datetime string.
		 * @param string               $message   Optional customer note.
		 */
		do_action( 'trdsp_portal_reschedule_requested', $id, $job, $preferred, $message );
		wp_safe_redirect( esc_url_raw( add_query_arg( 'trdsp_portal', 'rescheduled', $safe ) ) );
		exit;
	}

	/**
	 * Customer asked the office to schedule a sent estimate.
	 */
	public static function handle_estimate_request() {
		$id = isset( $_POST['estimate_id'] ) ? absint( wp_unslash( $_POST['estimate_id'] ) ) : 0;
		if ( ! isset( $_POST['trdsp_portal_estimate_request_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['trdsp_portal_estimate_request_nonce'] ) ), 'trdsp_portal_estimate_request_' . $id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'trade-dispatch' ) );
		}
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Unauthorized.', 'trade-dispatch' ) );
		}
		$redirect = isset( $_POST['trdsp_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['trdsp_redirect'] ) ) : home_url( '/' );
		$safe     = wp_validate_redirect( $redirect, home_url( '/' ) );
		$estimate = TRDSP_Estimates::get( $id );
		$user     = wp_get_current_user();
		$customer = TRDSP_Customers::get_by_email( (string) $user->user_email );
		if ( ! $estimate || ! $customer || (int) $estimate['customer_id'] !== (int) $customer['id'] || 'sent' !== (string) $estimate['status'] ) {
			wp_safe_redirect( esc_url_raw( add_query_arg( 'trdsp_portal', 'error', $safe ) ) );
			exit;
		}
		/**
		 * After a portal customer asks to schedule a sent estimate.
		 *
		 * @param int                  $id       Estimate ID.
		 * @param array<string,mixed> $estimate Estimate row.
		 */
		do_action( 'trdsp_portal_estimate_requested', $id, $estimate );
		wp_safe_redirect( esc_url_raw( add_query_arg( 'trdsp_portal', 'estimate_requested', $safe ) ) );
		exit;
	}

	/**
	 * Customer accepted a sent estimate (not a payment).
	 */
	public static function handle_accept_estimate() {
		$id = isset( $_POST['estimate_id'] ) ? absint( wp_unslash( $_POST['estimate_id'] ) ) : 0;
		if ( ! isset( $_POST['trdsp_portal_accept_estimate_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['trdsp_portal_accept_estimate_nonce'] ) ), 'trdsp_portal_accept_estimate_' . $id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'trade-dispatch' ) );
		}
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Unauthorized.', 'trade-dispatch' ) );
		}
		$redirect = isset( $_POST['trdsp_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['trdsp_redirect'] ) ) : home_url( '/' );
		$safe     = wp_validate_redirect( $redirect, home_url( '/' ) );
		$estimate = TRDSP_Estimates::get( $id );
		$user     = wp_get_current_user();
		$customer = TRDSP_Customers::get_by_email( (string) $user->user_email );
		if ( ! $estimate || ! $customer || (int) $estimate['customer_id'] !== (int) $customer['id'] || 'sent' !== (string) $estimate['status'] ) {
			wp_safe_redirect( esc_url_raw( add_query_arg( 'trdsp_portal', 'error', $safe ) ) );
			exit;
		}
		$saved = TRDSP_Estimates::save(
			array(
				'id'          => $id,
				'customer_id' => (int) $estimate['customer_id'],
				'job_id'      => (int) ( $estimate['job_id'] ?? 0 ),
				'title'       => (string) $estimate['title'],
				'amount'      => (float) $estimate['amount'],
				'status'      => 'accepted',
			)
		);
		if ( is_wp_error( $saved ) ) {
			wp_safe_redirect( esc_url_raw( add_query_arg( 'trdsp_portal', 'error', $safe ) ) );
			exit;
		}
		$fresh = TRDSP_Estimates::get( $id );
		/**
		 * After a portal customer accepts an estimate (not a charge).
		 *
		 * @param int                  $id       Estimate ID.
		 * @param array<string,mixed> $estimate Estimate row.
		 */
		do_action( 'trdsp_estimate_accepted', $id, $fresh ? $fresh : $estimate );
		wp_safe_redirect( esc_url_raw( add_query_arg( 'trdsp_portal', 'estimate_accepted', $safe ) ) );
		exit;
	}

	/**
	 * Single-event calendar file for a visit the customer owns.
	 */
	public static function handle_ics() {
		$id = isset( $_GET['job_id'] ) ? absint( wp_unslash( $_GET['job_id'] ) ) : 0;
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'trdsp_portal_ics_' . $id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'trade-dispatch' ) );
		}
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Unauthorized.', 'trade-dispatch' ) );
		}
		$job      = TRDSP_Jobs::get( $id );
		$user     = wp_get_current_user();
		$customer = TRDSP_Customers::get_by_email( (string) $user->user_email );
		if ( ! $job || ! $customer || (int) $job['customer_id'] !== (int) $customer['id'] || empty( $job['scheduled_at'] ) ) {
			wp_die( esc_html__( 'Visit not found.', 'trade-dispatch' ) );
		}
		$start = strtotime( (string) $job['scheduled_at'] );
		if ( ! $start ) {
			wp_die( esc_html__( 'Visit not found.', 'trade-dispatch' ) );
		}
		$addr = trim( implode( ', ', array_filter( array( $job['address_1'], $job['city'], $job['state'], $job['postcode'] ) ) ) );
		$desc = array();
		if ( ! empty( $job['gate_notes'] ) ) {
			$desc[] = __( 'Gate / access', 'trade-dispatch' ) . ': ' . (string) $job['gate_notes'];
		}
		if ( ! empty( $job['hazard_notes'] ) ) {
			$desc[] = __( 'Hazards', 'trade-dispatch' ) . ': ' . (string) $job['hazard_notes'];
		}
		$host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			$host = 'tradedispatch.local';
		}
		$ics  = "BEGIN:VCALENDAR\r\n";
		$ics .= "VERSION:2.0\r\n";
		$ics .= "PRODID:-//Trade Dispatch//EN\r\n";
		$ics .= "CALSCALE:GREGORIAN\r\n";
		$ics .= "METHOD:PUBLISH\r\n";
		$ics .= "BEGIN:VEVENT\r\n";
		$ics .= 'UID:trdsp-job-' . absint( $id ) . '@' . $host . "\r\n";
		$ics .= 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ) . "\r\n";
		$ics .= 'DTSTART:' . gmdate( 'Ymd\THis\Z', $start ) . "\r\n";
		$ics .= 'DTEND:' . gmdate( 'Ymd\THis\Z', $start + ( 2 * HOUR_IN_SECONDS ) ) . "\r\n";
		$ics .= 'SUMMARY:' . self::ics_text( (string) $job['title'] ) . "\r\n";
		if ( '' !== $addr ) {
			$ics .= 'LOCATION:' . self::ics_text( $addr ) . "\r\n";
		}
		if ( ! empty( $desc ) ) {
			$ics .= 'DESCRIPTION:' . self::ics_text( implode( "\n", $desc ) ) . "\r\n";
		}
		$ics .= "END:VEVENT\r\n";
		$ics .= "END:VCALENDAR\r\n";
		$filename = sanitize_file_name( 'trade-dispatch-visit-' . absint( $id ) . '.ics' );
		nocache_headers();
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		echo wp_kses( $ics, array() );
		exit;
	}

	/**
	 * Escape text for a single ICS property value.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	protected static function ics_text( $text ) {
		$text = str_replace( array( '\\', ';', ',', "\r\n", "\n", "\r" ), array( '\\\\', '\\;', '\\,', '\\n', '\\n', '' ), $text );
		return $text;
	}
}
