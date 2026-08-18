<?php
/**
 * WordPress admin screens.
 *
 * @package Trade_Dispatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin menus and customer/job CRUD.
 */
class TRDSP_Admin {

	/**
	 * Register admin hooks.
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
		add_action( 'admin_post_trdsp_save_customer', array( $this, 'handle_save_customer' ) );
		add_action( 'admin_post_trdsp_delete_customer', array( $this, 'handle_delete_customer' ) );
		add_action( 'admin_post_trdsp_save_job', array( $this, 'handle_save_job' ) );
		add_action( 'admin_post_trdsp_delete_job', array( $this, 'handle_delete_job' ) );
		add_action( 'admin_post_trdsp_add_job_note', array( $this, 'handle_add_job_note' ) );
		add_action( 'admin_post_trdsp_save_estimate', array( $this, 'handle_save_estimate' ) );
		add_action( 'admin_post_trdsp_delete_estimate', array( $this, 'handle_delete_estimate' ) );
		add_action( 'admin_post_trdsp_convert_estimate', array( $this, 'handle_convert_estimate' ) );
		add_action( 'admin_post_trdsp_send_estimate', array( $this, 'handle_send_estimate' ) );
		add_action( 'admin_post_trdsp_duplicate_job', array( $this, 'handle_duplicate_job' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
	}

	/**
	 * Load admin CSS only on plugin pages.
	 *
	 * @param string $hook Current admin hook.
	 */
	public function enqueue( $hook ) {
		if ( false === strpos( $hook, 'trade-dispatch' ) ) {
			return;
		}
		wp_enqueue_style(
			'trdsp-admin',
			TRDSP_PLUGIN_URL . 'assets/css/trdsp-admin.css',
			array(),
			TRDSP_VERSION
		);
		$view = isset( $_GET['trdsp_view'] ) ? sanitize_key( wp_unslash( $_GET['trdsp_view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Asset view only.
		if ( 'print' === $view ) {
			wp_enqueue_style(
				'trdsp-print',
				TRDSP_PLUGIN_URL . 'assets/css/trdsp-print.css',
				array( 'trdsp-admin' ),
				TRDSP_VERSION
			);
			wp_enqueue_script(
				'trdsp-print',
				TRDSP_PLUGIN_URL . 'assets/js/trdsp-print.js',
				array(),
				TRDSP_VERSION,
				true
			);
		}
	}

	/**
	 * Success/error notices after redirects.
	 */
	public function notices() {
		if ( ! isset( $_GET['trdsp_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display flag only.
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( (string) $screen->id, 'trade-dispatch' ) ) {
			return;
		}
		$key     = sanitize_key( wp_unslash( $_GET['trdsp_notice'] ) );
		$messages = array(
			'customer_saved'  => __( 'Customer saved.', 'trade-dispatch' ),
			'customer_deleted'=> __( 'Customer deleted.', 'trade-dispatch' ),
			'job_saved'       => __( 'Job saved.', 'trade-dispatch' ),
			'job_deleted'     => __( 'Job deleted.', 'trade-dispatch' ),
			'note_saved'      => __( 'Note added.', 'trade-dispatch' ),
			'estimate_saved'  => __( 'Estimate saved.', 'trade-dispatch' ),
			'estimate_deleted'=> __( 'Estimate deleted.', 'trade-dispatch' ),
			'job_from_estimate' => __( 'Job created from estimate.', 'trade-dispatch' ),
			'job_duplicated'    => __( 'Job duplicated.', 'trade-dispatch' ),
			'estimate_sent'     => __( 'Estimate emailed to the customer and the office.', 'trade-dispatch' ),
			'estimate_no_email' => __( 'This customer needs an email address before the estimate can be sent.', 'trade-dispatch' ),
			'error'           => __( 'Something went wrong. Check required fields and try again.', 'trade-dispatch' ),
		);
		if ( ! isset( $messages[ $key ] ) ) {
			return;
		}
		$class = ( 'error' === $key ) ? 'notice-error' : 'notice-success';
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $messages[ $key ] ) . '</p></div>';
	}

	/**
	 * Top-level menu and subpages.
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Trade Dispatch', 'trade-dispatch' ),
			__( 'Trade Dispatch', 'trade-dispatch' ),
			'manage_options',
			'trade-dispatch',
			array( $this, 'render_jobs' ),
			'dashicons-clipboard',
			30
		);
		add_submenu_page(
			'trade-dispatch',
			__( 'Jobs', 'trade-dispatch' ),
			__( 'Jobs', 'trade-dispatch' ),
			'manage_options',
			'trade-dispatch',
			array( $this, 'render_jobs' )
		);
		add_submenu_page(
			'trade-dispatch',
			__( 'Customers', 'trade-dispatch' ),
			__( 'Customers', 'trade-dispatch' ),
			'manage_options',
			'trade-dispatch-customers',
			array( $this, 'render_customers' )
		);
		add_submenu_page(
			'trade-dispatch',
			__( 'Estimates', 'trade-dispatch' ),
			__( 'Estimates', 'trade-dispatch' ),
			'manage_options',
			'trade-dispatch-estimates',
			array( $this, 'render_estimates' )
		);
		add_submenu_page(
			'trade-dispatch',
			__( 'Settings', 'trade-dispatch' ),
			__( 'Settings', 'trade-dispatch' ),
			'manage_options',
			'trade-dispatch-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting(
			'trdsp_settings_group',
			'trdsp_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(
					'delete_data_on_uninstall' => false,
					'notify_email'             => '',
					'business_name'            => '',
				),
			)
		);
	}

	/**
	 * Sanitize settings array.
	 *
	 * @param mixed $input Raw settings.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$clean = array(
			'delete_data_on_uninstall' => false,
			'notify_email'             => '',
			'business_name'            => '',
		);
		if ( is_array( $input ) ) {
			$clean['delete_data_on_uninstall'] = ! empty( $input['delete_data_on_uninstall'] );
			if ( isset( $input['notify_email'] ) ) {
				$clean['notify_email'] = sanitize_email( wp_unslash( $input['notify_email'] ) );
			}
			if ( isset( $input['business_name'] ) ) {
				$clean['business_name'] = sanitize_text_field( wp_unslash( $input['business_name'] ) );
			}
		}
		return $clean;
	}

	/**
	 * Jobs list or form.
	 */
	public function render_jobs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'trade-dispatch' ) );
		}
		$view = isset( $_GET['trdsp_view'] ) ? sanitize_key( wp_unslash( $_GET['trdsp_view'] ) ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- View switch.
		$id   = isset( $_GET['job_id'] ) ? absint( wp_unslash( $_GET['job_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ID for form.
		if ( 'edit' === $view || 'add' === $view ) {
			$this->render_job_form( $id );
			return;
		}
		if ( 'print' === $view ) {
			$this->render_job_work_order( $id );
			return;
		}
		if ( 'calendar' === $view ) {
			$this->render_jobs_calendar();
			return;
		}
		if ( 'month' === $view ) {
			$this->render_jobs_month();
			return;
		}
		if ( 'recurring' === $view ) {
			$this->render_jobs_recurring();
			return;
		}
		$this->render_jobs_list();
	}

	/**
	 * Jobs list with filters.
	 */
	protected function render_jobs_list() {
		$status   = isset( $_GET['trdsp_status'] ) ? sanitize_key( wp_unslash( $_GET['trdsp_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filter.
		$from     = isset( $_GET['trdsp_from'] ) ? sanitize_text_field( wp_unslash( $_GET['trdsp_from'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filter.
		$to       = isset( $_GET['trdsp_to'] ) ? sanitize_text_field( wp_unslash( $_GET['trdsp_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filter.
		$assignee = isset( $_GET['trdsp_crew'] ) ? absint( wp_unslash( $_GET['trdsp_crew'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filter.
		$search   = isset( $_GET['trdsp_q'] ) ? sanitize_text_field( wp_unslash( $_GET['trdsp_q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filter.
		$jobs     = TRDSP_Jobs::query(
			array(
				'status'           => $status,
				'from'             => $from ? $from . ' 00:00:00' : '',
				'to'               => $to ? $to . ' 23:59:59' : '',
				'assigned_user_id' => $assignee,
				'search'           => $search,
				'limit'            => 100,
			)
		);
		$add_url = add_query_arg(
			array(
				'page'       => 'trade-dispatch',
				'trdsp_view' => 'add',
			),
			admin_url( 'admin.php' )
		);
		echo '<div class="wrap trdsp-wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Jobs', 'trade-dispatch' ) . '</h1>';
		echo ' <a class="page-title-action" href="' . esc_url( $add_url ) . '">' . esc_html__( 'Add job', 'trade-dispatch' ) . '</a>';
		$requests = add_query_arg(
			array(
				'page'         => 'trade-dispatch',
				'trdsp_status' => 'requested',
			),
			admin_url( 'admin.php' )
		);
		echo ' <a class="page-title-action" href="' . esc_url( $requests ) . '">' . esc_html__( 'Open requests', 'trade-dispatch' ) . '</a>';
		$this->render_jobs_view_switch( 'list' );
		if ( '' === $from && '' === $to && '' === $status && '' === $search && $assignee < 1 ) {
			$today      = wp_date( 'Y-m-d' );
			$today_jobs = TRDSP_Jobs::query(
				array(
					'from'  => $today . ' 00:00:00',
					'to'    => $today . ' 23:59:59',
					'limit' => 100,
				)
			);
			echo '<p class="trdsp-today"><strong>' . esc_html(
				sprintf(
					/* translators: %d: number of jobs scheduled today */
					_n( '%d job scheduled today.', '%d jobs scheduled today.', count( $today_jobs ), 'trade-dispatch' ),
					count( $today_jobs )
				)
			) . '</strong></p>';
		}
		echo '<form class="trdsp-filters" method="get">';
		echo '<input type="hidden" name="page" value="trade-dispatch" />';
		echo '<label class="screen-reader-text" for="trdsp_status">' . esc_html__( 'Status', 'trade-dispatch' ) . '</label>';
		echo '<select id="trdsp_status" name="trdsp_status"><option value="">' . esc_html__( 'All statuses', 'trade-dispatch' ) . '</option>';
		foreach ( TRDSP_Jobs::statuses() as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $status, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select> ';
		echo '<label class="screen-reader-text" for="trdsp_crew">' . esc_html__( 'Crew', 'trade-dispatch' ) . '</label>';
		echo '<select id="trdsp_crew" name="trdsp_crew"><option value="0">' . esc_html__( 'All crew', 'trade-dispatch' ) . '</option>';
		foreach ( get_users(
			array(
				'orderby' => 'display_name',
				'number'  => 200,
			)
		) as $user ) {
			echo '<option value="' . esc_attr( (string) $user->ID ) . '" ' . selected( $assignee, (int) $user->ID, false ) . '>' . esc_html( $user->display_name ) . '</option>';
		}
		echo '</select> ';
		echo '<label class="screen-reader-text" for="trdsp_q">' . esc_html__( 'Search jobs', 'trade-dispatch' ) . '</label>';
		echo '<input type="search" id="trdsp_q" name="trdsp_q" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search title or address', 'trade-dispatch' ) . '" /> ';
		echo '<label class="screen-reader-text" for="trdsp_from">' . esc_html__( 'From', 'trade-dispatch' ) . '</label>';
		echo '<input type="date" id="trdsp_from" name="trdsp_from" value="' . esc_attr( $from ) . '" /> ';
		echo '<label class="screen-reader-text" for="trdsp_to">' . esc_html__( 'To', 'trade-dispatch' ) . '</label>';
		echo '<input type="date" id="trdsp_to" name="trdsp_to" value="' . esc_attr( $to ) . '" /> ';
		submit_button( __( 'Filter', 'trade-dispatch' ), 'secondary', '', false );
		echo '</form>';
		if ( empty( $jobs ) ) {
			echo '<div class="trdsp-empty"><p>' . esc_html__( 'No jobs yet. Create a job to assign it to a crew member (any WordPress user).', 'trade-dispatch' ) . '</p></div>';
			echo '</div>';
			return;
		}
		$statuses = TRDSP_Jobs::statuses();
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'When', 'trade-dispatch' ) . '</th>';
		echo '<th>' . esc_html__( 'Job', 'trade-dispatch' ) . '</th>';
		echo '<th>' . esc_html__( 'Customer', 'trade-dispatch' ) . '</th>';
		echo '<th>' . esc_html__( 'Crew', 'trade-dispatch' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'trade-dispatch' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'trade-dispatch' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $jobs as $job ) {
			$customer = TRDSP_Customers::get( (int) $job['customer_id'] );
			$crew     = (int) $job['assigned_user_id'] > 0 ? get_userdata( (int) $job['assigned_user_id'] ) : null;
			$when     = '';
			if ( ! empty( $job['scheduled_at'] ) ) {
				$when = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( (string) $job['scheduled_at'] ) );
			}
			$edit = add_query_arg(
				array(
					'page'       => 'trade-dispatch',
					'trdsp_view' => 'edit',
					'job_id'     => (int) $job['id'],
				),
				admin_url( 'admin.php' )
			);
			$del  = wp_nonce_url(
				add_query_arg(
					array(
						'action' => 'trdsp_delete_job',
						'job_id' => (int) $job['id'],
					),
					admin_url( 'admin-post.php' )
				),
				'trdsp_delete_job_' . (int) $job['id']
			);
			$dup  = wp_nonce_url(
				add_query_arg(
					array(
						'action' => 'trdsp_duplicate_job',
						'job_id' => (int) $job['id'],
					),
					admin_url( 'admin-post.php' )
				),
				'trdsp_duplicate_job_' . (int) $job['id']
			);
			echo '<tr>';
			echo '<td>' . esc_html( $when ) . '</td>';
			echo '<td>' . esc_html( (string) $job['title'] ) . '</td>';
			echo '<td>' . esc_html( $customer ? (string) $customer['name'] : '—' ) . '</td>';
			echo '<td>' . esc_html( $crew ? $crew->display_name : '—' ) . '</td>';
			echo '<td>' . esc_html( isset( $statuses[ $job['status'] ] ) ? $statuses[ $job['status'] ] : (string) $job['status'] ) . '</td>';
			echo '<td class="trdsp-actions"><a href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit', 'trade-dispatch' ) . '</a>';
			echo '<a href="' . esc_url( $dup ) . '">' . esc_html__( 'Duplicate', 'trade-dispatch' ) . '</a>';
			echo '<a href="' . esc_url( $del ) . '" onclick="return confirm(\'' . esc_js( __( 'Delete this job?', 'trade-dispatch' ) ) . '\');">' . esc_html__( 'Delete', 'trade-dispatch' ) . '</a></td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Add/edit job form.
	 *
	 * @param int $id Job ID.
	 */
	protected function render_job_form( $id ) {
		$job = $id > 0 ? TRDSP_Jobs::get( $id ) : null;
		if ( $id > 0 && ! $job ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Job not found.', 'trade-dispatch' ) . '</p></div>';
			return;
		}
		$job       = $job ? $job : array();
		if ( $id < 1 && empty( $job['customer_id'] ) && isset( $_GET['customer_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Prefill only.
			$job['customer_id'] = absint( wp_unslash( $_GET['customer_id'] ) );
		}
		if ( $id < 1 && empty( $job['scheduled_at'] ) && isset( $_GET['trdsp_scheduled'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Prefill only.
			$day = sanitize_text_field( wp_unslash( $_GET['trdsp_scheduled'] ) );
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) ) {
				$job['scheduled_at'] = $day . ' 09:00:00';
			}
		}
		$customers  = TRDSP_Customers::query( array( 'limit' => 200 ) );
		$users      = get_users( array( 'orderby' => 'display_name', 'number' => 200 ) );
		$scheduled  = '';
		if ( ! empty( $job['scheduled_at'] ) ) {
			$scheduled = gmdate( 'Y-m-d\TH:i', strtotime( (string) $job['scheduled_at'] ) );
		}
		echo '<div class="wrap trdsp-wrap">';
		echo '<h1>' . esc_html( $id > 0 ? __( 'Edit job', 'trade-dispatch' ) : __( 'Add job', 'trade-dispatch' ) ) . '</h1>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="trdsp_save_job" />';
		echo '<input type="hidden" name="job_id" value="' . esc_attr( (string) $id ) . '" />';
		wp_nonce_field( 'trdsp_save_job', 'trdsp_save_job_nonce' );
		echo '<table class="form-table" role="presentation">';
		$this->field_text( 'title', __( 'Title', 'trade-dispatch' ), (string) ( $job['title'] ?? '' ), true );
		echo '<tr><th scope="row"><label for="customer_id">' . esc_html__( 'Customer', 'trade-dispatch' ) . '</label></th><td><select name="customer_id" id="customer_id">';
		echo '<option value="0">' . esc_html__( '— None —', 'trade-dispatch' ) . '</option>';
		$current_cust = (int) ( $job['customer_id'] ?? 0 );
		foreach ( $customers as $customer ) {
			echo '<option value="' . esc_attr( (string) $customer['id'] ) . '" ' . selected( $current_cust, (int) $customer['id'], false ) . '>' . esc_html( (string) $customer['name'] ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th scope="row"><label for="assigned_user_id">' . esc_html__( 'Crew member', 'trade-dispatch' ) . '</label></th><td><select name="assigned_user_id" id="assigned_user_id">';
		echo '<option value="0">' . esc_html__( '— Unassigned —', 'trade-dispatch' ) . '</option>';
		$current_user = (int) ( $job['assigned_user_id'] ?? 0 );
		foreach ( $users as $user ) {
			echo '<option value="' . esc_attr( (string) $user->ID ) . '" ' . selected( $current_user, (int) $user->ID, false ) . '>' . esc_html( $user->display_name ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th scope="row"><label for="status">' . esc_html__( 'Status', 'trade-dispatch' ) . '</label></th><td><select name="status" id="status">';
		$current_status = (string) ( $job['status'] ?? 'scheduled' );
		foreach ( TRDSP_Jobs::statuses() as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $current_status, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th scope="row"><label for="scheduled_at">' . esc_html__( 'Scheduled', 'trade-dispatch' ) . '</label></th><td>';
		echo '<input type="datetime-local" name="scheduled_at" id="scheduled_at" value="' . esc_attr( $scheduled ) . '" /></td></tr>';
		$this->field_text( 'address_1', __( 'Address', 'trade-dispatch' ), (string) ( $job['address_1'] ?? '' ) );
		$this->field_text( 'city', __( 'City', 'trade-dispatch' ), (string) ( $job['city'] ?? '' ) );
		$this->field_text( 'state', __( 'State', 'trade-dispatch' ), (string) ( $job['state'] ?? '' ) );
		$this->field_text( 'postcode', __( 'Postal code', 'trade-dispatch' ), (string) ( $job['postcode'] ?? '' ) );
		echo '<tr><th scope="row"><label for="gate_notes">' . esc_html__( 'Gate / access notes', 'trade-dispatch' ) . '</label></th><td>';
		echo '<textarea name="gate_notes" id="gate_notes" class="large-text" rows="3">' . esc_textarea( (string) ( $job['gate_notes'] ?? '' ) ) . '</textarea></td></tr>';
		echo '<tr><th scope="row"><label for="hazard_notes">' . esc_html__( 'Hazards', 'trade-dispatch' ) . '</label></th><td>';
		echo '<textarea name="hazard_notes" id="hazard_notes" class="large-text" rows="3">' . esc_textarea( (string) ( $job['hazard_notes'] ?? '' ) ) . '</textarea></td></tr>';
		echo '<tr><th scope="row"><label for="recurrence">' . esc_html__( 'Recurring', 'trade-dispatch' ) . '</label></th><td><select name="recurrence" id="recurrence">';
		$current_rec = (string) ( $job['recurrence'] ?? '' );
		foreach ( TRDSP_Jobs::recurrences() as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $current_rec, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select><p class="description">' . esc_html__( 'When a recurring job is marked complete, the next visit is created automatically.', 'trade-dispatch' ) . '</p></td></tr>';
		echo '</table>';
		submit_button( $id > 0 ? __( 'Update job', 'trade-dispatch' ) : __( 'Create job', 'trade-dispatch' ) );
		echo '</form>';
		if ( $id > 0 ) {
			$print = add_query_arg(
				array(
					'page'       => 'trade-dispatch',
					'trdsp_view' => 'print',
					'job_id'     => $id,
				),
				admin_url( 'admin.php' )
			);
			echo '<p><a class="button" href="' . esc_url( $print ) . '">' . esc_html__( 'Print work order', 'trade-dispatch' ) . '</a></p>';
			$this->render_job_notes( $id );
			/**
			 * After job notes on the job edit screen.
			 *
			 * @param int $id Job ID.
			 */
			do_action( 'trdsp_job_edit_after_notes', $id );
		}
		echo '</div>';
	}

	/**
	 * List / calendar switcher.
	 *
	 * @param string $current Current view key.
	 */
	protected function render_jobs_view_switch( $current ) {
		$list = add_query_arg(
			array(
				'page'       => 'trade-dispatch',
				'trdsp_view' => 'list',
			),
			admin_url( 'admin.php' )
		);
		$cal  = add_query_arg(
			array(
				'page'       => 'trade-dispatch',
				'trdsp_view' => 'calendar',
			),
			admin_url( 'admin.php' )
		);
		echo '<p class="trdsp-view-switch">';
		if ( 'list' === $current ) {
			echo '<strong>' . esc_html__( 'List', 'trade-dispatch' ) . '</strong>';
		} else {
			echo '<a href="' . esc_url( $list ) . '">' . esc_html__( 'List', 'trade-dispatch' ) . '</a>';
		}
		echo ' | ';
		if ( 'calendar' === $current ) {
			echo '<strong>' . esc_html__( 'Week calendar', 'trade-dispatch' ) . '</strong>';
		} else {
			echo '<a href="' . esc_url( $cal ) . '">' . esc_html__( 'Week calendar', 'trade-dispatch' ) . '</a>';
		}
		echo ' | ';
		$month = add_query_arg(
			array(
				'page'       => 'trade-dispatch',
				'trdsp_view' => 'month',
			),
			admin_url( 'admin.php' )
		);
		if ( 'month' === $current ) {
			echo '<strong>' . esc_html__( 'Month calendar', 'trade-dispatch' ) . '</strong>';
		} else {
			echo '<a href="' . esc_url( $month ) . '">' . esc_html__( 'Month calendar', 'trade-dispatch' ) . '</a>';
		}
		echo ' | ';
		$recurring = add_query_arg(
			array(
				'page'       => 'trade-dispatch',
				'trdsp_view' => 'recurring',
			),
			admin_url( 'admin.php' )
		);
		if ( 'recurring' === $current ) {
			echo '<strong>' . esc_html__( 'Recurring', 'trade-dispatch' ) . '</strong>';
		} else {
			echo '<a href="' . esc_url( $recurring ) . '">' . esc_html__( 'Recurring', 'trade-dispatch' ) . '</a>';
		}
		echo '</p>';
	}

	/**
	 * Start of the displayed calendar week in the site timezone.
	 *
	 * @return \DateTimeImmutable
	 */
	protected function calendar_week_start() {
		$start_of_week = (int) get_option( 'start_of_week', 0 );
		$tz            = wp_timezone();
		$raw           = isset( $_GET['trdsp_week'] ) ? sanitize_text_field( wp_unslash( $_GET['trdsp_week'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- View date only.
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
			$anchor = date_create_immutable( $raw, $tz );
		} else {
			$anchor = current_datetime();
		}
		if ( ! $anchor ) {
			$anchor = current_datetime();
		}
		$dow  = (int) $anchor->format( 'w' );
		$diff = ( $dow - $start_of_week + 7 ) % 7;
		return $anchor->modify( '-' . $diff . ' days' )->setTime( 0, 0, 0 );
	}

	/**
	 * Week calendar of scheduled jobs.
	 */
	protected function render_jobs_calendar() {
		$week_start = $this->calendar_week_start();
		$week_end   = $week_start->modify( '+6 days' );
		$add_url    = add_query_arg(
			array(
				'page'       => 'trade-dispatch',
				'trdsp_view' => 'add',
			),
			admin_url( 'admin.php' )
		);
		$prev = add_query_arg(
			array(
				'page'       => 'trade-dispatch',
				'trdsp_view' => 'calendar',
				'trdsp_week' => $week_start->modify( '-7 days' )->format( 'Y-m-d' ),
			),
			admin_url( 'admin.php' )
		);
		$next = add_query_arg(
			array(
				'page'       => 'trade-dispatch',
				'trdsp_view' => 'calendar',
				'trdsp_week' => $week_start->modify( '+7 days' )->format( 'Y-m-d' ),
			),
			admin_url( 'admin.php' )
		);
		$jobs = TRDSP_Jobs::query(
			array(
				'from'  => $week_start->format( 'Y-m-d 00:00:00' ),
				'to'    => $week_end->format( 'Y-m-d 23:59:59' ),
				'limit' => 200,
			)
		);
		$by_day = array();
		for ( $i = 0; $i < 7; $i++ ) {
			$by_day[ $week_start->modify( '+' . $i . ' days' )->format( 'Y-m-d' ) ] = array();
		}
		foreach ( $jobs as $job ) {
			if ( empty( $job['scheduled_at'] ) ) {
				continue;
			}
			$day = wp_date( 'Y-m-d', strtotime( (string) $job['scheduled_at'] ) );
			if ( isset( $by_day[ $day ] ) ) {
				$by_day[ $day ][] = $job;
			}
		}
		$statuses = TRDSP_Jobs::statuses();
		echo '<div class="wrap trdsp-wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Jobs', 'trade-dispatch' ) . '</h1>';
		echo ' <a class="page-title-action" href="' . esc_url( $add_url ) . '">' . esc_html__( 'Add job', 'trade-dispatch' ) . '</a>';
		$this->render_jobs_view_switch( 'calendar' );
		echo '<p class="trdsp-cal-nav">';
		echo '<a class="button" href="' . esc_url( $prev ) . '">&larr; ' . esc_html__( 'Previous week', 'trade-dispatch' ) . '</a> ';
		echo '<strong>' . esc_html( wp_date( get_option( 'date_format' ), $week_start->getTimestamp() ) ) . ' – ' . esc_html( wp_date( get_option( 'date_format' ), $week_end->getTimestamp() ) ) . '</strong> ';
		echo '<a class="button" href="' . esc_url( $next ) . '">' . esc_html__( 'Next week', 'trade-dispatch' ) . ' &rarr;</a>';
		echo '</p>';
		echo '<table class="widefat striped trdsp-calendar"><thead><tr>';
		foreach ( array_keys( $by_day ) as $day_key ) {
			$label_dt = date_create_immutable( $day_key . ' 12:00:00', wp_timezone() );
			echo '<th>' . esc_html( $label_dt ? wp_date( 'D n/j', $label_dt->getTimestamp() ) : $day_key ) . '</th>';
		}
		echo '</tr></thead><tbody><tr>';
		foreach ( $by_day as $day_key => $day_jobs ) {
			echo '<td>';
			$add_day = add_query_arg(
				array(
					'page'            => 'trade-dispatch',
					'trdsp_view'      => 'add',
					'trdsp_scheduled' => $day_key,
				),
				admin_url( 'admin.php' )
			);
			if ( empty( $day_jobs ) ) {
				echo '<span class="trdsp-cal-empty">&mdash;</span>';
			}
			foreach ( $day_jobs as $job ) {
				$customer = TRDSP_Customers::get( (int) $job['customer_id'] );
				$edit     = add_query_arg(
					array(
						'page'       => 'trade-dispatch',
						'trdsp_view' => 'edit',
						'job_id'     => (int) $job['id'],
					),
					admin_url( 'admin.php' )
				);
				$when = wp_date( get_option( 'time_format' ), strtotime( (string) $job['scheduled_at'] ) );
				$status = isset( $statuses[ $job['status'] ] ) ? $statuses[ $job['status'] ] : (string) $job['status'];
				echo '<a class="trdsp-cal-job" href="' . esc_url( $edit ) . '">';
				echo '<strong>' . esc_html( $when . ' — ' . (string) $job['title'] ) . '</strong><br />';
				echo esc_html( ( $customer ? (string) $customer['name'] : '—' ) . ' · ' . $status );
				echo '</a>';
			}
			echo '<a class="trdsp-cal-add" href="' . esc_url( $add_day ) . '">' . esc_html__( 'Add job', 'trade-dispatch' ) . '</a>';
			echo '</td>';
		}
		echo '</tr></tbody></table></div>';
	}

	/**
	 * First day of the displayed calendar month in the site timezone.
	 *
	 * @return \DateTimeImmutable
	 */
	protected function calendar_month_start() {
		$tz  = wp_timezone();
		$raw = isset( $_GET['trdsp_month'] ) ? sanitize_text_field( wp_unslash( $_GET['trdsp_month'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- View date only.
		if ( preg_match( '/^\d{4}-\d{2}$/', $raw ) ) {
			$anchor = date_create_immutable( $raw . '-01', $tz );
		} else {
			$anchor = current_datetime()->modify( 'first day of this month' );
		}
		if ( ! $anchor ) {
			$anchor = current_datetime()->modify( 'first day of this month' );
		}
		return $anchor->setTime( 0, 0, 0 );
	}

	/**
	 * Month calendar of scheduled jobs.
	 */
	protected function render_jobs_month() {
		$month_start   = $this->calendar_month_start();
		$month_end     = $month_start->modify( 'last day of this month' );
		$start_of_week = (int) get_option( 'start_of_week', 0 );
		$dow           = (int) $month_start->format( 'w' );
		$lead          = ( $dow - $start_of_week + 7 ) % 7;
		$grid_start    = $month_start->modify( '-' . $lead . ' days' );
		$end_dow       = (int) $month_end->format( 'w' );
		$trail         = ( $start_of_week + 6 - $end_dow + 7 ) % 7;
		$grid_end      = $month_end->modify( '+' . $trail . ' days' );
		$add_url       = add_query_arg(
			array(
				'page'       => 'trade-dispatch',
				'trdsp_view' => 'add',
			),
			admin_url( 'admin.php' )
		);
		$prev = add_query_arg(
			array(
				'page'        => 'trade-dispatch',
				'trdsp_view'  => 'month',
				'trdsp_month' => $month_start->modify( '-1 month' )->format( 'Y-m' ),
			),
			admin_url( 'admin.php' )
		);
		$next = add_query_arg(
			array(
				'page'        => 'trade-dispatch',
				'trdsp_view'  => 'month',
				'trdsp_month' => $month_start->modify( '+1 month' )->format( 'Y-m' ),
			),
			admin_url( 'admin.php' )
		);
		$jobs = TRDSP_Jobs::query(
			array(
				'from'  => $grid_start->format( 'Y-m-d 00:00:00' ),
				'to'    => $grid_end->format( 'Y-m-d 23:59:59' ),
				'limit' => 200,
			)
		);
		$by_day = array();
		$cursor = $grid_start;
		while ( $cursor <= $grid_end ) {
			$by_day[ $cursor->format( 'Y-m-d' ) ] = array();
			$cursor = $cursor->modify( '+1 day' );
		}
		foreach ( $jobs as $job ) {
			if ( empty( $job['scheduled_at'] ) ) {
				continue;
			}
			$day = wp_date( 'Y-m-d', strtotime( (string) $job['scheduled_at'] ) );
			if ( isset( $by_day[ $day ] ) ) {
				$by_day[ $day ][] = $job;
			}
		}
		$statuses = TRDSP_Jobs::statuses();
		$keys     = array_keys( $by_day );
		$weeks    = array_chunk( $keys, 7 );
		echo '<div class="wrap trdsp-wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Jobs', 'trade-dispatch' ) . '</h1>';
		echo ' <a class="page-title-action" href="' . esc_url( $add_url ) . '">' . esc_html__( 'Add job', 'trade-dispatch' ) . '</a>';
		$this->render_jobs_view_switch( 'month' );
		echo '<p class="trdsp-cal-nav">';
		echo '<a class="button" href="' . esc_url( $prev ) . '">&larr; ' . esc_html__( 'Previous month', 'trade-dispatch' ) . '</a> ';
		echo '<strong>' . esc_html( wp_date( 'F Y', $month_start->getTimestamp() ) ) . '</strong> ';
		echo '<a class="button" href="' . esc_url( $next ) . '">' . esc_html__( 'Next month', 'trade-dispatch' ) . ' &rarr;</a>';
		echo '</p>';
		echo '<table class="widefat striped trdsp-calendar trdsp-calendar-month">';
		echo '<thead><tr>';
		$head = $grid_start;
		for ( $i = 0; $i < 7; $i++ ) {
			echo '<th>' . esc_html( wp_date( 'D', $head->getTimestamp() ) ) . '</th>';
			$head = $head->modify( '+1 day' );
		}
		echo '</tr></thead><tbody>';
		$month_key = $month_start->format( 'Y-m' );
		foreach ( $weeks as $week ) {
			echo '<tr>';
			foreach ( $week as $day_key ) {
				$in_month = ( 0 === strpos( $day_key, $month_key ) );
				$add_day  = add_query_arg(
					array(
						'page'            => 'trade-dispatch',
						'trdsp_view'      => 'add',
						'trdsp_scheduled' => $day_key,
					),
					admin_url( 'admin.php' )
				);
				echo '<td class="' . esc_attr( $in_month ? 'trdsp-cal-in' : 'trdsp-cal-out' ) . '">';
				$label_dt = date_create_immutable( $day_key . ' 12:00:00', wp_timezone() );
				echo '<div class="trdsp-cal-day">' . esc_html( $label_dt ? wp_date( 'j', $label_dt->getTimestamp() ) : $day_key ) . '</div>';
				$day_jobs = isset( $by_day[ $day_key ] ) ? $by_day[ $day_key ] : array();
				foreach ( $day_jobs as $job ) {
					$customer = TRDSP_Customers::get( (int) $job['customer_id'] );
					$edit     = add_query_arg(
						array(
							'page'       => 'trade-dispatch',
							'trdsp_view' => 'edit',
							'job_id'     => (int) $job['id'],
						),
						admin_url( 'admin.php' )
					);
					$when   = wp_date( get_option( 'time_format' ), strtotime( (string) $job['scheduled_at'] ) );
					$status = isset( $statuses[ $job['status'] ] ) ? $statuses[ $job['status'] ] : (string) $job['status'];
					echo '<a class="trdsp-cal-job" href="' . esc_url( $edit ) . '">';
					echo '<strong>' . esc_html( $when . ' — ' . (string) $job['title'] ) . '</strong><br />';
					echo esc_html( ( $customer ? (string) $customer['name'] : '—' ) . ' · ' . $status );
					echo '</a>';
				}
				echo '<a class="trdsp-cal-add" href="' . esc_url( $add_day ) . '">' . esc_html__( 'Add job', 'trade-dispatch' ) . '</a>';
				echo '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Jobs that have a recurrence interval.
	 */
	protected function render_jobs_recurring() {
		$jobs = TRDSP_Jobs::query(
			array(
				'has_recurrence' => true,
				'limit'          => 100,
			)
		);
		$add_url = add_query_arg(
			array(
				'page'       => 'trade-dispatch',
				'trdsp_view' => 'add',
			),
			admin_url( 'admin.php' )
		);
		$intervals = TRDSP_Jobs::recurrences();
		$statuses  = TRDSP_Jobs::statuses();
		echo '<div class="wrap trdsp-wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Jobs', 'trade-dispatch' ) . '</h1>';
		echo ' <a class="page-title-action" href="' . esc_url( $add_url ) . '">' . esc_html__( 'Add job', 'trade-dispatch' ) . '</a>';
		$this->render_jobs_view_switch( 'recurring' );
		echo '<p class="description">' . esc_html__( 'Jobs with a weekly, biweekly, or monthly interval. Completing one creates the next visit.', 'trade-dispatch' ) . '</p>';
		if ( empty( $jobs ) ) {
			echo '<div class="trdsp-empty"><p>' . esc_html__( 'No recurring jobs yet. Edit a job and choose a recurrence interval.', 'trade-dispatch' ) . '</p></div>';
			echo '</div>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Next visit', 'trade-dispatch' ) . '</th>';
		echo '<th>' . esc_html__( 'Job', 'trade-dispatch' ) . '</th>';
		echo '<th>' . esc_html__( 'Customer', 'trade-dispatch' ) . '</th>';
		echo '<th>' . esc_html__( 'Interval', 'trade-dispatch' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'trade-dispatch' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'trade-dispatch' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $jobs as $job ) {
			$customer = TRDSP_Customers::get( (int) $job['customer_id'] );
			$when     = ! empty( $job['scheduled_at'] ) ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( (string) $job['scheduled_at'] ) ) : '—';
			$edit     = add_query_arg(
				array(
					'page'       => 'trade-dispatch',
					'trdsp_view' => 'edit',
					'job_id'     => (int) $job['id'],
				),
				admin_url( 'admin.php' )
			);
			$interval = isset( $intervals[ $job['recurrence'] ] ) ? $intervals[ $job['recurrence'] ] : (string) $job['recurrence'];
			echo '<tr>';
			echo '<td>' . esc_html( $when ) . '</td>';
			echo '<td>' . esc_html( (string) $job['title'] ) . '</td>';
			echo '<td>' . esc_html( $customer ? (string) $customer['name'] : '—' ) . '</td>';
			echo '<td>' . esc_html( $interval ) . '</td>';
			echo '<td>' . esc_html( isset( $statuses[ $job['status'] ] ) ? $statuses[ $job['status'] ] : (string) $job['status'] ) . '</td>';
			echo '<td class="trdsp-actions"><a href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit', 'trade-dispatch' ) . '</a></td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Printable work order for one job.
	 *
	 * @param int $id Job ID.
	 */
	protected function render_job_work_order( $id ) {
		$job = TRDSP_Jobs::get( $id );
		if ( ! $job ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Job not found.', 'trade-dispatch' ) . '</p></div>';
			return;
		}
		$customer = TRDSP_Customers::get( (int) $job['customer_id'] );
		$crew     = (int) $job['assigned_user_id'] > 0 ? get_userdata( (int) $job['assigned_user_id'] ) : null;
		$statuses = TRDSP_Jobs::statuses();
		$notes    = TRDSP_Notes::for_job( $id );
		$when     = ! empty( $job['scheduled_at'] ) ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( (string) $job['scheduled_at'] ) ) : '—';
		$addr     = trim( implode( ', ', array_filter( array( $job['address_1'], $job['city'], $job['state'], $job['postcode'] ) ) ) );
		$back     = add_query_arg(
			array(
				'page'       => 'trade-dispatch',
				'trdsp_view' => 'edit',
				'job_id'     => $id,
			),
			admin_url( 'admin.php' )
		);
		echo '<div class="wrap trdsp-wrap">';
		echo '<p class="trdsp-no-print"><a href="' . esc_url( $back ) . '">&larr; ' . esc_html__( 'Back to job', 'trade-dispatch' ) . '</a></p>';
		echo '<p class="trdsp-no-print"><button type="button" class="button button-primary" id="trdsp-print-work-order">' . esc_html__( 'Print', 'trade-dispatch' ) . '</button></p>';
		echo '<div class="trdsp-work-order">';
		echo '<h1>' . esc_html__( 'Work order', 'trade-dispatch' ) . '</h1>';
		echo '<p>' . esc_html( TRDSP_Mail::company_name() ) . '</p>';
		echo '<dl>';
		echo '<dt>' . esc_html__( 'Job', 'trade-dispatch' ) . '</dt><dd>' . esc_html( (string) $job['title'] ) . '</dd>';
		echo '<dt>' . esc_html__( 'When', 'trade-dispatch' ) . '</dt><dd>' . esc_html( $when ) . '</dd>';
		echo '<dt>' . esc_html__( 'Status', 'trade-dispatch' ) . '</dt><dd>' . esc_html( isset( $statuses[ $job['status'] ] ) ? $statuses[ $job['status'] ] : (string) $job['status'] ) . '</dd>';
		echo '<dt>' . esc_html__( 'Customer', 'trade-dispatch' ) . '</dt><dd>' . esc_html( $customer ? (string) $customer['name'] : '—' ) . '</dd>';
		if ( $customer && ! empty( $customer['phone'] ) ) {
			echo '<dt>' . esc_html__( 'Phone', 'trade-dispatch' ) . '</dt><dd>' . esc_html( (string) $customer['phone'] ) . '</dd>';
		}
		if ( $customer && ! empty( $customer['email'] ) ) {
			echo '<dt>' . esc_html__( 'Email', 'trade-dispatch' ) . '</dt><dd>' . esc_html( (string) $customer['email'] ) . '</dd>';
		}
		echo '<dt>' . esc_html__( 'Address', 'trade-dispatch' ) . '</dt><dd>' . esc_html( '' !== $addr ? $addr : '—' ) . '</dd>';
		echo '<dt>' . esc_html__( 'Crew', 'trade-dispatch' ) . '</dt><dd>' . esc_html( $crew ? $crew->display_name : '—' ) . '</dd>';
		if ( ! empty( $job['gate_notes'] ) ) {
			echo '<dt>' . esc_html__( 'Gate / access', 'trade-dispatch' ) . '</dt><dd>' . esc_html( (string) $job['gate_notes'] ) . '</dd>';
		}
		if ( ! empty( $job['hazard_notes'] ) ) {
			echo '<dt>' . esc_html__( 'Hazards', 'trade-dispatch' ) . '</dt><dd>' . esc_html( (string) $job['hazard_notes'] ) . '</dd>';
		}
		echo '</dl>';
		if ( ! empty( $notes ) ) {
			echo '<h2>' . esc_html__( 'Notes', 'trade-dispatch' ) . '</h2><ul>';
			foreach ( $notes as $note ) {
				echo '<li>' . esc_html( (string) $note['note'] ) . '</li>';
			}
			echo '</ul>';
		}
		echo '</div></div>';
	}

	/**
	 * Notes on an existing job.
	 *
	 * @param int $job_id Job ID.
	 */
	protected function render_job_notes( $job_id ) {
		$notes = TRDSP_Notes::for_job( $job_id );
		echo '<h2>' . esc_html__( 'Job notes', 'trade-dispatch' ) . '</h2>';
		if ( empty( $notes ) ) {
			echo '<p>' . esc_html__( 'No notes on this job yet.', 'trade-dispatch' ) . '</p>';
		} else {
			echo '<ul class="trdsp-notes">';
			foreach ( $notes as $note ) {
				$author = (int) $note['user_id'] > 0 ? get_userdata( (int) $note['user_id'] ) : null;
				$when   = ! empty( $note['created_at'] ) ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( (string) $note['created_at'] ) ) : '';
				$who    = $author ? $author->display_name : __( 'Office', 'trade-dispatch' );
				echo '<li><p>' . esc_html( (string) $note['note'] ) . '</p>';
				echo '<p class="description">' . esc_html( $who . ' · ' . $when ) . '</p></li>';
			}
			echo '</ul>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="trdsp_add_job_note" />';
		echo '<input type="hidden" name="job_id" value="' . esc_attr( (string) $job_id ) . '" />';
		wp_nonce_field( 'trdsp_add_job_note', 'trdsp_add_job_note_nonce' );
		echo '<p><label for="trdsp_job_note">' . esc_html__( 'Add a note', 'trade-dispatch' ) . '</label></p>';
		echo '<p><textarea id="trdsp_job_note" name="note" class="large-text" rows="3" required></textarea></p>';
		submit_button( __( 'Add note', 'trade-dispatch' ), 'secondary' );
		echo '</form>';
	}

	/**
	 * Customers list or form.
	 */
	public function render_customers() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'trade-dispatch' ) );
		}
		$view = isset( $_GET['trdsp_view'] ) ? sanitize_key( wp_unslash( $_GET['trdsp_view'] ) ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- View switch.
		$id   = isset( $_GET['customer_id'] ) ? absint( wp_unslash( $_GET['customer_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ID for form.
		if ( 'edit' === $view || 'add' === $view ) {
			$this->render_customer_form( $id );
			return;
		}
		$search    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Search.
		$customers = TRDSP_Customers::query(
			array(
				'search' => $search,
				'limit'  => 100,
			)
		);
		$add_url   = add_query_arg(
			array(
				'page'       => 'trade-dispatch-customers',
				'trdsp_view' => 'add',
			),
			admin_url( 'admin.php' )
		);
		echo '<div class="wrap trdsp-wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Customers', 'trade-dispatch' ) . '</h1>';
		echo ' <a class="page-title-action" href="' . esc_url( $add_url ) . '">' . esc_html__( 'Add customer', 'trade-dispatch' ) . '</a>';
		echo '<form class="trdsp-filters" method="get"><input type="hidden" name="page" value="trade-dispatch-customers" />';
		echo '<input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search customers', 'trade-dispatch' ) . '" /> ';
		submit_button( __( 'Search', 'trade-dispatch' ), 'secondary', '', false );
		echo '</form>';
		if ( empty( $customers ) ) {
			echo '<div class="trdsp-empty"><p>' . esc_html__( 'No customers yet. Customer records live in this site’s database — there is no crew cap.', 'trade-dispatch' ) . '</p></div>';
			echo '</div>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Name', 'trade-dispatch' ) . '</th>';
		echo '<th>' . esc_html__( 'Email', 'trade-dispatch' ) . '</th>';
		echo '<th>' . esc_html__( 'Phone', 'trade-dispatch' ) . '</th>';
		echo '<th>' . esc_html__( 'City', 'trade-dispatch' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'trade-dispatch' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $customers as $customer ) {
			$edit = add_query_arg(
				array(
					'page'        => 'trade-dispatch-customers',
					'trdsp_view'  => 'edit',
					'customer_id' => (int) $customer['id'],
				),
				admin_url( 'admin.php' )
			);
			$del  = wp_nonce_url(
				add_query_arg(
					array(
						'action'      => 'trdsp_delete_customer',
						'customer_id' => (int) $customer['id'],
					),
					admin_url( 'admin-post.php' )
				),
				'trdsp_delete_customer_' . (int) $customer['id']
			);
			echo '<tr>';
			echo '<td>' . esc_html( (string) $customer['name'] ) . '</td>';
			echo '<td>' . esc_html( (string) $customer['email'] ) . '</td>';
			echo '<td>' . esc_html( (string) $customer['phone'] ) . '</td>';
			echo '<td>' . esc_html( (string) $customer['city'] ) . '</td>';
			echo '<td class="trdsp-actions"><a href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit', 'trade-dispatch' ) . '</a>';
			echo '<a href="' . esc_url( $del ) . '" onclick="return confirm(\'' . esc_js( __( 'Delete this customer?', 'trade-dispatch' ) ) . '\');">' . esc_html__( 'Delete', 'trade-dispatch' ) . '</a></td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Add/edit customer form.
	 *
	 * @param int $id Customer ID.
	 */
	protected function render_customer_form( $id ) {
		$customer = $id > 0 ? TRDSP_Customers::get( $id ) : null;
		if ( $id > 0 && ! $customer ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Customer not found.', 'trade-dispatch' ) . '</p></div>';
			return;
		}
		$customer = $customer ? $customer : array();
		echo '<div class="wrap trdsp-wrap">';
		echo '<h1>' . esc_html( $id > 0 ? __( 'Edit customer', 'trade-dispatch' ) : __( 'Add customer', 'trade-dispatch' ) ) . '</h1>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="trdsp_save_customer" />';
		echo '<input type="hidden" name="customer_id" value="' . esc_attr( (string) $id ) . '" />';
		wp_nonce_field( 'trdsp_save_customer', 'trdsp_save_customer_nonce' );
		echo '<table class="form-table" role="presentation">';
		$this->field_text( 'name', __( 'Name', 'trade-dispatch' ), (string) ( $customer['name'] ?? '' ), true );
		$this->field_text( 'email', __( 'Email', 'trade-dispatch' ), (string) ( $customer['email'] ?? '' ) );
		$this->field_text( 'phone', __( 'Phone', 'trade-dispatch' ), (string) ( $customer['phone'] ?? '' ) );
		$this->field_text( 'address_1', __( 'Address', 'trade-dispatch' ), (string) ( $customer['address_1'] ?? '' ) );
		$this->field_text( 'city', __( 'City', 'trade-dispatch' ), (string) ( $customer['city'] ?? '' ) );
		$this->field_text( 'state', __( 'State', 'trade-dispatch' ), (string) ( $customer['state'] ?? '' ) );
		$this->field_text( 'postcode', __( 'Postal code', 'trade-dispatch' ), (string) ( $customer['postcode'] ?? '' ) );
		echo '<tr><th scope="row"><label for="notes">' . esc_html__( 'Notes', 'trade-dispatch' ) . '</label></th><td>';
		echo '<textarea name="notes" id="notes" class="large-text" rows="4">' . esc_textarea( (string) ( $customer['notes'] ?? '' ) ) . '</textarea></td></tr>';
		echo '</table>';
		submit_button( $id > 0 ? __( 'Update customer', 'trade-dispatch' ) : __( 'Create customer', 'trade-dispatch' ) );
		echo '</form>';
		if ( $id > 0 ) {
			$cust_jobs = TRDSP_Jobs::query(
				array(
					'customer_id' => $id,
					'limit'       => 50,
				)
			);
			$add_job = add_query_arg(
				array(
					'page'        => 'trade-dispatch',
					'trdsp_view'  => 'add',
					'customer_id' => $id,
				),
				admin_url( 'admin.php' )
			);
			echo '<h2>' . esc_html__( 'Jobs', 'trade-dispatch' ) . '</h2>';
			echo '<p><a class="button" href="' . esc_url( $add_job ) . '">' . esc_html__( 'Add job for this customer', 'trade-dispatch' ) . '</a></p>';
			if ( empty( $cust_jobs ) ) {
				echo '<p>' . esc_html__( 'No jobs for this customer yet.', 'trade-dispatch' ) . '</p>';
			} else {
				$statuses = TRDSP_Jobs::statuses();
				echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'When', 'trade-dispatch' ) . '</th><th>' . esc_html__( 'Job', 'trade-dispatch' ) . '</th><th>' . esc_html__( 'Status', 'trade-dispatch' ) . '</th></tr></thead><tbody>';
				foreach ( $cust_jobs as $job ) {
					$when = ! empty( $job['scheduled_at'] ) ? wp_date( get_option( 'date_format' ), strtotime( (string) $job['scheduled_at'] ) ) : '—';
					$edit = add_query_arg(
						array(
							'page'       => 'trade-dispatch',
							'trdsp_view' => 'edit',
							'job_id'     => (int) $job['id'],
						),
						admin_url( 'admin.php' )
					);
					echo '<tr><td>' . esc_html( $when ) . '</td><td><a href="' . esc_url( $edit ) . '">' . esc_html( (string) $job['title'] ) . '</a></td><td>' . esc_html( isset( $statuses[ $job['status'] ] ) ? $statuses[ $job['status'] ] : (string) $job['status'] ) . '</td></tr>';
				}
				echo '</tbody></table>';
			}
		}
		echo '</div>';
	}

	/**
	 * Estimates list or form.
	 */
	public function render_estimates() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'trade-dispatch' ) );
		}
		$view = isset( $_GET['trdsp_view'] ) ? sanitize_key( wp_unslash( $_GET['trdsp_view'] ) ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- View switch.
		$id   = isset( $_GET['estimate_id'] ) ? absint( wp_unslash( $_GET['estimate_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ID for form.
		if ( 'edit' === $view || 'add' === $view ) {
			$this->render_estimate_form( $id );
			return;
		}
		$status    = isset( $_GET['trdsp_status'] ) ? sanitize_key( wp_unslash( $_GET['trdsp_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filter.
		$estimates = TRDSP_Estimates::query(
			array(
				'status' => $status,
				'limit'  => 100,
			)
		);
		$add_url   = add_query_arg(
			array(
				'page'       => 'trade-dispatch-estimates',
				'trdsp_view' => 'add',
			),
			admin_url( 'admin.php' )
		);
		echo '<div class="wrap trdsp-wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Estimates', 'trade-dispatch' ) . '</h1>';
		echo ' <a class="page-title-action" href="' . esc_url( $add_url ) . '">' . esc_html__( 'Add estimate', 'trade-dispatch' ) . '</a>';
		echo '<p class="description">' . esc_html__( 'Estimates are office records only. Trade Dispatch does not process payments.', 'trade-dispatch' ) . '</p>';
		echo '<form class="trdsp-filters" method="get">';
		echo '<input type="hidden" name="page" value="trade-dispatch-estimates" />';
		echo '<label class="screen-reader-text" for="trdsp_est_status">' . esc_html__( 'Status', 'trade-dispatch' ) . '</label>';
		echo '<select id="trdsp_est_status" name="trdsp_status"><option value="">' . esc_html__( 'All statuses', 'trade-dispatch' ) . '</option>';
		foreach ( TRDSP_Estimates::statuses() as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $status, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select> ';
		submit_button( __( 'Filter', 'trade-dispatch' ), 'secondary', '', false );
		echo '</form>';
		if ( empty( $estimates ) ) {
			echo '<div class="trdsp-empty"><p>' . esc_html__( 'No estimates yet. Create one to track a quoted amount for a customer or job.', 'trade-dispatch' ) . '</p></div>';
			echo '</div>';
			return;
		}
		$statuses = TRDSP_Estimates::statuses();
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Title', 'trade-dispatch' ) . '</th>';
		echo '<th>' . esc_html__( 'Customer', 'trade-dispatch' ) . '</th>';
		echo '<th>' . esc_html__( 'Job', 'trade-dispatch' ) . '</th>';
		echo '<th>' . esc_html__( 'Amount', 'trade-dispatch' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'trade-dispatch' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'trade-dispatch' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $estimates as $estimate ) {
			$customer = TRDSP_Customers::get( (int) $estimate['customer_id'] );
			$job      = (int) $estimate['job_id'] > 0 ? TRDSP_Jobs::get( (int) $estimate['job_id'] ) : null;
			$edit     = add_query_arg(
				array(
					'page'        => 'trade-dispatch-estimates',
					'trdsp_view'  => 'edit',
					'estimate_id' => (int) $estimate['id'],
				),
				admin_url( 'admin.php' )
			);
			$del      = wp_nonce_url(
				add_query_arg(
					array(
						'action'      => 'trdsp_delete_estimate',
						'estimate_id' => (int) $estimate['id'],
					),
					admin_url( 'admin-post.php' )
				),
				'trdsp_delete_estimate_' . (int) $estimate['id']
			);
			echo '<tr>';
			echo '<td>' . esc_html( (string) $estimate['title'] ) . '</td>';
			echo '<td>' . esc_html( $customer ? (string) $customer['name'] : '—' ) . '</td>';
			echo '<td>' . esc_html( $job ? (string) $job['title'] : '—' ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( (float) $estimate['amount'], 2 ) ) . '</td>';
			echo '<td>' . esc_html( isset( $statuses[ $estimate['status'] ] ) ? $statuses[ $estimate['status'] ] : (string) $estimate['status'] ) . '</td>';
			$send = wp_nonce_url(
				add_query_arg(
					array(
						'action'      => 'trdsp_send_estimate',
						'estimate_id' => (int) $estimate['id'],
					),
					admin_url( 'admin-post.php' )
				),
				'trdsp_send_estimate_' . (int) $estimate['id']
			);
			echo '<td class="trdsp-actions"><a href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit', 'trade-dispatch' ) . '</a>';
			echo '<a href="' . esc_url( $send ) . '">' . esc_html__( 'Email customer', 'trade-dispatch' ) . '</a>';
			echo '<a href="' . esc_url( $del ) . '" onclick="return confirm(\'' . esc_js( __( 'Delete this estimate?', 'trade-dispatch' ) ) . '\');">' . esc_html__( 'Delete', 'trade-dispatch' ) . '</a></td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Add/edit estimate form.
	 *
	 * @param int $id Estimate ID.
	 */
	protected function render_estimate_form( $id ) {
		$estimate = $id > 0 ? TRDSP_Estimates::get( $id ) : null;
		if ( $id > 0 && ! $estimate ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Estimate not found.', 'trade-dispatch' ) . '</p></div>';
			return;
		}
		$estimate  = $estimate ? $estimate : array();
		$customers = TRDSP_Customers::query( array( 'limit' => 200 ) );
		$jobs      = TRDSP_Jobs::query( array( 'limit' => 200 ) );
		echo '<div class="wrap trdsp-wrap">';
		echo '<h1>' . esc_html( $id > 0 ? __( 'Edit estimate', 'trade-dispatch' ) : __( 'Add estimate', 'trade-dispatch' ) ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'This is a record of a quoted amount. It does not charge anyone.', 'trade-dispatch' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="trdsp_save_estimate" />';
		echo '<input type="hidden" name="estimate_id" value="' . esc_attr( (string) $id ) . '" />';
		wp_nonce_field( 'trdsp_save_estimate', 'trdsp_save_estimate_nonce' );
		echo '<table class="form-table" role="presentation">';
		$this->field_text( 'title', __( 'Title', 'trade-dispatch' ), (string) ( $estimate['title'] ?? '' ), true );
		echo '<tr><th scope="row"><label for="customer_id">' . esc_html__( 'Customer', 'trade-dispatch' ) . '</label></th><td><select name="customer_id" id="customer_id">';
		echo '<option value="0">' . esc_html__( '— None —', 'trade-dispatch' ) . '</option>';
		$current_cust = (int) ( $estimate['customer_id'] ?? 0 );
		foreach ( $customers as $customer ) {
			echo '<option value="' . esc_attr( (string) $customer['id'] ) . '" ' . selected( $current_cust, (int) $customer['id'], false ) . '>' . esc_html( (string) $customer['name'] ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th scope="row"><label for="job_id">' . esc_html__( 'Job', 'trade-dispatch' ) . '</label></th><td><select name="job_id" id="job_id">';
		echo '<option value="0">' . esc_html__( '— None —', 'trade-dispatch' ) . '</option>';
		$current_job = (int) ( $estimate['job_id'] ?? 0 );
		foreach ( $jobs as $job ) {
			echo '<option value="' . esc_attr( (string) $job['id'] ) . '" ' . selected( $current_job, (int) $job['id'], false ) . '>' . esc_html( (string) $job['title'] ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th scope="row"><label for="amount">' . esc_html__( 'Amount', 'trade-dispatch' ) . '</label></th><td>';
		echo '<input type="number" class="regular-text" name="amount" id="amount" step="0.01" min="0" value="' . esc_attr( (string) ( $estimate['amount'] ?? '0.00' ) ) . '" /></td></tr>';
		echo '<tr><th scope="row"><label for="status">' . esc_html__( 'Status', 'trade-dispatch' ) . '</label></th><td><select name="status" id="status">';
		$current_status = (string) ( $estimate['status'] ?? 'draft' );
		foreach ( TRDSP_Estimates::statuses() as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $current_status, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '</table>';
		submit_button( $id > 0 ? __( 'Update estimate', 'trade-dispatch' ) : __( 'Create estimate', 'trade-dispatch' ) );
		echo '</form>';
		if ( $id > 0 ) {
			$send = wp_nonce_url(
				add_query_arg(
					array(
						'action'      => 'trdsp_send_estimate',
						'estimate_id' => $id,
					),
					admin_url( 'admin-post.php' )
				),
				'trdsp_send_estimate_' . $id
			);
			echo '<p><a class="button" href="' . esc_url( $send ) . '">' . esc_html__( 'Email estimate to customer', 'trade-dispatch' ) . '</a></p>';
		}
		if ( $id > 0 && (int) ( $estimate['job_id'] ?? 0 ) < 1 ) {
			$convert = wp_nonce_url(
				add_query_arg(
					array(
						'action'      => 'trdsp_convert_estimate',
						'estimate_id' => $id,
					),
					admin_url( 'admin-post.php' )
				),
				'trdsp_convert_estimate_' . $id
			);
			echo '<p><a class="button" href="' . esc_url( $convert ) . '">' . esc_html__( 'Create job from this estimate', 'trade-dispatch' ) . '</a></p>';
		}
		echo '</div>';
	}

	/**
	 * Settings screen.
	 */
	public function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'trade-dispatch' ) );
		}
		$settings = get_option( 'trdsp_settings', array() );
		$delete   = ! empty( $settings['delete_data_on_uninstall'] );
		$notify   = isset( $settings['notify_email'] ) ? (string) $settings['notify_email'] : '';
		$biz      = isset( $settings['business_name'] ) ? (string) $settings['business_name'] : '';
		echo '<div class="wrap trdsp-wrap">';
		echo '<h1>' . esc_html__( 'Trade Dispatch Settings', 'trade-dispatch' ) . '</h1>';
		echo '<form method="post" action="options.php">';
		settings_fields( 'trdsp_settings_group' );
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row"><label for="trdsp_business_name">' . esc_html__( 'Business name on emails', 'trade-dispatch' ) . '</label></th><td>';
		echo '<input type="text" class="regular-text" id="trdsp_business_name" name="trdsp_settings[business_name]" value="' . esc_attr( $biz ) . '" />';
		echo '<p class="description">' . esc_html__( 'Shown at the bottom of Trade Dispatch emails and on printed work orders. Leave blank to use the WordPress site title.', 'trade-dispatch' ) . '</p></td></tr>';
		echo '<tr><th scope="row"><label for="trdsp_notify_email">' . esc_html__( 'Office notification email', 'trade-dispatch' ) . '</label></th><td>';
		echo '<input type="email" class="regular-text" id="trdsp_notify_email" name="trdsp_settings[notify_email]" value="' . esc_attr( $notify ) . '" />';
		echo '<p class="description">' . esc_html__( 'Booking, assignment, job-complete, estimate, and portal reschedule emails are sent with WordPress mail. Leave blank to use the site admin email.', 'trade-dispatch' ) . '</p></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Shortcodes and blocks', 'trade-dispatch' ) . '</th><td>';
		echo '<p><code>[trdsp_booking]</code> — ' . esc_html__( 'public booking form (also a Gutenberg block)', 'trade-dispatch' ) . '</p>';
		echo '<p><code>[trdsp_portal]</code> — ' . esc_html__( 'customer portal: upcoming and past visits, estimates, and a reschedule request (also a Gutenberg block)', 'trade-dispatch' ) . '</p>';
		echo '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Uninstall', 'trade-dispatch' ) . '</th><td><label><input type="checkbox" name="trdsp_settings[delete_data_on_uninstall]" value="1" ';
		checked( $delete );
		echo ' /> ';
		echo esc_html__( 'Delete customers, jobs, estimates, and notes when this plugin is deleted.', 'trade-dispatch' );
		echo '</label></td></tr></table>';
		submit_button();
		echo '</form></div>';
	}

	/**
	 * Text field row.
	 *
	 * @param string $name     Field name.
	 * @param string $label    Label.
	 * @param string $value    Value.
	 * @param bool   $required Required.
	 */
	protected function field_text( $name, $label, $value, $required = false ) {
		echo '<tr><th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<input type="text" class="regular-text" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" ';
		if ( $required ) {
			echo 'required ';
		}
		echo '/></td></tr>';
	}

	/**
	 * Save customer.
	 */
	public function handle_save_customer() {
		if ( ! isset( $_POST['trdsp_save_customer_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['trdsp_save_customer_nonce'] ) ), 'trdsp_save_customer' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'trade-dispatch' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'trade-dispatch' ) );
		}
		$result = TRDSP_Customers::save(
			array(
				'id'        => isset( $_POST['customer_id'] ) ? absint( wp_unslash( $_POST['customer_id'] ) ) : 0,
				'name'      => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
				'email'     => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
				'phone'     => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
				'address_1' => isset( $_POST['address_1'] ) ? sanitize_text_field( wp_unslash( $_POST['address_1'] ) ) : '',
				'city'      => isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '',
				'state'     => isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '',
				'postcode'  => isset( $_POST['postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['postcode'] ) ) : '',
				'notes'     => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
			)
		);
		$url = add_query_arg(
			array(
				'page'         => 'trade-dispatch-customers',
				'trdsp_notice' => is_wp_error( $result ) ? 'error' : 'customer_saved',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( esc_url_raw( $url ) );
		exit;
	}

	/**
	 * Delete customer.
	 */
	public function handle_delete_customer() {
		$id = isset( $_GET['customer_id'] ) ? absint( wp_unslash( $_GET['customer_id'] ) ) : 0;
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'trdsp_delete_customer_' . $id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'trade-dispatch' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'trade-dispatch' ) );
		}
		TRDSP_Estimates::delete_for_customer( $id );
		TRDSP_Customers::delete( $id );
		wp_safe_redirect(
			esc_url_raw(
				add_query_arg(
					array(
						'page'         => 'trade-dispatch-customers',
						'trdsp_notice' => 'customer_deleted',
					),
					admin_url( 'admin.php' )
				)
			)
		);
		exit;
	}

	/**
	 * Save job.
	 */
	public function handle_save_job() {
		if ( ! isset( $_POST['trdsp_save_job_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['trdsp_save_job_nonce'] ) ), 'trdsp_save_job' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'trade-dispatch' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'trade-dispatch' ) );
		}
		$result = TRDSP_Jobs::save(
			array(
				'id'               => isset( $_POST['job_id'] ) ? absint( wp_unslash( $_POST['job_id'] ) ) : 0,
				'customer_id'      => isset( $_POST['customer_id'] ) ? absint( wp_unslash( $_POST['customer_id'] ) ) : 0,
				'assigned_user_id' => isset( $_POST['assigned_user_id'] ) ? absint( wp_unslash( $_POST['assigned_user_id'] ) ) : 0,
				'title'            => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
				'status'           => isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'scheduled',
				'scheduled_at'     => isset( $_POST['scheduled_at'] ) ? sanitize_text_field( wp_unslash( $_POST['scheduled_at'] ) ) : '',
				'address_1'        => isset( $_POST['address_1'] ) ? sanitize_text_field( wp_unslash( $_POST['address_1'] ) ) : '',
				'city'             => isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '',
				'state'            => isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '',
				'postcode'         => isset( $_POST['postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['postcode'] ) ) : '',
				'gate_notes'       => isset( $_POST['gate_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['gate_notes'] ) ) : '',
				'hazard_notes'     => isset( $_POST['hazard_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['hazard_notes'] ) ) : '',
				'recurrence'       => isset( $_POST['recurrence'] ) ? sanitize_key( wp_unslash( $_POST['recurrence'] ) ) : '',
			)
		);
		$url = add_query_arg(
			array(
				'page'         => 'trade-dispatch',
				'trdsp_notice' => is_wp_error( $result ) ? 'error' : 'job_saved',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( esc_url_raw( $url ) );
		exit;
	}

	/**
	 * Delete job.
	 */
	public function handle_delete_job() {
		$id = isset( $_GET['job_id'] ) ? absint( wp_unslash( $_GET['job_id'] ) ) : 0;
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'trdsp_delete_job_' . $id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'trade-dispatch' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'trade-dispatch' ) );
		}
		TRDSP_Jobs::delete( $id );
		wp_safe_redirect(
			esc_url_raw(
				add_query_arg(
					array(
						'page'         => 'trade-dispatch',
						'trdsp_notice' => 'job_deleted',
					),
					admin_url( 'admin.php' )
				)
			)
		);
		exit;
	}

	/**
	 * Add a job note.
	 */
	public function handle_add_job_note() {
		if ( ! isset( $_POST['trdsp_add_job_note_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['trdsp_add_job_note_nonce'] ) ), 'trdsp_add_job_note' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'trade-dispatch' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'trade-dispatch' ) );
		}
		$job_id = isset( $_POST['job_id'] ) ? absint( wp_unslash( $_POST['job_id'] ) ) : 0;
		$note   = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
		$result = TRDSP_Notes::add( $job_id, $note, get_current_user_id() );
		$url    = add_query_arg(
			array(
				'page'         => 'trade-dispatch',
				'trdsp_view'   => 'edit',
				'job_id'       => $job_id,
				'trdsp_notice' => is_wp_error( $result ) ? 'error' : 'note_saved',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( esc_url_raw( $url ) );
		exit;
	}

	/**
	 * Save estimate.
	 */
	public function handle_save_estimate() {
		if ( ! isset( $_POST['trdsp_save_estimate_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['trdsp_save_estimate_nonce'] ) ), 'trdsp_save_estimate' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'trade-dispatch' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'trade-dispatch' ) );
		}
		$result = TRDSP_Estimates::save(
			array(
				'id'          => isset( $_POST['estimate_id'] ) ? absint( wp_unslash( $_POST['estimate_id'] ) ) : 0,
				'customer_id' => isset( $_POST['customer_id'] ) ? absint( wp_unslash( $_POST['customer_id'] ) ) : 0,
				'job_id'      => isset( $_POST['job_id'] ) ? absint( wp_unslash( $_POST['job_id'] ) ) : 0,
				'title'       => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
				'amount'      => isset( $_POST['amount'] ) ? (float) sanitize_text_field( wp_unslash( $_POST['amount'] ) ) : 0,
				'status'      => isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'draft',
			)
		);
		$url = add_query_arg(
			array(
				'page'         => 'trade-dispatch-estimates',
				'trdsp_notice' => is_wp_error( $result ) ? 'error' : 'estimate_saved',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( esc_url_raw( $url ) );
		exit;
	}

	/**
	 * Delete estimate.
	 */
	public function handle_delete_estimate() {
		$id = isset( $_GET['estimate_id'] ) ? absint( wp_unslash( $_GET['estimate_id'] ) ) : 0;
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'trdsp_delete_estimate_' . $id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'trade-dispatch' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'trade-dispatch' ) );
		}
		TRDSP_Estimates::delete( $id );
		wp_safe_redirect(
			esc_url_raw(
				add_query_arg(
					array(
						'page'         => 'trade-dispatch-estimates',
						'trdsp_notice' => 'estimate_deleted',
					),
					admin_url( 'admin.php' )
				)
			)
		);
		exit;
	}

	/**
	 * Create a scheduled job from an estimate and link them.
	 */
	public function handle_convert_estimate() {
		$id = isset( $_GET['estimate_id'] ) ? absint( wp_unslash( $_GET['estimate_id'] ) ) : 0;
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'trdsp_convert_estimate_' . $id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'trade-dispatch' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'trade-dispatch' ) );
		}
		$estimate = TRDSP_Estimates::get( $id );
		if ( ! $estimate ) {
			wp_safe_redirect(
				esc_url_raw(
					add_query_arg(
						array(
							'page'         => 'trade-dispatch-estimates',
							'trdsp_notice' => 'error',
						),
						admin_url( 'admin.php' )
					)
				)
			);
			exit;
		}
		$existing_job = (int) ( $estimate['job_id'] ?? 0 );
		if ( $existing_job > 0 ) {
			wp_safe_redirect(
				esc_url_raw(
					add_query_arg(
						array(
							'page'         => 'trade-dispatch',
							'job_id'       => $existing_job,
							'trdsp_notice' => 'job_from_estimate',
						),
						admin_url( 'admin.php' )
					)
				)
			);
			exit;
		}
		$job_id = TRDSP_Jobs::save(
			array(
				'customer_id'  => (int) ( $estimate['customer_id'] ?? 0 ),
				'title'        => (string) $estimate['title'],
				'status'       => 'scheduled',
				'scheduled_at' => wp_date( 'Y-m-d' ) . ' 09:00:00',
			)
		);
		if ( is_wp_error( $job_id ) ) {
			wp_safe_redirect(
				esc_url_raw(
					add_query_arg(
						array(
							'page'         => 'trade-dispatch-estimates',
							'trdsp_notice' => 'error',
						),
						admin_url( 'admin.php' )
					)
				)
			);
			exit;
		}
		TRDSP_Estimates::save(
			array(
				'id'          => $id,
				'customer_id' => (int) ( $estimate['customer_id'] ?? 0 ),
				'job_id'      => (int) $job_id,
				'title'       => (string) $estimate['title'],
				'amount'      => (float) ( $estimate['amount'] ?? 0 ),
				'status'      => (string) ( $estimate['status'] ?? 'draft' ),
			)
		);
		/**
		 * After an estimate is turned into a job.
		 *
		 * @param int $id     Estimate ID.
		 * @param int $job_id New job ID.
		 */
		do_action( 'trdsp_after_estimate_converted_to_job', $id, (int) $job_id );
		wp_safe_redirect(
			esc_url_raw(
				add_query_arg(
					array(
						'page'         => 'trade-dispatch',
						'job_id'       => (int) $job_id,
						'trdsp_notice' => 'job_from_estimate',
					),
					admin_url( 'admin.php' )
				)
			)
		);
		exit;
	}

	/**
	 * Email an estimate to the customer (quote only — no charge).
	 */
	public function handle_send_estimate() {
		$id = isset( $_GET['estimate_id'] ) ? absint( wp_unslash( $_GET['estimate_id'] ) ) : 0;
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'trdsp_send_estimate_' . $id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'trade-dispatch' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'trade-dispatch' ) );
		}
		$estimate = TRDSP_Estimates::get( $id );
		$back     = array(
			'page'        => 'trade-dispatch-estimates',
			'trdsp_view'  => 'edit',
			'estimate_id' => $id,
		);
		if ( ! $estimate ) {
			wp_safe_redirect(
				esc_url_raw(
					add_query_arg(
						array(
							'page'         => 'trade-dispatch-estimates',
							'trdsp_notice' => 'error',
						),
						admin_url( 'admin.php' )
					)
				)
			);
			exit;
		}
		$customer = TRDSP_Customers::get( (int) ( $estimate['customer_id'] ?? 0 ) );
		if ( ! $customer || empty( $customer['email'] ) || ! is_email( (string) $customer['email'] ) ) {
			wp_safe_redirect(
				esc_url_raw(
					add_query_arg(
						array_merge( $back, array( 'trdsp_notice' => 'estimate_no_email' ) ),
						admin_url( 'admin.php' )
					)
				)
			);
			exit;
		}
		$status = (string) ( $estimate['status'] ?? 'draft' );
		if ( 'draft' === $status ) {
			$status = 'sent';
		}
		$saved = TRDSP_Estimates::save(
			array(
				'id'          => $id,
				'customer_id' => (int) ( $estimate['customer_id'] ?? 0 ),
				'job_id'      => (int) ( $estimate['job_id'] ?? 0 ),
				'title'       => (string) $estimate['title'],
				'amount'      => (float) ( $estimate['amount'] ?? 0 ),
				'status'      => $status,
			)
		);
		if ( is_wp_error( $saved ) ) {
			wp_safe_redirect(
				esc_url_raw(
					add_query_arg(
						array_merge( $back, array( 'trdsp_notice' => 'error' ) ),
						admin_url( 'admin.php' )
					)
				)
			);
			exit;
		}
		$fresh = TRDSP_Estimates::get( $id );
		/**
		 * After an estimate is emailed to the customer.
		 *
		 * @param int                  $id        Estimate ID.
		 * @param array<string,mixed> $estimate  Saved row.
		 */
		do_action( 'trdsp_estimate_sent', $id, $fresh ? $fresh : $estimate );
		wp_safe_redirect(
			esc_url_raw(
				add_query_arg(
					array_merge( $back, array( 'trdsp_notice' => 'estimate_sent' ) ),
					admin_url( 'admin.php' )
				)
			)
		);
		exit;
	}

	/**
	 * Duplicate a job (notes are not copied).
	 */
	public function handle_duplicate_job() {
		$id = isset( $_GET['job_id'] ) ? absint( wp_unslash( $_GET['job_id'] ) ) : 0;
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'trdsp_duplicate_job_' . $id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'trade-dispatch' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'trade-dispatch' ) );
		}
		$job = TRDSP_Jobs::get( $id );
		if ( ! $job ) {
			wp_safe_redirect(
				esc_url_raw(
					add_query_arg(
						array(
							'page'         => 'trade-dispatch',
							'trdsp_notice' => 'error',
						),
						admin_url( 'admin.php' )
					)
				)
			);
			exit;
		}
		$result = TRDSP_Jobs::save(
			array(
				'customer_id'      => (int) ( $job['customer_id'] ?? 0 ),
				'assigned_user_id' => (int) ( $job['assigned_user_id'] ?? 0 ),
				'title'            => sprintf(
					/* translators: %s original job title */
					__( '%s (copy)', 'trade-dispatch' ),
					(string) $job['title']
				),
				'status'           => 'scheduled',
				'scheduled_at'     => (string) ( $job['scheduled_at'] ?? '' ),
				'address_1'        => (string) ( $job['address_1'] ?? '' ),
				'city'             => (string) ( $job['city'] ?? '' ),
				'state'            => (string) ( $job['state'] ?? '' ),
				'postcode'         => (string) ( $job['postcode'] ?? '' ),
				'gate_notes'       => (string) ( $job['gate_notes'] ?? '' ),
				'hazard_notes'     => (string) ( $job['hazard_notes'] ?? '' ),
				'recurrence'       => (string) ( $job['recurrence'] ?? '' ),
			)
		);
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				esc_url_raw(
					add_query_arg(
						array(
							'page'         => 'trade-dispatch',
							'trdsp_notice' => 'error',
						),
						admin_url( 'admin.php' )
					)
				)
			);
			exit;
		}
		wp_safe_redirect(
			esc_url_raw(
				add_query_arg(
					array(
						'page'         => 'trade-dispatch',
						'trdsp_view'   => 'edit',
						'job_id'       => (int) $result,
						'trdsp_notice' => 'job_duplicated',
					),
					admin_url( 'admin.php' )
				)
			)
		);
		exit;
	}

	/**
	 * Today’s jobs on the WordPress dashboard.
	 */
	public function register_dashboard_widget() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'trdsp_today_jobs',
			__( 'Trade Dispatch — Today', 'trade-dispatch' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render today’s jobs widget.
	 */
	public function render_dashboard_widget() {
		$today = wp_date( 'Y-m-d' );
		$jobs  = TRDSP_Jobs::query(
			array(
				'from'  => $today . ' 00:00:00',
				'to'    => $today . ' 23:59:59',
				'limit' => 20,
			)
		);
		$all = add_query_arg(
			array(
				'page' => 'trade-dispatch',
			),
			admin_url( 'admin.php' )
		);
		$requests = add_query_arg(
			array(
				'page'         => 'trade-dispatch',
				'trdsp_status' => 'requested',
			),
			admin_url( 'admin.php' )
		);
		$requested = TRDSP_Jobs::query(
			array(
				'status' => 'requested',
				'limit'  => 100,
			)
		);
		echo '<p><a href="' . esc_url( $requests ) . '">' . esc_html(
			sprintf(
				/* translators: %d: booking requests */
				_n( '%d booking request', '%d booking requests', count( $requested ), 'trade-dispatch' ),
				count( $requested )
			)
		) . '</a></p>';
		if ( empty( $jobs ) ) {
			echo '<p>' . esc_html__( 'No jobs scheduled today.', 'trade-dispatch' ) . '</p>';
			echo '<p><a href="' . esc_url( $all ) . '">' . esc_html__( 'Open jobs', 'trade-dispatch' ) . '</a></p>';
			return;
		}
		$statuses = TRDSP_Jobs::statuses();
		echo '<ul>';
		foreach ( $jobs as $job ) {
			$edit = add_query_arg(
				array(
					'page'       => 'trade-dispatch',
					'trdsp_view' => 'edit',
					'job_id'     => (int) $job['id'],
				),
				admin_url( 'admin.php' )
			);
			$when = ! empty( $job['scheduled_at'] ) ? wp_date( get_option( 'time_format' ), strtotime( (string) $job['scheduled_at'] ) ) : '';
			$label = trim( $when . ' ' . (string) $job['title'] );
			echo '<li><a href="' . esc_url( $edit ) . '">' . esc_html( $label ) . '</a> — ' . esc_html( isset( $statuses[ $job['status'] ] ) ? $statuses[ $job['status'] ] : (string) $job['status'] ) . '</li>';
		}
		echo '</ul>';
		echo '<p><a href="' . esc_url( $all ) . '">' . esc_html__( 'Open jobs', 'trade-dispatch' ) . '</a></p>';
	}
}
