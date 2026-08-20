<?php
/**
 * Customer repository.
 *
 * @package Trade_Dispatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for wp_trdsp_customers.
 */
class TRDSP_Customers {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'trdsp_customers';
	}

	/**
	 * Get one customer.
	 *
	 * @param int $id Customer ID.
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
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, $id ),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Find by email.
	 *
	 * @param string $email Email.
	 * @return array<string,mixed>|null
	 */
	public static function get_by_email( $email ) {
		global $wpdb;
		$email = sanitize_email( $email );
		if ( '' === $email ) {
			return null;
		}
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup.
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE email = %s ORDER BY id ASC LIMIT 1', $table, $email ),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/**
	 * List customers.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return array<int,array<string,mixed>>
	 */
	public static function query( $args = array() ) {
		global $wpdb;
		$args  = wp_parse_args(
			$args,
			array(
				'search' => '',
				'limit'  => 50,
				'offset' => 0,
			)
		);
		$table = self::table();
		$limit = min( 200, max( 1, absint( $args['limit'] ) ) );
		$offset = max( 0, absint( $args['offset'] ) );
		$search = sanitize_text_field( (string) $args['search'] );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE name LIKE %s OR email LIKE %s OR phone LIKE %s ORDER BY name ASC, id DESC LIMIT %d OFFSET %d',
					$table,
					$like,
					$like,
					$like,
					$limit,
					$offset
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i ORDER BY name ASC, id DESC LIMIT %d OFFSET %d',
					$table,
					$limit,
					$offset
				),
				ARRAY_A
			);
		}
		// phpcs:enable
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count customers.
	 *
	 * @param string $search Optional search.
	 * @return int
	 */
	public static function count( $search = '' ) {
		global $wpdb;
		$table  = self::table();
		$search = sanitize_text_field( $search );
		if ( '' === $search ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Count; table via %i.
			return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
		}
		$like = '%' . $wpdb->esc_like( $search ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Count with search.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE name LIKE %s OR email LIKE %s OR phone LIKE %s',
				$table,
				$like,
				$like,
				$like
			)
		);
	}

	/**
	 * Insert or update.
	 *
	 * @param array<string,mixed> $data Customer data.
	 * @return int|\WP_Error
	 */
	public static function save( $data ) {
		global $wpdb;
		$now  = gmdate( 'Y-m-d H:i:s' );
		$id   = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
		$row  = array(
			'name'      => sanitize_text_field( (string) ( $data['name'] ?? '' ) ),
			'email'     => sanitize_email( (string) ( $data['email'] ?? '' ) ),
			'phone'     => sanitize_text_field( (string) ( $data['phone'] ?? '' ) ),
			'address_1' => sanitize_text_field( (string) ( $data['address_1'] ?? '' ) ),
			'city'      => sanitize_text_field( (string) ( $data['city'] ?? '' ) ),
			'state'     => sanitize_text_field( (string) ( $data['state'] ?? '' ) ),
			'postcode'  => sanitize_text_field( (string) ( $data['postcode'] ?? '' ) ),
			'notes'     => sanitize_textarea_field( (string) ( $data['notes'] ?? '' ) ),
			'updated_at'=> $now,
		);
		if ( '' === $row['name'] ) {
			return new WP_Error( 'trdsp_customer_name', __( 'Customer name is required.', 'trade-dispatch' ) );
		}

		if ( $id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update.
			$updated = $wpdb->update( self::table(), $row, array( 'id' => $id ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ), array( '%d' ) );
			if ( false === $updated ) {
				return new WP_Error( 'trdsp_customer_update', __( 'Could not update customer.', 'trade-dispatch' ) );
			}
			/**
			 * Fires after a customer is saved.
			 *
			 * @param int   $id   Customer ID.
			 * @param array $row  Saved row.
			 */
			do_action( 'trdsp_after_customer_save', $id, $row );
			return $id;
		}

		$row['created_at'] = $now;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert.
		$inserted = $wpdb->insert(
			self::table(),
			$row,
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( false === $inserted ) {
			return new WP_Error( 'trdsp_customer_insert', __( 'Could not create customer.', 'trade-dispatch' ) );
		}
		$id = (int) $wpdb->insert_id;
		do_action( 'trdsp_after_customer_save', $id, $row );
		return $id;
	}

	/**
	 * Delete a customer.
	 *
	 * @param int $id Customer ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$id = absint( $id );
		if ( $id < 1 ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete.
		$deleted = $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
		return false !== $deleted;
	}
}
