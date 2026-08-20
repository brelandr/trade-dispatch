<?php
/**
 * Customer, Employee, and Dispatcher roles.
 *
 * @package Trade_Dispatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers roles and capability helpers. Checks use caps, never role names.
 */
class TRDSP_Roles {

	const VERSION = '1';

	/**
	 * Hook listeners.
	 */
	public static function hooks() {
		self::maybe_register();
		add_action( 'show_user_profile', array( __CLASS__, 'render_user_roles' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'render_user_roles' ) );
		add_action( 'user_new_form', array( __CLASS__, 'render_user_roles_new' ) );
		add_action( 'profile_update', array( __CLASS__, 'save_user_roles' ) );
		add_action( 'user_register', array( __CLASS__, 'save_user_roles' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect_admin' ) );
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 10, 3 );
		add_filter( 'show_admin_bar', array( __CLASS__, 'maybe_hide_admin_bar' ) );
		add_filter( 'option_page_capability_trdsp_settings_group', array( __CLASS__, 'settings_page_capability' ) );
	}

	/**
	 * Role slugs we own.
	 *
	 * @return array<string,string>
	 */
	public static function role_slugs() {
		return array(
			'trdsp_customer'   => __( 'Customer', 'trade-dispatch' ),
			'trdsp_employee'   => __( 'Employee', 'trade-dispatch' ),
			'trdsp_dispatcher' => __( 'Dispatcher', 'trade-dispatch' ),
		);
	}

	/**
	 * Plugin-owned capabilities granted to administrator.
	 *
	 * @return array<int,string>
	 */
	public static function plugin_caps() {
		return array(
			'trdsp_portal',
			'trdsp_access',
			'trdsp_edit_own_jobs',
			'trdsp_manage_jobs',
			'trdsp_manage_customers',
			'trdsp_manage_estimates',
			'trdsp_manage_settings',
		);
	}

	/**
	 * Create roles and grant administrator caps when the version changes.
	 */
	public static function maybe_register() {
		if ( (string) get_option( 'trdsp_roles_version', '' ) === self::VERSION ) {
			if ( get_role( 'trdsp_customer' ) ) {
				return;
			}
		}
		self::register();
	}

	/**
	 * Add or refresh roles.
	 */
	public static function register() {
		add_role(
			'trdsp_customer',
			__( 'Customer', 'trade-dispatch' ),
			array(
				'read'         => true,
				'trdsp_portal' => true,
			)
		);
		add_role(
			'trdsp_employee',
			__( 'Employee', 'trade-dispatch' ),
			array(
				'read'                => true,
				'trdsp_access'        => true,
				'trdsp_edit_own_jobs' => true,
			)
		);
		add_role(
			'trdsp_dispatcher',
			__( 'Dispatcher', 'trade-dispatch' ),
			array(
				'read'                   => true,
				'trdsp_access'           => true,
				'trdsp_manage_jobs'      => true,
				'trdsp_manage_customers' => true,
				'trdsp_manage_estimates' => true,
				'trdsp_manage_settings'  => true,
			)
		);
		$defs = array(
			'trdsp_customer'   => array( 'read', 'trdsp_portal' ),
			'trdsp_employee'   => array( 'read', 'trdsp_access', 'trdsp_edit_own_jobs' ),
			'trdsp_dispatcher' => array( 'read', 'trdsp_access', 'trdsp_manage_jobs', 'trdsp_manage_customers', 'trdsp_manage_estimates', 'trdsp_manage_settings' ),
		);
		foreach ( $defs as $slug => $caps ) {
			$role = get_role( $slug );
			if ( ! $role ) {
				continue;
			}
			foreach ( $caps as $cap ) {
				$role->add_cap( $cap );
			}
		}
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( self::plugin_caps() as $cap ) {
				$admin->add_cap( $cap );
			}
		}
		update_option( 'trdsp_roles_version', self::VERSION, false );
	}

