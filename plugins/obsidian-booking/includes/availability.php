<?php
/**
 * Availability Engine + Color Variant Helpers
 *
 * Core date-overlap logic for determining car availability,
 * plus per-color and per-branch inventory helpers used by the
 * REST API, admin meta box, and car-grid block.
 *
 * Phase 11: every function now accepts an optional `$location_id`
 * parameter (default 0 = aggregated across all branches, preserves
 * pre-Phase-11 behaviour). Pass an explicit branch ID to scope.
 *
 * @package obsidian-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ══════════════════════════════════════════════════════════════
   COLOR / SWATCH HELPERS
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

/* ══════════════════════════════════════════════════════════════
   CAR INVENTORY HELPERS (Phase 11)
   ══════════════════════════════════════════════════════════════ */

/**
 * Decode the per-branch inventory JSON for a car.
 *
 * @param int $car_id The Car post ID.
 * @return array Nested map: [ branch_id => [ color => [ 'units' => int ] ] ]
 */
function obsidian_get_car_inventory( $car_id ) {
	$json = get_post_meta( $car_id, '_car_inventory', true );

	if ( empty( $json ) ) {
		return array();
	}

	$decoded = json_decode( $json, true );
	return is_array( $decoded ) ? $decoded : array();
}

/**
 * Decode the per-color shared galleries JSON for a car.
 *
 * @param int $car_id The Car post ID.
 * @return array Map of [ color => [ attachment_id, ... ] ]
 */
function obsidian_get_car_galleries( $car_id ) {
	$json = get_post_meta( $car_id, '_car_galleries', true );

	if ( empty( $json ) ) {
		return array();
	}

	$decoded = json_decode( $json, true );
	if ( ! is_array( $decoded ) ) {
		return array();
	}

	// Normalise: keys lowercase, values arrays of positive integers.
	$out = array();
	foreach ( $decoded as $color => $images ) {
		if ( ! is_array( $images ) ) {
			continue;
		}
		$ids = array_values( array_filter( array_map( 'intval', $images ), function ( $id ) {
			return $id > 0;
		} ) );
		if ( ! empty( $ids ) ) {
			$out[ strtolower( $color ) ] = $ids;
		}
	}

	return $out;
}

/**
 * Get the gallery attachment IDs for a single color (shared across branches).
 *
 * @param int    $car_id The Car post ID.
 * @param string $color  The color slug (case-insensitive).
 * @return array Attachment IDs (empty if none configured).
 */
function obsidian_resolve_color_gallery( $car_id, $color ) {
	$galleries = obsidian_get_car_galleries( $car_id );
	return $galleries[ strtolower( trim( $color ) ) ] ?? array();
}

/**
 * IDs of all active branches the car is stocked at.
 *
 * Filters out branches whose `location_status` is not `active`, so a car
 * stocked only at "coming_soon" branches reports an empty array (and
 * therefore won't appear on the public fleet).
 *
 * @param int $car_id The Car post ID.
 * @return int[]
 */
function obsidian_get_car_branches( $car_id ) {
	$inventory = obsidian_get_car_inventory( $car_id );

	if ( empty( $inventory ) ) {
		return array();
	}

	$branch_ids = array();
	foreach ( array_keys( $inventory ) as $branch_id ) {
		$branch_id = (int) $branch_id;
		if ( $branch_id <= 0 ) {
			continue;
		}
		if ( get_post_status( $branch_id ) !== 'publish' ) {
			continue;
		}
		$status = get_post_meta( $branch_id, 'location_status', true );
		if ( $status && $status !== 'active' ) {
			continue;
		}
		$branch_ids[] = $branch_id;
	}

	return $branch_ids;
}

/**
 * Region term slugs derived from a car's active branches.
 *
 * @param int $car_id The Car post ID.
 * @return string[] Lowercase region slugs (deduplicated).
 */
function obsidian_get_car_regions( $car_id ) {
	$slugs = array();

	foreach ( obsidian_get_car_branches( $car_id ) as $branch_id ) {
		$terms = wp_get_object_terms( $branch_id, 'region', array( 'fields' => 'slugs' ) );
		if ( is_wp_error( $terms ) ) {
			continue;
		}
		foreach ( $terms as $slug ) {
			$slugs[ $slug ] = true;
		}
	}

	return array_keys( $slugs );
}

/**
 * Quick boolean: is this car stocked at this branch?
 *
 * @param int $car_id    The Car post ID.
 * @param int $branch_id The Location post ID.
 * @return bool
 */
