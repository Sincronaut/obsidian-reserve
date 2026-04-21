<?php
/**
 * ACF field group for the Car CPT — Phase 11.x.
 *
 * Registered in code (rather than the ACF admin UI) so the schema is
 * version-controlled and auto-applies on every install. Mirrors the
 * pattern used by includes/location-fields.php.
 *
 * IMPORTANT — these fields describe the VEHICLE itself and are SHARED
 * across every branch that stocks this car. Per-branch data (units per
 * color and image galleries) is managed in custom meta boxes, not ACF:
 *
 *   - `_car_inventory` (JSON) → per-branch units per color
 *      → admin UI: admin/car-meta-box.php "Inventory — by Branch" box
 *
 *   - `_car_galleries` (JSON) → shared per-color image galleries
 *      → admin UI: admin/car-meta-box.php "Color Galleries" box
 *
 * If a duplicate "Car Details" group exists in the database (created via
 * the ACF admin UI before this migration), it is hidden by the filter at
 * the bottom of this file so the admin doesn't see two copies.
 *
 * Requires ACF Free (already a project dependency).
 *
 * @package obsidian-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Color choices for the `car_colors` checkbox.
 *
 * Must stay in sync with `obsidian_get_color_hex()` in includes/availability.php
 * — that helper maps these slugs to the hex codes used everywhere a swatch
 * is rendered. Add a new entry here AND in obsidian_get_color_hex().
 *
 * @return array<string,string> slug => label
 */
function obsidian_get_car_color_choices() {
	return array(
		'orange' => __( 'Orange', 'obsidian-booking' ),
		'black'  => __( 'Black', 'obsidian-booking' ),
		'red'    => __( 'Red', 'obsidian-booking' ),
		'blue'   => __( 'Blue', 'obsidian-booking' ),
		'white'  => __( 'White', 'obsidian-booking' ),
		'silver' => __( 'Silver', 'obsidian-booking' ),
		'yellow' => __( 'Yellow', 'obsidian-booking' ),
		'green'  => __( 'Green', 'obsidian-booking' ),
		'gray'   => __( 'Gray', 'obsidian-booking' ),
	);
}

/**
 * Register the "Car Details" field group programmatically.
 */
