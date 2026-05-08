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
 *
 * @param int $booking_id Booking post ID.
 * @return array
 */
function obsidian_get_booking_email_data( $booking_id ) {
	$all_meta = get_post_custom( $booking_id );

	// Helper to get meta value from the custom array (WP returns meta as arrays).
	$get_meta = function ( $key ) use ( $all_meta ) {
		return isset( $all_meta[ $key ][0] ) ? $all_meta[ $key ][0] : '';
	};

	$car_id  = (int) $get_meta( '_booking_car_id' );
	$user_id = (int) $get_meta( '_booking_user_id' );
	$user    = get_userdata( $user_id );

	$booking_reference = function_exists( 'obsidian_get_booking_reference' )
		? obsidian_get_booking_reference( $booking_id )
		: '#' . $booking_id;

	return array(
		'booking_id'       => $booking_id,
		'booking_reference' => $booking_reference,
		'car_id'           => $car_id,
		'car_name'         => get_the_title( $car_id ),
		'user'             => $user,
		'user_email'       => $user ? $user->user_email : '',
		'first_name'       => $get_meta( '_booking_first_name' ),
		'last_name'        => $get_meta( '_booking_last_name' ),
		'start_date'       => $get_meta( '_booking_start_date' ),
		'end_date'         => $get_meta( '_booking_end_date' ),
		'color'            => $get_meta( '_booking_color' ),
		'customer_type'    => $get_meta( '_booking_customer_type' ),
		'total_price'      => (float) $get_meta( '_booking_total_price' ),
		'location'         => $get_meta( '_booking_location' ),
		'denial_reason'        => $get_meta( '_booking_denial_reason' ),
		'cancellation_reason'  => $get_meta( '_booking_cancellation_reason' ),
		'cancelled_by'         => $get_meta( '_booking_cancelled_by' ),
		'payment_amount'       => (float) $get_meta( '_booking_payment_amount' ),
		'payment_option'   => $get_meta( '_booking_payment_option' ),
		'delivery_dropoff' => $get_meta( '_booking_delivery_dropoff' ),
		'delivery_date'    => $get_meta( '_booking_delivery_date' ),
		'delivery_time'    => $get_meta( '_booking_delivery_time' ),
		'admin_url'        => admin_url( 'post.php?post=' . $booking_id . '&action=edit' ),
	);
}

/**
 * Render an email template and return the HTML string.
 *
 * @param string $template_name Template file slug.
 * @param array  $vars          Variables for the template.
 * @return string
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
 *
 * @param string $to      Recipient email.
 * @param string $subject Email subject.
 * @param string $html    Email HTML body.
 * @return void
 */
function obsidian_send_email( $to, $subject, $html ) {
	$headers = array(
		'Content-Type: text/html; charset=UTF-8',
		'From: Obsidian Reserve <no-reply@' . wp_parse_url( home_url(), PHP_URL_HOST ) . '>',
	);

	wp_mail( $to, $subject, $html, $headers );
}

/**
 * Main dispatcher - schedules background emails so the user does not wait.
 *
 * @param int    $booking_id Booking post ID.
 * @param string $old_status Previous status.
 * @param string $new_status New status.
 * @return void
 */
function obsidian_notify_on_status_change( $booking_id, $old_status, $new_status ) {
	// Offload to a background task (Phase 11.16 optimization).
	// This makes the API response instant because wp_mail() is synchronous.
	wp_schedule_single_event( time(), 'obsidian_send_async_notifications', array( $booking_id, $old_status, $new_status ) );
}
add_action( 'obsidian_booking_status_changed', 'obsidian_notify_on_status_change', 10, 3 );

/**
 * Track when a booking enters awaiting payment status.
 *
 * @param int    $booking_id Booking post ID.
 * @param string $old_status Previous status.
 * @param string $new_status New status.
 * @return void
 */
function obsidian_record_awaiting_payment_timestamp( $booking_id, $old_status, $new_status ) {
	if ( 'awaiting_payment' !== $new_status ) {
		return;
	}

	update_post_meta( $booking_id, '_booking_awaiting_payment_at', current_time( 'timestamp', true ) );
}
add_action( 'obsidian_booking_status_changed', 'obsidian_record_awaiting_payment_timestamp', 9, 3 );

