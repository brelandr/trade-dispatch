<?php
/**
 * Bookable services (quote hints only — no charges).
 *
 * @package Trade_Dispatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for wp_trdsp_services.
 */
class TRDSP_Services {

	/**
	 * Register hooks.
	 */
	public static function hooks() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 15 );
		add_action( 'admin_post_trdsp_save_service', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_trdsp_delete_service', array( __CLASS__, 'handle_delete' ) );
		add_filter( 'trdsp_job_overlap_minutes', array( __CLASS__, 'overlap_minutes' ), 10, 4 );
	}

	/**
	 * Use the job's service default duration for overlap warnings.
	 *
	 * @param int    $minutes      Default minutes.
	 * @param int    $user_id      Assignee.
	 * @param string $scheduled_at Datetime.
	 * @param int    $job_id       Job being saved.
	 * @return int
	 */
	public static function overlap_minutes( $minutes, $user_id, $scheduled_at, $job_id ) {
		unset( $user_id, $scheduled_at );
		$job_id = absint( $job_id );
		if ( $job_id < 1 || ! class_exists( 'TRDSP_Jobs' ) ) {
			return absint( $minutes );
		}
		$job = TRDSP_Jobs::get( $job_id );
		if ( ! $job || empty( $job['service_id'] ) ) {
			return absint( $minutes );
		}
		$service = self::get( (int) $job['service_id'] );
		if ( $service && (int) $service['default_minutes'] >= 15 ) {
			return (int) $service['default_minutes'];
		}
		return absint( $minutes );
	}

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'trdsp_services';
	}

	/**
	 * List services.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function query() {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table list.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY name ASC LIMIT 200', $table ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get one.
	 *
	 * @param int $id Service ID.
	 * @return array<string,mixed>|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$id = absint( $id );
		if ( $id < 1 ) {
			return null;
		}
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Submenu.
	 */
	public static function register_menu() {
		add_submenu_page(
			'trade-dispatch',
			__( 'Services', 'trade-dispatch' ),
			__( 'Services', 'trade-dispatch' ),
			'trdsp_manage_jobs',
			'trade-dispatch-services',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Save.
	 */
	public static function handle_save() {
		if ( ! isset( $_POST['trdsp_service_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['trdsp_service_nonce'] ) ), 'trdsp_save_service' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'trade-dispatch' ) );
		}
		if ( ! TRDSP_Roles::can_manage_office() ) {
			wp_die( esc_html__( 'Unauthorized.', 'trade-dispatch' ) );
		}
		global $wpdb;
		$id      = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
		$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$desc    = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$minutes = isset( $_POST['default_minutes'] ) ? absint( wp_unslash( $_POST['default_minutes'] ) ) : 60;
		$amount  = isset( $_POST['default_amount'] ) ? (float) sanitize_text_field( wp_unslash( $_POST['default_amount'] ) ) : 0;
		if ( '' === $name ) {
			wp_safe_redirect( esc_url_raw( add_query_arg( 'trdsp_notice', 'error', admin_url( 'admin.php?page=trade-dispatch-services' ) ) ) );
			exit;
		}
		$now = gmdate( 'Y-m-d H:i:s' );
		$row = array(
			'name'            => $name,
			'description'     => $desc,
			'default_minutes' => $minutes,
			'default_amount'  => number_format( $amount, 2, '.', '' ),
			'updated_at'      => $now,
		);
		if ( $id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update.
			$wpdb->update( self::table(), $row, array( 'id' => $id ), array( '%s', '%s', '%d', '%s', '%s' ), array( '%d' ) );
		} else {
			$row['created_at'] = $now;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert.
			$wpdb->insert( self::table(), $row, array( '%s', '%s', '%d', '%s', '%s', '%s' ) );
		}
		wp_safe_redirect( esc_url_raw( add_query_arg( 'trdsp_notice', 'saved', admin_url( 'admin.php?page=trade-dispatch-services' ) ) ) );
		exit;
	}

	/**
	 * Delete.
	 */
	public static function handle_delete() {
		if ( ! isset( $_POST['trdsp_service_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['trdsp_service_nonce'] ) ), 'trdsp_delete_service' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'trade-dispatch' ) );
		}
		if ( ! TRDSP_Roles::can_manage_office() ) {
			wp_die( esc_html__( 'Unauthorized.', 'trade-dispatch' ) );
		}
		global $wpdb;
		$id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
		if ( $id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete.
			$wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
		}
		wp_safe_redirect( esc_url_raw( add_query_arg( 'trdsp_notice', 'deleted', admin_url( 'admin.php?page=trade-dispatch-services' ) ) ) );
		exit;
	}

	/**
	 * Admin page.
	 */
	public static function render_page() {
		if ( ! TRDSP_Roles::can_manage_office() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'trade-dispatch' ) );
		}
		$edit_id = isset( $_GET['edit'] ) ? absint( wp_unslash( $_GET['edit'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- View only.
		$edit    = $edit_id > 0 ? self::get( $edit_id ) : null;
		echo '<div class="wrap"><h1>' . esc_html__( 'Services', 'trade-dispatch' ) . '</h1>';
		echo '<p>' . esc_html__( 'Optional list for the public booking form. Amounts are quote hints only — the free plugin does not charge cards.', 'trade-dispatch' ) . '</p>';
		echo '<h2>' . ( $edit ? esc_html__( 'Edit service', 'trade-dispatch' ) : esc_html__( 'New service', 'trade-dispatch' ) ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="trdsp_save_service" />';
		echo '<input type="hidden" name="id" value="' . esc_attr( (string) ( $edit ? $edit['id'] : 0 ) ) . '" />';
		wp_nonce_field( 'trdsp_save_service', 'trdsp_service_nonce' );
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th><label for="name">' . esc_html__( 'Name', 'trade-dispatch' ) . '</label></th><td><input required class="regular-text" id="name" name="name" value="' . esc_attr( $edit ? (string) $edit['name'] : '' ) . '" /></td></tr>';
		echo '<tr><th><label for="description">' . esc_html__( 'Description', 'trade-dispatch' ) . '</label></th><td><textarea class="large-text" rows="3" id="description" name="description">' . esc_textarea( $edit ? (string) $edit['description'] : '' ) . '</textarea></td></tr>';
		echo '<tr><th><label for="default_minutes">' . esc_html__( 'Default minutes', 'trade-dispatch' ) . '</label></th><td><input id="default_minutes" name="default_minutes" type="number" min="1" step="1" value="' . esc_attr( $edit ? (string) $edit['default_minutes'] : '60' ) . '" /></td></tr>';
		echo '<tr><th><label for="default_amount">' . esc_html__( 'Default quote amount', 'trade-dispatch' ) . '</label></th><td><input id="default_amount" name="default_amount" value="' . esc_attr( $edit ? (string) $edit['default_amount'] : '0.00' ) . '" /></td></tr>';
		echo '</table>';
		submit_button( __( 'Save service', 'trade-dispatch' ) );
		echo '</form>';
		echo '<h2>' . esc_html__( 'All services', 'trade-dispatch' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Name', 'trade-dispatch' ) . '</th><th>' . esc_html__( 'Minutes', 'trade-dispatch' ) . '</th><th>' . esc_html__( 'Amount', 'trade-dispatch' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( self::query() as $row ) {
			$edit_url = add_query_arg(
				array(
					'page' => 'trade-dispatch-services',
					'edit' => (int) $row['id'],
				),
				admin_url( 'admin.php' )
			);
			echo '<tr><td><a href="' . esc_url( $edit_url ) . '">' . esc_html( (string) $row['name'] ) . '</a></td>';
			echo '<td>' . esc_html( (string) $row['default_minutes'] ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( (float) $row['default_amount'], 2 ) ) . '</td><td>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline;">';
			echo '<input type="hidden" name="action" value="trdsp_delete_service" />';
			echo '<input type="hidden" name="id" value="' . esc_attr( (string) $row['id'] ) . '" />';
			wp_nonce_field( 'trdsp_delete_service', 'trdsp_service_nonce' );
			submit_button( __( 'Delete', 'trade-dispatch' ), 'delete small', 'submit', false );
			echo '</form></td></tr>';
		}
		echo '</tbody></table></div>';
	}
}
