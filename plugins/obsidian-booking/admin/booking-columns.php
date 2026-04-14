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
		'booking_color'    => __( 'Color', 'obsidian-booking' ),
		'booking_dates'    => __( 'Dates', 'obsidian-booking' ),
		'booking_type'     => __( 'Type', 'obsidian-booking' ),
		'booking_status'   => __( 'Status', 'obsidian-booking' ),
		'booking_total'    => __( 'Total', 'obsidian-booking' ),
		'date'             => $columns['date'],
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
	}
}
add_action( 'manage_booking_posts_custom_column', 'obsidian_booking_column_content', 10, 2 );

/**
 * Make key columns sortable.
 */
function obsidian_booking_sortable_columns( $columns ) {
	$columns['booking_status'] = 'booking_status';
	$columns['booking_total']  = 'booking_total';
	$columns['booking_dates']  = 'booking_dates';
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
 * Apply the status filter to the query.
 */
function obsidian_booking_apply_status_filter( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() || $query->get( 'post_type' ) !== 'booking' ) {
		return;
	}

	$filter = isset( $_GET['booking_status_filter'] ) ? sanitize_text_field( $_GET['booking_status_filter'] ) : '';

	if ( ! empty( $filter ) ) {
		$meta_query = $query->get( 'meta_query' ) ?: array();
		$meta_query[] = array(
			'key'   => '_booking_status',
			'value' => $filter,
		);
		$query->set( 'meta_query', $meta_query );
	}
}
add_action( 'pre_get_posts', 'obsidian_booking_apply_status_filter' );
