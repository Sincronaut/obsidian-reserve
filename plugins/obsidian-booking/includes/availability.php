<?php
/**
 * Availability Engine
 *
 * Core date-overlap logic for determining car availability.
 * This is the foundation of the booking system — the modal, the REST API,
 * and the booking handler all depend on these functions.
 *
 * @package obsidian-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Count how many units of a car are still available for a date range.
 *
 * Logic:
 *  1. Get the car's total units (from ACF field).
 *  2. Query all bookings for this car that overlap the requested dates.
 *  3. Only count bookings with active statuses (pending, confirmed, active).
 *  4. Subtract overlapping bookings from total → available units.
 *
 * Date-overlap formula:
 *   existing_start < requested_end AND existing_end > requested_start
 *
 * @param int    $car_id     The Car post ID.
 * @param string $start_date Requested start date (Y-m-d).
 * @param string $end_date   Requested end date (Y-m-d).
 * @param int    $exclude_booking_id Optional booking ID to exclude (for edits).
 *
 * @return int Number of available units (0 = fully booked).
 */
function obsidian_get_available_units( $car_id, $start_date, $end_date, $exclude_booking_id = 0 ) {

	// Get total units from ACF
	$total_units = (int) get_field( 'car_total_units', $car_id );

	if ( $total_units <= 0 ) {
		return 0;
	}

	// Statuses that "hold" a unit (completed/denied don't block inventory)
	$blocking_statuses = array( 'pending_review', 'awaiting_payment', 'paid', 'confirmed', 'active' );

	// Query overlapping bookings
	$args = array(
		'post_type'      => 'booking',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array(
			'relation' => 'AND',

			// Same car
			array(
				'key'     => '_booking_car_id',
				'value'   => $car_id,
				'compare' => '=',
				'type'    => 'NUMERIC',
			),

			// Status is one that blocks inventory
			array(
				'key'     => '_booking_status',
				'value'   => $blocking_statuses,
				'compare' => 'IN',
			),

			// Date overlap: existing_start < requested_end
			array(
				'key'     => '_booking_start_date',
				'value'   => $end_date,
				'compare' => '<',
				'type'    => 'DATE',
			),

			// Date overlap: existing_end > requested_start
			array(
				'key'     => '_booking_end_date',
				'value'   => $start_date,
				'compare' => '>',
				'type'    => 'DATE',
			),
		),
	);

	$query = new WP_Query( $args );
	$overlapping_count = $query->found_posts;

	// If we're editing an existing booking, don't count it against itself
	if ( $exclude_booking_id > 0 ) {
		$overlapping_ids = $query->posts;
		if ( in_array( $exclude_booking_id, $overlapping_ids, true ) ) {
			$overlapping_count--;
		}
	}

	$available = $total_units - $overlapping_count;

	return max( 0, $available );
}

/**
 * Check if a car has at least one unit available for a date range.
 *
 * Simple boolean wrapper around obsidian_get_available_units().
 *
 * @param int    $car_id     The Car post ID.
 * @param string $start_date Requested start date (Y-m-d).
 * @param string $end_date   Requested end date (Y-m-d).
 *
 * @return bool True if at least 1 unit is free.
 */
function obsidian_is_car_available( $car_id, $start_date, $end_date ) {
	return obsidian_get_available_units( $car_id, $start_date, $end_date ) > 0;
}

/**
 * Get an array of fully-booked dates for a car.
 *
 * Loops through the next N days and checks availability for each single day.
 * Returns dates where ALL units are booked — these feed directly into
 * Flatpickr's `disable` option to gray out unavailable dates.
 *
 * @param int $car_id     The Car post ID.
 * @param int $days_ahead How many days into the future to check (default 90).
 *
 * @return array Array of 'Y-m-d' strings where all units are booked.
 */
function obsidian_get_unavailable_dates( $car_id, $days_ahead = 90 ) {

	$unavailable = array();
	$today       = new DateTime( 'today', wp_timezone() );

	for ( $i = 0; $i < $days_ahead; $i++ ) {
		$check_date = clone $today;
		$check_date->modify( "+{$i} days" );
		$date_string = $check_date->format( 'Y-m-d' );

		// For a single day, start = that day, end = next day
		// This catches any booking that includes this day
		$next_day = clone $check_date;
		$next_day->modify( '+1 day' );

		$available = obsidian_get_available_units(
			$car_id,
			$date_string,
			$next_day->format( 'Y-m-d' )
		);

		if ( $available <= 0 ) {
			$unavailable[] = $date_string;
		}
	}

	return $unavailable;
}
