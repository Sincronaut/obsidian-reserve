<?php
/**
 * Register custom meta fields for the Booking CPT.
 *
 * Car fields are handled by ACF (admin fills them in manually).
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
		'_booking_pickup_location' => 'string',     // e.g. "Airport", "Hotel", custom address
		'_booking_customer_type'   => 'string',     // "local" or "foreigner"
		'_booking_status'          => 'string',     // pending | confirmed | active | completed | denied
		'_booking_documents'       => 'string',     // JSON array of attachment IDs
		'_booking_admin_notes'     => 'string',     // Internal notes from staff
		'_booking_total_price'     => 'number',     // daily_rate × number_of_days
		'_booking_color'           => 'string',     // Which color variant the user picked
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
