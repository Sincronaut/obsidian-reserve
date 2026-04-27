<?php
/**
 * Booking Admin List Columns
 *
 * Replaces the default Title/Date columns with useful booking data:
 * Car, Customer, Color, Dates, Type, Status, Total.
 *
 * @package obsidian-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Define custom columns for the Bookings list table.
 */
function obsidian_booking_columns( $columns ) {
	return array(
		'cb'               => $columns['cb'],
		'booking_car'      => __( 'Car', 'obsidian-booking' ),
		'booking_customer' => __( 'Customer', 'obsidian-booking' ),
		'booking_location' => __( 'Branch', 'obsidian-booking' ),
		'booking_color'    => __( 'Color', 'obsidian-booking' ),
		'booking_dates'    => __( 'Dates', 'obsidian-booking' ),
		'booking_type'     => __( 'Type', 'obsidian-booking' ),
		'booking_status'   => __( 'Status', 'obsidian-booking' ),
		'booking_total'    => __( 'Total', 'obsidian-booking' ),
		'booking_actions'  => __( 'Actions', 'obsidian-booking' ),
	);
}
add_filter( 'manage_booking_posts_columns', 'obsidian_booking_columns' );

/**
 * Render content for each custom column.
 */
function obsidian_booking_column_content( $column, $post_id ) {

	switch ( $column ) {

		case 'booking_car':
			$car_id = (int) get_post_meta( $post_id, '_booking_car_id', true );
			printf( '<span class="obsidian-col-ref">BK-%s</span>', esc_html( str_pad( $post_id, 4, '0', STR_PAD_LEFT ) ) );
			if ( $car_id && get_post( $car_id ) ) {
				printf(
					'<a href="%s"><strong>%s</strong></a>',
					esc_url( get_edit_post_link( $car_id ) ),
					esc_html( get_the_title( $car_id ) )
				);
			} else {
				echo '<span class="obsidian-col-muted">—</span>';
			}
			break;

		case 'booking_customer':
			$first = get_post_meta( $post_id, '_booking_first_name', true );
			$last  = get_post_meta( $post_id, '_booking_last_name', true );
			$email = get_post_meta( $post_id, '_booking_email', true );
			$name  = trim( $first . ' ' . $last );

			if ( $name ) {
				echo '<strong>' . esc_html( $name ) . '</strong>';
			}
			if ( $email ) {
				echo '<br><span class="obsidian-col-muted">' . esc_html( $email ) . '</span>';
			}
			break;

		case 'booking_location':
			$location_id = (int) get_post_meta( $post_id, '_booking_location_id', true );

			if ( $location_id > 0 && get_post( $location_id ) ) {
				$region_terms = wp_get_post_terms( $location_id, 'region', array( 'number' => 1 ) );
				$region_name  = ! is_wp_error( $region_terms ) && ! empty( $region_terms ) ? $region_terms[0]->name : '';

				printf(
					'<a href="%s"><strong>%s</strong></a>',
					esc_url( get_edit_post_link( $location_id ) ),
					esc_html( get_the_title( $location_id ) )
				);
				if ( $region_name ) {
					echo '<br><span class="obsidian-col-muted">' . esc_html( $region_name ) . '</span>';
				}
			} else {
				// Pre-Phase-11 booking — fall back to the legacy string label.
				$legacy = get_post_meta( $post_id, '_booking_location', true );
				if ( $legacy ) {
					echo esc_html( $legacy );
				} else {
					echo '<span class="obsidian-col-muted">—</span>';
				}
			}
			break;

		case 'booking_color':
			$color = get_post_meta( $post_id, '_booking_color', true );
			if ( $color ) {
				$hex = function_exists( 'obsidian_get_color_hex' ) ? obsidian_get_color_hex( $color ) : '#888';
				printf(
					'<span class="obsidian-col-color"><span class="obsidian-col-swatch" style="background:%s;"></span>%s</span>',
					esc_attr( $hex ),
					esc_html( ucfirst( $color ) )
				);
			} else {
				echo '<span class="obsidian-col-muted">—</span>';
			}
			break;

		case 'booking_dates':
			$start = get_post_meta( $post_id, '_booking_start_date', true );
			$end   = get_post_meta( $post_id, '_booking_end_date', true );
			if ( $start && $end ) {
				$start_dt = DateTime::createFromFormat( 'Y-m-d', $start );
				$end_dt   = DateTime::createFromFormat( 'Y-m-d', $end );
				$days     = $start_dt && $end_dt ? $start_dt->diff( $end_dt )->days : 0;

				printf(
					'%s – %s<br><span class="obsidian-col-muted">%d day%s</span>',
					$start_dt ? esc_html( $start_dt->format( 'M j, Y' ) ) : esc_html( $start ),
					$end_dt ? esc_html( $end_dt->format( 'M j' ) ) : esc_html( $end ),
					$days,
					$days !== 1 ? 's' : ''
				);
			} else {
				echo '<span class="obsidian-col-muted">—</span>';
			}
			break;

		case 'booking_type':
			$type = get_post_meta( $post_id, '_booking_customer_type', true );
			if ( $type === 'international' ) {
				echo '<span class="obsidian-badge obsidian-badge-intl">International</span>';
			} elseif ( $type === 'local' ) {
				echo '<span class="obsidian-badge obsidian-badge-local">Local</span>';
			} else {
				echo '<span class="obsidian-col-muted">—</span>';
			}
			break;

		case 'booking_status':
			$status = get_post_meta( $post_id, '_booking_status', true );
			$map    = array(
				'pending_review'   => array( 'Pending Review', 'pending' ),
				'awaiting_payment' => array( 'Awaiting Payment', 'awaiting' ),
				'paid'             => array( 'Paid', 'paid' ),
				'confirmed'        => array( 'Confirmed', 'confirmed' ),
				'active'           => array( 'Active', 'active' ),
				'completed'        => array( 'Completed', 'completed' ),
				'denied'           => array( 'Denied', 'denied' ),
			);

			if ( isset( $map[ $status ] ) ) {
				printf(
					'<span class="obsidian-status obsidian-status-%s">%s</span>',
					esc_attr( $map[ $status ][1] ),
					esc_html( $map[ $status ][0] )
				);
			} else {
				echo '<span class="obsidian-col-muted">' . esc_html( $status ?: '—' ) . '</span>';
			}
			break;

		case 'booking_total':
			$total = (float) get_post_meta( $post_id, '_booking_total_price', true );
			if ( $total > 0 ) {
				echo '<strong>₱' . esc_html( number_format( $total ) ) . '</strong>';
			} else {
				echo '<span class="obsidian-col-muted">—</span>';
			}
			break;

		case 'booking_actions':
			$edit_url  = get_edit_post_link( $post_id );
			$trash_url = get_delete_post_link( $post_id );
			echo '<div class="obsidian-row-actions">';
			printf(
				'<a href="%s" class="obsidian-action-btn obsidian-action-edit" title="%s"><span class="dashicons dashicons-visibility"></span></a>',
				esc_url( $edit_url ),
				esc_attr__( 'View / Edit', 'obsidian-booking' )
			);
			printf(
				'<a href="%s" class="obsidian-action-btn obsidian-action-trash" title="%s"><span class="dashicons dashicons-trash"></span></a>',
				esc_url( $trash_url ),
				esc_attr__( 'Move to Trash', 'obsidian-booking' )
			);
			echo '</div>';
			break;
	}
}
add_action( 'manage_booking_posts_custom_column', 'obsidian_booking_column_content', 10, 2 );

