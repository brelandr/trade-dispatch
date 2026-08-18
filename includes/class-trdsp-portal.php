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

		ob_start();
		echo '<div class="trdsp-portal">';
		echo '<h2>' . esc_html__( 'Your visits', 'trade-dispatch' ) . '</h2>';
		echo '<p>' . esc_html( (string) $customer['name'] ) . '</p>';
		if ( empty( $jobs ) ) {
			echo '<p>' . esc_html__( 'No visits on file yet.', 'trade-dispatch' ) . '</p>';
		} else {
			echo '<table class="trdsp-table"><thead><tr>';
			echo '<th>' . esc_html__( 'When', 'trade-dispatch' ) . '</th>';
			echo '<th>' . esc_html__( 'Service', 'trade-dispatch' ) . '</th>';
			echo '<th>' . esc_html__( 'Status', 'trade-dispatch' ) . '</th>';
			echo '<th>' . esc_html__( 'Address', 'trade-dispatch' ) . '</th>';
			echo '</tr></thead><tbody>';
			$statuses = TRDSP_Jobs::statuses();
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
				echo '<td>' . esc_html( $addr ) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';
		return (string) ob_get_clean();
	}
}
