<?php
/**
 * Register custom meta fields for the Booking and Car CPTs.
 *
 * Most Car fields are handled by ACF (admin fills them in manually).
 * Structured JSON fields (per-branch inventory, per-color galleries) are
 * registered here because ACF Free can't model nested data without the
 * Repeater field.
 *
 * Booking fields are registered here with register_post_meta()
 * because bookings are created/updated programmatically via REST API.
 *
 * @package obsidian-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function obsidian_register_booking_meta() {

	$fields = array(
		'_booking_car_id'          => 'integer',    // ID of the Car post
		'_booking_user_id'         => 'integer',    // ID of the WP user who booked
		'_booking_start_date'      => 'string',     // Format: Y-m-d
		'_booking_end_date'        => 'string',     // Format: Y-m-d
		'_booking_customer_type'   => 'string',     // "local" or "international"
		'_booking_status'          => 'string',     // pending_review | awaiting_payment | paid | confirmed | active | completed | denied
		'_booking_total_price'     => 'number',     // daily_rate × number_of_days
		'_booking_color'           => 'string',     // Which color variant the user picked

		// Contact & identity fields (from booking form)
		'_booking_first_name'      => 'string',
		'_booking_last_name'       => 'string',
		'_booking_email'           => 'string',     // Pulled from logged-in user
		'_booking_address'         => 'string',
		'_booking_birth_date'      => 'string',     // Format: Y-m-d (must be 21+)
		'_booking_phone'           => 'string',     // Local only — with +63 prefix
		'_booking_license_number'  => 'string',     // Driver license number (held 2+ years)
		'_booking_location'        => 'string',     // DEPRECATED — legacy slug; replaced by _booking_location_id (Phase 11)
		'_booking_location_id'     => 'integer',    // ID of the Location (branch) post the booking is tied to (Phase 11)

		// Local-only fields
		'_booking_gov_id_type'     => 'string',     // Government ID type (e.g. "Philippine Passport")
		'_booking_gov_id_type_2'   => 'string',     // Second government ID type

		// International-only fields
		'_booking_passport_number' => 'string',     // Passport ID number
		'_booking_nationality'     => 'string',     // Country of origin

		// Document uploads — JSON arrays of attachment IDs
		'_booking_documents'       => 'string',     // JSON: { license: [id], gov_id: [id,id], passport: [id], proof_of_arrival: [id] }
		'_booking_admin_notes'     => 'string',     // Internal notes from staff
		'_booking_denial_reason'   => 'string',     // Reason for denial (shown to user in email)

		// Payment fields (Phase 6.3)
		'_booking_payment_type'    => 'string',     // down_payment | full_prepayment
		'_booking_payment_amount'  => 'number',     // Amount actually charged (50% or 100%)
		'_booking_deposit_amount'  => 'number',     // Security deposit held
		'_booking_balance_due'     => 'number',     // Remaining balance due at pickup
		'_booking_payment_id'      => 'string',     // PayMongo payment intent ID
		'_booking_payment_status'  => 'string',     // unpaid | paid | deposit_released
	);

	foreach ( $fields as $key => $type ) {
		register_post_meta( 'booking', $key, array(
			'show_in_rest'      => true,
			'single'            => true,
			'type'              => $type,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => function () {
				return current_user_can( 'edit_posts' );
			},
		) );
	}
}
add_action( 'init', 'obsidian_register_booking_meta' );

/**
 * Register structured JSON meta for the Car CPT.
 *
 * Three fields:
 *
 *   _car_inventory        — Per-branch, per-color unit counts (Phase 11)
 *                           { "12": { "orange": { "units": 3 }, "black": { "units": 2 } },
 *                             "15": { "orange": { "units": 2 } } }
 *                           Top-level keys are Location post IDs (branches).
 *                           Inner keys are color slugs (lowercase).
 *
 *   _car_galleries        — Per-color image IDs, shared across all branches (Phase 11)
 *                           { "orange": [101, 102, 103, 104, 105, 106],
 *                             "black":  [201, 202, 203, 204, 205, 206] }
 *                           Galleries are shared because the same colour car
 *                           looks the same regardless of branch — saves admin
 *                           time and storage.
 *
 *   _car_color_variants   — DEPRECATED legacy structure (Phase 4 - 10).
 *                           Kept registered so the one-time migration in
 *                           Step 11.4 can read it. Will be removed in a
 *                           future release.
 *
 * The ACF car_colors checkbox remains the source of truth for WHICH colors
 * a car offers. These fields store HOW MANY of each per branch and WHAT
 * IMAGES to show.
 *
 * Helper functions (obsidian_get_color_variants, obsidian_get_color_hex,
 * obsidian_get_car_inventory, obsidian_get_car_branches, etc.) live in
 * includes/availability.php alongside the availability engine.
 */
function obsidian_register_car_meta() {

	$car_meta = array(
		'_car_inventory'      => 'string',  // JSON nested object — see above
		'_car_galleries'      => 'string',  // JSON object keyed by colour slug
		'_car_color_variants' => 'string',  // DEPRECATED — kept for migration
	);

	foreach ( $car_meta as $key => $type ) {
		register_post_meta( 'car', $key, array(
			'show_in_rest'      => true,
			'single'            => true,
			'type'              => $type,
			'sanitize_callback' => 'obsidian_sanitize_json_meta',
			'auth_callback'     => function () {
				return current_user_can( 'edit_posts' );
			},
		) );
	}
}
add_action( 'init', 'obsidian_register_car_meta' );

/**
 * Sanitize JSON-string meta without mangling braces, brackets, or quotes.
 *
 * sanitize_text_field() strips newlines and tabs (which is fine for JSON
 * since whitespace is insignificant) but, more importantly, leaves
 * structural characters like {}[]":, intact. We re-encode through
 * json_decode/json_encode to guarantee valid JSON and reject anything
 * that's not parseable.
 *
 * @param mixed $value Incoming meta value.
 * @return string A JSON string (empty string if invalid input).
 */
function obsidian_sanitize_json_meta( $value ) {

	if ( is_array( $value ) || is_object( $value ) ) {
		return wp_json_encode( $value );
	}

	if ( ! is_string( $value ) || $value === '' ) {
		return '';
	}

	$decoded = json_decode( wp_unslash( $value ), true );

	if ( json_last_error() !== JSON_ERROR_NONE ) {
		// Not valid JSON — strip aggressively to avoid storing junk.
		return sanitize_text_field( $value );
	}

	return wp_json_encode( $decoded );
}