/**
 * Make key columns sortable.
 */
function obsidian_booking_sortable_columns( $columns ) {
	$columns['booking_status']   = 'booking_status';
	$columns['booking_total']    = 'booking_total';
	$columns['booking_dates']    = 'booking_dates';
	$columns['booking_location'] = 'booking_location';
	return $columns;
}
add_filter( 'manage_edit-booking_sortable_columns', 'obsidian_booking_sortable_columns' );

/**
 * Handle sorting by custom meta fields.
 */
function obsidian_booking_sort_query( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() || $query->get( 'post_type' ) !== 'booking' ) {
		return;
	}

	$orderby = $query->get( 'orderby' );

	if ( $orderby === 'booking_status' ) {
		$query->set( 'meta_key', '_booking_status' );
		$query->set( 'orderby', 'meta_value' );
	} elseif ( $orderby === 'booking_total' ) {
		$query->set( 'meta_key', '_booking_total_price' );
		$query->set( 'orderby', 'meta_value_num' );
	} elseif ( $orderby === 'booking_dates' ) {
		$query->set( 'meta_key', '_booking_start_date' );
		$query->set( 'orderby', 'meta_value' );
	} elseif ( $orderby === 'booking_location' ) {
		$query->set( 'meta_key', '_booking_location_id' );
		$query->set( 'orderby', 'meta_value_num' );
	}
}
add_action( 'pre_get_posts', 'obsidian_booking_sort_query' );

/**
 * Add status filter dropdown above the bookings list.
 */