/**
 * The actual notification runner, called via WP-Cron.
 *
 * @param int    $booking_id Booking post ID.
 * @param string $old_status Previous status.
 * @param string $new_status New status.
 * @return void
 */
function obsidian_run_async_notifications( $booking_id, $old_status, $new_status ) {
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

		case 'cancelled':
			obsidian_notify_booking_cancelled( $data, $admin_email );
			break;
	}
}
add_action( 'obsidian_send_async_notifications', 'obsidian_run_async_notifications', 10, 3 );

// -------------------------------------------------------------------------
// Individual notification functions
// -------------------------------------------------------------------------

/**
 * New booking submitted - notify admin.
 *
 * @param array  $data        Email template data.
 * @param string $admin_email Admin email.
 * @return void
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
 * Booking received - receipt to user.
 *
 * @param array $data Email template data.
 * @return void
 */
function obsidian_notify_booking_received( $data ) {
	$subject = 'Your Reservation Request Has Been Received';
	$html    = obsidian_render_email_template( 'booking-received', $data );
	obsidian_send_email( $data['user_email'], $subject, $html );
}

/**
 * Documents approved - send payment link to user.
 *
 * @param array $data       Email template data.
 * @param int   $booking_id Booking post ID.
 * @return void
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
 * Booking denied - notify user with reason.
 *
 * @param array $data Email template data.
 * @return void
 */
function obsidian_notify_booking_denied( $data ) {
	$subject = 'Update on Your Reservation Request';
	$html    = obsidian_render_email_template( 'booking-denied', $data );
	obsidian_send_email( $data['user_email'], $subject, $html );
}

/**
 * Payment received - notify admin.
 *
 * @param array  $data        Email template data.
 * @param string $admin_email Admin email.
 * @return void
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
 * Booking confirmed - notify user.
 *
 * @param array $data Email template data.
 * @return void
 */
function obsidian_notify_booking_confirmed( $data ) {
	$subject = sprintf( 'Your %s Reservation is Confirmed ✅', $data['car_name'] );
	$html    = obsidian_render_email_template( 'booking-confirmed', $data );
	obsidian_send_email( $data['user_email'], $subject, $html );
}

/**
 * Booking cancelled - notify user + admin.
 *
 * Uses different email templates depending on whether the cancellation
 * was user-initiated or admin-initiated (includes reason).
 *
 * @param array  $data        Email template data.
 * @param string $admin_email Admin email.
 * @return void
 */
function obsidian_notify_booking_cancelled( $data, $admin_email ) {

	$cancelled_by = $data['cancelled_by'] ?? 'user';

	if ( 'admin' === $cancelled_by ) {
		// Admin-initiated cancellation — send email with reason to user.
		$subject = 'Your Reservation Has Been Cancelled';
		$html    = obsidian_render_email_template( 'booking-cancelled-admin', $data );
		obsidian_send_email( $data['user_email'], $subject, $html );
	} else {
		// User-initiated cancellation — notify user of their own cancellation.
		$subject = 'Your Reservation Has Been Cancelled';
		$html    = obsidian_render_email_template( 'booking-cancelled', $data );
		obsidian_send_email( $data['user_email'], $subject, $html );

		// Also notify admin that a user cancelled.
		$admin_subject = sprintf(
			'[Booking Cancelled] %s %s — %s',
			$data['first_name'],
			$data['last_name'],
			$data['car_name']
		);
		$admin_html = obsidian_render_email_template( 'booking-cancelled', $data );
		obsidian_send_email( $admin_email, $admin_subject, $admin_html );
	}
}

// -------------------------------------------------------------------------
// Delivery reminder cron
// -------------------------------------------------------------------------

/**
 * Schedule the daily delivery reminder cron on plugin activation.
 *
 * @return void
 */
function obsidian_schedule_reminder_cron() {
	if ( ! wp_next_scheduled( 'obsidian_daily_pickup_reminders' ) ) {
		wp_schedule_event( time(), 'daily', 'obsidian_daily_pickup_reminders' );
	}
}
add_action( 'init', 'obsidian_schedule_reminder_cron' );

/**
 * Unschedule on deactivation.
 *
 * @return void
 */
