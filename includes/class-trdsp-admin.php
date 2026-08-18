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
		);
		if ( is_array( $input ) ) {
			$clean['delete_data_on_uninstall'] = ! empty( $input['delete_data_on_uninstall'] );
			if ( isset( $input['notify_email'] ) ) {
				$clean['notify_email'] = sanitize_email( wp_unslash( $input['notify_email'] ) );
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
		$this->render_jobs_list();
	}

	/**
	 * Jobs list with filters.
	 */
	protected function render_jobs_list() {
		$status = isset( $_GET['trdsp_status'] ) ? sanitize_key( wp_unslash( $_GET['trdsp_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filter.
		$from   = isset( $_GET['trdsp_from'] ) ? sanitize_text_field( wp_unslash( $_GET['trdsp_from'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filter.
		$to     = isset( $_GET['trdsp_to'] ) ? sanitize_text_field( wp_unslash( $_GET['trdsp_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filter.
		$jobs   = TRDSP_Jobs::query(
			array(
				'status' => $status,
				'from'   => $from ? $from . ' 00:00:00' : '',
				'to'     => $to ? $to . ' 23:59:59' : '',
				'limit'  => 100,
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
		echo '<form class="trdsp-filters" method="get">';
		echo '<input type="hidden" name="page" value="trade-dispatch" />';
		echo '<label class="screen-reader-text" for="trdsp_status">' . esc_html__( 'Status', 'trade-dispatch' ) . '</label>';
		echo '<select id="trdsp_status" name="trdsp_status"><option value="">' . esc_html__( 'All statuses', 'trade-dispatch' ) . '</option>';
		foreach ( TRDSP_Jobs::statuses() as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $status, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select> ';
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
			echo '<tr>';
			echo '<td>' . esc_html( $when ) . '</td>';
			echo '<td>' . esc_html( (string) $job['title'] ) . '</td>';
			echo '<td>' . esc_html( $customer ? (string) $customer['name'] : '—' ) . '</td>';
			echo '<td>' . esc_html( $crew ? $crew->display_name : '—' ) . '</td>';
			echo '<td>' . esc_html( isset( $statuses[ $job['status'] ] ) ? $statuses[ $job['status'] ] : (string) $job['status'] ) . '</td>';
			echo '<td class="trdsp-actions"><a href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit', 'trade-dispatch' ) . '</a>';
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
		$job        = $job ? $job : array();
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
		echo '</form></div>';
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
		echo '</form></div>';
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
		echo '<div class="wrap trdsp-wrap">';
		echo '<h1>' . esc_html__( 'Trade Dispatch Settings', 'trade-dispatch' ) . '</h1>';
		echo '<form method="post" action="options.php">';
		settings_fields( 'trdsp_settings_group' );
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row"><label for="trdsp_notify_email">' . esc_html__( 'Office notification email', 'trade-dispatch' ) . '</label></th><td>';
		echo '<input type="email" class="regular-text" id="trdsp_notify_email" name="trdsp_settings[notify_email]" value="' . esc_attr( $notify ) . '" />';
		echo '<p class="description">' . esc_html__( 'Booking and job-complete emails are sent with WordPress mail. Leave blank to use the site admin email.', 'trade-dispatch' ) . '</p></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Shortcodes', 'trade-dispatch' ) . '</th><td>';
		echo '<p><code>[trdsp_booking]</code> — ' . esc_html__( 'public booking form', 'trade-dispatch' ) . '</p>';
		echo '<p><code>[trdsp_portal]</code> — ' . esc_html__( 'customer portal (logged-in users, matched by email)', 'trade-dispatch' ) . '</p>';
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
}