function obsidian_branch_has_car( $car_id, $branch_id ) {
	$inventory = obsidian_get_car_inventory( $car_id );
	$key       = (string) (int) $branch_id;

	if ( ! isset( $inventory[ $key ] ) || ! is_array( $inventory[ $key ] ) ) {
		return false;
	}

	foreach ( $inventory[ $key ] as $color_data ) {
		if ( (int) ( $color_data['units'] ?? 0 ) > 0 ) {
			return true;
		}
	}

	return false;
}

/**
 * Sum total units of a car across every branch in a given region.
 *
 * @param int    $car_id      The Car post ID.
 * @param string $region_slug Region taxonomy slug (e.g. 'luzon').
 * @return int
 */
function obsidian_get_car_total_units_in_region( $car_id, $region_slug ) {
	$total = 0;

	foreach ( obsidian_get_car_branches( $car_id ) as $branch_id ) {
		$terms = wp_get_object_terms( $branch_id, 'region', array( 'fields' => 'slugs' ) );
		if ( is_wp_error( $terms ) || ! in_array( $region_slug, $terms, true ) ) {
			continue;
		}
		$total += obsidian_get_total_units( $car_id, $branch_id );
	}

	return $total;
}

/**
 * Get the decoded color variants for a car (optionally scoped to a branch).
 *
 * Return shape (per color):
 *   [ 'units' => int, 'images' => int[] ]
 *
 * Scoping rules:
 *   - $location_id > 0 → return only colours stocked at that branch with
 *     branch-specific unit counts. Galleries come from the shared
 *     `_car_galleries` field (same images across branches).
 *   - $location_id = 0 → aggregate units across all active branches per
 *     color (legacy behaviour).
 *
 * Backwards-compatibility:
 *   - If `_car_inventory` is empty, falls back to the legacy
 *     `_car_color_variants` JSON (Phase 4 format).
 *   - If THAT is also empty, splits ACF `car_total_units` evenly across
 *     `car_colors` (very old fallback).
 *
 * @param int $car_id      The Car post ID.
 * @param int $location_id Optional branch ID. 0 = aggregate across branches.
 * @return array e.g. [ 'orange' => [ 'units' => 3, 'images' => [101, 102, ...] ], ... ]
 */