function obsidian_register_car_acf_fields() {

	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group( array(
		'key'                   => 'group_obsidian_car_details',
		'title'                 => __( 'Car Details — shared across branches', 'obsidian-booking' ),
		'fields'                => array(

			/* ── Top-of-group note ─────────────────────────────────────────── */
			array(
				'key'     => 'field_obsidian_car_intro',
				'label'   => '',
				'name'    => '',
				'type'    => 'message',
				'message' => __(
					"These details describe the vehicle itself and are <strong>shared across every branch</strong> that stocks it.\n\nPer-branch <strong>unit counts</strong> are managed in <em>Inventory — by Branch</em> below. Per-color <strong>image galleries</strong> are managed once for the whole vehicle in <em>Color Galleries</em> below.",
					'obsidian-booking'
				),
				'new_lines'         => 'wpautop',
				'esc_html'          => 0,
			),

			/* ── Identification ───────────────────────────────────────────── */
			array(
				'key'          => 'field_obsidian_car_make',
				'label'        => __( 'Make', 'obsidian-booking' ),
				'name'         => 'car_make',
				'type'         => 'text',
				'instructions' => __( 'Manufacturer, e.g. "Nissan", "Toyota".', 'obsidian-booking' ),
				'required'     => 1,
				'wrapper'      => array( 'width' => '50' ),
			),
			array(
				'key'          => 'field_obsidian_car_model',
				'label'        => __( 'Model', 'obsidian-booking' ),
				'name'         => 'car_model',
				'type'         => 'text',
				'instructions' => __( 'Model name, e.g. "GTR", "Vios".', 'obsidian-booking' ),
				'required'     => 1,
				'wrapper'      => array( 'width' => '50' ),
			),
			array(
				'key'          => 'field_obsidian_car_year',
				'label'        => __( 'Year', 'obsidian-booking' ),
				'name'         => 'car_year',
				'type'         => 'number',
				'instructions' => __( 'Model year, e.g. 2024.', 'obsidian-booking' ),
				'required'     => 1,
				'min'          => 1980,
				'max'          => (int) date( 'Y' ) + 2,
				'step'         => 1,
				'wrapper'      => array( 'width' => '50' ),
			),
			array(
				'key'          => 'field_obsidian_car_daily_rate',
				'label'        => __( 'Daily Rate (PHP)', 'obsidian-booking' ),
				'name'         => 'car_daily_rate',
				'type'         => 'number',
				'instructions' => __( 'Daily rental rate in PHP, e.g. 850.00. Same rate at every branch.', 'obsidian-booking' ),
				'required'     => 1,
				'min'          => 0,
				'step'         => 0.01,
				'wrapper'      => array( 'width' => '50' ),
			),

			/* ── Specs ────────────────────────────────────────────────────── */
			array(
				'key'          => 'field_obsidian_car_specs',
				'label'        => __( 'Specifications', 'obsidian-booking' ),
				'name'         => 'car_specs',
				'type'         => 'textarea',
				'instructions' => __( 'One bullet per line — these render as a list in the booking modal (e.g. "5 seats", "Automatic transmission", "Bluetooth").', 'obsidian-booking' ),
				'required'     => 0,
				'rows'         => 5,
				'new_lines'    => '',
			),

			/* ── Master color list (per-branch units handled separately) ──── */
			array(
				'key'           => 'field_obsidian_car_colors',
				'label'         => __( 'Available Colors', 'obsidian-booking' ),
				'name'          => 'car_colors',
				'type'          => 'checkbox',
				'instructions'  => __( 'Master list of color variants this vehicle comes in. After saving, set how many units of each color are stocked at each branch in the Inventory section below.', 'obsidian-booking' ),
				'required'      => 1,
				'choices'       => obsidian_get_car_color_choices(),
				'allow_custom'  => 0,
				'save_custom'   => 0,
				'layout'        => 'horizontal',
				'toggle'        => 0,
				'return_format' => 'value',
			),

			/* ── Vehicle-wide status ──────────────────────────────────────── */
			array(
				'key'           => 'field_obsidian_car_status',
				'label'         => __( 'Vehicle Status', 'obsidian-booking' ),
				'name'          => 'car_status',
				'type'          => 'select',
				'instructions'  => __( 'Vehicle-wide listing status. Use "Maintenance" or "Retired" to hide this car from every branch at once. To pull the car from a single branch only, set its units to 0 in the Inventory section instead.', 'obsidian-booking' ),
				'required'      => 1,
				'choices'       => array(
					'available'   => __( 'Available', 'obsidian-booking' ),
					'maintenance' => __( 'Maintenance', 'obsidian-booking' ),
					'retired'     => __( 'Retired', 'obsidian-booking' ),
				),
				'default_value' => 'available',
				'allow_null'    => 0,
				'return_format' => 'value',
			),
		),
		'location'              => array(
			array(
				array(
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => 'car',
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
add_action( 'acf/init', 'obsidian_register_car_acf_fields' );

/**
 * Hide any LEGACY duplicate "Car Details" field group that was created in
 * the ACF admin UI before this code-defined version existed.
 *
 * Without this filter, an admin who originally created a "Car Details"
 * group via Custom Fields → Field Groups would see TWO copies on every
 * Car edit screen after this file loads. The DB version is filtered out
 * here so only the code-defined group renders.
 *
 * To clean up permanently:
 *   WP Admin → Custom Fields → Field Groups →
 *   trash the legacy "Car Details" group → drop this filter.
 *
 * @param array $groups Field groups ACF is about to render.
 * @return array
 */
function obsidian_suppress_legacy_car_field_groups( $groups ) {

	if ( empty( $groups ) || ! is_array( $groups ) ) {
		return $groups;
	}

	foreach ( $groups as $i => $group ) {

		// Skip our own code-defined group.
		if ( ! empty( $group['key'] ) && $group['key'] === 'group_obsidian_car_details' ) {
			continue;
		}

		// A legacy DB-defined group is identified by being assigned to the
		// `car` post type AND not owned by us. We match by location rules.
		$is_for_car = false;
		if ( ! empty( $group['location'] ) && is_array( $group['location'] ) ) {
			foreach ( $group['location'] as $rule_group ) {
				foreach ( (array) $rule_group as $rule ) {
					if (
						isset( $rule['param'], $rule['operator'], $rule['value'] )
						&& $rule['param'] === 'post_type'
						&& $rule['operator'] === '=='
						&& $rule['value'] === 'car'
					) {
						$is_for_car = true;
						break 2;
					}
				}
			}
		}

		if ( $is_for_car ) {
			unset( $groups[ $i ] );
		}
	}

	return array_values( $groups );
}
add_filter( 'acf/load_field_groups', 'obsidian_suppress_legacy_car_field_groups', 99 );
