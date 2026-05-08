<?php
/**
 * Booking Audit Log
 *
 * Stores booking status changes in a custom table and provides helpers
 * to write/read audit entries.
 *
 * @package obsidian-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the audit log table name.
 *
 * @return string
 */
function obsidian_get_audit_table_name() {
	global $wpdb;

	return $wpdb->prefix . 'obsidian_booking_audit';
}

/**
 * Create or update the audit log table.
 *
 * @return void
 */
function obsidian_create_audit_table() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table_name      = obsidian_get_audit_table_name();
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table_name} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		booking_id bigint(20) unsigned NOT NULL,
		user_id bigint(20) unsigned DEFAULT NULL,
		user_role varchar(100) DEFAULT NULL,
		source varchar(50) DEFAULT NULL,
		action varchar(50) NOT NULL,
		from_status varchar(50) DEFAULT NULL,
		to_status varchar(50) DEFAULT NULL,
		notes text DEFAULT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY booking_id (booking_id),
		KEY created_at (created_at)
	) {$charset_collate};";

	dbDelta( $sql );
	update_option( 'obsidian_booking_audit_db_version', '2' );
}

/**
 * Ensure the audit table exists.
 *
 * @return void
 */
function obsidian_maybe_create_audit_table() {
	global $wpdb;

	$version    = get_option( 'obsidian_booking_audit_db_version' );
	$table_name = obsidian_get_audit_table_name();
	$needs_upgrade = ( '2' !== $version );

	if ( ! $needs_upgrade ) {
		$column = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table_name} LIKE %s", 'source' ) );
		if ( empty( $column ) ) {
			$needs_upgrade = true;
		}
	}

	if ( $needs_upgrade ) {
		obsidian_create_audit_table();
	}
}
add_action( 'init', 'obsidian_maybe_create_audit_table', 5 );

/**
 * Get the client IP address.
 *
 * @return string
 */
function obsidian_get_audit_source() {
	if ( wp_doing_cron() ) {
		return 'cron';
	}

	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return 'rest';
	}

	if ( is_admin() ) {
		return 'admin';
	}

	return 'system';
}

/**
 * Log a booking status change.
 *
 * @param int    $booking_id Booking post ID.
 * @param string $old_status Previous status.
 * @param string $new_status New status.
 * @return void
 */
function obsidian_log_booking_audit( $booking_id, $old_status, $new_status ) {
	global $wpdb;

	$booking_id = (int) $booking_id;
	if ( ! $booking_id ) {
		return;
	}

	$table_name = obsidian_get_audit_table_name();

	$user_id   = get_current_user_id();
	$user_role = '';
	if ( $user_id ) {
		$user = get_userdata( $user_id );
		if ( $user && ! empty( $user->roles ) ) {
			$user_role = (string) $user->roles[0];
		}
	}

	$has_source = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table_name} LIKE %s", 'source' ) );
	$created_at = current_time( 'mysql' );

	if ( $has_source ) {
		$data = array(
			'booking_id'  => $booking_id,
			'user_id'     => $user_id ? $user_id : null,
			'user_role'   => $user_role,
			'source'      => obsidian_get_audit_source(),
			'action'      => 'status_change',
			'from_status' => $old_status,
			'to_status'   => $new_status,
			'notes'       => null,
			'created_at'  => $created_at,
		);
		$formats = array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );
	} else {
		$data = array(
			'booking_id'  => $booking_id,
			'user_id'     => $user_id ? $user_id : null,
			'user_role'   => $user_role,
			'action'      => 'status_change',
			'from_status' => $old_status,
			'to_status'   => $new_status,
			'notes'       => null,
			'created_at'  => $created_at,
		);
		$formats = array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' );
	}

	$wpdb->insert( $table_name, $data, $formats );
}
add_action( 'obsidian_booking_status_changed', 'obsidian_log_booking_audit', 20, 3 );

/**
 * Get audit entries for a booking.
 *
 * @param int $booking_id Booking post ID.
 * @param int $limit      Number of entries to fetch.
 * @return array
 */
function obsidian_get_booking_audit_entries( $booking_id, $limit = 25 ) {
	global $wpdb;

	$booking_id = (int) $booking_id;
	$limit      = max( 1, (int) $limit );
	$table_name = obsidian_get_audit_table_name();

	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, user_id, user_role, source, action, from_status, to_status, notes, created_at
			 FROM {$table_name}
			 WHERE booking_id = %d
			 ORDER BY created_at DESC, id DESC
			 LIMIT %d",
			$booking_id,
			$limit
		),
		ARRAY_A
	);
}
