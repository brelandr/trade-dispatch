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
);

foreach ( $trdsp_tables as $trdsp_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstall DROP; table from trusted prefix + fixed slug.
	$wpdb->query( "DROP TABLE IF EXISTS `{$trdsp_table}`" );
}

delete_option( 'trdsp_settings' );
delete_option( 'trdsp_db_version' );
wp_clear_scheduled_hook( 'trdsp_cron_recurring_jobs' );
