<?php
/**
 * Job notes.
 *
 * @package Trade_Dispatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for wp_trdsp_job_notes.
 */
class TRDSP_Notes {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'trdsp_job_notes';
	}

	/**
	 * Notes for a job.
	 *
	 * @param int $job_id Job ID.
	 * @return array<int,array<string,mixed>>
	 */
	public static function for_job( $job_id ) {
		global $wpdb;
		$job_id = absint( $job_id );
		if ( $job_id < 1 ) {
			return array();
		}
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table list.
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE job_id = %d ORDER BY id DESC LIMIT 100", $job_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table from prefix + fixed slug.
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Add a note.
	 *
	 * @param int    $job_id Job ID.
	 * @param string $note   Note text.
	 * @param int    $user_id User ID.
	 * @return int|\WP_Error
	 */
	public static function add( $job_id, $note, $user_id = 0 ) {
		global $wpdb;
		$job_id = absint( $job_id );
		$note   = sanitize_textarea_field( $note );
		if ( $job_id < 1 || '' === $note ) {
			return new WP_Error( 'trdsp_note', __( 'A note is required.', 'trade-dispatch' ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert.
		$inserted = $wpdb->insert(
			self::table(),
			array(
				'job_id'     => $job_id,
				'user_id'    => absint( $user_id ),
				'note'       => $note,
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%d', '%s', '%s' )
		);
		if ( false === $inserted ) {
			return new WP_Error( 'trdsp_note_insert', __( 'Could not save note.', 'trade-dispatch' ) );
		}
		$id = (int) $wpdb->insert_id;
		/**
		 * Fires after a job note is added.
		 *
		 * @param int $id     Note ID.
		 * @param int $job_id Job ID.
		 */
		do_action( 'trdsp_after_job_note_save', $id, $job_id );
		return $id;
	}

	/**
	 * Delete notes for a job.
	 *
	 * @param int $job_id Job ID.
	 */
	public static function delete_for_job( $job_id ) {
		global $wpdb;
		$job_id = absint( $job_id );
		if ( $job_id < 1 ) {
			return;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete.
		$wpdb->delete( self::table(), array( 'job_id' => $job_id ), array( '%d' ) );
	}
}
