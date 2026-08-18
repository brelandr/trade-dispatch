<?php
/**
 * Activation and schema upgrades.
 *
 * @package Trade_Dispatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates custom tables and default options.
 */
class TRDSP_Activator {

	/**
	 * Run on plugin activation.
	 */
	public static function activate() {
		self::create_tables();
		add_option(
			'trdsp_settings',
			array(
				'delete_data_on_uninstall' => false,
				'notify_email'             => '',
				'business_name'            => '',
			),
			'',
			false
		);
		add_option( 'trdsp_db_version', TRDSP_DB_VERSION );
		if ( ! wp_next_scheduled( 'trdsp_cron_recurring_jobs' ) ) {
			wp_schedule_event( time(), 'daily', 'trdsp_cron_recurring_jobs' );
		}
	}

	/**
	 * Create or upgrade custom tables with dbDelta.
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$customers       = $wpdb->prefix . 'trdsp_customers';
		$jobs            = $wpdb->prefix . 'trdsp_jobs';
		$estimates       = $wpdb->prefix . 'trdsp_estimates';
		$notes           = $wpdb->prefix . 'trdsp_job_notes';
		$services        = $wpdb->prefix . 'trdsp_services';

		$sql = "CREATE TABLE {$customers} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL DEFAULT '',
			email varchar(191) NOT NULL DEFAULT '',
			phone varchar(50) NOT NULL DEFAULT '',
			address_1 varchar(191) NOT NULL DEFAULT '',
			city varchar(100) NOT NULL DEFAULT '',
			state varchar(100) NOT NULL DEFAULT '',
			postcode varchar(20) NOT NULL DEFAULT '',
			notes longtext NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY email (email)
		) {$charset_collate};

		CREATE TABLE {$jobs} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			customer_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			assigned_user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			title varchar(191) NOT NULL DEFAULT '',
			status varchar(32) NOT NULL DEFAULT 'scheduled',
			scheduled_at datetime NULL,
			address_1 varchar(191) NOT NULL DEFAULT '',
			city varchar(100) NOT NULL DEFAULT '',
			state varchar(100) NOT NULL DEFAULT '',
			postcode varchar(20) NOT NULL DEFAULT '',
			gate_notes text NULL,
			hazard_notes text NULL,
			recurrence varchar(32) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY customer_id (customer_id),
			KEY assigned_user_id (assigned_user_id),
			KEY status (status),
			KEY scheduled_at (scheduled_at)
		) {$charset_collate};

		CREATE TABLE {$estimates} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			customer_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			job_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			title varchar(191) NOT NULL DEFAULT '',
			amount decimal(12,2) NOT NULL DEFAULT 0.00,
			status varchar(32) NOT NULL DEFAULT 'draft',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY customer_id (customer_id),
			KEY job_id (job_id)
		) {$charset_collate};

		CREATE TABLE {$notes} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			job_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			note longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY job_id (job_id)
		) {$charset_collate};

		CREATE TABLE {$services} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL DEFAULT '',
			description text NOT NULL,
			default_minutes int(11) UNSIGNED NOT NULL DEFAULT 60,
			default_amount decimal(12,2) NOT NULL DEFAULT 0.00,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id)
		) {$charset_collate};";

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		dbDelta( $sql );

		update_option( 'trdsp_db_version', TRDSP_DB_VERSION );
	}
}
