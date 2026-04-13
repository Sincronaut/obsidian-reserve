<?php
/**
 * Register custom meta fields for the Booking and Car CPTs.
 *
 * Most Car fields are handled by ACF (admin fills them in manually).
 * _car_color_variants is registered here because it stores structured
 * JSON data (per-color units + image IDs) that ACF Free can't handle.
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
		'_booking_location'        => 'string',     // Selected Obsidian location

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
 * Register color variant meta for the Car CPT.
 *
 * Stores per-color inventory and image data as JSON.
 * Example value:
 *   {"orange":{"units":3,"image_id":456},"black":{"units":2,"image_id":789}}
 *
 * The ACF car_colors checkbox remains the source of truth for WHICH colors
 * a car offers. This field stores HOW MANY of each and WHAT IMAGE to show.
 *
 * Helper functions (obsidian_get_color_variants, obsidian_get_color_hex)
 * live in includes/availability.php alongside the availability engine.
 */
function obsidian_register_car_meta() {
	register_post_meta( 'car', '_car_color_variants', array(
		'show_in_rest'      => true,
		'single'            => true,
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_text_field',
		'auth_callback'     => function () {
			return current_user_can( 'edit_posts' );
		},
	) );
}
add_action( 'init', 'obsidian_register_car_meta' );
