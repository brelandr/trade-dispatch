<?php
/**
 * Main plugin bootstrap.
 *
 * @package Trade_Dispatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads admin, privacy, booking, portal, and schema upgrades.
 */
class TRDSP_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var TRDSP_Plugin|null
	 */
	protected static $instance = null;

	/**
	 * Admin controller.
	 *
	 * @var TRDSP_Admin|null
	 */
	protected $admin = null;

	/**
	 * Get singleton.
	 *
	 * @return TRDSP_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hook runtime.
	 */
	private function __construct() {
		$this->maybe_upgrade_schema();
		$this->maybe_schedule_cron();
		add_action( 'admin_init', array( $this, 'register_privacy' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_public_assets' ) );
		add_action( 'trdsp_cron_recurring_jobs', array( 'TRDSP_Jobs', 'generate_due_occurrences' ) );
		TRDSP_Mail::hooks();
		TRDSP_Privacy::hooks();
		TRDSP_Booking::hooks();
		TRDSP_Portal::hooks();
		TRDSP_REST::hooks();
		if ( is_admin() ) {
			$this->admin = new TRDSP_Admin();
			$this->admin->hooks();
		}
	}

	/**
	 * Register public stylesheet.
	 */
	public function register_public_assets() {
		wp_register_style(
			'trdsp-public',
			TRDSP_PLUGIN_URL . 'assets/css/trdsp-public.css',
			array(),
			TRDSP_VERSION
		);
	}

	/**
	 * Schedule daily recurrence cron once.
	 */
	protected function maybe_schedule_cron() {
		if ( ! wp_next_scheduled( 'trdsp_cron_recurring_jobs' ) ) {
			wp_schedule_event( time(), 'daily', 'trdsp_cron_recurring_jobs' );
		}
	}

	/**
	 * Upgrade tables when the schema version changes.
	 */
	protected function maybe_upgrade_schema() {
		$installed = get_option( 'trdsp_db_version', '' );
		if ( (string) $installed !== (string) TRDSP_DB_VERSION ) {
			TRDSP_Activator::create_tables();
		}
	}

	/**
	 * Suggest privacy policy language for stored customer data.
	 */
	public function register_privacy() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$content = '<p>' . esc_html__( 'Trade Dispatch stores customer names, contact details, job addresses, service notes, and estimates on this site so the business can schedule and complete field work. Booking form submissions are emailed to the site owner with WordPress mail. Data stays on this WordPress installation unless the site owner connects a separately installed premium add-on.', 'trade-dispatch' ) . '</p>';
		wp_add_privacy_policy_content( 'Trade Dispatch', wp_kses_post( $content ) );
	}
}
