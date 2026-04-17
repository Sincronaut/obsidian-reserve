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

	$days_ahead          = isset( $request['days'] ) ? (int) $request['days'] : 90;
	$total_units         = obsidian_get_total_units( $car_id );
	$unavailable         = obsidian_get_unavailable_dates( $car_id, $days_ahead );
	$unavailable_by_color = obsidian_get_unavailable_dates_by_color( $car_id, $days_ahead );

	return rest_ensure_response( array(
		'car_id'                     => $car_id,
		'total_units'                => $total_units,
		'unavailable_dates'          => $unavailable,
		'unavailable_dates_by_color' => $unavailable_by_color,
		'days_checked'               => $days_ahead,
	) );
}

/**
 * POST /bookings
 * Creates a new booking. Validates all inputs and re-checks availability.
 */
function obsidian_api_create_booking( $request ) {

	$params = $request->get_json_params();

	// --- Required fields (shared) ---
	$required = array( 'car_id', 'start_date', 'end_date', 'customer_type', 'first_name', 'last_name', 'address', 'birth_date', 'license_number', 'location' );
	foreach ( $required as $field ) {
		if ( empty( $params[ $field ] ) ) {
			return new WP_Error(
				'missing_field',
				sprintf( __( 'Missing required field: %s', 'obsidian-booking' ), $field ),
				array( 'status' => 400 )
			);
		}
	}

	$car_id        = (int) $params['car_id'];
	$start_date    = sanitize_text_field( $params['start_date'] );
	$end_date      = sanitize_text_field( $params['end_date'] );
	$customer_type = sanitize_text_field( $params['customer_type'] );
	$color         = isset( $params['color'] ) ? sanitize_text_field( strtolower( $params['color'] ) ) : '';
	$user_id       = get_current_user_id();
	$user          = wp_get_current_user();

	// Contact & identity
	$first_name     = sanitize_text_field( $params['first_name'] );
	$last_name      = sanitize_text_field( $params['last_name'] );
	$address        = sanitize_text_field( $params['address'] );
	$birth_date     = sanitize_text_field( $params['birth_date'] );
	$phone          = isset( $params['phone'] ) ? sanitize_text_field( $params['phone'] ) : '';
	$license_number = sanitize_text_field( $params['license_number'] );
	$location       = sanitize_text_field( $params['location'] );

	// Local-only
	$gov_id_type   = isset( $params['gov_id_type'] ) ? sanitize_text_field( $params['gov_id_type'] ) : '';
	$gov_id_type_2 = isset( $params['gov_id_type_2'] ) ? sanitize_text_field( $params['gov_id_type_2'] ) : '';

	// International-only
	$passport_number = isset( $params['passport_number'] ) ? sanitize_text_field( $params['passport_number'] ) : '';

	// Delivery fields
	$delivery_contact = isset( $params['delivery_contact'] ) ? sanitize_text_field( $params['delivery_contact'] ) : '';
	$delivery_dropoff = isset( $params['delivery_dropoff'] ) ? sanitize_text_field( $params['delivery_dropoff'] ) : '';
	$delivery_date    = isset( $params['delivery_date'] ) ? sanitize_text_field( $params['delivery_date'] ) : '';
	$delivery_time    = isset( $params['delivery_time'] ) ? sanitize_text_field( $params['delivery_time'] ) : '';
	$return_address   = isset( $params['return_address'] ) ? sanitize_text_field( $params['return_address'] ) : '';
	$return_date      = isset( $params['return_date'] ) ? sanitize_text_field( $params['return_date'] ) : '';
	$return_time      = isset( $params['return_time'] ) ? sanitize_text_field( $params['return_time'] ) : '';
	$special_requests = isset( $params['special_requests'] ) ? sanitize_textarea_field( $params['special_requests'] ) : '';

	// Documents — structured object with attachment IDs per category
	$documents = isset( $params['documents'] ) ? $params['documents'] : array();

	// --- Validate car exists ---
	$car = get_post( $car_id );
	if ( ! $car || $car->post_type !== 'car' || $car->post_status !== 'publish' ) {
		return new WP_Error(
			'invalid_car',
			__( 'This car does not exist.', 'obsidian-booking' ),
			array( 'status' => 400 )
		);
	}

	// --- Validate color exists for this car ---
	if ( ! empty( $color ) ) {
		$variants = obsidian_get_color_variants( $car_id );
		if ( ! isset( $variants[ $color ] ) ) {
			return new WP_Error(
				'invalid_color',
				__( 'This color is not available for this vehicle.', 'obsidian-booking' ),
				array( 'status' => 400 )
			);
		}
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

	// --- Validate birth date (must be 21+) ---
	$dob = DateTime::createFromFormat( 'Y-m-d', $birth_date );
	if ( ! $dob ) {
		return new WP_Error(
			'invalid_birth_date',
			__( 'Invalid birth date format.', 'obsidian-booking' ),
			array( 'status' => 400 )
		);
	}
	$age = $dob->diff( $today )->y;
	if ( $age < 21 ) {
		return new WP_Error(
			'underage',
			__( 'You must be at least 21 years old to rent a vehicle.', 'obsidian-booking' ),
			array( 'status' => 400 )
		);
	}

	// --- Validate customer type ---
	if ( ! in_array( $customer_type, array( 'local', 'international' ), true ) ) {
		return new WP_Error(
			'invalid_customer_type',
			__( 'Customer type must be "local" or "international".', 'obsidian-booking' ),
			array( 'status' => 400 )
		);
	}

	// --- Type-specific validation ---
	if ( $customer_type === 'local' && empty( $phone ) ) {
		return new WP_Error(
			'missing_field',
			__( 'Phone number is required for local renters.', 'obsidian-booking' ),
			array( 'status' => 400 )
		);
	}

	if ( $customer_type === 'international' && empty( $passport_number ) ) {
		return new WP_Error(
			'missing_field',
			__( 'Passport number is required for international renters.', 'obsidian-booking' ),
			array( 'status' => 400 )
		);
	}

	// --- RE-CHECK AVAILABILITY (race condition protection) ---
	if ( ! empty( $color ) ) {
		$color_available = obsidian_get_available_units_by_color( $car_id, $color, $start_date, $end_date );
		if ( $color_available <= 0 ) {
			return new WP_Error(
				'not_available',
				__( 'Sorry, this color variant is no longer available for the selected dates.', 'obsidian-booking' ),
				array( 'status' => 409 )
			);
		}
	} elseif ( ! obsidian_is_car_available( $car_id, $start_date, $end_date ) ) {
		return new WP_Error(
			'not_available',
			__( 'Sorry, this car is no longer available for the selected dates.', 'obsidian-booking' ),
			array( 'status' => 409 )
		);
	}

	// --- Calculate price ---
	$daily_rate = (float) get_field( 'car_daily_rate', $car_id );
	$num_days   = $start->diff( $end )->days;
	$total      = $daily_rate * $num_days;

	// --- Create the booking post ---
	$booking_title = sprintf(
		'%s — %s %s (%s to %s)',
		get_the_title( $car_id ),
		$first_name,
		$last_name,
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
	update_post_meta( $booking_id, '_booking_customer_type', $customer_type );
	update_post_meta( $booking_id, '_booking_status', 'pending_review' );
	update_post_meta( $booking_id, '_booking_total_price', $total );
	update_post_meta( $booking_id, '_booking_color', $color );

	// Contact & identity
	update_post_meta( $booking_id, '_booking_first_name', $first_name );
	update_post_meta( $booking_id, '_booking_last_name', $last_name );
	update_post_meta( $booking_id, '_booking_email', $user->user_email );
	update_post_meta( $booking_id, '_booking_address', $address );
	update_post_meta( $booking_id, '_booking_birth_date', $birth_date );
	update_post_meta( $booking_id, '_booking_phone', $phone );
	update_post_meta( $booking_id, '_booking_license_number', $license_number );
	update_post_meta( $booking_id, '_booking_location', $location );

	// Type-specific
	update_post_meta( $booking_id, '_booking_gov_id_type', $gov_id_type );
	update_post_meta( $booking_id, '_booking_gov_id_type_2', $gov_id_type_2 );
	update_post_meta( $booking_id, '_booking_passport_number', $passport_number );

	// Documents
	update_post_meta( $booking_id, '_booking_documents', wp_json_encode( $documents ) );

	// Delivery info
	update_post_meta( $booking_id, '_booking_delivery_contact', $delivery_contact );
	update_post_meta( $booking_id, '_booking_delivery_dropoff', $delivery_dropoff );
	update_post_meta( $booking_id, '_booking_delivery_date', $delivery_date );
	update_post_meta( $booking_id, '_booking_delivery_time', $delivery_time );
	update_post_meta( $booking_id, '_booking_return_address', $return_address );
	update_post_meta( $booking_id, '_booking_return_date', $return_date );
	update_post_meta( $booking_id, '_booking_return_time', $return_time );
	update_post_meta( $booking_id, '_booking_special_requests', $special_requests );

	// Admin & payment defaults
	update_post_meta( $booking_id, '_booking_admin_notes', '' );
	update_post_meta( $booking_id, '_booking_denial_reason', '' );
	update_post_meta( $booking_id, '_booking_payment_type', '' );
	update_post_meta( $booking_id, '_booking_payment_amount', 0 );
	update_post_meta( $booking_id, '_booking_deposit_amount', 0 );
	update_post_meta( $booking_id, '_booking_balance_due', $total );
	update_post_meta( $booking_id, '_booking_payment_id', '' );
	update_post_meta( $booking_id, '_booking_payment_status', 'unpaid' );

	do_action( 'obsidian_booking_status_changed', $booking_id, '', 'pending_review' );

	return rest_ensure_response( array(
		'success'    => true,
		'booking_id' => $booking_id,
		'status'     => 'pending_review',
		'total'      => $total,
		'message'    => __( 'Your documents have been submitted for review! We will email you once approved.', 'obsidian-booking' ),
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
			'booking_id'       => $booking->ID,
			'car_id'           => $car_id,
			'car_name'         => get_the_title( $car_id ),
			'car_image'        => get_the_post_thumbnail_url( $car_id, 'medium' ),
			'start_date'       => get_post_meta( $booking->ID, '_booking_start_date', true ),
			'end_date'         => get_post_meta( $booking->ID, '_booking_end_date', true ),
			'pickup_location'  => get_post_meta( $booking->ID, '_booking_pickup_location', true ),
			'customer_type'    => get_post_meta( $booking->ID, '_booking_customer_type', true ),
			'status'           => get_post_meta( $booking->ID, '_booking_status', true ),
			'total_price'      => (float) get_post_meta( $booking->ID, '_booking_total_price', true ),
			'payment_type'     => get_post_meta( $booking->ID, '_booking_payment_type', true ),
			'payment_amount'   => (float) get_post_meta( $booking->ID, '_booking_payment_amount', true ),
			'balance_due'      => (float) get_post_meta( $booking->ID, '_booking_balance_due', true ),
			'payment_status'   => get_post_meta( $booking->ID, '_booking_payment_status', true ),
			'color'            => get_post_meta( $booking->ID, '_booking_color', true ),
			'created_at'       => $booking->post_date,
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

	// Build per-color variant data with full gallery per color
	$variants       = obsidian_get_color_variants( $car_id );
	$color_variants = array();
	$fallback_img   = get_the_post_thumbnail_url( $car_id, 'large' ) ?: '';

	foreach ( $variants as $color_name => $data ) {
		$images     = isset( $data['images'] ) && is_array( $data['images'] ) ? $data['images'] : array();
		$gallery    = array();

		foreach ( $images as $img_id ) {
			$img_id = (int) $img_id;
			if ( $img_id > 0 ) {
				$url = wp_get_attachment_image_url( $img_id, 'large' );
				if ( $url ) {
					$gallery[] = $url;
				}
			}
		}

		$color_variants[] = array(
			'color'   => $color_name,
			'hex'     => obsidian_get_color_hex( $color_name ),
			'units'   => (int) ( $data['units'] ?? 0 ),
			'image'   => ! empty( $gallery ) ? $gallery[0] : $fallback_img,
			'gallery' => $gallery,
		);
	}

	// Specifications — WYSIWYG or textarea field
	$specs_raw = get_field( 'car_specs', $car_id );
	$specs     = $specs_raw ? wp_kses_post( $specs_raw ) : '';

	return array(
		'id'             => $car_id,
		'name'           => get_the_title( $car_id ),
		'slug'           => get_post_field( 'post_name', $car_id ),
		'description'    => get_the_excerpt( $car_id ),
		'image'          => $fallback_img,
		'car_class'      => $car_class,
		'make'           => get_field( 'car_make', $car_id ) ?: '',
		'model'          => get_field( 'car_model', $car_id ) ?: '',
		'year'           => (int) get_field( 'car_year', $car_id ),
		'daily_rate'     => (float) get_field( 'car_daily_rate', $car_id ),
		'total_units'    => obsidian_get_total_units( $car_id ),
		'colors'         => $colors,
		'color_variants' => $color_variants,
		'specifications' => $specs,
		'status'         => get_field( 'car_status', $car_id ) ?: 'available',
		'link'           => get_permalink( $car_id ),
	);
}
