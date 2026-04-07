<?php
/**
 * Register taxonomies for the Car CPT.
 *
 * Car Class lets you categorize vehicles (Exotic, Executive, SUV, Sport)
 * for filtering on the fleet page and in admin.
 *
 * @package obsidian-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function obsidian_register_taxonomies() {

	$labels = array(
		'name'              => __( 'Car Classes', 'obsidian-booking' ),
		'singular_name'     => __( 'Car Class', 'obsidian-booking' ),
		'search_items'      => __( 'Search Car Classes', 'obsidian-booking' ),
		'all_items'         => __( 'All Car Classes', 'obsidian-booking' ),
		'edit_item'         => __( 'Edit Car Class', 'obsidian-booking' ),
		'update_item'       => __( 'Update Car Class', 'obsidian-booking' ),
		'add_new_item'      => __( 'Add New Car Class', 'obsidian-booking' ),
		'new_item_name'     => __( 'New Car Class Name', 'obsidian-booking' ),
		'menu_name'         => __( 'Car Classes', 'obsidian-booking' ),
	);

	$args = array(
		'labels'            => $labels,
		'hierarchical'      => true,        // Works like categories (parent/child)
		'public'            => true,
		'show_ui'           => true,
		'show_in_rest'      => true,        // Block editor + REST API support
		'show_admin_column' => true,        // Shows class column in Cars list
		'rewrite'           => array( 'slug' => 'car-class', 'with_front' => false ),
	);

	register_taxonomy( 'car_class', 'car', $args );
}
add_action( 'init', 'obsidian_register_taxonomies' );

/**
 * Pre-populate default car classes on plugin activation.
 * Called from the activation hook in obsidian-booking.php.
 */
function obsidian_seed_car_classes() {
	$defaults = array( 'Exotic', 'Executive', 'SUV', 'Sport' );

	foreach ( $defaults as $class ) {
		if ( ! term_exists( $class, 'car_class' ) ) {
			wp_insert_term( $class, 'car_class' );
		}
	}
}
