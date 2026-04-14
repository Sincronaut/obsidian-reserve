<?php
/**
 * Booking Page Routing
 *
 * Registers rewrite rules so /booking/payment/ and /booking/confirmation/
 * resolve to the same WordPress "Booking" page with an ob_step query var.
 *
 * @package obsidian-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register ob_step as a public query variable.
 */
function obsidian_register_query_vars( $vars ) {
	$vars[] = 'ob_step';
	return $vars;
}
add_filter( 'query_vars', 'obsidian_register_query_vars' );

/**
 * Register rewrite rules for payment and confirmation sub-pages.
 */
function obsidian_register_booking_rewrites() {
	add_rewrite_rule( '^booking/payment/?$', 'index.php?pagename=booking&ob_step=payment', 'top' );
	add_rewrite_rule( '^booking/confirmation/?$', 'index.php?pagename=booking&ob_step=confirmation', 'top' );
}
add_action( 'init', 'obsidian_register_booking_rewrites' );
