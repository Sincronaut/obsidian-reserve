<?php
/**
 * Register Custom Post Types: Car & Booking
 *
 * @package obsidian-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ══════════════════════════════════════════════════
   CAR Custom Post Type
   ══════════════════════════════════════════════════ */

function obsidian_register_car_post_type() {

	$labels = array(
		'name'               => __( 'Cars', 'obsidian-booking' ),
		'singular_name'      => __( 'Car', 'obsidian-booking' ),
		'menu_name'          => __( 'Cars', 'obsidian-booking' ),
		'add_new'            => __( 'Add New Car', 'obsidian-booking' ),
		'add_new_item'       => __( 'Add New Car', 'obsidian-booking' ),
		'edit_item'          => __( 'Edit Car', 'obsidian-booking' ),
		'new_item'           => __( 'New Car', 'obsidian-booking' ),
		'view_item'          => __( 'View Car', 'obsidian-booking' ),
		'search_items'       => __( 'Search Cars', 'obsidian-booking' ),
		'not_found'          => __( 'No cars found', 'obsidian-booking' ),
		'not_found_in_trash' => __( 'No cars found in Trash', 'obsidian-booking' ),
		'all_items'          => __( 'All Cars', 'obsidian-booking' ),
	);

	$args = array(
		'labels'              => $labels,
		'description'         => __( 'Luxury vehicles available for reservation.', 'obsidian-booking' ),
		'public'              => true,
		'publicly_queryable'  => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_rest'        => true,
		'menu_position'       => 5,
		'menu_icon'           => 'dashicons-car',
		'has_archive'         => true,
		'rewrite'             => array( 'slug' => 'cars', 'with_front' => false ),
		'supports'            => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
		'capability_type'     => 'post',
		'exclude_from_search' => false,
	);

	register_post_type( 'car', $args );
}
add_action( 'init', 'obsidian_register_car_post_type' );

/* ══════════════════════════════════════════════════
   BOOKING Custom Post Type
   ══════════════════════════════════════════════════ */

function obsidian_register_booking_post_type() {

	$labels = array(
		'name'               => __( 'Bookings', 'obsidian-booking' ),
		'singular_name'      => __( 'Booking', 'obsidian-booking' ),
		'menu_name'          => __( 'Bookings', 'obsidian-booking' ),
		'add_new'            => __( 'Add New Booking', 'obsidian-booking' ),
		'add_new_item'       => __( 'Add New Booking', 'obsidian-booking' ),
		'edit_item'          => __( 'Edit Booking', 'obsidian-booking' ),
		'new_item'           => __( 'New Booking', 'obsidian-booking' ),
		'view_item'          => __( 'View Booking', 'obsidian-booking' ),
		'search_items'       => __( 'Search Bookings', 'obsidian-booking' ),
		'not_found'          => __( 'No bookings found', 'obsidian-booking' ),
		'not_found_in_trash' => __( 'No bookings found in Trash', 'obsidian-booking' ),
		'all_items'          => __( 'All Bookings', 'obsidian-booking' ),
	);

	$args = array(
		'labels'              => $labels,
		'description'         => __( 'Customer vehicle reservations.', 'obsidian-booking' ),
		'public'              => false,       // Bookings have NO public URL
		'publicly_queryable'  => false,
		'show_ui'             => true,        // But staff CAN see them in admin
		'show_in_menu'        => true,
		'show_in_rest'        => true,        // Needed for our custom REST API
		'menu_position'       => 6,
		'menu_icon'           => 'dashicons-calendar-alt',
		'has_archive'         => false,
		'supports'            => array( 'title', 'custom-fields' ),
		'capability_type'     => 'post',
		'exclude_from_search' => true,
	);

	register_post_type( 'booking', $args );
}
add_action( 'init', 'obsidian_register_booking_post_type' );

/* ══════════════════════════════════════════════════
   LOCATION (Branch) Custom Post Type — Phase 11
   ══════════════════════════════════════════════════ */

/**
 * Branches are physical Obsidian Reserve stores (e.g. "Makati", "Cebu City").
 *
 * Each Location post:
 *   - Belongs to one Region taxonomy term (Luzon / Visayas / Mindanao)
 *   - Carries address + contact + map coordinates via ACF
 *   - Holds per-color car inventory through the `_car_inventory` meta on each Car
 *
 * Bookings reference a single branch via `_booking_location_id`. Inventory
 * is consumed only at the booking's branch.
 */
function obsidian_register_location_post_type() {

	$labels = array(
		'name'               => __( 'Locations', 'obsidian-booking' ),
		'singular_name'      => __( 'Location', 'obsidian-booking' ),
		'menu_name'          => __( 'Locations', 'obsidian-booking' ),
		'add_new'            => __( 'Add New Branch', 'obsidian-booking' ),
		'add_new_item'       => __( 'Add New Branch', 'obsidian-booking' ),
		'edit_item'          => __( 'Edit Branch', 'obsidian-booking' ),
		'new_item'           => __( 'New Branch', 'obsidian-booking' ),
		'view_item'          => __( 'View Branch', 'obsidian-booking' ),
		'search_items'       => __( 'Search Branches', 'obsidian-booking' ),
		'not_found'          => __( 'No branches found', 'obsidian-booking' ),
		'not_found_in_trash' => __( 'No branches found in Trash', 'obsidian-booking' ),
		'all_items'          => __( 'All Branches', 'obsidian-booking' ),
	);

	$args = array(
		'labels'              => $labels,
		'description'         => __( 'Physical Obsidian Reserve branches with their own car inventory.', 'obsidian-booking' ),
		'public'              => false,        // No front-end archive — branches surface via the fleet page + map block
		'publicly_queryable'  => false,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_rest'        => true,
		'menu_position'       => 7,
		'menu_icon'           => 'dashicons-location-alt',
		'has_archive'         => false,
		'supports'            => array( 'title', 'editor', 'thumbnail' ),
		'capability_type'     => 'post',
		'exclude_from_search' => true,
	);

	register_post_type( 'location', $args );
}
add_action( 'init', 'obsidian_register_location_post_type' );
