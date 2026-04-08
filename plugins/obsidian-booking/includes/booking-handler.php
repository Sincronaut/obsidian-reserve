<?php
/**
 * Booking Handler
 *
 * Manages booking lifecycle — status transitions, validation,
 * and firing action hooks that trigger notifications.
 *
 * This is the business logic layer. The REST API and admin UI
 * both call these functions rather than manipulating meta directly.
 *
 * @package obsidian-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Valid booking statuses and their allowed transitions.
 *
 * Key = current status, Value = array of statuses it can transition to.
 * This prevents invalid state changes like going from "completed" back to "pending".
 *
 * @return array
 */
function obsidian_get_status_transitions() {
	return array(
		'pending_review'    => array( 'awaiting_payment', 'denied' ),
		'awaiting_payment'  => array( 'paid', 'denied' ),
		'paid'              => array( 'confirmed' ),
		'confirmed'         => array( 'active', 'denied' ),  // denied = emergency override only
		'active'            => array( 'completed' ),
		'completed'         => array(),     // Terminal state — no further changes
		'denied'            => array(),     // Terminal state — no further changes
	);
}

/**
 * Get a human-readable label for a booking status.
 *
 * Used in admin columns, emails, and the My Reservations page.
 *
 * @param string $status The status key.
 * @return string The human-readable label.
 */
function obsidian_get_status_label( $status ) {
	$labels = array(
		'pending_review'    => __( 'Pending Review', 'obsidian-booking' ),
		'awaiting_payment'  => __( 'Awaiting Payment', 'obsidian-booking' ),
		'paid'              => __( 'Paid', 'obsidian-booking' ),
		'confirmed'         => __( 'Confirmed', 'obsidian-booking' ),
		'active'            => __( 'Active', 'obsidian-booking' ),
		'completed'         => __( 'Completed', 'obsidian-booking' ),
		'denied'            => __( 'Denied', 'obsidian-booking' ),
	);

	return isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( $status );
}

/**
 * Get the CSS color associated with a booking status.
 *
 * Used for status badges in admin and frontend.
 *
 * @param string $status The status key.
 * @return string Hex color code.
 */
function obsidian_get_status_color( $status ) {
	$colors = array(
		'pending_review'    => '#F0AD4E',  // Amber/gold — waiting for doc review
		'awaiting_payment'  => '#E67E22',  // Orange — docs approved, needs payment
		'paid'              => '#3498DB',  // Blue — payment received
		'confirmed'         => '#5CB85C',  // Green — booking locked in
		'active'            => '#5BC0DE',  // Teal — car is currently rented out
		'completed'         => '#777777',  // Gray — trip is done
		'denied'            => '#D9534F',  // Red — rejected
	);

	return isset( $colors[ $status ] ) ? $colors[ $status ] : '#999999';
}

/**
 * Update a booking's status with validation.
 *
 * This is the ONLY function that should change a booking's status.
 * It validates the transition, updates the meta, and fires the
 * action hook that triggers email notifications.
 *
 * @param int    $booking_id The Booking post ID.
 * @param string $new_status The new status to set.
 * @param string $notes      Optional admin notes explaining the decision.
 *
 * @return true|WP_Error True on success, WP_Error on failure.
 */