function obsidian_unschedule_reminder_cron() {
	$timestamp = wp_next_scheduled( 'obsidian_daily_pickup_reminders' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'obsidian_daily_pickup_reminders' );
	}
}
register_deactivation_hook( OBSIDIAN_BOOKING_FILE, 'obsidian_unschedule_reminder_cron' );

/**
 * Send delivery reminder emails for bookings starting tomorrow.
 *
 * @return void
 */
function obsidian_send_pickup_reminders() {
	$tomorrow = gmdate( 'Y-m-d', strtotime( '+1 day' ) );

	$bookings = get_posts(
		array(
			'post_type'      => 'booking',
			'posts_per_page' => -1,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
			'relation' => 'AND',
			array(
				'key'   => '_booking_start_date',
				'value' => $tomorrow,
			),
			array(
				'key'   => '_booking_status',
				'value' => 'confirmed',
			),
			),
		)
	);

	foreach ( $bookings as $booking ) {
		$already_sent = get_post_meta( $booking->ID, '_booking_reminder_sent', true );
		if ( $already_sent ) {
			continue;
		}

		$data    = obsidian_get_booking_email_data( $booking->ID );
		$subject = sprintf( 'Your %s delivery is tomorrow!', $data['car_name'] );
		$html    = obsidian_render_email_template( 'booking-reminder', $data );
		obsidian_send_email( $data['user_email'], $subject, $html );

		update_post_meta( $booking->ID, '_booking_reminder_sent', '1' );
	}
}
add_action( 'obsidian_daily_pickup_reminders', 'obsidian_send_pickup_reminders' );

// -------------------------------------------------------------------------
// Awaiting payment expiry cron
// -------------------------------------------------------------------------

/**
 * Schedule the daily payment expiry cron on plugin activation.
 *
 * @return void
 */
function obsidian_schedule_payment_expiry_cron() {
	if ( ! wp_next_scheduled( 'obsidian_daily_payment_expiry' ) ) {
		wp_schedule_event( time(), 'daily', 'obsidian_daily_payment_expiry' );
	}
}
add_action( 'init', 'obsidian_schedule_payment_expiry_cron' );

/**
 * Unschedule the payment expiry cron on deactivation.
 *
 * @return void
 */
function obsidian_unschedule_payment_expiry_cron() {
	$timestamp = wp_next_scheduled( 'obsidian_daily_payment_expiry' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'obsidian_daily_payment_expiry' );
	}
}
register_deactivation_hook( OBSIDIAN_BOOKING_FILE, 'obsidian_unschedule_payment_expiry_cron' );

/**
 * Auto-deny awaiting payment bookings that have expired.
 *
 * @return void
 */
function obsidian_expire_awaiting_payment_bookings() {
	$cutoff = time() - ( 48 * HOUR_IN_SECONDS );

	$booking_ids = get_posts(
		array(
			'post_type'      => 'booking',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => '_booking_status',
					'value' => 'awaiting_payment',
				),
			),
		)
	);

	if ( empty( $booking_ids ) ) {
		return;
	}

	foreach ( $booking_ids as $booking_id ) {
		$awaiting_at = (int) get_post_meta( $booking_id, '_booking_awaiting_payment_at', true );

		if ( ! $awaiting_at ) {
			$booking = get_post( $booking_id );
			if ( $booking ) {
				$awaiting_at = strtotime( $booking->post_date_gmt );
				if ( ! $awaiting_at ) {
					$awaiting_at = strtotime( $booking->post_date );
				}
			}
		}

		if ( $awaiting_at && $awaiting_at <= $cutoff ) {
			update_post_meta( $booking_id, '_booking_cancellation_reason', 'Payment window expired.' );
			update_post_meta( $booking_id, '_booking_cancelled_by', 'admin' );
			update_post_meta( $booking_id, '_booking_cancellation_date', current_time( 'mysql' ) );
			$notes  = 'Auto-cancelled: payment window expired.';
			$result = obsidian_update_booking_status( $booking_id, 'cancelled', $notes );

			if ( ! is_wp_error( $result ) ) {
				obsidian_finalize_payment_session_by_booking( $booking_id );
			}
		}
	}
}
add_action( 'obsidian_daily_payment_expiry', 'obsidian_expire_awaiting_payment_bookings' );
