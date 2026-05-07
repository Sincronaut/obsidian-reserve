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

/*
 * ======================================================================
 * PAYMENT TOKEN - Secure link for the payment page
 * ======================================================================
 */

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
 * Build transient key for payment sessions.
 *
 * @param string $session_id Opaque payment session ID.
 * @return string
 */
function obsidian_payment_session_key( $session_id ) {

	return 'obsidian_payment_session_' . $session_id;
}

/**
 * Payment session lifetime in seconds.
 *
 * @return int
 */
function obsidian_payment_session_ttl() {

	return 2 * DAY_IN_SECONDS;
}

/**
 * Finalize an existing payment session so it cannot create new intents.
 * Keeps booking/user context for confirmation rendering.
 *
 * @param string $session_id Opaque payment session ID.
 * @return void
 */
function obsidian_finalize_payment_session( $session_id ) {

	$session_id = sanitize_key( $session_id );
	if ( ! preg_match( '/^[a-z0-9]{32}$/', $session_id ) ) {
		return;
	}

	$data = get_transient( obsidian_payment_session_key( $session_id ) );
	if ( ! is_array( $data ) ) {
		return;
	}

	$data['token']        = '';
	$data['finalized_at'] = time();

	set_transient( obsidian_payment_session_key( $session_id ), $data, DAY_IN_SECONDS );
}

/**
 * Finalize payment session linked to a booking.
 *
 * @param int $booking_id Booking post ID.
 * @return void
 */
function obsidian_finalize_payment_session_by_booking( $booking_id ) {

	$booking_id = (int) $booking_id;
	$session_id = get_post_meta( $booking_id, '_booking_payment_session_id', true );

	if ( is_string( $session_id ) && preg_match( '/^[a-z0-9]{32}$/', $session_id ) ) {
		obsidian_finalize_payment_session( $session_id );
	}
}

/**
 * Create or reuse a short-lived payment session for a booking/token pair.
 *
 * @param int    $booking_id Booking post ID.
 * @param string $token      Payment token.
 * @return string Session ID.
 */
function obsidian_create_payment_session( $booking_id, $token ) {

	$booking_id = (int) $booking_id;
	$token      = sanitize_text_field( $token );

	$existing = get_post_meta( $booking_id, '_booking_payment_session_id', true );
	if ( is_string( $existing ) && preg_match( '/^[a-z0-9]{32}$/', $existing ) ) {
		$data = get_transient( obsidian_payment_session_key( $existing ) );
		// phpcs:ignore WordPress.PHP.YodaConditions.NotYoda -- Both sides are dynamic values.
		if ( is_array( $data ) && $booking_id === (int) ( $data['booking_id'] ?? 0 ) ) {
			$existing_token = sanitize_text_field( $data['token'] ?? '' );
			$existing_user  = (int) ( $data['user_id'] ?? 0 );
			$booking_user   = (int) get_post_meta( $booking_id, '_booking_user_id', true );

			if ( $token === $existing_token && $existing_user === $booking_user && '' !== $existing_token ) {
				return $existing;
			}
		}
		delete_transient( obsidian_payment_session_key( $existing ) );
	}

	try {
		$session_id = bin2hex( random_bytes( 16 ) );
	} catch ( Exception $e ) {
		$session_id = strtolower( wp_generate_password( 32, false, false ) );
	}
	$data = array(
		'booking_id' => $booking_id,
		'token'      => $token,
		'user_id'    => (int) get_post_meta( $booking_id, '_booking_user_id', true ),
		'created_at' => time(),
	);

	set_transient( obsidian_payment_session_key( $session_id ), $data, obsidian_payment_session_ttl() );
	update_post_meta( $booking_id, '_booking_payment_session_id', $session_id );

	return $session_id;
}

/**
 * Resolve a payment session ID into booking context.
 *
 * @param string $session_id    Opaque payment session ID.
 * @param bool   $require_token Whether a live token is required.
 * @return array|false
 */
function obsidian_get_payment_session( $session_id, $require_token = true ) {

	$session_id = sanitize_key( $session_id );
	if ( ! preg_match( '/^[a-z0-9]{32}$/', $session_id ) ) {
		return false;
	}

	$data = get_transient( obsidian_payment_session_key( $session_id ) );
	if ( ! is_array( $data ) ) {
		return false;
	}

	$booking_id = (int) ( $data['booking_id'] ?? 0 );
	$token      = sanitize_text_field( $data['token'] ?? '' );
	$user_id    = (int) ( $data['user_id'] ?? 0 );

	if ( ! $booking_id || ! $user_id ) {
		return false;
	}

	if ( $require_token && '' === $token ) {
		return false;
	}

	return array(
		'session_id' => $session_id,
		'booking_id' => $booking_id,
		'token'      => $token,
		'user_id'    => $user_id,
	);
}

