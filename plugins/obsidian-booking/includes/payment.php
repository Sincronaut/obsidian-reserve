<?php
/**
 * PayMongo Payment Integration
 *
 * Token generation for secure payment links, PayMongo Payment Intent
 * creation, and webhook handler for payment confirmation.
 *
 * Requires constants in wp-config.php:
 *   define( 'PAYMONGO_SECRET_KEY', 'sk_test_...' );
 *   define( 'PAYMONGO_PUBLIC_KEY', 'pk_test_...' );
 *
 * @package obsidian-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ══════════════════════════════════════════════════════════════
   PAYMENT TOKEN — Secure link for the payment page
   ══════════════════════════════════════════════════════════════ */

/**
 * Generate a secure payment token for a booking.
 * Called when admin approves documents.
 *
 * @param int $booking_id The Booking post ID.
 * @return string The generated token.
 */
function obsidian_generate_payment_token( $booking_id ) {
	$token = hash( 'sha256', $booking_id . wp_salt( 'auth' ) . time() . wp_rand() );
	update_post_meta( $booking_id, '_booking_payment_token', $token );
	return $token;
}

/**
 * Verify a payment token matches the stored token for a booking.
 *
 * @param int    $booking_id The Booking post ID.
 * @param string $token      The token to verify.
 * @return bool True if valid.
 */
function obsidian_verify_payment_token( $booking_id, $token ) {
	if ( empty( $token ) ) {
		return false;
	}
	$stored = get_post_meta( $booking_id, '_booking_payment_token', true );
	return hash_equals( $stored, $token );
}

/**
 * Build the payment page URL for a booking.
 *
 * @param int    $booking_id The Booking post ID.
 * @param string $token      The payment token.
 * @return string Full URL to the payment page.
 */
function obsidian_get_payment_url( $booking_id, $token ) {
	return add_query_arg(
		array(
			'booking_id' => $booking_id,
			'token'      => $token,
		),
		home_url( '/booking/payment/' )
	);
}

/* ══════════════════════════════════════════════════════════════
   PAYMONGO API — Payment Intent creation
   ══════════════════════════════════════════════════════════════ */

/**
 * Create a PayMongo Payment Intent for a booking.
 *
 * @param int    $booking_id     The Booking post ID.
 * @param string $payment_option 'down' for 50% or 'full' for 100%.
 * @param string $payment_method PayMongo payment method type.
 * @return array|WP_Error Payment Intent data or error.
 */
function obsidian_create_payment_intent( $booking_id, $payment_option = 'full', $payment_method = 'card' ) {

	if ( ! defined( 'PAYMONGO_SECRET_KEY' ) || empty( PAYMONGO_SECRET_KEY ) ) {
		return new WP_Error( 'paymongo_not_configured', 'PayMongo API keys are not configured.' );
	}

	$total         = (float) get_post_meta( $booking_id, '_booking_total_price', true );
	$min_deposit   = 10000;
	$deposit       = max( $min_deposit, $total * 0.40 );

	$rental_charge = ( $payment_option === 'full' ) ? $total : round( $total * 0.50 );
	$charge_amount = $rental_charge + $deposit;

	// PayMongo expects amount in centavos (₱100 = 10000)
	$amount_centavos = (int) round( $charge_amount * 100 );

	// Minimum ₱100 (10000 centavos)
	if ( $amount_centavos < 10000 ) {
		$amount_centavos = 10000;
	}

	$car_id   = (int) get_post_meta( $booking_id, '_booking_car_id', true );
	$car_name = get_the_title( $car_id );

	// Allowed payment methods for this intent
	$allowed_methods = array( 'card', 'dob', 'dob_ubp', 'grab_pay' );
	$method_type     = in_array( $payment_method, $allowed_methods, true ) ? $payment_method : 'card';

	$response = wp_remote_post( 'https://api.paymongo.com/v1/payment_intents', array(
		'headers' => array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Basic ' . base64_encode( PAYMONGO_SECRET_KEY . ':' ),
		),
		'body'    => wp_json_encode( array(
			'data' => array(
				'attributes' => array(
					'amount'                 => $amount_centavos,
					'payment_method_allowed' => array( $method_type ),
					'currency'               => 'PHP',
					'description'            => sprintf( 'Booking #%d — %s', $booking_id, $car_name ),
					'statement_descriptor'   => 'Obsidian Reserve',
					'metadata'               => array(
						'booking_id' => (string) $booking_id,
					),
				),
			),
		) ),
		'timeout' => 30,
	) );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$status_code = wp_remote_retrieve_response_code( $response );
	$body        = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $status_code !== 200 || empty( $body['data'] ) ) {
		$error_msg = $body['errors'][0]['detail'] ?? 'Failed to create payment intent.';
		return new WP_Error( 'paymongo_error', $error_msg );
	}

	$intent_id  = $body['data']['id'];
	$client_key = $body['data']['attributes']['client_key'];

	// Store the intent ID on the booking
	update_post_meta( $booking_id, '_booking_payment_id', $intent_id );
	update_post_meta( $booking_id, '_booking_deposit_amount', $deposit );

	return array(
		'intent_id'      => $intent_id,
		'client_key'     => $client_key,
		'amount'         => $charge_amount,
		'deposit'        => $deposit,
		'rental_charge'  => $rental_charge,
		'rental_total'   => $total,
	);
}

