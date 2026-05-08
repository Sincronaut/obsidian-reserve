<?php
/**
 * Booking Calendar (Admin)
 *
 * @package obsidian-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the Booking Calendar admin page.
 *
 * @return void
 */
function obsidian_register_booking_calendar_page() {
	add_submenu_page(
		'edit.php?post_type=booking',
		__( 'Booking Calendar', 'obsidian-booking' ),
		__( 'Calendar', 'obsidian-booking' ),
		'edit_posts',
		'obsidian-booking-calendar',
		'obsidian_render_booking_calendar_page'
	);
}
add_action( 'admin_menu', 'obsidian_register_booking_calendar_page' );

/**
 * Render the Booking Calendar page.
 *
 * @return void
 */
function obsidian_render_booking_calendar_page() {
	$locations = get_posts(
		array(
			'post_type'      => 'location',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);

	$status_options = array(
		''                 => __( 'All Statuses', 'obsidian-booking' ),
		'pending_review'   => __( 'Pending Review', 'obsidian-booking' ),
		'awaiting_payment' => __( 'Awaiting Payment', 'obsidian-booking' ),
		'paid'             => __( 'Paid', 'obsidian-booking' ),
		'confirmed'        => __( 'Confirmed', 'obsidian-booking' ),
		'active'           => __( 'Active', 'obsidian-booking' ),
		'completed'        => __( 'Completed', 'obsidian-booking' ),
		'denied'           => __( 'Denied', 'obsidian-booking' ),
		'cancelled'        => __( 'Cancelled', 'obsidian-booking' ),
	);
	?>
	<div class="wrap obsidian-calendar-page">
		<h1><?php echo esc_html__( 'Booking Calendar', 'obsidian-booking' ); ?></h1>

		<div class="obsidian-calendar-toolbar">
			<label for="obm-calendar-status" class="screen-reader-text"><?php echo esc_html__( 'Status', 'obsidian-booking' ); ?></label>
			<select id="obm-calendar-status">
				<?php foreach ( $status_options as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>

			<label for="obm-calendar-location" class="screen-reader-text"><?php echo esc_html__( 'Branch', 'obsidian-booking' ); ?></label>
			<select id="obm-calendar-location">
				<option value=""><?php echo esc_html__( 'All Branches', 'obsidian-booking' ); ?></option>
				<?php foreach ( $locations as $location ) : ?>
					<option value="<?php echo esc_attr( $location->ID ); ?>"><?php echo esc_html( $location->post_title ); ?></option>
				<?php endforeach; ?>
			</select>

			<button type="button" class="button" id="obm-calendar-refresh"><?php echo esc_html__( 'Refresh', 'obsidian-booking' ); ?></button>
		</div>

		<div id="obsidian-booking-calendar"></div>
	</div>
	<?php
}
