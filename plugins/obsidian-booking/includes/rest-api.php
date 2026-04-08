<?php
/**
 * REST API Endpoints
 *
 * All custom REST routes for the booking system.
 * Namespace: obsidian-booking/v1
 *
 * These endpoints are the bridge between your frontend JavaScript (modal, forms)
 * and the backend PHP (availability engine, booking handler, WordPress database).
 *
 * @package obsidian-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register all REST routes.
 */
function obsidian_register_rest_routes() {

	$namespace = 'obsidian-booking/v1';

	/* ══════════════════════════════════════════════
	   PUBLIC ENDPOINTS (no login required)
	   ══════════════════════════════════════════════ */

	// GET /cars — List all available cars
	register_rest_route( $namespace, '/cars', array(
		'methods'             => 'GET',
		'callback'            => 'obsidian_api_get_cars',
		'permission_callback' => '__return_true',
	) );

	// GET /cars/{id} — Single car with full details
	register_rest_route( $namespace, '/cars/(?P<id>\d+)', array(
		'methods'             => 'GET',
		'callback'            => 'obsidian_api_get_car',
		'permission_callback' => '__return_true',
		'args'                => array(
			'id' => array(
				'validate_callback' => function ( $param ) {
					return is_numeric( $param );
				},
			),
		),
	) );

	// GET /availability/{car_id} — Unavailable dates for Flatpickr
	register_rest_route( $namespace, '/availability/(?P<car_id>\d+)', array(
		'methods'             => 'GET',
		'callback'            => 'obsidian_api_get_availability',
		'permission_callback' => '__return_true',
		'args'                => array(
			'car_id' => array(
				'validate_callback' => function ( $param ) {
					return is_numeric( $param );
				},
			),
		),
	) );

	/* ══════════════════════════════════════════════
	   PROTECTED ENDPOINTS (login required)
	   ══════════════════════════════════════════════ */

	// POST /bookings — Create a new booking
	register_rest_route( $namespace, '/bookings', array(
		'methods'             => 'POST',
		'callback'            => 'obsidian_api_create_booking',
		'permission_callback' => function () {
			return is_user_logged_in();
		},
	) );

	// GET /bookings/mine — Current user's bookings
	register_rest_route( $namespace, '/bookings/mine', array(
		'methods'             => 'GET',
		'callback'            => 'obsidian_api_get_my_bookings',
		'permission_callback' => function () {
			return is_user_logged_in();
		},
	) );

	// POST /upload-document — Upload ID/passport
	register_rest_route( $namespace, '/upload-document', array(
		'methods'             => 'POST',
		'callback'            => 'obsidian_api_upload_document',
		'permission_callback' => function () {
			return is_user_logged_in();
		},
	) );
}
add_action( 'rest_api_init', 'obsidian_register_rest_routes' );


/* ══════════════════════════════════════════════════════════════
   CALLBACK FUNCTIONS
   ══════════════════════════════════════════════════════════════ */

/**
 * GET /cars
 * Returns all published cars with their ACF fields and featured image.
 */
function obsidian_api_get_cars( $request ) {

	$args = array(
		'post_type'      => 'car',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	);

	$cars  = get_posts( $args );
	$data  = array();

	foreach ( $cars as $car ) {
		$data[] = obsidian_format_car_data( $car->ID );
	}

	return rest_ensure_response( $data );
}

/**
 * GET /cars/{id}
 * Returns a single car's full details.
 */
function obsidian_api_get_car( $request ) {

	$car_id = (int) $request['id'];
	$car    = get_post( $car_id );

	if ( ! $car || $car->post_type !== 'car' || $car->post_status !== 'publish' ) {
		return new WP_Error(
			'car_not_found',
			__( 'Car not found.', 'obsidian-booking' ),
			array( 'status' => 404 )
		);
	}

	return rest_ensure_response( obsidian_format_car_data( $car_id ) );
}

/**
 * GET /availability/{car_id}
 * Returns unavailable dates and total units for a car.
 */
