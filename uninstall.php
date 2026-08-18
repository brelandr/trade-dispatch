<?php
/**
 * Uninstall cleanup for Trade Dispatch.
 *
 * Drops custom tables only when the site owner opted in under Settings.
 *
 * @package Trade_Dispatch
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$trdsp_settings = get_option( 'trdsp_settings', array() );
if ( empty( $trdsp_settings['delete_data_on_uninstall'] ) ) {
	return;
}

global $wpdb;

$trdsp_tables = array(
	$wpdb->prefix . 'trdsp_customers',
	$wpdb->prefix . 'trdsp_jobs',
	$wpdb->prefix . 'trdsp_estimates',
	$wpdb->prefix . 'trdsp_job_notes',
	$wpdb->prefix . 'trdsp_services',
);

foreach ( $trdsp_tables as $trdsp_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstall DROP; table from trusted prefix + fixed slug.
	$wpdb->query( "DROP TABLE IF EXISTS `{$trdsp_table}`" );
}

delete_option( 'trdsp_settings' );
delete_option( 'trdsp_db_version' );
delete_option( 'trdsp_preferred_times' );
delete_option( 'trdsp_estimate_requests' );
delete_option( 'trdsp_email_templates' );
delete_option( 'trdsp_roles_version' );
wp_clear_scheduled_hook( 'trdsp_cron_recurring_jobs' );

$trdsp_admin = get_role( 'administrator' );
if ( $trdsp_admin ) {
	foreach ( array( 'trdsp_portal', 'trdsp_access', 'trdsp_edit_own_jobs', 'trdsp_manage_jobs', 'trdsp_manage_customers', 'trdsp_manage_estimates', 'trdsp_manage_settings' ) as $trdsp_cap ) {
		$trdsp_admin->remove_cap( $trdsp_cap );
	}
}
remove_role( 'trdsp_customer' );
remove_role( 'trdsp_employee' );
remove_role( 'trdsp_dispatcher' );