	/**
	 * Remove roles and administrator caps (uninstall only).
	 */
	public static function uninstall() {
		foreach ( array_keys( self::role_slugs() ) as $slug ) {
			remove_role( $slug );
		}
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( self::plugin_caps() as $cap ) {
				$admin->remove_cap( $cap );
			}
		}
		delete_option( 'trdsp_roles_version' );
	}

	/**
	 * Can open Trade Dispatch admin.
	 *
	 * @param int $user_id User ID or 0 for current.
	 * @return bool
	 */
	public static function can_access( $user_id = 0 ) {
		return self::user_can( $user_id, 'manage_options' ) || self::user_can( $user_id, 'trdsp_access' );
	}

	/**
	 * Full office (all jobs, customers, estimates).
	 *
	 * @param int $user_id User ID or 0 for current.
	 * @return bool
	 */
	public static function can_manage_office( $user_id = 0 ) {
		return self::user_can( $user_id, 'manage_options' ) || self::user_can( $user_id, 'trdsp_manage_jobs' );
	}

	/**
	 * Customers screens.
	 *
	 * @param int $user_id User ID or 0 for current.
	 * @return bool
	 */
	public static function can_manage_customers( $user_id = 0 ) {
		return self::user_can( $user_id, 'manage_options' ) || self::user_can( $user_id, 'trdsp_manage_customers' );
	}

	/**
	 * Estimates screens.
	 *
	 * @param int $user_id User ID or 0 for current.
	 * @return bool
	 */
	public static function can_manage_estimates( $user_id = 0 ) {
		return self::user_can( $user_id, 'manage_options' ) || self::user_can( $user_id, 'trdsp_manage_estimates' );
	}

	/**
	 * Trade Dispatch settings (not WP Settings).
	 *
	 * @param int $user_id User ID or 0 for current.
	 * @return bool
	 */
	public static function can_manage_settings( $user_id = 0 ) {
		return self::user_can( $user_id, 'manage_options' ) || self::user_can( $user_id, 'trdsp_manage_settings' );
	}

	/**
	 * Assigned-job field user without office caps.
	 *
	 * @param int $user_id User ID or 0 for current.
	 * @return bool
	 */
	public static function is_field_only( $user_id = 0 ) {
		return self::user_can( $user_id, 'trdsp_edit_own_jobs' ) && ! self::can_manage_office( $user_id );
	}

	/**
	 * Portal-only account (no Trade Dispatch admin).
	 *
	 * @param int $user_id User ID or 0 for current.
	 * @return bool
	 */
	public static function is_customer_only( $user_id = 0 ) {
		if ( self::can_access( $user_id ) ) {
			return false;
		}
		return self::user_can( $user_id, 'trdsp_portal' );
	}

	/**
	 * Whether the user may view or update this job.
	 *
	 * @param array<string,mixed>|null $job     Job row.
	 * @param int                      $user_id User ID or 0 for current.
	 * @return bool
	 */
	public static function can_edit_job( $job, $user_id = 0 ) {
		if ( self::can_manage_office( $user_id ) ) {
			return true;
		}
		if ( ! is_array( $job ) || ! self::user_can( $user_id, 'trdsp_edit_own_jobs' ) ) {
			return false;
		}
		$uid = $user_id > 0 ? absint( $user_id ) : get_current_user_id();
		return (int) ( $job['assigned_user_id'] ?? 0 ) === $uid;
	}

	/**
	 * options.php capability for Trade Dispatch settings.
	 *
	 * @return string
	 */
	public static function settings_page_capability() {
		return 'trdsp_manage_settings';
	}

	/**
	 * Extra-role checkboxes on Add User (no user object yet).
	 *
	 * @param string $context newuser / add-existing-user.
	 */
	public static function render_user_roles_new( $context ) {
		unset( $context );
		if ( ! current_user_can( 'promote_users' ) ) {
			return;
		}
		self::render_role_checkboxes( null );
	}

	/**
	 * Extra-role checkboxes on profile.
	 *
	 * @param \WP_User $user User.
	 */
	public static function render_user_roles( $user ) {
		if ( ! current_user_can( 'promote_users' ) ) {
			return;
		}
		self::render_role_checkboxes( $user );
	}

	/**
	 * Checkbox table.
	 *
	 * @param \WP_User|null $user User or null on Add User.
	 */
	protected static function render_role_checkboxes( $user ) {
		echo '<h2>' . esc_html__( 'Trade Dispatch roles', 'trade-dispatch' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Check a Trade Dispatch role here. Leave the WordPress Role dropdown as Administrator, Editor, or Subscriber — that dropdown replaces all roles if you pick Employee or Dispatcher there.', 'trade-dispatch' ) . '</p>';
		wp_nonce_field( 'trdsp_user_roles', 'trdsp_user_roles_nonce' );
		echo '<table class="form-table" role="presentation">';
		foreach ( self::role_slugs() as $slug => $label ) {
			$has = ( $user instanceof WP_User ) && in_array( $slug, (array) $user->roles, true );
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>';
			echo '<label><input type="checkbox" name="trdsp_extra_roles[' . esc_attr( $slug ) . ']" value="1" ' . checked( $has, true, false ) . ' /> ';
			echo esc_html( $label ) . '</label></td></tr>';
		}
		echo '</table>';
	}

	/**
	 * Add or remove extra roles after core set_role() on profile save.
	 *
	 * Must run on profile_update / user_register (after wp_update_user), not on
	 * edit_user_profile_update — that fires first and core then wipes extras.
	 *
	 * @param int $user_id User ID.
	 */
	public static function save_user_roles( $user_id ) {
		$user_id = absint( $user_id );
		if ( $user_id < 1 ) {
			return;
		}
		if ( ! isset( $_POST['trdsp_user_roles_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['trdsp_user_roles_nonce'] ) ), 'trdsp_user_roles' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'trade-dispatch' ) );
		}
		if ( ! current_user_can( 'promote_users' ) || ! current_user_can( 'edit_user', $user_id ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'trade-dispatch' ) );
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}
		$posted = isset( $_POST['trdsp_extra_roles'] ) && is_array( $_POST['trdsp_extra_roles'] ) ? map_deep( wp_unslash( $_POST['trdsp_extra_roles'] ), 'sanitize_key' ) : array();
		foreach ( array_keys( self::role_slugs() ) as $slug ) {
			$want = ! empty( $posted[ $slug ] );
			$has  = in_array( $slug, (array) $user->roles, true );
			if ( $want && ! $has ) {
				$user->add_role( $slug );
			}
			if ( ! $want && $has ) {
				$user->remove_role( $slug );
			}
		}
		$user = get_userdata( $user_id );
		if ( $user && empty( $user->roles ) ) {
			$user->add_role( 'subscriber' );
		}
	}

	/**
	 * Keep customer-only users out of wp-admin; send field users to Jobs.
	 */
	public static function maybe_redirect_admin() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		global $pagenow;
		$page = isset( $pagenow ) ? (string) $pagenow : '';
		if ( in_array( $page, array( 'admin-ajax.php', 'admin-post.php' ), true ) ) {
			return;
		}
		if ( self::is_customer_only() ) {
			$portal = class_exists( 'TRDSP_Portal' ) ? TRDSP_Portal::url() : home_url( '/' );
			wp_safe_redirect( esc_url_raw( $portal ) );
			exit;
		}
		if ( ! self::is_field_only() && ! ( self::can_manage_office() && ! current_user_can( 'edit_posts' ) && ! current_user_can( 'manage_options' ) ) ) {
			return;
		}
		if ( 'profile.php' === $page ) {
			return;
		}
		$screen_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Redirect target.
		if ( '' !== $screen_page && 0 === strpos( $screen_page, 'trade-dispatch' ) ) {
			return;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=trade-dispatch' ) );
		exit;
	}

	/**
	 * After login, send customers home and field/office staff to Jobs.
	 *
	 * @param string           $redirect Redirect URL.
	 * @param string           $requested Requested redirect.
	 * @param \WP_User|\WP_Error $user    User.
	 * @return string
	 */
	public static function login_redirect( $redirect, $requested, $user ) {
		unset( $requested );
		if ( ! $user instanceof WP_User ) {
			return $redirect;
		}
		if ( self::is_customer_only( $user->ID ) ) {
			return class_exists( 'TRDSP_Portal' ) ? TRDSP_Portal::url() : home_url( '/' );
		}
		if ( self::can_access( $user->ID ) && ! user_can( $user, 'edit_posts' ) && ! user_can( $user, 'manage_options' ) ) {
			return admin_url( 'admin.php?page=trade-dispatch' );
		}
		return $redirect;
	}

	/**
	 * Hide the admin bar for portal-only customers.
	 *
	 * @param bool $show Current.
	 * @return bool
	 */
	public static function maybe_hide_admin_bar( $show ) {
		if ( self::is_customer_only() ) {
			return false;
		}
		return $show;
	}

	/**
	 * Capability check for a user id (0 = current).
	 *
	 * @param int    $user_id User ID.
	 * @param string $cap     Capability.
	 * @return bool
	 */
	protected static function user_can( $user_id, $cap ) {
		$user_id = absint( $user_id );
		$cap     = sanitize_key( (string) $cap );
		if ( $user_id > 0 ) {
			return user_can( $user_id, $cap );
		}
		return current_user_can( $cap );
	}
}
