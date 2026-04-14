<?php
/**
 * Email Notifications
 *
 * Hooks into obsidian_booking_status_changed to send lifecycle emails.
 *
 * @package obsidian-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gather common booking data used across all email templates.
 */
function obsidian_get_booking_email_data( $booking_id ) {
	$car_id    = (int) get_post_meta( $booking_id, '_booking_car_id', true );
	$user_id   = (int) get_post_meta( $booking_id, '_booking_user_id', true );
	$user      = get_userdata( $user_id );

	return array(
		'booking_id'       => $booking_id,
		'car_id'           => $car_id,
		'car_name'         => get_the_title( $car_id ),
		'user'             => $user,
		'user_email'       => $user ? $user->user_email : '',
		'first_name'       => get_post_meta( $booking_id, '_booking_first_name', true ),
		'last_name'        => get_post_meta( $booking_id, '_booking_last_name', true ),
		'start_date'       => get_post_meta( $booking_id, '_booking_start_date', true ),
		'end_date'         => get_post_meta( $booking_id, '_booking_end_date', true ),
		'color'            => get_post_meta( $booking_id, '_booking_color', true ),
		'customer_type'    => get_post_meta( $booking_id, '_booking_customer_type', true ),
		'total_price'      => (float) get_post_meta( $booking_id, '_booking_total_price', true ),
		'location'         => get_post_meta( $booking_id, '_booking_location', true ),
		'denial_reason'    => get_post_meta( $booking_id, '_booking_denial_reason', true ),
		'payment_amount'   => (float) get_post_meta( $booking_id, '_booking_payment_amount', true ),
		'payment_option'   => get_post_meta( $booking_id, '_booking_payment_option', true ),
		'delivery_dropoff' => get_post_meta( $booking_id, '_booking_delivery_dropoff', true ),
		'delivery_date'    => get_post_meta( $booking_id, '_booking_delivery_date', true ),
		'delivery_time'    => get_post_meta( $booking_id, '_booking_delivery_time', true ),
		'admin_url'        => admin_url( 'post.php?post=' . $booking_id . '&action=edit' ),
	);
}

/**
 * Render an email template and return the HTML string.
 */
function obsidian_render_email_template( $template_name, $vars = array() ) {
	$template_path = OBSIDIAN_BOOKING_DIR . 'templates/emails/' . $template_name . '.php';

	if ( ! file_exists( $template_path ) ) {
		return '';
	}

	// Extract vars so they're available to the template.
	extract( $vars ); // phpcs:ignore WordPress.PHP.DontExtract

	ob_start();
	include $template_path;
	return ob_get_clean();
}

/**
 * Send an HTML email via wp_mail.
 */
function obsidian_send_email( $to, $subject, $html ) {
	$headers = array(
		'Content-Type: text/html; charset=UTF-8',
		'From: Obsidian Reserve <no-reply@' . wp_parse_url( home_url(), PHP_URL_HOST ) . '>',
	);

	wp_mail( $to, $subject, $html, $headers );
}

/**
 * Main dispatcher — fires on every booking status change.
 */
function obsidian_notify_on_status_change( $booking_id, $old_status, $new_status ) {
	$data = obsidian_get_booking_email_data( $booking_id );

	if ( empty( $data['user_email'] ) ) {
		return;
	}

	$admin_email = get_option( 'admin_email' );

	switch ( $new_status ) {

		case 'pending_review':
			obsidian_notify_booking_submitted( $data, $admin_email );
			obsidian_notify_booking_received( $data );
			break;

		case 'awaiting_payment':
			obsidian_notify_docs_approved( $data, $booking_id );
			break;

		case 'denied':
			obsidian_notify_booking_denied( $data );
			break;

		case 'paid':
			obsidian_notify_payment_received( $data, $admin_email );
			break;

		case 'confirmed':
			obsidian_notify_booking_confirmed( $data );
			break;
	}
}
add_action( 'obsidian_booking_status_changed', 'obsidian_notify_on_status_change', 10, 3 );

// -------------------------------------------------------------------------
// Individual notification functions
// -------------------------------------------------------------------------

/**
 * New booking submitted — notify admin.
 */
function obsidian_notify_booking_submitted( $data, $admin_email ) {
	$subject = sprintf(
		'[New Booking] %s %s — %s — Docs to Review',
		$data['first_name'],
		$data['last_name'],
		$data['car_name']
	);

	$html = obsidian_render_email_template( 'booking-submitted', $data );
	obsidian_send_email( $admin_email, $subject, $html );
}

