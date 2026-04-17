<?php
/**
 * One-time data migrations.
 *
 * Each migration is gated by a wp_options flag so it runs exactly once,
 * even across multiple plugin activations or admin page loads.
 *
 * @package obsidian-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ══════════════════════════════════════════════════════════════
   Migration v2 — Multi-Location / Branches (Phase 11)
   ══════════════════════════════════════════════════════════════
   - Auto-creates a "Main Branch" Location post if none exist.
   - Splits each car's `_car_color_variants` into the new per-branch
     `_car_inventory` and shared `_car_galleries` meta fields.
   - Backfills `_booking_location_id` on every existing booking,
     pointing it at the Main Branch.

   Old fields (`_car_color_variants`, `_booking_location`) are left
   intact for one release as a fallback.
   ══════════════════════════════════════════════════════════════ */

const OBSIDIAN_MIGRATION_V2_OPTION = 'obsidian_migration_v2_done';

/**
 * Entry point — runs on every admin page load and bails fast if already done.
 *
 * Hooked to `admin_init` (not `init`) so migrations never run during REST,
 * AJAX, or cron requests, where they could time-out or partially complete.
 */
function obsidian_run_migration_v2() {

	if ( get_option( OBSIDIAN_MIGRATION_V2_OPTION ) === 'yes' ) {
		return;
	}

	if ( wp_doing_ajax() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
		return;
	}

	if ( ! post_type_exists( 'location' ) || ! taxonomy_exists( 'region' ) ) {
		return; // Try again next request when CPT/taxonomy are registered.
	}

	$branch_id = obsidian_migration_v2_ensure_main_branch();
	if ( ! $branch_id ) {
		return; // Couldn't create or find a default branch — retry next request.
	}

	obsidian_migration_v2_migrate_cars( $branch_id );
	obsidian_migration_v2_migrate_bookings( $branch_id );

	update_option( OBSIDIAN_MIGRATION_V2_OPTION, 'yes', true );
}
add_action( 'admin_init', 'obsidian_run_migration_v2' );

/**
 * Find an existing default branch or create a new "Main Branch".
 *
 * Resolution order:
 *   1. The first published `location` with `location_status = active`.
 *   2. The first published `location` regardless of status.
 *   3. A newly-created "Main Branch" assigned to the Luzon region.
 *
 * @return int Location post ID (0 on failure).
 */
function obsidian_migration_v2_ensure_main_branch() {

	$existing = get_posts( array(
		'post_type'      => 'location',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'orderby'        => 'date',
		'order'          => 'ASC',
		'meta_query'     => array(
			array(
				'key'     => 'location_status',
				'value'   => 'active',
				'compare' => '=',
			),
		),
	) );

	if ( ! empty( $existing ) ) {
		return (int) $existing[0];
	}

	$any = get_posts( array(
		'post_type'      => 'location',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'orderby'        => 'date',
		'order'          => 'ASC',
	) );

	if ( ! empty( $any ) ) {
		return (int) $any[0];
	}

	$branch_id = wp_insert_post( array(
		'post_type'    => 'location',
		'post_status'  => 'publish',
		'post_title'   => __( 'Main Branch', 'obsidian-booking' ),
		'post_content' => __( 'Auto-created during the multi-location migration. Please edit this branch to fill in its real address, contact details, and coordinates — or create new branches and reassign your inventory.', 'obsidian-booking' ),
	), true );

	if ( is_wp_error( $branch_id ) || ! $branch_id ) {
		return 0;
	}

	// Default the auto-created branch to "active" so it shows on the fleet.
	update_post_meta( $branch_id, 'location_status', 'active' );

	// Assign to the Luzon region if it exists; otherwise the first region term.
	$luzon = get_term_by( 'name', 'Luzon', 'region' );
	if ( $luzon && ! is_wp_error( $luzon ) ) {
		wp_set_object_terms( $branch_id, array( (int) $luzon->term_id ), 'region', false );
	} else {
		$any_region = get_terms( array(
			'taxonomy'   => 'region',
			'hide_empty' => false,
			'number'     => 1,
		) );
		if ( ! empty( $any_region ) && ! is_wp_error( $any_region ) ) {
			wp_set_object_terms( $branch_id, array( (int) $any_region[0]->term_id ), 'region', false );
		}
	}

	return (int) $branch_id;
}

/**
 * Walk every Car post and split `_car_color_variants` into the new
 * `_car_inventory` (per-branch units) + `_car_galleries` (shared images).
 *
 * Each migrated car gets a `_migrated_v2` meta flag so the loop is
 * idempotent if the run is interrupted mid-way.
 *
 * @param int $branch_id The default branch ID to attach inventory to.
 */
function obsidian_migration_v2_migrate_cars( $branch_id ) {

	$cars = get_posts( array(
		'post_type'      => 'car',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array(
			array(
				'key'     => '_migrated_v2',
				'compare' => 'NOT EXISTS',
			),
		),
	) );

	foreach ( $cars as $car_id ) {

		$legacy_json     = get_post_meta( $car_id, '_car_color_variants', true );
		$legacy_variants = ! empty( $legacy_json ) ? json_decode( $legacy_json, true ) : array();

		$inventory = array();
		$galleries = array();

		if ( is_array( $legacy_variants ) && ! empty( $legacy_variants ) ) {

			$branch_units = array();

			foreach ( $legacy_variants as $color => $data ) {
				$color_key = strtolower( $color );
				$units     = (int) ( $data['units'] ?? 0 );

				$branch_units[ $color_key ] = array( 'units' => $units );

				$images = array();
				if ( isset( $data['images'] ) && is_array( $data['images'] ) ) {
					$images = array_values( array_filter( array_map( 'intval', $data['images'] ) ) );
				} elseif ( isset( $data['image_id'] ) ) {
					$single = (int) $data['image_id'];
					if ( $single > 0 ) {
						$images = array( $single );
					}
				}

				if ( ! empty( $images ) ) {
					$galleries[ $color_key ] = $images;
				}
			}

			$inventory = array( (string) $branch_id => $branch_units );
		}

		// Only write meta if we actually have something to write — but always
		// flag the car as migrated so we don't reprocess empty cars on every load.
		if ( ! empty( $inventory ) ) {
			update_post_meta( $car_id, '_car_inventory', wp_json_encode( $inventory ) );
		}
		if ( ! empty( $galleries ) ) {
			update_post_meta( $car_id, '_car_galleries', wp_json_encode( $galleries ) );
		}

		update_post_meta( $car_id, '_migrated_v2', 1 );
	}
}

/**
 * Backfill `_booking_location_id` on every existing booking that is
 * still using the legacy string-slug `_booking_location` field.
 *
 * @param int $branch_id The default branch ID to assign.
 */
function obsidian_migration_v2_migrate_bookings( $branch_id ) {

	$bookings = get_posts( array(
		'post_type'      => 'booking',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array(
			array(
				'key'     => '_booking_location_id',
				'compare' => 'NOT EXISTS',
			),
		),
	) );

	foreach ( $bookings as $booking_id ) {
		update_post_meta( $booking_id, '_booking_location_id', $branch_id );
	}
}