/* ══════════════════════════════════════════════════════════════
   REST ENDPOINTS — Payment Intent + Webhook
   ══════════════════════════════════════════════════════════════ */

function obsidian_register_payment_routes() {

	$namespace = 'obsidian-booking/v1';

	// Create Payment Intent (called by payment page JS)
	register_rest_route( $namespace, '/create-payment-intent', array(
		'methods'             => 'POST',
		'callback'            => 'obsidian_api_create_payment_intent',
		'permission_callback' => function () {
			return is_user_logged_in();
		},
	) );

	// Confirm Payment (called by confirmation.js after successful attach)
	register_rest_route( $namespace, '/confirm-payment', array(
		'methods'             => 'POST',
		'callback'            => 'obsidian_api_confirm_payment',
		'permission_callback' => function () {
			return is_user_logged_in();
		},
	) );

	// PayMongo Webhook (called by PayMongo servers)
	register_rest_route( $namespace, '/paymongo-webhook', array(
		'methods'             => 'POST',
		'callback'            => 'obsidian_api_paymongo_webhook',
		'permission_callback' => '__return_true',
	) );
}
add_action( 'rest_api_init', 'obsidian_register_payment_routes' );

/**
 * POST /create-payment-intent
 * Creates a PayMongo Payment Intent for the given booking.
 */
function obsidian_api_create_payment_intent( $request ) {

	$params         = $request->get_json_params();
	$booking_id     = (int) ( $params['booking_id'] ?? 0 );
	$token          = sanitize_text_field( $params['token'] ?? '' );
	$payment_option = sanitize_text_field( $params['payment_option'] ?? 'full' );
	$payment_method = sanitize_text_field( $params['payment_method'] ?? 'card' );

	if ( ! $booking_id ) {
		return new WP_Error( 'missing_booking', 'Booking ID is required.', array( 'status' => 400 ) );
	}

	// Verify token
	if ( ! obsidian_verify_payment_token( $booking_id, $token ) ) {
		return new WP_Error( 'invalid_token', 'Invalid or expired payment link.', array( 'status' => 403 ) );
	}

	// Verify status
	$status = get_post_meta( $booking_id, '_booking_status', true );
	if ( $status !== 'awaiting_payment' ) {
		return new WP_Error( 'invalid_status', 'This booking is not awaiting payment.', array( 'status' => 400 ) );
	}

	// Verify user owns this booking
	$booking_user = (int) get_post_meta( $booking_id, '_booking_user_id', true );
	if ( $booking_user !== get_current_user_id() ) {
		return new WP_Error( 'unauthorized', 'You do not own this booking.', array( 'status' => 403 ) );
	}

	// Store the selected payment option
	update_post_meta( $booking_id, '_booking_payment_option', $payment_option );
	update_post_meta( $booking_id, '_booking_payment_method', $payment_method );

	$result = obsidian_create_payment_intent( $booking_id, $payment_option, $payment_method );

	if ( is_wp_error( $result ) ) {
		return new WP_Error( 'payment_error', $result->get_error_message(), array( 'status' => 500 ) );
	}

	return rest_ensure_response( $result );
}

/**
 * POST /confirm-payment
 * Called by confirmation.js after PayMongo attach returns succeeded.
 * Verifies the intent status server-side before updating booking.
 */