function obsidian_booking_status_filter() {
	global $typenow;

	if ( $typenow !== 'booking' ) {
		return;
	}

	$current = isset( $_GET['booking_status_filter'] ) ? sanitize_text_field( $_GET['booking_status_filter'] ) : '';
	$statuses = array(
		''                 => __( 'All Statuses', 'obsidian-booking' ),
		'pending_review'   => __( 'Pending Review', 'obsidian-booking' ),
		'awaiting_payment' => __( 'Awaiting Payment', 'obsidian-booking' ),
		'paid'             => __( 'Paid', 'obsidian-booking' ),
		'confirmed'        => __( 'Confirmed', 'obsidian-booking' ),
		'active'           => __( 'Active', 'obsidian-booking' ),
		'completed'        => __( 'Completed', 'obsidian-booking' ),
		'denied'           => __( 'Denied', 'obsidian-booking' ),
	);

	echo '<select name="booking_status_filter">';
	foreach ( $statuses as $val => $label ) {
		printf(
			'<option value="%s" %s>%s</option>',
			esc_attr( $val ),
			selected( $current, $val, false ),
			esc_html( $label )
		);
	}
	echo '</select>';
}
add_action( 'restrict_manage_posts', 'obsidian_booking_status_filter' );

/**
 * Branch filter dropdown — grouped by region for quick scanning when there
 * are many branches.
 */
function obsidian_booking_branch_filter() {
	global $typenow;

	if ( $typenow !== 'booking' ) {
		return;
	}

	$current = isset( $_GET['booking_location_filter'] ) ? (int) $_GET['booking_location_filter'] : 0;

	// Group branches by region term so the <optgroup>s mirror the data model.
	$regions = get_terms( array(
		'taxonomy'   => 'region',
		'hide_empty' => false,
		'orderby'    => 'name',
		'order'      => 'ASC',
	) );

	if ( is_wp_error( $regions ) ) {
		return;
	}

	echo '<select name="booking_location_filter">';
	echo '<option value="0">' . esc_html__( 'All Branches', 'obsidian-booking' ) . '</option>';

	foreach ( $regions as $region ) {
		$branch_ids = get_posts( array(
			'post_type'      => 'location',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'title',
			'order'          => 'ASC',
			'tax_query'      => array(
				array(
					'taxonomy' => 'region',
					'field'    => 'term_id',
					'terms'    => $region->term_id,
				),
			),
		) );

		if ( empty( $branch_ids ) ) {
			continue;
		}

		printf( '<optgroup label="%s">', esc_attr( $region->name ) );
		foreach ( $branch_ids as $branch_id ) {
			printf(
				'<option value="%d" %s>%s</option>',
				(int) $branch_id,
				selected( $current, (int) $branch_id, false ),
				esc_html( get_the_title( $branch_id ) )
			);
		}
		echo '</optgroup>';
	}

	// Surface any branches that exist but aren't assigned to a region yet so
	// they're still filterable.
	$orphan_ids = get_posts( array(
		'post_type'      => 'location',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'orderby'        => 'title',
		'order'          => 'ASC',
		'tax_query'      => array(
			array(
				'taxonomy' => 'region',
				'operator' => 'NOT EXISTS',
			),
		),
	) );

	if ( ! empty( $orphan_ids ) ) {
		printf( '<optgroup label="%s">', esc_attr__( 'Unassigned', 'obsidian-booking' ) );
		foreach ( $orphan_ids as $branch_id ) {
			printf(
				'<option value="%d" %s>%s</option>',
				(int) $branch_id,
				selected( $current, (int) $branch_id, false ),
				esc_html( get_the_title( $branch_id ) )
			);
		}
		echo '</optgroup>';
	}

	echo '</select>';
}
add_action( 'restrict_manage_posts', 'obsidian_booking_branch_filter' );

/**
 * Apply the status filter to the query.
 */
function obsidian_booking_apply_status_filter( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() || $query->get( 'post_type' ) !== 'booking' ) {
		return;
	}

	$status_filter = isset( $_GET['booking_status_filter'] ) ? sanitize_text_field( $_GET['booking_status_filter'] ) : '';
	$branch_filter = isset( $_GET['booking_location_filter'] ) ? (int) $_GET['booking_location_filter'] : 0;

	$meta_query = $query->get( 'meta_query' ) ?: array();

	if ( ! empty( $status_filter ) ) {
		$meta_query[] = array(
			'key'   => '_booking_status',
			'value' => $status_filter,
		);
	}

	if ( $branch_filter > 0 ) {
		$meta_query[] = array(
			'key'     => '_booking_location_id',
			'value'   => $branch_filter,
			'compare' => '=',
			'type'    => 'NUMERIC',
		);
	}

	if ( ! empty( $meta_query ) ) {
		$query->set( 'meta_query', $meta_query );
	}
}
add_action( 'pre_get_posts', 'obsidian_booking_apply_status_filter' );
