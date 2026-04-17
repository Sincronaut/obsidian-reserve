<?php
/**
 * ACF field group for the Location (Branch) CPT — Phase 11.
 *
 * Registered in code (rather than the ACF admin UI) so the schema is
 * version-controlled and auto-applies on every install. Mirrors the
 * structure documented in MASTERPLAN.md → Step 11.2.
 *
 * Requires ACF Free (already a project dependency).
 *
 * @package obsidian-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function obsidian_register_location_acf_fields() {

	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group( array(
		'key'                   => 'group_obsidian_branch_details',
		'title'                 => __( 'Branch Details', 'obsidian-booking' ),
		'fields'                => array(
			array(
				'key'          => 'field_obsidian_location_address',
				'label'        => __( 'Address', 'obsidian-booking' ),
				'name'         => 'location_address',
				'type'         => 'textarea',
				'instructions' => __( 'Full street address shown on the fleet page, confirmation page, and emails.', 'obsidian-booking' ),
				'required'     => 1,
				'rows'         => 3,
				'new_lines'    => 'br',
			),
			array(
				'key'          => 'field_obsidian_location_contact_number',
				'label'        => __( 'Contact Number', 'obsidian-booking' ),
				'name'         => 'location_contact_number',
				'type'         => 'text',
				'instructions' => __( 'Branch landline or mobile (with country code, e.g. +63...).', 'obsidian-booking' ),
				'required'     => 1,
			),
			array(
				'key'          => 'field_obsidian_location_contact_email',
				'label'        => __( 'Contact Email', 'obsidian-booking' ),
				'name'         => 'location_contact_email',
				'type'         => 'email',
				'instructions' => __( 'Branch inbox for customer enquiries.', 'obsidian-booking' ),
				'required'     => 0,
			),
			array(
				'key'          => 'field_obsidian_location_hours',
				'label'        => __( 'Operating Hours', 'obsidian-booking' ),
				'name'         => 'location_hours',
				'type'         => 'textarea',
				'instructions' => __( 'e.g. "Mon–Sat 8am–8pm, Sun closed".', 'obsidian-booking' ),
				'required'     => 0,
				'rows'         => 2,
				'new_lines'    => 'br',
			),
			array(
				'key'          => 'field_obsidian_location_map_url',
				'label'        => __( 'Google Maps URL', 'obsidian-booking' ),
				'name'         => 'location_map_url',
				'type'         => 'url',
				'instructions' => __( 'Used for the "View on Google Maps" CTA on the map popup and emails.', 'obsidian-booking' ),
				'required'     => 0,
			),
			array(
				'key'          => 'field_obsidian_location_latitude',
				'label'        => __( 'Latitude', 'obsidian-booking' ),
				'name'         => 'location_latitude',
				'type'         => 'number',
				'instructions' => __( 'Decimal degrees, e.g. 14.5547. Required for the branch to appear on the interactive map.', 'obsidian-booking' ),
				'required'     => 0,
				'min'          => -90,
				'max'          => 90,
				'step'         => 'any',
				'wrapper'      => array( 'width' => '50' ),
			),
			array(
				'key'          => 'field_obsidian_location_longitude',
				'label'        => __( 'Longitude', 'obsidian-booking' ),
				'name'         => 'location_longitude',
				'type'         => 'number',
				'instructions' => __( 'Decimal degrees, e.g. 121.0244.', 'obsidian-booking' ),
				'required'     => 0,
				'min'          => -180,
				'max'          => 180,
				'step'         => 'any',
				'wrapper'      => array( 'width' => '50' ),
			),
			array(
				'key'           => 'field_obsidian_location_status',
				'label'         => __( 'Status', 'obsidian-booking' ),
				'name'          => 'location_status',
				'type'          => 'select',
				'instructions'  => __( 'Only "active" branches are bookable. "Coming soon" appears on the map (greyed out) but cannot be selected. "Closed" is hidden.', 'obsidian-booking' ),
				'required'      => 1,
				'choices'       => array(
					'active'      => __( 'Active', 'obsidian-booking' ),
					'coming_soon' => __( 'Coming Soon', 'obsidian-booking' ),
					'closed'      => __( 'Closed', 'obsidian-booking' ),
				),
				'default_value' => 'active',
				'allow_null'    => 0,
				'return_format' => 'value',
			),
		),
		'location'              => array(
			array(
				array(
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => 'location',
				),
			),
		),
		'menu_order'            => 0,
		'position'              => 'normal',
		'style'                 => 'default',
		'label_placement'       => 'top',
		'instruction_placement' => 'label',
		'active'                => true,
		'show_in_rest'          => 1,
	) );
}
add_action( 'acf/init', 'obsidian_register_location_acf_fields' );