/**
 * Build the payment page URL for a booking.
 *
 * @param int    $booking_id The Booking post ID.
 * @param string $token      The payment token.
 * @return string Full URL to the payment page.
 */
function obsidian_get_payment_url( $booking_id, $token ) {

	$session_id = obsidian_create_payment_session( $booking_id, $token );
	return home_url( '/booking/payment/' . rawurlencode( $session_id ) . '/' );
}

/*
 * ======================================================================
 * PAYMONGO API - Payment Intent creation
 * ======================================================================
 */

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

	$total       = (float) get_post_meta( $booking_id, '_booking_total_price', true );
	$min_deposit = 10000;
	$deposit     = max( $min_deposit, $total * 0.40 );

	$rental_charge = ( 'full' === $payment_option ) ? $total : round( $total * 0.50 );
	$charge_amount = $rental_charge + $deposit;

	// PayMongo expects amount in centavos (PHP 100 = 10000).
	$amount_centavos = (int) round( $charge_amount * 100 );

	// Minimum PHP 100 (10000 centavos).
	if ( 10000 > $amount_centavos ) {
		$amount_centavos = 10000;
	}

	$car_id   = (int) get_post_meta( $booking_id, '_booking_car_id', true );
	$car_name = get_the_title( $car_id );

	// Allowed payment methods for this intent.
	$allowed_methods = array( 'card', 'dob', 'dob_ubp', 'grab_pay' );
	$method_type     = in_array( $payment_method, $allowed_methods, true ) ? $payment_method : 'card';

	$response = wp_remote_post(
		'https://api.paymongo.com/v1/payment_intents',
		array(
			'headers' => array(
				'Content-Type'  => 'application/json',
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required by PayMongo API.
				'Authorization' => 'Basic ' . base64_encode( PAYMONGO_SECRET_KEY . ':' ),
			),
			'body'    => wp_json_encode(
				array(
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
				)
			),
			'timeout' => 30,
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$status_code = wp_remote_retrieve_response_code( $response );
	$body        = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( 200 !== $status_code || empty( $body['data'] ) ) {
		$error_msg = $body['errors'][0]['detail'] ?? 'Failed to create payment intent.';
		return new WP_Error( 'paymongo_error', $error_msg );
	}

	$intent_id  = $body['data']['id'];
	$client_key = $body['data']['attributes']['client_key'];

	// Store the intent ID on the booking.
	update_post_meta( $booking_id, '_booking_payment_id', $intent_id );
	update_post_meta( $booking_id, '_booking_deposit_amount', $deposit );

	return array(
		'intent_id'     => $intent_id,
		'client_key'    => $client_key,
		'amount'        => $charge_amount,
		'deposit'       => $deposit,
		'rental_charge' => $rental_charge,
		'rental_total'  => $total,
	);
}

/*
 * ======================================================================
 * REST ENDPOINTS - Payment Intent + Webhook
 * ======================================================================
 */

/**
 * Register REST routes for payment operations.
 *
 * @return void
 */
function obsidian_register_payment_routes() {

	$namespace = 'obsidian-booking/v1';

	// Create Payment Intent (called by payment page JS).
	register_rest_route(
		$namespace,
		'/create-payment-intent',
		array(
			'methods'             => 'POST',
			'callback'            => 'obsidian_api_create_payment_intent',
			'permission_callback' => function () {
				return is_user_logged_in();
			},
		)
	);

	// Confirm Payment (called by confirmation.js after successful attach).
	register_rest_route(
		$namespace,
		'/confirm-payment',
		array(
			'methods'             => 'POST',
			'callback'            => 'obsidian_api_confirm_payment',
			'permission_callback' => function () {
				return is_user_logged_in();
			},
		)
	);

	// PayMongo Webhook (called by PayMongo servers).
	register_rest_route(
		$namespace,
		'/paymongo-webhook',
		array(
			'methods'             => 'POST',
			'callback'            => 'obsidian_api_paymongo_webhook',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'obsidian_register_payment_routes' );

/**
 * POST /create-payment-intent
 * Creates a PayMongo Payment Intent for the given booking.
 *
 * @param WP_REST_Request $request REST request.
 * @return WP_REST_Response|WP_Error
 */
function obsidian_api_create_payment_intent( $request ) {

	// Rate limit: max 5 payment intents per hour per user.
	$rl = obsidian_rate_limit( 'payment', 5, HOUR_IN_SECONDS );
	if ( is_wp_error( $rl ) ) {
		return $rl;
	}

	$params         = $request->get_json_params();
	$session_id     = sanitize_key( $params['payment_session_id'] ?? '' );
	$payment_option = sanitize_text_field( $params['payment_option'] ?? 'full' );
	$payment_method = sanitize_text_field( $params['payment_method'] ?? 'card' );
	$session        = obsidian_get_payment_session( $session_id );
	$booking_id     = $session ? (int) $session['booking_id'] : 0;
	$token          = $session ? sanitize_text_field( $session['token'] ) : '';

	if ( ! $session || ! $booking_id ) {
		return new WP_Error( 'missing_session', 'Payment session is required.', array( 'status' => 400 ) );
	}

	if ( get_current_user_id() !== (int) $session['user_id'] ) {
		return new WP_Error( 'unauthorized', 'You do not own this payment session.', array( 'status' => 403 ) );
	}

	// Verify token.
	if ( ! obsidian_verify_payment_token( $booking_id, $token ) ) {
		return new WP_Error( 'invalid_token', 'Invalid or expired payment link.', array( 'status' => 403 ) );
	}

	// Verify status.
	$status = get_post_meta( $booking_id, '_booking_status', true );
	if ( 'awaiting_payment' !== $status ) {
		return new WP_Error( 'invalid_status', 'This booking is not awaiting payment.', array( 'status' => 400 ) );
	}

	// Verify user owns this booking.
	$booking_user = (int) get_post_meta( $booking_id, '_booking_user_id', true );
	if ( get_current_user_id() !== $booking_user ) {
		return new WP_Error( 'unauthorized', 'You do not own this booking.', array( 'status' => 403 ) );
	}

	// Store the selected payment option.
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
 *
 * @param WP_REST_Request $request REST request.
 * @return WP_REST_Response|WP_Error
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
	if ( get_current_user_id() !== $booking_user ) {
		return new WP_Error( 'unauthorized', 'You do not own this booking.', array( 'status' => 403 ) );
	}

	// Verify the stored intent ID matches.
	$stored_intent = get_post_meta( $booking_id, '_booking_payment_id', true );
	if ( $intent_id !== $stored_intent ) {
		return new WP_Error( 'intent_mismatch', 'Payment intent does not match this booking.', array( 'status' => 400 ) );
	}

	$current_status = get_post_meta( $booking_id, '_booking_status', true );
	if ( 'awaiting_payment' !== $current_status ) {
		return rest_ensure_response(
			array(
				'message' => 'Booking already processed.',
				'status'  => $current_status,
			)
		);
	}

	// Verify with PayMongo that the intent actually succeeded.
	$verify_response = wp_remote_get(
		'https://api.paymongo.com/v1/payment_intents/' . $intent_id,
		array(
			'headers' => array(
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required by PayMongo API.
				'Authorization' => 'Basic ' . base64_encode( PAYMONGO_SECRET_KEY . ':' ),
			),
			'timeout' => 15,
		)
	);

	if ( is_wp_error( $verify_response ) ) {
		return new WP_Error( 'verify_failed', 'Could not verify payment with PayMongo.', array( 'status' => 500 ) );
	}

	$verify_body   = json_decode( wp_remote_retrieve_body( $verify_response ), true );
	$intent_status = $verify_body['data']['attributes']['status'] ?? '';

	if ( 'succeeded' !== $intent_status ) {
		return new WP_Error( 'not_paid', 'Payment intent has not succeeded. Status: ' . $intent_status, array( 'status' => 400 ) );
	}

	// Payment verified - update booking.
	$amount_paid = ( $verify_body['data']['attributes']['amount'] ?? 0 ) / 100;

	update_post_meta( $booking_id, '_booking_status', 'paid' );
	update_post_meta( $booking_id, '_booking_payment_status', 'paid' );
	update_post_meta( $booking_id, '_booking_payment_amount', $amount_paid );

	do_action( 'obsidian_booking_status_changed', $booking_id, 'awaiting_payment', 'paid' );

	// Auto-transition to confirmed.
	update_post_meta( $booking_id, '_booking_status', 'confirmed' );
	do_action( 'obsidian_booking_status_changed', $booking_id, 'paid', 'confirmed' );
	obsidian_finalize_payment_session_by_booking( $booking_id );

	return rest_ensure_response(
		array(
			'message' => 'Booking confirmed.',
			'status'  => 'confirmed',
		)
	);
}

/**
 * POST /paymongo-webhook
 * Handles incoming PayMongo webhook events.
 *
 * Security: Verifies the `Paymongo-Signature` header using HMAC-SHA256
 * against the webhook secret key before processing any event. This
 * prevents forged requests from auto-confirming unpaid bookings.
 *
 * Requires constant in wp-config.php:
 *   define( 'PAYMONGO_WEBHOOK_SECRET', 'whsk_...' );
 *
 * @param WP_REST_Request $request REST request.
 * @return WP_REST_Response
 */
function obsidian_api_paymongo_webhook( $request ) {

	$body = $request->get_body();

	// --- Verify webhook signature (Critical Security). ---
	if ( defined( 'PAYMONGO_WEBHOOK_SECRET' ) && ! empty( PAYMONGO_WEBHOOK_SECRET ) ) {
		$signature_header = $request->get_header( 'Paymongo-Signature' );

		if ( empty( $signature_header ) ) {
			return new WP_REST_Response( array( 'message' => 'Missing signature header.' ), 401 );
		}

		// PayMongo signature format: t=<timestamp>,te=<test_signature>,li=<live_signature>
		// Parse the components from the header.
		$sig_parts = array();
		foreach ( explode( ',', $signature_header ) as $part ) {
			$pair = explode( '=', $part, 2 );
			if ( 2 === count( $pair ) ) {
				$sig_parts[ $pair[0] ] = $pair[1];
			}
		}

		$timestamp = $sig_parts['t'] ?? '';

		// Use test signature (te) in test mode, live signature (li) in production.
		// Check both — whichever is present.
		$provided_signature = $sig_parts['li'] ?? ( $sig_parts['te'] ?? '' );

		if ( empty( $timestamp ) || empty( $provided_signature ) ) {
			return new WP_REST_Response( array( 'message' => 'Invalid signature format.' ), 401 );
		}

		// Reconstruct the signed payload: concatenate timestamp + '.' + raw body.
		$signed_payload    = $timestamp . '.' . $body;
		$expected_signature = hash_hmac( 'sha256', $signed_payload, PAYMONGO_WEBHOOK_SECRET );

		if ( ! hash_equals( $expected_signature, $provided_signature ) ) {
			return new WP_REST_Response( array( 'message' => 'Invalid webhook signature.' ), 401 );
		}

		// Optional: Reject webhooks older than 5 minutes to prevent replay attacks.
		$webhook_age = time() - (int) $timestamp;
		if ( $webhook_age > 300 ) {
			return new WP_REST_Response( array( 'message' => 'Webhook timestamp too old.' ), 401 );
		}
	}

	$data = json_decode( $body, true );

	if ( empty( $data['data']['attributes']['type'] ) ) {
		return new WP_REST_Response( array( 'message' => 'Invalid webhook payload.' ), 400 );
	}

	$event_type = $data['data']['attributes']['type'];

	if ( 'payment.paid' === $event_type ) {

		$payment_data   = $data['data']['attributes']['data'] ?? array();
		$payment_intent = $payment_data['attributes']['payment_intent_id'] ?? '';

		if ( empty( $payment_intent ) ) {
			return new WP_REST_Response( array( 'message' => 'Missing payment intent ID.' ), 400 );
		}

		// Find the booking by payment intent ID.
		$bookings = get_posts(
			array(
				'post_type'      => 'booking',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'meta_key'       => '_booking_payment_id',
				'meta_value'     => $payment_intent,
			)
		);

		if ( empty( $bookings ) ) {
			return new WP_REST_Response( array( 'message' => 'Booking not found for this payment.' ), 404 );
		}

		$booking_id     = $bookings[0]->ID;
		$current_status = get_post_meta( $booking_id, '_booking_status', true );

		if ( 'awaiting_payment' === $current_status ) {
			$amount_paid = ( $payment_data['attributes']['amount'] ?? 0 ) / 100;

			update_post_meta( $booking_id, '_booking_status', 'paid' );
			update_post_meta( $booking_id, '_booking_payment_status', 'paid' );
			update_post_meta( $booking_id, '_booking_payment_amount', $amount_paid );

			do_action( 'obsidian_booking_status_changed', $booking_id, 'awaiting_payment', 'paid' );

			// Auto-transition to confirmed.
			update_post_meta( $booking_id, '_booking_status', 'confirmed' );
			do_action( 'obsidian_booking_status_changed', $booking_id, 'paid', 'confirmed' );
			obsidian_finalize_payment_session_by_booking( $booking_id );
		}
		return new WP_REST_Response( array( 'message' => 'Payment processed.' ), 200 );
	}

	return new WP_REST_Response( array( 'message' => 'Event type not handled.' ), 200 );
}

