<?php
/**
 * Availability Engine + Color Variant Helpers
 *
 * Core date-overlap logic for determining car availability,
 * plus per-color inventory helpers used by the REST API,
 * admin meta box, and car-grid block.
 *
 * @package obsidian-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ══════════════════════════════════════════════════════════════
   COLOR VARIANT HELPERS
   ══════════════════════════════════════════════════════════════ */

/**
 * Map a color name to its hex code for rendering swatches.
 *
 * @param string $color_name The color name (case-insensitive).
 * @return string Hex color code.
 */
function obsidian_get_color_hex( $color_name ) {
	$map = array(
		'orange' => '#FF6B00',
		'black'  => '#1A1A1A',
		'red'    => '#CC0000',
		'blue'   => '#0066CC',
		'white'  => '#F5F5F5',
		'silver' => '#C0C0C0',
		'yellow' => '#FFD700',
		'green'  => '#2E8B57',
		'gray'   => '#808080',
		'grey'   => '#808080',
	);

	return $map[ strtolower( trim( $color_name ) ) ] ?? '#888888';
}

/**
 * Get the decoded color variants for a car.
 *
 * Returns an associative array keyed by lowercase color name.
 * Each entry has 'units' (int) and 'images' (array of attachment IDs).
 *
 * Handles backwards compatibility:
 *  - Old format: { "orange": { "units": 3, "image_id": 123 } }
 *  - New format: { "orange": { "units": 3, "images": [101, 102, ...] } }
 *
 * Falls back to car_total_units split evenly across ACF car_colors
 * if _car_color_variants hasn't been populated yet.
 *
 * @param int $car_id The Car post ID.
 * @return array e.g. ['orange' => ['units' => 3, 'images' => [101, 102, ...]], ...]
 */
function obsidian_get_color_variants( $car_id ) {
	$json     = get_post_meta( $car_id, '_car_color_variants', true );
	$variants = ! empty( $json ) ? json_decode( $json, true ) : null;

	if ( ! empty( $variants ) && is_array( $variants ) ) {
		// Normalise old image_id format → images array
		foreach ( $variants as $color => &$data ) {
			if ( ! isset( $data['images'] ) || ! is_array( $data['images'] ) ) {
				$legacy_id      = (int) ( $data['image_id'] ?? 0 );
				$data['images'] = $legacy_id > 0 ? array( $legacy_id ) : array();
				unset( $data['image_id'] );
			}
		}
		unset( $data );
		return $variants;
	}

	// Backwards compat: split car_total_units evenly across car_colors
	$colors      = get_field( 'car_colors', $car_id );
	$total_units = (int) get_field( 'car_total_units', $car_id );

	if ( empty( $colors ) || ! is_array( $colors ) ) {
		return array();
	}

	$per_color = max( 1, intdiv( $total_units, count( $colors ) ) );
	$fallback  = array();
	foreach ( $colors as $color ) {
		$fallback[ strtolower( $color ) ] = array(
			'units'  => $per_color,
			'images' => array(),
		);
	}

	return $fallback;
}

/**
 * Derive total units from color variants (sum of all per-color units).
 * Falls back to ACF car_total_units if no variants are set.
 *
 * @param int $car_id The Car post ID.
 * @return int Total units across all colors.
 */
function obsidian_get_total_units( $car_id ) {
	$variants = obsidian_get_color_variants( $car_id );

	if ( ! empty( $variants ) ) {
		$total = 0;
		foreach ( $variants as $data ) {
			$total += (int) ( $data['units'] ?? 0 );
		}
		return $total;
	}

	return (int) get_field( 'car_total_units', $car_id );
}

/* ══════════════════════════════════════════════════════════════
   AVAILABILITY FUNCTIONS
   ══════════════════════════════════════════════════════════════ */

/**
 * Statuses that block inventory (everything before completed/denied).
 *
 * @return array
 */
function obsidian_get_blocking_statuses() {
	return array( 'pending_review', 'awaiting_payment', 'paid', 'confirmed', 'active' );
}

/**
 * Count how many units of a car are still available for a date range.
 *
 * Now derives total from the sum of all color variant units instead
 * of the single car_total_units ACF field.
 *
 * @param int    $car_id             The Car post ID.
 * @param string $start_date         Requested start date (Y-m-d).
 * @param string $end_date           Requested end date (Y-m-d).
 * @param int    $exclude_booking_id Optional booking ID to exclude (for edits).
 *
 * @return int Number of available units (0 = fully booked).
 */
