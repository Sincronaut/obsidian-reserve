<?php
/**
 * Obsidian Booking — Uninstall
 *
 * Runs when the plugin is deleted from WP Admin.
 * Cleans up all custom data (CPTs, meta, options).
 *
 * @package obsidian-booking
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove all Car posts
$cars = get_posts( array(
	'post_type'      => 'car',
	'post_status'    => 'any',
	'posts_per_page' => -1,
	'fields'         => 'ids',
) );

foreach ( $cars as $car_id ) {
	wp_delete_post( $car_id, true );
}

// Remove all Booking posts
$bookings = get_posts( array(
	'post_type'      => 'booking',
	'post_status'    => 'any',
	'posts_per_page' => -1,
	'fields'         => 'ids',
) );

foreach ( $bookings as $booking_id ) {
	wp_delete_post( $booking_id, true );
}

// Remove the Car Class taxonomy terms
$terms = get_terms( array(
	'taxonomy'   => 'car_class',
	'hide_empty' => false,
	'fields'     => 'ids',
) );

if ( ! is_wp_error( $terms ) ) {
	foreach ( $terms as $term_id ) {
		wp_delete_term( $term_id, 'car_class' );
	}
}

// Clean up any plugin options (if added later)
// delete_option( 'obsidian_booking_settings' );

// Flush rewrite rules
flush_rewrite_rules();