function obsidian_api_confirm_payment( $request ) {

	$params     = $request->get_json_params();
	$booking_id = (int) ( $params['booking_id'] ?? 0 );
	$intent_id  = sanitize_text_field( $params['intent_id'] ?? '' );

	if ( ! $booking_id || ! $intent_id ) {
		return new WP_Error( 'missing_data', 'Booking ID and intent ID are required.', array( 'status' => 400 ) );
	}

	// Verify user owns this booking.
	$booking_user = (int) get_post_meta( $booking_id, '_booking_user_id', true );
	if ( $booking_user !== get_current_user_id() ) {
		return new WP_Error( 'unauthorized', 'You do not own this booking.', array( 'status' => 403 ) );
	}

	// Verify the stored intent ID matches.
	$stored_intent = get_post_meta( $booking_id, '_booking_payment_id', true );
	if ( $stored_intent !== $intent_id ) {
		return new WP_Error( 'intent_mismatch', 'Payment intent does not match this booking.', array( 'status' => 400 ) );
	}

	$current_status = get_post_meta( $booking_id, '_booking_status', true );
	if ( $current_status !== 'awaiting_payment' ) {
		return rest_ensure_response( array( 'message' => 'Booking already processed.', 'status' => $current_status ) );
	}

	// Verify with PayMongo that the intent actually succeeded.
	$verify_response = wp_remote_get( 'https://api.paymongo.com/v1/payment_intents/' . $intent_id, array(
		'headers' => array(
			'Authorization' => 'Basic ' . base64_encode( PAYMONGO_SECRET_KEY . ':' ),
		),
		'timeout' => 15,
	) );

	if ( is_wp_error( $verify_response ) ) {
		return new WP_Error( 'verify_failed', 'Could not verify payment with PayMongo.', array( 'status' => 500 ) );
	}

	$verify_body   = json_decode( wp_remote_retrieve_body( $verify_response ), true );
	$intent_status = $verify_body['data']['attributes']['status'] ?? '';

	if ( $intent_status !== 'succeeded' ) {
		return new WP_Error( 'not_paid', 'Payment intent has not succeeded. Status: ' . $intent_status, array( 'status' => 400 ) );
	}

	// Payment verified — update booking.
	$amount_paid = ( $verify_body['data']['attributes']['amount'] ?? 0 ) / 100;

	update_post_meta( $booking_id, '_booking_status', 'paid' );
	update_post_meta( $booking_id, '_booking_payment_status', 'paid' );
	update_post_meta( $booking_id, '_booking_payment_amount', $amount_paid );

	do_action( 'obsidian_booking_status_changed', $booking_id, 'awaiting_payment', 'paid' );

	// Auto-transition to confirmed.
	update_post_meta( $booking_id, '_booking_status', 'confirmed' );
	do_action( 'obsidian_booking_status_changed', $booking_id, 'paid', 'confirmed' );

	return rest_ensure_response( array( 'message' => 'Booking confirmed.', 'status' => 'confirmed' ) );
}

/**
 * POST /paymongo-webhook
 * Handles incoming PayMongo webhook events.
 */
function obsidian_api_paymongo_webhook( $request ) {

	$body = $request->get_body();
	$data = json_decode( $body, true );

	if ( empty( $data['data']['attributes']['type'] ) ) {
		return new WP_REST_Response( array( 'message' => 'Invalid webhook payload.' ), 400 );
	}

	$event_type = $data['data']['attributes']['type'];

	if ( $event_type === 'payment.paid' ) {

		$payment_data    = $data['data']['attributes']['data'] ?? array();
		$payment_intent  = $payment_data['attributes']['payment_intent_id'] ?? '';

		if ( empty( $payment_intent ) ) {
			return new WP_REST_Response( array( 'message' => 'Missing payment intent ID.' ), 400 );
		}

		// Find the booking by payment intent ID
		$bookings = get_posts( array(
			'post_type'      => 'booking',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'meta_key'       => '_booking_payment_id',
			'meta_value'     => $payment_intent,
		) );

		if ( empty( $bookings ) ) {
			return new WP_REST_Response( array( 'message' => 'Booking not found for this payment.' ), 404 );
		}

		$booking_id     = $bookings[0]->ID;
		$current_status = get_post_meta( $booking_id, '_booking_status', true );

		if ( $current_status === 'awaiting_payment' ) {
			$amount_paid = ( $payment_data['attributes']['amount'] ?? 0 ) / 100;

			update_post_meta( $booking_id, '_booking_status', 'paid' );
			update_post_meta( $booking_id, '_booking_payment_status', 'paid' );
			update_post_meta( $booking_id, '_booking_payment_amount', $amount_paid );

			do_action( 'obsidian_booking_status_changed', $booking_id, 'awaiting_payment', 'paid' );

			// Auto-transition to confirmed
			update_post_meta( $booking_id, '_booking_status', 'confirmed' );
			do_action( 'obsidian_booking_status_changed', $booking_id, 'paid', 'confirmed' );
		}

		return new WP_REST_Response( array( 'message' => 'Payment processed.' ), 200 );
	}

	return new WP_REST_Response( array( 'message' => 'Event type not handled.' ), 200 );
}