function obsidian_api_get_availability( $request ) {

	$car_id = (int) $request['car_id'];
	$car    = get_post( $car_id );

	if ( ! $car || $car->post_type !== 'car' ) {
		return new WP_Error(
			'car_not_found',
			__( 'Car not found.', 'obsidian-booking' ),
			array( 'status' => 404 )
		);
	}

	$days_ahead       = isset( $request['days'] ) ? (int) $request['days'] : 90;
	$total_units      = (int) get_field( 'car_total_units', $car_id );
	$unavailable      = obsidian_get_unavailable_dates( $car_id, $days_ahead );

	return rest_ensure_response( array(
		'car_id'            => $car_id,
		'total_units'       => $total_units,
		'unavailable_dates' => $unavailable,
		'days_checked'      => $days_ahead,
	) );
}

/**
 * POST /bookings
 * Creates a new booking. Validates all inputs and re-checks availability.
 */
function obsidian_api_create_booking( $request ) {

	$params = $request->get_json_params();

	// --- Required field validation ---
	$required = array( 'car_id', 'start_date', 'end_date', 'pickup_location', 'customer_type' );
	foreach ( $required as $field ) {
		if ( empty( $params[ $field ] ) ) {
			return new WP_Error(
				'missing_field',
				sprintf( __( 'Missing required field: %s', 'obsidian-booking' ), $field ),
				array( 'status' => 400 )
			);
		}
	}

	$car_id          = (int) $params['car_id'];
	$start_date      = sanitize_text_field( $params['start_date'] );
	$end_date        = sanitize_text_field( $params['end_date'] );
	$pickup_location = sanitize_text_field( $params['pickup_location'] );
	$customer_type   = sanitize_text_field( $params['customer_type'] );
	$color           = isset( $params['color'] ) ? sanitize_text_field( $params['color'] ) : '';
	$documents       = isset( $params['documents'] ) ? $params['documents'] : array();
	$user_id         = get_current_user_id();

	// --- Validate car exists ---
	$car = get_post( $car_id );
	if ( ! $car || $car->post_type !== 'car' || $car->post_status !== 'publish' ) {
		return new WP_Error(
			'invalid_car',
			__( 'This car does not exist.', 'obsidian-booking' ),
			array( 'status' => 400 )
		);
	}

	// --- Validate dates ---
	$start = DateTime::createFromFormat( 'Y-m-d', $start_date );
	$end   = DateTime::createFromFormat( 'Y-m-d', $end_date );

	if ( ! $start || ! $end ) {
		return new WP_Error(
			'invalid_dates',
			__( 'Invalid date format. Use Y-m-d.', 'obsidian-booking' ),
			array( 'status' => 400 )
		);
	}

	if ( $end <= $start ) {
		return new WP_Error(
			'invalid_date_range',
			__( 'End date must be after start date.', 'obsidian-booking' ),
			array( 'status' => 400 )
		);
	}

	$today = new DateTime( 'today', wp_timezone() );
	if ( $start < $today ) {
		return new WP_Error(
			'past_date',
			__( 'Start date cannot be in the past.', 'obsidian-booking' ),
			array( 'status' => 400 )
		);
	}

	// --- Validate customer type ---
	if ( ! in_array( $customer_type, array( 'local', 'foreigner' ), true ) ) {
		return new WP_Error(
			'invalid_customer_type',
			__( 'Customer type must be "local" or "foreigner".', 'obsidian-booking' ),
			array( 'status' => 400 )
		);
	}

	// --- RE-CHECK AVAILABILITY (race condition protection) ---
	if ( ! obsidian_is_car_available( $car_id, $start_date, $end_date ) ) {
		return new WP_Error(
			'not_available',
			__( 'Sorry, this car is no longer available for the selected dates.', 'obsidian-booking' ),
			array( 'status' => 409 )  // 409 Conflict
		);
	}

	// --- Calculate price ---
	$daily_rate = (float) get_field( 'car_daily_rate', $car_id );
	$num_days   = $start->diff( $end )->days;
	$total      = $daily_rate * $num_days;

	// --- Create the booking post ---
	$booking_title = sprintf(
		'%s — %s (%s to %s)',
		get_the_title( $car_id ),
		wp_get_current_user()->display_name,
		$start_date,
		$end_date
	);

	$booking_id = wp_insert_post( array(
		'post_type'   => 'booking',
		'post_title'  => $booking_title,
		'post_status' => 'publish',
	) );

	if ( is_wp_error( $booking_id ) ) {
		return new WP_Error(
			'booking_failed',
			__( 'Failed to create booking. Please try again.', 'obsidian-booking' ),
			array( 'status' => 500 )
		);
	}

	// --- Save all meta fields ---
	update_post_meta( $booking_id, '_booking_car_id', $car_id );
	update_post_meta( $booking_id, '_booking_user_id', $user_id );
	update_post_meta( $booking_id, '_booking_start_date', $start_date );
	update_post_meta( $booking_id, '_booking_end_date', $end_date );
	update_post_meta( $booking_id, '_booking_pickup_location', $pickup_location );
	update_post_meta( $booking_id, '_booking_customer_type', $customer_type );
	update_post_meta( $booking_id, '_booking_status', 'pending' );
	update_post_meta( $booking_id, '_booking_total_price', $total );
	update_post_meta( $booking_id, '_booking_color', $color );
	update_post_meta( $booking_id, '_booking_documents', wp_json_encode( $documents ) );
	update_post_meta( $booking_id, '_booking_admin_notes', '' );

	// --- Fire action hook (Phase 7 will listen for this to send emails) ---
	do_action( 'obsidian_booking_status_changed', $booking_id, '', 'pending' );

	return rest_ensure_response( array(
		'success'    => true,
		'booking_id' => $booking_id,
		'status'     => 'pending',
		'total'      => $total,
		'message'    => __( 'Your reservation has been submitted! We will review it shortly.', 'obsidian-booking' ),
	) );
}