function obsidian_update_booking_status( $booking_id, $new_status, $notes = '' ) {

	// --- Validate the booking exists ---
	$booking = get_post( $booking_id );
	if ( ! $booking || $booking->post_type !== 'booking' ) {
		return new WP_Error(
			'invalid_booking',
			__( 'Booking not found.', 'obsidian-booking' )
		);
	}

	// --- Get current status ---
	$old_status = get_post_meta( $booking_id, '_booking_status', true );

	// Already in this status — no-op
	if ( $old_status === $new_status ) {
		return true;
	}

	// --- Validate the transition is allowed ---
	$transitions = obsidian_get_status_transitions();

	if ( ! isset( $transitions[ $old_status ] ) ) {
		return new WP_Error(
			'unknown_status',
			sprintf(
				__( 'Current status "%s" is not recognized.', 'obsidian-booking' ),
				$old_status
			)
		);
	}

	if ( ! in_array( $new_status, $transitions[ $old_status ], true ) ) {
		return new WP_Error(
			'invalid_transition',
			sprintf(
				__( 'Cannot change status from "%s" to "%s".', 'obsidian-booking' ),
				$old_status,
				$new_status
			)
		);
	}

	// --- If approving (confirming), re-check availability ---
	if ( $new_status === 'confirmed' ) {
		$car_id     = (int) get_post_meta( $booking_id, '_booking_car_id', true );
		$start_date = get_post_meta( $booking_id, '_booking_start_date', true );
		$end_date   = get_post_meta( $booking_id, '_booking_end_date', true );

		// Exclude this booking from the count (it's already "pending" and counted)
		$available  = obsidian_get_available_units( $car_id, $start_date, $end_date, $booking_id );

		if ( $available <= 0 ) {
			return new WP_Error(
				'no_units_available',
				__( 'Cannot confirm — all units are already booked for these dates.', 'obsidian-booking' )
			);
		}
	}

	// --- Update the status ---
	update_post_meta( $booking_id, '_booking_status', $new_status );

	// --- Update admin notes if provided ---
	if ( ! empty( $notes ) ) {
		$existing_notes = get_post_meta( $booking_id, '_booking_admin_notes', true );
		$timestamp      = current_time( 'Y-m-d H:i' );
		$appended       = $existing_notes
			? $existing_notes . "\n\n[{$timestamp}] {$notes}"
			: "[{$timestamp}] {$notes}";
		update_post_meta( $booking_id, '_booking_admin_notes', $appended );
	}

	/**
	 * Fires when a booking's status changes.
	 *
	 * Phase 7 (notifications.php) will hook into this to send emails.
	 * Any other code that needs to react to status changes can use this too.
	 *
	 * @param int    $booking_id The Booking post ID.
	 * @param string $old_status The previous status.
	 * @param string $new_status The new status.
	 */
	do_action( 'obsidian_booking_status_changed', $booking_id, $old_status, $new_status );

	return true;
}

/**
 * Get a summary of a booking's data.
 *
 * Used by admin meta boxes, emails, and the My Reservations page.
 * Pulls all meta into one clean array.
 *
 * @param int $booking_id The Booking post ID.
 * @return array|false Booking data array, or false if not found.
 */
function obsidian_get_booking_summary( $booking_id ) {

	$booking = get_post( $booking_id );
	if ( ! $booking || $booking->post_type !== 'booking' ) {
		return false;
	}

	$car_id  = (int) get_post_meta( $booking_id, '_booking_car_id', true );
	$user_id = (int) get_post_meta( $booking_id, '_booking_user_id', true );
	$user    = get_userdata( $user_id );

	$start = get_post_meta( $booking_id, '_booking_start_date', true );
	$end   = get_post_meta( $booking_id, '_booking_end_date', true );

	// Calculate number of days
	$start_dt = new DateTime( $start );
	$end_dt   = new DateTime( $end );
	$num_days = $start_dt->diff( $end_dt )->days;

	return array(
		'booking_id'       => $booking_id,
		'car_id'           => $car_id,
		'car_name'         => get_the_title( $car_id ),
		'car_image'        => get_the_post_thumbnail_url( $car_id, 'medium' ),
		'user_id'          => $user_id,
		'user_name'        => $user ? $user->display_name : __( 'Unknown', 'obsidian-booking' ),
		'user_email'       => $user ? $user->user_email : '',
		'start_date'       => $start,
		'end_date'         => $end,
		'num_days'         => $num_days,
		'pickup_location'  => get_post_meta( $booking_id, '_booking_pickup_location', true ),
		'customer_type'    => get_post_meta( $booking_id, '_booking_customer_type', true ),
		'color'            => get_post_meta( $booking_id, '_booking_color', true ),
		'status'           => get_post_meta( $booking_id, '_booking_status', true ),
		'total_price'      => (float) get_post_meta( $booking_id, '_booking_total_price', true ),
		'payment_type'     => get_post_meta( $booking_id, '_booking_payment_type', true ),
		'payment_amount'   => (float) get_post_meta( $booking_id, '_booking_payment_amount', true ),
		'deposit_amount'   => (float) get_post_meta( $booking_id, '_booking_deposit_amount', true ),
		'balance_due'      => (float) get_post_meta( $booking_id, '_booking_balance_due', true ),
		'payment_id'       => get_post_meta( $booking_id, '_booking_payment_id', true ),
		'payment_status'   => get_post_meta( $booking_id, '_booking_payment_status', true ),
		'denial_reason'    => get_post_meta( $booking_id, '_booking_denial_reason', true ),
		'documents'        => json_decode( get_post_meta( $booking_id, '_booking_documents', true ), true ) ?: array(),
		'admin_notes'      => get_post_meta( $booking_id, '_booking_admin_notes', true ),
		'created_at'       => $booking->post_date,
	);
}