/**
 * Booking received — receipt to user.
 */
function obsidian_notify_booking_received( $data ) {
	$subject = 'Your Reservation Request Has Been Received';
	$html    = obsidian_render_email_template( 'booking-received', $data );
	obsidian_send_email( $data['user_email'], $subject, $html );
}

/**
 * Documents approved — send payment link to user.
 */
function obsidian_notify_docs_approved( $data, $booking_id ) {
	if ( function_exists( 'obsidian_generate_payment_token' ) ) {
		$token       = obsidian_generate_payment_token( $booking_id );
		$payment_url = obsidian_get_payment_url( $booking_id, $token );
	} else {
		$payment_url = home_url( '/booking/payment/' );
	}

	$data['payment_url'] = $payment_url;

	$subject = sprintf( 'Booking #%d Approved — Complete Your Payment', $booking_id );
	$html    = obsidian_render_email_template( 'docs-approved', $data );
	obsidian_send_email( $data['user_email'], $subject, $html );
}

/**
 * Booking denied — notify user with reason.
 */
function obsidian_notify_booking_denied( $data ) {
	$subject = 'Update on Your Reservation Request';
	$html    = obsidian_render_email_template( 'booking-denied', $data );
	obsidian_send_email( $data['user_email'], $subject, $html );
}

/**
 * Payment received — notify admin.
 */
function obsidian_notify_payment_received( $data, $admin_email ) {
	$subject = sprintf(
		'[Payment Received] %s %s — %s',
		$data['first_name'],
		$data['last_name'],
		$data['car_name']
	);

	$html = obsidian_render_email_template( 'payment-received', $data );
	obsidian_send_email( $admin_email, $subject, $html );
}

/**
 * Booking confirmed — notify user.
 */
function obsidian_notify_booking_confirmed( $data ) {
	$subject = sprintf( 'Your %s Reservation is Confirmed ✅', $data['car_name'] );
	$html    = obsidian_render_email_template( 'booking-confirmed', $data );
	obsidian_send_email( $data['user_email'], $subject, $html );
}

// -------------------------------------------------------------------------
// Pickup reminder cron
// -------------------------------------------------------------------------

/**
 * Schedule the daily pickup reminder cron on plugin activation.
 */
function obsidian_schedule_reminder_cron() {
	if ( ! wp_next_scheduled( 'obsidian_daily_pickup_reminders' ) ) {
		wp_schedule_event( time(), 'daily', 'obsidian_daily_pickup_reminders' );
	}
}
add_action( 'init', 'obsidian_schedule_reminder_cron' );

/**
 * Unschedule on deactivation.
 */
function obsidian_unschedule_reminder_cron() {
	$timestamp = wp_next_scheduled( 'obsidian_daily_pickup_reminders' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'obsidian_daily_pickup_reminders' );
	}
}
register_deactivation_hook( OBSIDIAN_BOOKING_FILE, 'obsidian_unschedule_reminder_cron' );

/**
 * Send pickup reminder emails for bookings starting tomorrow.
 */
function obsidian_send_pickup_reminders() {
	$tomorrow = gmdate( 'Y-m-d', strtotime( '+1 day' ) );

	$bookings = get_posts( array(
		'post_type'      => 'obsidian_booking',
		'posts_per_page' => -1,
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
			'relation' => 'AND',
			array(
				'key'   => '_booking_start_date',
				'value' => $tomorrow,
			),
			array(
				'key'     => '_booking_status',
				'value'   => 'confirmed',
			),
		),
	) );

	foreach ( $bookings as $booking ) {
		$already_sent = get_post_meta( $booking->ID, '_booking_reminder_sent', true );
		if ( $already_sent ) {
			continue;
		}

		$data    = obsidian_get_booking_email_data( $booking->ID );
		$subject = sprintf( 'Your %s pickup is tomorrow!', $data['car_name'] );
		$html    = obsidian_render_email_template( 'booking-reminder', $data );
		obsidian_send_email( $data['user_email'], $subject, $html );

		update_post_meta( $booking->ID, '_booking_reminder_sent', '1' );
	}
}
add_action( 'obsidian_daily_pickup_reminders', 'obsidian_send_pickup_reminders' );