/**
 * GET /bookings/mine
 * Returns all bookings belonging to the current logged-in user.
 */
function obsidian_api_get_my_bookings( $request ) {

	$user_id = get_current_user_id();

	$args = array(
		'post_type'      => 'booking',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'meta_key'       => '_booking_user_id',
		'meta_value'     => $user_id,
		'meta_type'      => 'NUMERIC',
		'orderby'        => 'date',
		'order'          => 'DESC',
	);

	$bookings = get_posts( $args );
	$data     = array();

	foreach ( $bookings as $booking ) {
		$car_id = (int) get_post_meta( $booking->ID, '_booking_car_id', true );

		$data[] = array(
			'booking_id'      => $booking->ID,
			'car_id'          => $car_id,
			'car_name'        => get_the_title( $car_id ),
			'car_image'       => get_the_post_thumbnail_url( $car_id, 'medium' ),
			'start_date'      => get_post_meta( $booking->ID, '_booking_start_date', true ),
			'end_date'        => get_post_meta( $booking->ID, '_booking_end_date', true ),
			'pickup_location' => get_post_meta( $booking->ID, '_booking_pickup_location', true ),
			'customer_type'   => get_post_meta( $booking->ID, '_booking_customer_type', true ),
			'status'          => get_post_meta( $booking->ID, '_booking_status', true ),
			'total_price'     => (float) get_post_meta( $booking->ID, '_booking_total_price', true ),
			'color'           => get_post_meta( $booking->ID, '_booking_color', true ),
			'created_at'      => $booking->post_date,
		);
	}

	return rest_ensure_response( $data );
}

/**
 * POST /upload-document
 * Handles secure file upload for ID/passport documents.
 * Uses WordPress's native wp_handle_upload() for security.
 */
