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

	/* ── Phase 11: Locations & Regions ─────────────────── */

	// GET /regions — All regions, each with its child branches.
	register_rest_route( $namespace, '/regions', array(
		'methods'             => 'GET',
		'callback'            => 'obsidian_api_get_regions',
		'permission_callback' => '__return_true',
	) );

	// GET /locations — All branches (filterable by ?region=slug or ?status=)
	register_rest_route( $namespace, '/locations', array(
		'methods'             => 'GET',
		'callback'            => 'obsidian_api_get_locations',
		'permission_callback' => '__return_true',
	) );

	// GET /locations/{id} — Single branch detail
	register_rest_route( $namespace, '/locations/(?P<id>\d+)', array(
		'methods'             => 'GET',
		'callback'            => 'obsidian_api_get_location',
		'permission_callback' => '__return_true',
		'args'                => array(
			'id' => array(
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
 *
 * Optional query params:
 *   ?location_id=12  → only cars stocked at that branch (and counts scoped to it)
 *   ?region=luzon    → only cars stocked at any branch in that region
 *
 * When neither is provided, returns every published car with units summed
 * across all branches (legacy behaviour).
 */
function obsidian_api_get_cars( $request ) {

	$location_id  = isset( $request['location_id'] ) ? (int) $request['location_id'] : 0;
	$region_slug  = isset( $request['region'] ) ? sanitize_title( $request['region'] ) : '';

	$cars = get_posts( array(
		'post_type'      => 'car',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	) );

	$data = array();

	foreach ( $cars as $car ) {

		// Apply branch / region filtering.
		$branches = obsidian_get_car_branches( $car->ID );

		if ( $location_id > 0 ) {
			if ( ! in_array( $location_id, $branches, true ) ) {
				continue;
			}
		} elseif ( $region_slug !== '' ) {
			$regions = obsidian_get_car_regions( $car->ID );
			if ( ! in_array( $region_slug, $regions, true ) ) {
				continue;
			}
		}

		$data[] = obsidian_format_car_data( $car->ID, $location_id );
	}

	return rest_ensure_response( $data );
}

/**
 * GET /cars/{id}
 *
 * Optional query param:
 *   ?location_id=12 — scope color_variants and units to that branch.
 *   Without it, returns the multi-branch aggregated view + a `branches`
 *   array describing every branch this car is stocked at.
 */
function obsidian_api_get_car( $request ) {

	$car_id      = (int) $request['id'];
	$location_id = isset( $request['location_id'] ) ? (int) $request['location_id'] : 0;
	$car         = get_post( $car_id );

	if ( ! $car || $car->post_type !== 'car' || $car->post_status !== 'publish' ) {
		return new WP_Error(
			'car_not_found',
			__( 'Car not found.', 'obsidian-booking' ),
			array( 'status' => 404 )
		);
	}

	if ( $location_id > 0 && ! obsidian_branch_has_car( $car_id, $location_id ) ) {
		return new WP_Error(
			'car_not_at_branch',
			__( 'This vehicle is not available at the selected branch.', 'obsidian-booking' ),
			array( 'status' => 404 )
		);
	}

	return rest_ensure_response( obsidian_format_car_data( $car_id, $location_id ) );
}

/**
 * GET /availability/{car_id}
 *
 * Optional query params:
 *   ?location_id=12 — scope availability to that branch (recommended).
 *   ?days=N         — how many days ahead to compute (default 90).
 *
 * Without `location_id` the response represents aggregated availability
 * across all branches — which the modal uses when the user hasn't picked
 * a specific branch yet.
 */
function obsidian_api_get_availability( $request ) {

	$car_id      = (int) $request['car_id'];
	$location_id = isset( $request['location_id'] ) ? (int) $request['location_id'] : 0;
	$car         = get_post( $car_id );

	if ( ! $car || $car->post_type !== 'car' ) {
		return new WP_Error(
			'car_not_found',
			__( 'Car not found.', 'obsidian-booking' ),
			array( 'status' => 404 )
		);
	}

	if ( $location_id > 0 && ! obsidian_branch_has_car( $car_id, $location_id ) ) {
		return new WP_Error(
			'car_not_at_branch',
			__( 'This vehicle is not available at the selected branch.', 'obsidian-booking' ),
			array( 'status' => 404 )
		);
	}

	$days_ahead           = isset( $request['days'] ) ? (int) $request['days'] : 90;
	$total_units          = obsidian_get_total_units( $car_id, $location_id );
	$unavailable          = obsidian_get_unavailable_dates( $car_id, $days_ahead, $location_id );
	$unavailable_by_color = obsidian_get_unavailable_dates_by_color( $car_id, $days_ahead, $location_id );

	return rest_ensure_response( array(
		'car_id'                     => $car_id,
		'location_id'                => $location_id,
		'total_units'                => $total_units,
		'unavailable_dates'          => $unavailable,
		'unavailable_dates_by_color' => $unavailable_by_color,
		'days_checked'               => $days_ahead,
	) );
}

/**
 * GET /regions
 *
 * Returns every region term with its child active branches embedded.
 * Used by the header mega-menu, fleet sidebar filters, and any UI that
 * needs the full hierarchy in one round-trip.
 */
function obsidian_api_get_regions( $request ) {

	$regions = get_terms( array(
		'taxonomy'   => 'region',
		'hide_empty' => false,
		'orderby'    => 'name',
		'order'      => 'ASC',
	) );

	if ( is_wp_error( $regions ) ) {
		return rest_ensure_response( array() );
	}

	$out = array();

	foreach ( $regions as $region ) {

		$branch_ids = get_posts( array(
			'post_type'      => 'location',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'title',
			'order'          => 'ASC',
			'tax_query'      => array(
				array(
					'taxonomy' => 'region',
					'field'    => 'term_id',
					'terms'    => $region->term_id,
				),
			),
		) );

		$branches = array();
		foreach ( $branch_ids as $branch_id ) {
			$branches[] = obsidian_format_location_data( $branch_id, false );
		}

		$out[] = array(
			'id'       => $region->term_id,
			'name'     => $region->name,
			'slug'     => $region->slug,
			'branches' => $branches,
		);
	}

	return rest_ensure_response( $out );
}

/**
 * GET /locations
 *
 * Filterable by ?region=slug or ?status=active|coming_soon|closed.
 * Returns full branch detail (address, contact, coordinates) for each.
 */
function obsidian_api_get_locations( $request ) {

	$region_slug = isset( $request['region'] ) ? sanitize_title( $request['region'] ) : '';
	$status      = isset( $request['status'] ) ? sanitize_text_field( $request['status'] ) : '';

	$args = array(
		'post_type'      => 'location',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'fields'         => 'ids',
	);

	if ( $region_slug !== '' ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'region',
				'field'    => 'slug',
				'terms'    => $region_slug,
			),
		);
	}

	if ( $status !== '' ) {
		$args['meta_query'] = array(
			array(
				'key'     => 'location_status',
				'value'   => $status,
				'compare' => '=',
			),
		);
	}

	$ids  = get_posts( $args );
	$data = array();

	foreach ( $ids as $id ) {
		$data[] = obsidian_format_location_data( $id, true );
	}

	return rest_ensure_response( $data );
}

/**
 * GET /locations/{id} — Single branch detail.
 */
function obsidian_api_get_location( $request ) {

	$id   = (int) $request['id'];
	$post = get_post( $id );

	if ( ! $post || $post->post_type !== 'location' || $post->post_status !== 'publish' ) {
		return new WP_Error(
			'location_not_found',
			__( 'Branch not found.', 'obsidian-booking' ),
			array( 'status' => 404 )
		);
	}

	return rest_ensure_response( obsidian_format_location_data( $id, true ) );
}

/**
 * POST /bookings
 * Creates a new booking. Validates all inputs and re-checks availability.
 */
function obsidian_api_create_booking( $request ) {

	$params = $request->get_json_params();

	// --- Required fields (shared) ---
	// Note: `location_id` is the canonical Phase-11 branch reference. The legacy
	// `location` string field is still accepted for one release (and resolved to
	// the Main Branch below) so old cached frontends don't break mid-deploy.
	$required = array( 'car_id', 'start_date', 'end_date', 'customer_type', 'first_name', 'last_name', 'address', 'birth_date', 'license_number' );
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

	// Location — prefer the new integer branch ID, fall back to legacy slug.
	$location_id  = isset( $params['location_id'] ) ? (int) $params['location_id'] : 0;
	$location_str = isset( $params['location'] ) ? sanitize_text_field( $params['location'] ) : '';

	// Backward-compat: if no branch ID was sent (old form), point this booking
	// at the Main Branch (the same target the migration uses) so we never
	// orphan a booking from a branch.
	if ( $location_id <= 0 ) {
		$fallback = obsidian_migration_v2_ensure_main_branch();
		if ( $fallback > 0 ) {
			$location_id = $fallback;
		}
	}

	if ( $location_id <= 0 ) {
		return new WP_Error(
			'missing_field',
			__( 'A pickup branch must be selected.', 'obsidian-booking' ),
			array( 'status' => 400 )
		);
	}

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

	// --- Validate branch exists, is published, and is active ---
	$branch = get_post( $location_id );
	if ( ! $branch || $branch->post_type !== 'location' || $branch->post_status !== 'publish' ) {
		return new WP_Error(
			'invalid_branch',
			__( 'The selected branch does not exist.', 'obsidian-booking' ),
			array( 'status' => 400 )
		);
	}
	$branch_status = get_post_meta( $location_id, 'location_status', true );
	if ( $branch_status && $branch_status !== 'active' ) {
		return new WP_Error(
			'branch_inactive',
			__( 'The selected branch is not currently accepting bookings.', 'obsidian-booking' ),
			array( 'status' => 400 )
		);
	}

	// --- Validate the car is actually stocked at this branch ---
	if ( ! obsidian_branch_has_car( $car_id, $location_id ) ) {
		return new WP_Error(
			'car_not_at_branch',
			__( 'This vehicle is not available at the selected branch.', 'obsidian-booking' ),
			array( 'status' => 400 )
		);
	}

	// --- Validate color exists for this car at this branch ---
	if ( ! empty( $color ) ) {
		$variants = obsidian_get_color_variants( $car_id, $location_id );
		if ( ! isset( $variants[ $color ] ) || (int) $variants[ $color ]['units'] <= 0 ) {
			return new WP_Error(
				'invalid_color',
				__( 'This color is not available for this vehicle at the selected branch.', 'obsidian-booking' ),
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
	// Always scope to the selected branch so two customers can't race to book
	// the last Orange GTR at the same branch.
	if ( ! empty( $color ) ) {
		$color_available = obsidian_get_available_units_by_color( $car_id, $color, $start_date, $end_date, $location_id );
		if ( $color_available <= 0 ) {
			return new WP_Error(
				'not_available',
				__( 'Sorry, this color variant is no longer available at this branch for the selected dates.', 'obsidian-booking' ),
				array( 'status' => 409 )
			);
		}
	} elseif ( ! obsidian_is_car_available( $car_id, $start_date, $end_date, $location_id ) ) {
		return new WP_Error(
			'not_available',
			__( 'Sorry, this car is no longer available at this branch for the selected dates.', 'obsidian-booking' ),
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

	// Phase 11: canonical branch reference + legacy string for one-release compat.
	update_post_meta( $booking_id, '_booking_location_id', $location_id );
	if ( $location_str !== '' ) {
		update_post_meta( $booking_id, '_booking_location', $location_str );
	} else {
		// Store the branch title as a human-readable fallback for old reports/UIs.
		update_post_meta( $booking_id, '_booking_location', get_the_title( $location_id ) );
	}

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
 * @param int $car_id      The Car post ID.
 * @param int $location_id Optional branch to scope inventory & units to.
 *                         0 = aggregate across all branches.
 * @return array Formatted car data.
 */
function obsidian_format_car_data( $car_id, $location_id = 0 ) {

	$location_id = (int) $location_id;

	// Get car class terms
	$classes    = wp_get_post_terms( $car_id, 'car_class', array( 'fields' => 'names' ) );
	$car_class  = ! is_wp_error( $classes ) && ! empty( $classes ) ? $classes[0] : '';

	// Master color list (universe of colors this vehicle exists in).
	$master_colors = get_field( 'car_colors', $car_id );
	if ( ! is_array( $master_colors ) ) {
		$master_colors = array();
	}

	// Build per-color variant data with full gallery per color, scoped to the
	// requested branch (or aggregated when $location_id is 0).
	$variants       = obsidian_get_color_variants( $car_id, $location_id );

	// `colors` field served to the frontend = the colors actually relevant
	// to the current scope. When a branch is selected, this is the subset
	// stocked at that branch; otherwise it's the master list.
	if ( $location_id > 0 ) {
		$scoped_color_keys = array_keys( $variants );
		$colors            = array();
		foreach ( $master_colors as $c ) {
			if ( in_array( strtolower( (string) $c ), $scoped_color_keys, true ) ) {
				$colors[] = $c;
			}
		}
	} else {
		$colors = $master_colors;
	}
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

	// "Available at" — every branch this car is stocked at, regardless of
	// the current scope. Used by the fleet card badge and the modal's
	// branch picker.
	$branch_ids   = obsidian_get_car_branches( $car_id );
	$car_branches = array();
	foreach ( $branch_ids as $branch_id ) {
		$car_branches[] = obsidian_format_location_data( $branch_id, false );
	}

	// Specifications — WYSIWYG or textarea field
	$specs_raw = get_field( 'car_specs', $car_id );
	$specs     = $specs_raw ? wp_kses_post( $specs_raw ) : '';

	// Status — when a branch is selected, return the per-branch status
	// (so the modal/UI can react to that branch being in maintenance even
	// while the vehicle is globally `available`). Otherwise return the
	// vehicle-wide ACF `car_status` as the headline status.
	$global_status = get_field( 'car_status', $car_id ) ?: 'available';
	$status        = $location_id > 0
		? obsidian_get_branch_status( $car_id, $location_id )
		: $global_status;

	return array(
		'id'              => $car_id,
		'name'            => get_the_title( $car_id ),
		'slug'            => get_post_field( 'post_name', $car_id ),
		'description'     => get_the_excerpt( $car_id ),
		'image'           => $fallback_img,
		'car_class'       => $car_class,
		'make'            => get_field( 'car_make', $car_id ) ?: '',
		'model'           => get_field( 'car_model', $car_id ) ?: '',
		'year'            => (int) get_field( 'car_year', $car_id ),
		'daily_rate'      => (float) get_field( 'car_daily_rate', $car_id ),
		'total_units'     => obsidian_get_total_units( $car_id, $location_id ),
		'colors'          => $colors,
		'master_colors'   => $master_colors,
		'color_variants'  => $color_variants,
		'specifications'  => $specs,
		'status'          => $status,
		'global_status'   => $global_status,
		'branch_status'   => $location_id > 0 ? obsidian_get_branch_status( $car_id, $location_id ) : '',
		'link'            => get_permalink( $car_id ),
		'location_id'     => $location_id,
		'branches'        => $car_branches,
	);
}

/**
 * Format a Location post for REST output.
 *
 * @param int  $id          Location post ID.
 * @param bool $with_detail When true, include address/contact/coordinates.
 *                          When false, only the bare minimum (id, name, slug,
 *                          region) — used inside /cars and /regions to keep
 *                          payload size reasonable.
 * @return array
 */
function obsidian_format_location_data( $id, $with_detail = true ) {

	$id     = (int) $id;
	$region = wp_get_post_terms( $id, 'region', array( 'number' => 1 ) );
	$region = ! is_wp_error( $region ) && ! empty( $region ) ? $region[0] : null;

	$base = array(
		'id'          => $id,
		'name'        => get_the_title( $id ),
		'slug'        => get_post_field( 'post_name', $id ),
		'status'      => get_post_meta( $id, 'location_status', true ) ?: 'active',
		'region_id'   => $region ? (int) $region->term_id : 0,
		'region_name' => $region ? $region->name : '',
		'region_slug' => $region ? $region->slug : '',
	);

	if ( ! $with_detail ) {
		return $base;
	}

	return array_merge( $base, array(
		'address'        => get_post_meta( $id, 'location_address', true ),
		'contact_number' => get_post_meta( $id, 'location_contact_number', true ),
		'contact_email'  => get_post_meta( $id, 'location_contact_email', true ),
		'hours'          => get_post_meta( $id, 'location_hours', true ),
		'map_url'        => get_post_meta( $id, 'location_map_url', true ),
		'latitude'       => (float) get_post_meta( $id, 'location_latitude', true ),
		'longitude'      => (float) get_post_meta( $id, 'location_longitude', true ),
		'image'          => get_the_post_thumbnail_url( $id, 'large' ) ?: '',
		'description'    => apply_filters( 'the_content', get_post_field( 'post_content', $id ) ),
		'link'           => get_permalink( $id ),
	) );
}