function obsidian_get_available_units( $car_id, $start_date, $end_date, $exclude_booking_id = 0 ) {

	$total_units = obsidian_get_total_units( $car_id );

	if ( $total_units <= 0 ) {
		return 0;
	}

	$args = array(
		'post_type'      => 'booking',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array(
			'relation' => 'AND',
			array(
				'key'     => '_booking_car_id',
				'value'   => $car_id,
				'compare' => '=',
				'type'    => 'NUMERIC',
			),
			array(
				'key'     => '_booking_status',
				'value'   => obsidian_get_blocking_statuses(),
				'compare' => 'IN',
			),
			array(
				'key'     => '_booking_start_date',
				'value'   => $end_date,
				'compare' => '<',
				'type'    => 'DATE',
			),
			array(
				'key'     => '_booking_end_date',
				'value'   => $start_date,
				'compare' => '>',
				'type'    => 'DATE',
			),
		),
	);

	$query             = new WP_Query( $args );
	$overlapping_count = $query->found_posts;

	if ( $exclude_booking_id > 0 ) {
		$overlapping_ids = $query->posts;
		if ( in_array( $exclude_booking_id, $overlapping_ids, true ) ) {
			$overlapping_count--;
		}
	}

	return max( 0, $total_units - $overlapping_count );
}

/**
 * Count how many units of a SPECIFIC COLOR are available for a date range.
 *
 * Same overlap logic as obsidian_get_available_units() but also
 * filters bookings by _booking_color.
 *
 * @param int    $car_id             The Car post ID.
 * @param string $color              The color name (lowercase).
 * @param string $start_date         Requested start date (Y-m-d).
 * @param string $end_date           Requested end date (Y-m-d).
 * @param int    $exclude_booking_id Optional booking ID to exclude.
 *
 * @return int Number of available units of this color (0 = sold out).
 */
function obsidian_get_available_units_by_color( $car_id, $color, $start_date, $end_date, $exclude_booking_id = 0 ) {

	$variants    = obsidian_get_color_variants( $car_id );
	$color_lower = strtolower( $color );

	if ( ! isset( $variants[ $color_lower ] ) ) {
		return 0;
	}

	$color_units = (int) ( $variants[ $color_lower ]['units'] ?? 0 );

	if ( $color_units <= 0 ) {
		return 0;
	}

	$args = array(
		'post_type'      => 'booking',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array(
			'relation' => 'AND',
			array(
				'key'     => '_booking_car_id',
				'value'   => $car_id,
				'compare' => '=',
				'type'    => 'NUMERIC',
			),
			array(
				'key'     => '_booking_color',
				'value'   => $color_lower,
				'compare' => '=',
			),
			array(
				'key'     => '_booking_status',
				'value'   => obsidian_get_blocking_statuses(),
				'compare' => 'IN',
			),
			array(
				'key'     => '_booking_start_date',
				'value'   => $end_date,
				'compare' => '<',
				'type'    => 'DATE',
			),
			array(
				'key'     => '_booking_end_date',
				'value'   => $start_date,
				'compare' => '>',
				'type'    => 'DATE',
			),
		),
	);

	$query             = new WP_Query( $args );
	$overlapping_count = $query->found_posts;

	if ( $exclude_booking_id > 0 ) {
		$overlapping_ids = $query->posts;
		if ( in_array( $exclude_booking_id, $overlapping_ids, true ) ) {
			$overlapping_count--;
		}
	}

	return max( 0, $color_units - $overlapping_count );
}

/**
 * Check if a car has at least one unit available for a date range.
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
 * Returns dates where ALL units are booked — feeds into
 * Flatpickr's `disable` option.
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

/**
 * Get fully-booked dates per color variant.
 *
 * Returns an associative array keyed by lowercase color name. Each value
 * is an array of 'Y-m-d' strings where ALL units of that color are booked.
 *
 * Used by the booking modal to disable dates in the calendar when a
 * specific color is selected (so users don't pick dates for a color that
 * is sold out, even if other colors are still available).
 *
 * @param int $car_id     The Car post ID.
 * @param int $days_ahead How many days into the future to check (default 90).
 *
 * @return array e.g. [ 'orange' => [ '2026-12-10', ... ], 'black' => [...] ]
 */
function obsidian_get_unavailable_dates_by_color( $car_id, $days_ahead = 90 ) {

	$variants = obsidian_get_color_variants( $car_id );
	$result   = array();

	if ( empty( $variants ) ) {
		return $result;
	}

	$today = new DateTime( 'today', wp_timezone() );

	foreach ( $variants as $color => $data ) {
		$result[ $color ] = array();
		$color_units      = (int) ( $data['units'] ?? 0 );

		// If the color has 0 stock, every date is "unavailable" for it.
		if ( $color_units <= 0 ) {
			for ( $i = 0; $i < $days_ahead; $i++ ) {
				$d = clone $today;
				$d->modify( "+{$i} days" );
				$result[ $color ][] = $d->format( 'Y-m-d' );
			}
			continue;
		}

		for ( $i = 0; $i < $days_ahead; $i++ ) {
			$check_date = clone $today;
			$check_date->modify( "+{$i} days" );
			$date_string = $check_date->format( 'Y-m-d' );

			$next_day = clone $check_date;
			$next_day->modify( '+1 day' );

			$available = obsidian_get_available_units_by_color(
				$car_id,
				$color,
				$date_string,
				$next_day->format( 'Y-m-d' )
			);

			if ( $available <= 0 ) {
				$result[ $color ][] = $date_string;
			}
		}
	}

	return $result;
}