function obsidian_api_upload_document( $request ) {

	$files = $request->get_file_params();

	if ( empty( $files['document'] ) ) {
		return new WP_Error(
			'no_file',
			__( 'No file was uploaded.', 'obsidian-booking' ),
			array( 'status' => 400 )
		);
	}

	$file = $files['document'];

	// Validate file type (security: only allow safe formats)
	$allowed_types = array( 'image/jpeg', 'image/png', 'image/webp', 'application/pdf' );
	if ( ! in_array( $file['type'], $allowed_types, true ) ) {
		return new WP_Error(
			'invalid_file_type',
			__( 'Only JPG, PNG, WebP, and PDF files are allowed.', 'obsidian-booking' ),
			array( 'status' => 400 )
		);
	}

	// Max file size: 5MB
	$max_size = 5 * 1024 * 1024;
	if ( $file['size'] > $max_size ) {
		return new WP_Error(
			'file_too_large',
			__( 'File must be under 5MB.', 'obsidian-booking' ),
			array( 'status' => 400 )
		);
	}

	// Use WordPress's secure upload handler
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$upload_overrides = array(
		'test_form' => false,
		'mimes'     => array(
			'jpg|jpeg' => 'image/jpeg',
			'png'      => 'image/png',
			'webp'     => 'image/webp',
			'pdf'      => 'application/pdf',
		),
	);

	$uploaded = wp_handle_upload( $file, $upload_overrides );

	if ( isset( $uploaded['error'] ) ) {
		return new WP_Error(
			'upload_failed',
			$uploaded['error'],
			array( 'status' => 500 )
		);
	}

	// Create an attachment post so we can track it in the media library
	$attachment_id = wp_insert_attachment(
		array(
			'post_title'     => sanitize_file_name( $file['name'] ),
			'post_mime_type' => $uploaded['type'],
			'post_status'    => 'inherit',
		),
		$uploaded['file']
	);

	if ( is_wp_error( $attachment_id ) ) {
		return new WP_Error(
			'attachment_failed',
			__( 'Failed to create media attachment.', 'obsidian-booking' ),
			array( 'status' => 500 )
		);
	}

	// Generate image metadata (thumbnails, etc.)
	$metadata = wp_generate_attachment_metadata( $attachment_id, $uploaded['file'] );
	wp_update_attachment_metadata( $attachment_id, $metadata );

	return rest_ensure_response( array(
		'success'       => true,
		'attachment_id' => $attachment_id,
		'url'           => $uploaded['url'],
	) );
}


/* ══════════════════════════════════════════════════════════════
   HELPER FUNCTIONS
   ══════════════════════════════════════════════════════════════ */

/**
 * Format a car post into a clean data array for the API response.
 *
 * Pulls data from WordPress core (title, thumbnail) and ACF fields.
 * Used by both the /cars list and /cars/{id} single endpoints.
 *
 * @param int $car_id The Car post ID.
 * @return array Formatted car data.
 */
function obsidian_format_car_data( $car_id ) {

	// Get car class terms
	$classes    = wp_get_post_terms( $car_id, 'car_class', array( 'fields' => 'names' ) );
	$car_class  = ! is_wp_error( $classes ) && ! empty( $classes ) ? $classes[0] : '';

	// Get ACF color choices
	$colors = get_field( 'car_colors', $car_id );
	if ( ! is_array( $colors ) ) {
		$colors = array();
	}

	// Build gallery from individual image fields
	$gallery = array();
	$image_fields = array( 'car_img_exterior', 'car_img_interior', 'car_img_engine', 'car_img_detail' );
	foreach ( $image_fields as $field_name ) {
		$img = get_field( $field_name, $car_id );
		if ( $img ) {
			$gallery[] = is_array( $img ) ? $img['url'] : $img;
		}
	}

	return array(
		'id'          => $car_id,
		'name'        => get_the_title( $car_id ),
		'slug'        => get_post_field( 'post_name', $car_id ),
		'description' => get_the_excerpt( $car_id ),
		'image'       => get_the_post_thumbnail_url( $car_id, 'large' ) ?: '',
		'gallery'     => $gallery,
		'car_class'   => $car_class,
		'make'        => get_field( 'car_make', $car_id ) ?: '',
		'model'       => get_field( 'car_model', $car_id ) ?: '',
		'year'        => (int) get_field( 'car_year', $car_id ),
		'daily_rate'  => (float) get_field( 'car_daily_rate', $car_id ),
		'total_units' => (int) get_field( 'car_total_units', $car_id ),
		'colors'      => $colors,
		'status'      => get_field( 'car_status', $car_id ) ?: 'available',
		'link'        => get_permalink( $car_id ),
	);
}