function obsidian_get_color_variants( $car_id, $location_id = 0 ) {

	$inventory   = obsidian_get_car_inventory( $car_id );
	$galleries   = obsidian_get_car_galleries( $car_id );
	$location_id = (int) $location_id;

	if ( ! empty( $inventory ) ) {

		$result = array();

		if ( $location_id > 0 ) {
			$key = (string) $location_id;
			if ( isset( $inventory[ $key ] ) && is_array( $inventory[ $key ] ) ) {
				foreach ( $inventory[ $key ] as $color => $data ) {
					$color_key             = strtolower( $color );
					$result[ $color_key ]  = array(
						'units'  => (int) ( $data['units'] ?? 0 ),
						'images' => $galleries[ $color_key ] ?? array(),
					);
				}
			}
			return $result;
		}

		// Aggregate across active branches.
		$active_branches = obsidian_get_car_branches( $car_id );

		foreach ( $active_branches as $branch_id ) {
			$key = (string) $branch_id;
			if ( ! isset( $inventory[ $key ] ) || ! is_array( $inventory[ $key ] ) ) {
				continue;
			}
			foreach ( $inventory[ $key ] as $color => $data ) {
				$color_key = strtolower( $color );
				if ( ! isset( $result[ $color_key ] ) ) {
					$result[ $color_key ] = array(
						'units'  => 0,
						'images' => $galleries[ $color_key ] ?? array(),
					);
				}
				$result[ $color_key ]['units'] += (int) ( $data['units'] ?? 0 );
			}
		}

		return $result;
	}

	/* ── Legacy fallback: pre-Phase-11 _car_color_variants ── */

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

	/* ── Ancient fallback: car_total_units split across car_colors ── */

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
 * Sum of all per-color units (optionally scoped to a branch).
 *
 * @param int $car_id      The Car post ID.
 * @param int $location_id Optional branch ID. 0 = sum across all branches.
 * @return int Total units.
 */
function obsidian_get_total_units( $car_id, $location_id = 0 ) {

	$variants = obsidian_get_color_variants( $car_id, $location_id );

	if ( ! empty( $variants ) ) {
		$total = 0;
		foreach ( $variants as $data ) {
			$total += (int) ( $data['units'] ?? 0 );
		}
		return $total;
	}

	// Legacy fallback only meaningful when not scoped to a branch.
	if ( $location_id === 0 ) {
		return (int) get_field( 'car_total_units', $car_id );
	}

	return 0;
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
 * Build the meta_query clause that scopes a booking lookup to a branch.
 *
 * Returns an empty array when `$location_id` is 0, so callers can
 * `array_merge` it unconditionally.
 *
 * @param int $location_id Branch ID, or 0 for "any branch".
 * @return array
 */
function obsidian_branch_meta_query( $location_id ) {
	$location_id = (int) $location_id;
	if ( $location_id <= 0 ) {
		return array();
	}
	return array(
		array(
			'key'     => '_booking_location_id',
			'value'   => $location_id,
			'compare' => '=',
			'type'    => 'NUMERIC',
		),
	);
}

/**
 * Count how many units of a car are still available for a date range.
 *
 * @param int    $car_id             The Car post ID.
 * @param string $start_date         Requested start date (Y-m-d).
 * @param string $end_date           Requested end date (Y-m-d).
 * @param int    $exclude_booking_id Optional booking ID to exclude (for edits).
 * @param int    $location_id        Optional branch ID. 0 = aggregate across all branches.
 *
 * @return int Number of available units (0 = fully booked).
 */
function obsidian_get_available_units( $car_id, $start_date, $end_date, $exclude_booking_id = 0, $location_id = 0 ) {

	$total_units = obsidian_get_total_units( $car_id, $location_id );

	if ( $total_units <= 0 ) {
		return 0;
	}

	$meta_query = array(
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
	);

	$meta_query = array_merge( $meta_query, obsidian_branch_meta_query( $location_id ) );

	$query = new WP_Query( array(
		'post_type'      => 'booking',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => $meta_query,
	) );

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
 * @param int    $car_id             The Car post ID.
 * @param string $color              The color name (case-insensitive).
 * @param string $start_date         Requested start date (Y-m-d).
 * @param string $end_date           Requested end date (Y-m-d).
 * @param int    $exclude_booking_id Optional booking ID to exclude.
 * @param int    $location_id        Optional branch ID. 0 = aggregate across branches.
 *
 * @return int Number of available units of this color (0 = sold out).
 */
function obsidian_get_available_units_by_color( $car_id, $color, $start_date, $end_date, $exclude_booking_id = 0, $location_id = 0 ) {

	$variants    = obsidian_get_color_variants( $car_id, $location_id );
	$color_lower = strtolower( $color );

	if ( ! isset( $variants[ $color_lower ] ) ) {
		return 0;
	}

	$color_units = (int) ( $variants[ $color_lower ]['units'] ?? 0 );

	if ( $color_units <= 0 ) {
		return 0;
	}

	$meta_query = array(
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
	);

	$meta_query = array_merge( $meta_query, obsidian_branch_meta_query( $location_id ) );

	$query = new WP_Query( array(
		'post_type'      => 'booking',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => $meta_query,
	) );

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
 * @param int    $car_id      The Car post ID.
 * @param string $start_date  Requested start date (Y-m-d).
 * @param string $end_date    Requested end date (Y-m-d).
 * @param int    $location_id Optional branch ID. 0 = aggregate across branches.
 *
 * @return bool True if at least 1 unit is free.
 */
function obsidian_is_car_available( $car_id, $start_date, $end_date, $location_id = 0 ) {
	return obsidian_get_available_units( $car_id, $start_date, $end_date, 0, $location_id ) > 0;
}

/**
 * Get an array of fully-booked dates for a car.
 *
 * Returns dates where ALL units are booked — feeds into
 * Flatpickr's `disable` option.
 *
 * @param int $car_id      The Car post ID.
 * @param int $days_ahead  How many days into the future to check (default 90).
 * @param int $location_id Optional branch ID. 0 = aggregate across branches.
 *
 * @return array Array of 'Y-m-d' strings where all units are booked.
 */
function obsidian_get_unavailable_dates( $car_id, $days_ahead = 90, $location_id = 0 ) {

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
			$next_day->format( 'Y-m-d' ),
			0,
			$location_id
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
 * @param int $car_id      The Car post ID.
 * @param int $days_ahead  How many days into the future to check (default 90).
 * @param int $location_id Optional branch ID. 0 = aggregate across branches.
 *
 * @return array e.g. [ 'orange' => [ '2026-12-10', ... ], 'black' => [...] ]
 */
function obsidian_get_unavailable_dates_by_color( $car_id, $days_ahead = 90, $location_id = 0 ) {

	$variants = obsidian_get_color_variants( $car_id, $location_id );
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
				$next_day->format( 'Y-m-d' ),
				0,
				$location_id
			);

			if ( $available <= 0 ) {
				$result[ $color ][] = $date_string;
			}
		}
	}

	return $result;
}
