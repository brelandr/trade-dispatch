<?php
/**
 * Field service jobs, customers, scheduling, and a client web portal.
 *
 * @package Trade_Dispatch
 *
 * Plugin Name: Trade Dispatch
 * Description: Field service management for small trades: customers, jobs, scheduling, and a client portal on your own WordPress site.
 * Version: 0.2.0
 * Author: Land Tech Web Designs, Corp
 * Author URI: https://landtechwebdesigns.com
 * Plugin URI: https://tradedispatch.app
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: trade-dispatch
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'TRDSP_VERSION' ) ) {
	define( 'TRDSP_VERSION', '0.2.0' );
}
if ( ! defined( 'TRDSP_DB_VERSION' ) ) {
	define( 'TRDSP_DB_VERSION', '1' );
}
if ( ! defined( 'TRDSP_PLUGIN_FILE' ) ) {
	define( 'TRDSP_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'TRDSP_PLUGIN_DIR' ) ) {
	define( 'TRDSP_PLUGIN_DIR', plugin_dir_path( TRDSP_PLUGIN_FILE ) );
}
if ( ! defined( 'TRDSP_PLUGIN_URL' ) ) {
	define( 'TRDSP_PLUGIN_URL', plugins_url( '/', TRDSP_PLUGIN_FILE ) );
}

require_once TRDSP_PLUGIN_DIR . 'includes/class-trdsp-activator.php';
require_once TRDSP_PLUGIN_DIR . 'includes/class-trdsp-customers.php';
require_once TRDSP_PLUGIN_DIR . 'includes/class-trdsp-jobs.php';
require_once TRDSP_PLUGIN_DIR . 'includes/class-trdsp-mail.php';
require_once TRDSP_PLUGIN_DIR . 'includes/class-trdsp-privacy.php';
require_once TRDSP_PLUGIN_DIR . 'includes/class-trdsp-booking.php';
require_once TRDSP_PLUGIN_DIR . 'includes/class-trdsp-portal.php';
require_once TRDSP_PLUGIN_DIR . 'includes/class-trdsp-rest.php';
require_once TRDSP_PLUGIN_DIR . 'includes/class-trdsp-plugin.php';
require_once TRDSP_PLUGIN_DIR . 'includes/class-trdsp-admin.php';

register_activation_hook( TRDSP_PLUGIN_FILE, array( 'TRDSP_Activator', 'activate' ) );
register_deactivation_hook( TRDSP_PLUGIN_FILE, 'trdsp_deactivate' );

/**
 * Clear scheduled events on deactivation. Data stays until uninstall.
 */
function trdsp_deactivate() {
	wp_clear_scheduled_hook( 'trdsp_cron_recurring_jobs' );
}

add_action( 'plugins_loaded', array( 'TRDSP_Plugin', 'instance' ) );
