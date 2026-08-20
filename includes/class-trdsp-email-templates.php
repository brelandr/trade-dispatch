<?php
/**
 * Editable plain-text email templates.
 *
 * @package Trade_Dispatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings screen and placeholder renderer for Trade Dispatch mail.
 */
class TRDSP_Email_Templates {

	const OPTION = 'trdsp_email_templates';

	/**
	 * Register hooks.
	 */
	public static function hooks() {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, array(), '', 'no' );
		}
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 32 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_trdsp_test_email_template', array( __CLASS__, 'handle_test' ) );
		add_action( 'admin_post_trdsp_restore_email_template', array( __CLASS__, 'handle_restore' ) );
		add_filter( 'option_page_capability_trdsp_email_templates_group', array( __CLASS__, 'settings_capability' ) );
	}

	/**
	 * options.php capability.
	 *
	 * @return string
	 */
	public static function settings_capability() {
		return 'trdsp_manage_settings';
	}

	/**
	 * Emails submenu.
	 */
	public static function register_menu() {
		add_submenu_page(
			'trade-dispatch',
			__( 'Emails', 'trade-dispatch' ),
			__( 'Emails', 'trade-dispatch' ),
			'trdsp_manage_settings',
			'trade-dispatch-emails',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Register the templates option.
	 */
	public static function register_settings() {
		register_setting(
			'trdsp_email_templates_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Template keys and UI labels (labels translated at call time).
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function schema() {
		return array(
			'booked_customer'                    => array(
				'group' => 'customer',
				'label' => __( 'Booking received (customer)', 'trade-dispatch' ),
			),
			'booked_office'                      => array(
				'group' => 'office',
				'label' => __( 'Booking received (office)', 'trade-dispatch' ),
			),
			'confirmed_customer'                 => array(
				'group' => 'customer',
				'label' => __( 'Visit confirmed (customer)', 'trade-dispatch' ),
			),
			'confirmed_office'                   => array(
				'group' => 'office',
				'label' => __( 'Visit confirmed (office)', 'trade-dispatch' ),
			),
			'completed_customer'                 => array(
				'group' => 'customer',
				'label' => __( 'Job complete (customer)', 'trade-dispatch' ),
			),
			'completed_office'                   => array(
				'group' => 'office',
				'label' => __( 'Job complete (office)', 'trade-dispatch' ),
			),
			'preferred_applied_customer'         => array(
				'group' => 'customer',
				'label' => __( 'Requested time applied (customer)', 'trade-dispatch' ),
			),
			'preferred_applied_office'           => array(
				'group' => 'office',
				'label' => __( 'Requested time applied (office)', 'trade-dispatch' ),
			),
			'preferred_declined_customer'        => array(
				'group' => 'customer',
				'label' => __( 'Requested time declined (customer)', 'trade-dispatch' ),
			),
			'preferred_declined_office'          => array(
				'group' => 'office',
				'label' => __( 'Requested time declined (office)', 'trade-dispatch' ),
			),
			'booking_declined_customer'          => array(
				'group' => 'customer',
				'label' => __( 'Booking declined (customer)', 'trade-dispatch' ),
			),
			'booking_declined_office'            => array(
				'group' => 'office',
				'label' => __( 'Booking declined (office)', 'trade-dispatch' ),
			),
			'estimate_sent_customer'             => array(
				'group' => 'customer',
				'label' => __( 'Estimate emailed (customer)', 'trade-dispatch' ),
			),
			'estimate_sent_office'               => array(
				'group' => 'office',
				'label' => __( 'Estimate emailed (office)', 'trade-dispatch' ),
			),
			'estimate_reminded_customer'         => array(
				'group' => 'customer',
				'label' => __( 'Estimate reminder (customer)', 'trade-dispatch' ),
			),
			'estimate_reminded_office'           => array(
				'group' => 'office',
				'label' => __( 'Estimate reminder (office)', 'trade-dispatch' ),
			),
			'estimate_request_approved_customer' => array(
				'group' => 'customer',
				'label' => __( 'Estimate schedule approved (customer)', 'trade-dispatch' ),
			),
			'estimate_request_approved_office'   => array(
				'group' => 'office',
				'label' => __( 'Estimate schedule approved (office)', 'trade-dispatch' ),
			),
			'estimate_request_declined_customer' => array(
				'group' => 'customer',
				'label' => __( 'Estimate schedule declined (customer)', 'trade-dispatch' ),
			),
			'estimate_request_declined_office'   => array(
				'group' => 'office',
				'label' => __( 'Estimate schedule declined (office)', 'trade-dispatch' ),
			),
			'reschedule_requested_office'        => array(
				'group' => 'office',
				'label' => __( 'Portal time request (office)', 'trade-dispatch' ),
			),
			'estimate_requested_office'          => array(
				'group' => 'office',
				'label' => __( 'Portal estimate schedule request (office)', 'trade-dispatch' ),
			),
			'estimate_accepted_office'           => array(
				'group' => 'office',
				'label' => __( 'Estimate accepted (office)', 'trade-dispatch' ),
			),
			'assigned_crew'                      => array(
				'group' => 'crew',
				'label' => __( 'Job assigned (crew)', 'trade-dispatch' ),
			),
			'assigned_office'                    => array(
				'group' => 'office',
				'label' => __( 'Job assigned (office)', 'trade-dispatch' ),
			),
			'crew_tomorrow_digest'               => array(
				'group' => 'crew',
				'label' => __( 'Tomorrow job list (crew)', 'trade-dispatch' ),
			),
		);
	}

	/**
	 * Default subject and body for a key (translated at send time).
	 *
	 * @param string $key Template key.
	 * @return array{subject:string,body:string}
	 */
	public static function default_for( $key ) {
		$job_block = "{intro}\n\n" . __( 'Job', 'trade-dispatch' ) . ": {job_title}\n" . __( 'Status', 'trade-dispatch' ) . ": {status}\n" . __( 'Scheduled', 'trade-dispatch' ) . ": {scheduled}\n" . __( 'Address', 'trade-dispatch' ) . ": {address}\n" . __( 'Customer', 'trade-dispatch' ) . ": {customer_name}\n{office_note}\n{portal_line}\n{photos_line}\n{invoice_line}\n{time_line}\n{quotes_line}\n{parts_line}\n{checklist_line}\n{here_line}\n\n{company_name}";
		$est_block = "{intro}\n\n" . __( 'Estimate', 'trade-dispatch' ) . ": {estimate_title}\n" . __( 'Amount', 'trade-dispatch' ) . ": {amount}\n" . __( 'Customer', 'trade-dispatch' ) . ": {customer_name}\n{office_note}\n{portal_line}\n\n{company_name}";
		$map       = array(
			'booked_customer'                    => array(
				'subject' => __( 'Booking received: {job_title}', 'trade-dispatch' ),
				'body'    => str_replace( '{intro}', __( 'We received your service request. The office will confirm a time shortly.', 'trade-dispatch' ), $job_block ),
			),
			'booked_office'                      => array(
				'subject' => __( 'Booking received: {job_title}', 'trade-dispatch' ),
				'body'    => str_replace( '{intro}', __( 'A new booking was submitted on the website.', 'trade-dispatch' ), $job_block ),
			),
			'confirmed_customer'                 => array(
				'subject' => __( 'Visit confirmed: {job_title}', 'trade-dispatch' ),
				'body'    => str_replace( '{intro}', __( 'Your visit is confirmed. See you then. If this is your first visit, check your email for a customer portal login.', 'trade-dispatch' ), $job_block ),
			),
			'confirmed_office'                   => array(
				'subject' => __( 'Visit confirmed: {job_title}', 'trade-dispatch' ),
				'body'    => str_replace( '{intro}', __( 'Your visit is confirmed. See you then.', 'trade-dispatch' ), $job_block ),
			),
			'completed_customer'                 => array(
				'subject' => __( 'Job complete: {job_title}', 'trade-dispatch' ),
				'body'    => str_replace( '{intro}', __( 'The scheduled service has been marked complete.', 'trade-dispatch' ), $job_block ),
			),
			'completed_office'                   => array(
				'subject' => __( 'Job complete: {job_title}', 'trade-dispatch' ),
				'body'    => str_replace( '{intro}', __( 'The scheduled service has been marked complete.', 'trade-dispatch' ), $job_block ),
			),
			'preferred_applied_customer'         => array(
				'subject' => __( 'Visit time updated: {job_title}', 'trade-dispatch' ),
				'body'    => str_replace( '{intro}', __( 'The office set your visit to the time you requested.', 'trade-dispatch' ), $job_block ),
			),
			'preferred_applied_office'           => array(
				'subject' => __( 'Visit time updated: {job_title}', 'trade-dispatch' ),
				'body'    => str_replace( '{intro}', __( 'The office set your visit to the time you requested.', 'trade-dispatch' ), $job_block ),
			),
			'preferred_declined_customer'        => array(
				'subject' => __( 'Visit time not available: {job_title}', 'trade-dispatch' ),
				'body'    => str_replace( '{intro}', __( 'The office could not use the time you requested. Your current visit time is unchanged.', 'trade-dispatch' ), $job_block ),
			),
			'preferred_declined_office'          => array(
				'subject' => __( 'Visit time declined: {job_title}', 'trade-dispatch' ),
				'body'    => str_replace( '{intro}', __( 'A portal time request was declined. The scheduled time was not changed.', 'trade-dispatch' ), $job_block ),
			),
			'booking_declined_customer'          => array(
				'subject' => __( 'Booking not confirmed: {job_title}', 'trade-dispatch' ),
				'body'    => str_replace( '{intro}', __( 'The office could not confirm this booking request.', 'trade-dispatch' ), $job_block ),
			),
			'booking_declined_office'            => array(
				'subject' => __( 'Booking declined: {job_title}', 'trade-dispatch' ),
				'body'    => str_replace( '{intro}', __( 'A booking request was declined and the job was cancelled.', 'trade-dispatch' ), $job_block ),
			),
			'estimate_sent_customer'             => array(
				'subject' => __( 'Estimate: {estimate_title}', 'trade-dispatch' ),
				'body'    => str_replace( '{intro}', __( 'Here is your estimate. This is a quote only and is not a charge.', 'trade-dispatch' ), $est_block ),
			),
			'estimate_sent_office'               => array(
				'subject' => __( 'Estimate: {estimate_title}', 'trade-dispatch' ),
				'body'    => str_replace( '{intro}', __( 'Here is your estimate. This is a quote only and is not a charge.', 'trade-dispatch' ), $est_block ),
			),
			'estimate_reminded_customer'         => array(
				'subject' => __( 'Reminder: estimate {estimate_title}', 'trade-dispatch' ),
				'body'    => str_replace( '{intro}', __( 'This is a reminder of your estimate. This is a quote only and is not a charge.', 'trade-dispatch' ), $est_block ),
			),
			'estimate_reminded_office'           => array(
				'subject' => __( 'Reminder: estimate {estimate_title}', 'trade-dispatch' ),
				'body'    => str_replace( '{intro}', __( 'This is a reminder of your estimate. This is a quote only and is not a charge.', 'trade-dispatch' ), $est_block ),
			),
			'estimate_request_approved_customer' => array(
				'subject' => __( 'We will schedule: {estimate_title}', 'trade-dispatch' ),
				'body'    => str_replace( '{intro}', __( 'The office received your request to schedule this estimate and will follow up.', 'trade-dispatch' ), $est_block ),
			),
			'estimate_request_approved_office'   => array(
				'subject' => __( 'Estimate schedule approved: {estimate_title}', 'trade-dispatch' ),
				'body'    => str_replace( '{intro}', __( 'An estimate schedule request was approved. Create a job from the estimate if needed.', 'trade-dispatch' ), $est_block ),
			),
			'estimate_request_declined_customer' => array(
				'subject' => __( 'Could not schedule: {estimate_title}', 'trade-dispatch' ),
				'body'    => str_replace( '{intro}', __( 'The office could not schedule this estimate at this time.', 'trade-dispatch' ), $est_block ),
			),
			'estimate_request_declined_office'   => array(
				'subject' => __( 'Estimate schedule declined: {estimate_title}', 'trade-dispatch' ),
				'body'    => str_replace( '{intro}', __( 'An estimate schedule request was declined.', 'trade-dispatch' ), $est_block ),
			),
			'reschedule_requested_office'        => array(
				'subject' => __( 'Reschedule requested: {job_title}', 'trade-dispatch' ),
				'body'    => str_replace( '{intro}', __( 'A customer requested a new visit time from the portal.', 'trade-dispatch' ) . ' ' . __( 'Preferred', 'trade-dispatch' ) . ': {preferred_time}' . "\n{customer_message}", $job_block ),
			),
			'estimate_requested_office'          => array(
				'subject' => __( 'Customer wants to schedule: {estimate_title}', 'trade-dispatch' ),
				'body'    => str_replace( '{intro}', __( 'A customer asked to schedule this estimate from the portal. This is not a payment.', 'trade-dispatch' ) . "\n{customer_message}", $est_block ),
			),
			'estimate_accepted_office'           => array(
				'subject' => __( 'Estimate accepted: {estimate_title}', 'trade-dispatch' ),
				'body'    => str_replace( '{intro}', __( 'A customer accepted this estimate from the portal. This is not a payment.', 'trade-dispatch' ), $est_block ),
			),
			'assigned_crew'                      => array(
				'subject' => __( 'Job assigned: {job_title}', 'trade-dispatch' ),
				'body'    => str_replace( '{intro}', __( 'A job was assigned to you in Trade Dispatch.', 'trade-dispatch' ), str_replace( '{quotes_line}', "{quotes_line}\n{office_brief}", $job_block ) ),
			),
			'assigned_office'                    => array(
				'subject' => __( 'Job assigned: {job_title}', 'trade-dispatch' ),
				'body'    => str_replace( '{intro}', __( 'A job was assigned to you in Trade Dispatch.', 'trade-dispatch' ), str_replace( '{quotes_line}', "{quotes_line}\n{office_brief}", $job_block ) ),
			),
			'crew_tomorrow_digest'               => array(
				'subject' => __( 'Tomorrow\'s jobs — {digest_date}', 'trade-dispatch' ),
				'body'    => __( 'You have jobs scheduled tomorrow.', 'trade-dispatch' ) . "\n\n{job_list}\n{company_name}",
			),
		);
		if ( isset( $map[ $key ] ) ) {
			return $map[ $key ];
		}
		return array(
			'subject' => '',
			'body'    => '',
		);
	}

	/**
	 * Sanitize saved templates.
	 *
	 * @param mixed $input Raw.
	 * @return array<string,array<string,string>>
	 */
	public static function sanitize( $input ) {
		$clean = array();
		if ( ! is_array( $input ) ) {
			return $clean;
		}
		foreach ( array_keys( self::schema() ) as $key ) {
			$subject = isset( $input[ $key ]['subject'] ) ? sanitize_text_field( wp_unslash( $input[ $key ]['subject'] ) ) : '';
			$body    = isset( $input[ $key ]['body'] ) ? sanitize_textarea_field( wp_unslash( $input[ $key ]['body'] ) ) : '';
			if ( '' === $subject && '' === $body ) {
				continue;
			}
			$clean[ $key ] = array(
				'subject' => $subject,
				'body'    => $body,
			);
		}
		return $clean;
	}

	/**
	 * Render a template with placeholders replaced.
	 *
	 * @param string               $key  Template key.
	 * @param array<string,string> $vars Placeholder values.
	 * @return array{subject:string,body:string}
	 */
	public static function render( $key, $vars ) {
		$key      = sanitize_key( $key );
		$saved    = get_option( self::OPTION, array() );
		$defaults = self::default_for( $key );
		$subject  = $defaults['subject'];
		$body     = $defaults['body'];
		if ( is_array( $saved ) && isset( $saved[ $key ] ) && is_array( $saved[ $key ] ) ) {
			if ( ! empty( $saved[ $key ]['subject'] ) ) {
				$subject = (string) $saved[ $key ]['subject'];
			}
			if ( ! empty( $saved[ $key ]['body'] ) ) {
				$body = (string) $saved[ $key ]['body'];
			}
		}
		$pairs = array();
		foreach ( $vars as $name => $value ) {
			$pairs[ '{' . sanitize_key( (string) $name ) . '}' ] = (string) $value;
		}
		$subject = strtr( $subject, $pairs );
		$body    = strtr( $body, $pairs );
		return array(
			'subject' => $subject,
			'body'    => $body,
		);
	}

	/**
	 * Sample placeholders for a test send.
	 *
	 * @return array<string,string>
	 */
	public static function sample_vars() {
		$portal = class_exists( 'TRDSP_Portal' ) ? TRDSP_Portal::url_if_set() : '';
		return array(
			'customer_name'  => __( 'Jane Example', 'trade-dispatch' ),
			'job_title'      => __( 'Sample job', 'trade-dispatch' ),
			'estimate_title' => __( 'Sample estimate', 'trade-dispatch' ),
			'amount'         => number_format_i18n( 150, 2 ),
			'scheduled'      => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
			'preferred_time' => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
			'address'        => __( '123 Main St, Example, TX 75001', 'trade-dispatch' ),
			'status'         => __( 'Scheduled', 'trade-dispatch' ),
			'company_name'   => class_exists( 'TRDSP_Mail' ) ? TRDSP_Mail::company_name() : (string) get_bloginfo( 'name' ),
			'portal_url'     => $portal,
			'portal_line'    => '' !== $portal ? __( 'Your portal', 'trade-dispatch' ) . ': ' . $portal : '',
			'office_note'       => __( 'Sample office note.', 'trade-dispatch' ),
			'customer_message'  => __( 'Sample note from the customer.', 'trade-dispatch' ),
			'crew_name'      => __( 'Alex Crew', 'trade-dispatch' ),
			'job_list'       => '• ' . __( 'Sample job — 9:00 AM', 'trade-dispatch' ),
			'digest_date'    => wp_date( get_option( 'date_format' ), strtotime( '+1 day' ) ),
			'photos_line'    => __( 'Visit photos are in the customer portal.', 'trade-dispatch' ),
			'invoice_line'   => __( 'An invoice is in your portal.', 'trade-dispatch' ),
			'time_line'      => __( 'Time on site: 45 minutes', 'trade-dispatch' ),
			'quotes_line'    => __( '1 field quote is on this job.', 'trade-dispatch' ),
			'parts_line'     => __( '3 parts used on this job.', 'trade-dispatch' ),
			'checklist_line' => __( 'Checklist: 2/5', 'trade-dispatch' ),
			'here_line'      => __( 'On the way 8:15 AM', 'trade-dispatch' ),
			'office_brief'   => __( 'Bring extra fittings. Customer prefers afternoon.', 'trade-dispatch' ),
		);
	}

	/**
	 * Emails screen URL.
	 *
	 * @param string $notice Notice key.
	 * @return string
	 */
	protected static function page_url( $notice ) {
		return add_query_arg(
			array(
				'page'         => 'trade-dispatch-emails',
				'trdsp_notice' => $notice,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Send a test copy of one template to the current user.
	 */
	public static function handle_test() {
		if ( ! isset( $_GET['_wpnonce'] ) ) {
			wp_die( esc_html__( 'Security check failed.', 'trade-dispatch' ) );
		}
		$key = isset( $_GET['trdsp_template'] ) ? sanitize_key( wp_unslash( $_GET['trdsp_template'] ) ) : '';
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'trdsp_test_email_template_' . $key ) ) {
			wp_die( esc_html__( 'Security check failed.', 'trade-dispatch' ) );
		}
		if ( ! class_exists( 'TRDSP_Roles' ) || ! TRDSP_Roles::can_manage_settings() ) {
			wp_die( esc_html__( 'Unauthorized.', 'trade-dispatch' ) );
		}
		if ( ! isset( self::schema()[ $key ] ) ) {
			wp_safe_redirect( esc_url_raw( self::page_url( 'error' ) ) );
			exit;
		}
		$user  = wp_get_current_user();
		$email = $user && $user->exists() ? sanitize_email( (string) $user->user_email ) : '';
		if ( '' === $email || ! is_email( $email ) ) {
			wp_safe_redirect( esc_url_raw( self::page_url( 'test_no_email' ) ) );
			exit;
		}
		$mail = self::render( $key, self::sample_vars() );
		wp_mail( $email, '[Test] ' . $mail['subject'], $mail['body'] );
		wp_safe_redirect( esc_url_raw( self::page_url( 'test_sent' ) ) );
		exit;
	}

	/**
	 * Clear a saved template so the default is used again.
	 */
	public static function handle_restore() {
		if ( ! isset( $_GET['_wpnonce'] ) ) {
			wp_die( esc_html__( 'Security check failed.', 'trade-dispatch' ) );
		}
		$key = isset( $_GET['trdsp_template'] ) ? sanitize_key( wp_unslash( $_GET['trdsp_template'] ) ) : '';
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'trdsp_restore_email_template_' . $key ) ) {
			wp_die( esc_html__( 'Security check failed.', 'trade-dispatch' ) );
		}
		if ( ! class_exists( 'TRDSP_Roles' ) || ! TRDSP_Roles::can_manage_settings() ) {
			wp_die( esc_html__( 'Unauthorized.', 'trade-dispatch' ) );
		}
		if ( ! isset( self::schema()[ $key ] ) ) {
			wp_safe_redirect( esc_url_raw( self::page_url( 'error' ) ) );
			exit;
		}
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		unset( $saved[ $key ] );
		update_option( self::OPTION, $saved, false );
		wp_safe_redirect( esc_url_raw( self::page_url( 'template_restored' ) ) );
		exit;
	}

	/**
	 * Emails settings screen.
	 */
	public static function render_page() {
		if ( ! class_exists( 'TRDSP_Roles' ) || ! TRDSP_Roles::can_manage_settings() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'trade-dispatch' ) );
		}
		$saved  = get_option( self::OPTION, array() );
		$groups = array(
			'customer' => __( 'Customer emails', 'trade-dispatch' ),
			'office'   => __( 'Office emails', 'trade-dispatch' ),
			'crew'     => __( 'Crew emails', 'trade-dispatch' ),
		);
		echo '<div class="wrap trdsp-wrap">';
		echo '<h1>' . esc_html__( 'Emails', 'trade-dispatch' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Plain-text WordPress mail. Leave a field blank to use the default. Placeholders: {customer_name} {job_title} {estimate_title} {amount} {scheduled} {preferred_time} {address} {status} {company_name} {portal_url} {portal_line} {photos_line} {invoice_line} {time_line} {quotes_line} {parts_line} {checklist_line} {here_line} {office_brief} {office_note} {customer_message} {crew_name} {job_list} {digest_date}', 'trade-dispatch' ) . '</p>';
		echo '<form method="post" action="options.php">';
		settings_fields( 'trdsp_email_templates_group' );
		foreach ( $groups as $group => $heading ) {
			echo '<h2>' . esc_html( $heading ) . '</h2>';
			foreach ( self::schema() as $key => $meta ) {
				if ( $group !== $meta['group'] ) {
					continue;
				}
				$def     = self::default_for( $key );
				$subject = isset( $saved[ $key ]['subject'] ) ? (string) $saved[ $key ]['subject'] : '';
				$body    = isset( $saved[ $key ]['body'] ) ? (string) $saved[ $key ]['body'] : '';
				echo '<h3>' . esc_html( $meta['label'] ) . '</h3>';
				echo '<table class="form-table" role="presentation">';
				echo '<tr><th scope="row"><label for="trdsp_tpl_' . esc_attr( $key ) . '_subject">' . esc_html__( 'Subject', 'trade-dispatch' ) . '</label></th><td>';
				echo '<input type="text" class="large-text" id="trdsp_tpl_' . esc_attr( $key ) . '_subject" name="' . esc_attr( self::OPTION ) . '[' . esc_attr( $key ) . '][subject]" value="' . esc_attr( $subject ) . '" placeholder="' . esc_attr( $def['subject'] ) . '" /></td></tr>';
				echo '<tr><th scope="row"><label for="trdsp_tpl_' . esc_attr( $key ) . '_body">' . esc_html__( 'Body', 'trade-dispatch' ) . '</label></th><td>';
				echo '<textarea class="large-text" rows="8" id="trdsp_tpl_' . esc_attr( $key ) . '_body" name="' . esc_attr( self::OPTION ) . '[' . esc_attr( $key ) . '][body]" placeholder="' . esc_attr( $def['body'] ) . '">' . esc_textarea( $body ) . '</textarea></td></tr>';
				echo '</table>';
				$test = wp_nonce_url(
					add_query_arg(
						array(
							'action'          => 'trdsp_test_email_template',
							'trdsp_template'  => $key,
						),
						admin_url( 'admin-post.php' )
					),
					'trdsp_test_email_template_' . $key
				);
				$restore = wp_nonce_url(
					add_query_arg(
						array(
							'action'         => 'trdsp_restore_email_template',
							'trdsp_template' => $key,
						),
						admin_url( 'admin-post.php' )
					),
					'trdsp_restore_email_template_' . $key
				);
				echo '<p>';
				echo '<a class="button" href="' . esc_url( $test ) . '">' . esc_html__( 'Send test to me', 'trade-dispatch' ) . '</a> ';
				if ( '' !== $subject || '' !== $body ) {
					echo '<a class="button" href="' . esc_url( $restore ) . '" data-trdsp-confirm="' . esc_attr( __( 'Clear this template and use the default again?', 'trade-dispatch' ) ) . '">' . esc_html__( 'Restore default', 'trade-dispatch' ) . '</a>';
				}
				echo '</p>';
			}
		}
		submit_button();
		echo '</form></div>';
	}
}
