<?php
/**
 * Branch Utilization Dashboard Widget — Phase 11.
 *
 * Sits below the existing booking pipeline widget and gives the operator
 * an at-a-glance read on inventory utilization per branch, grouped by
 * region.
 *
 * "Rented today" counts a booking when:
 *   - Its `_booking_location_id` matches the branch.
 *   - Its `_booking_status` is in the blocking list (pending_review,
 *     awaiting_payment, paid, confirmed, active) — i.e. the booking
 *     currently holds a unit.
 *   - Today's date falls between the start_date and end_date inclusive.
 *
 * "Total" is the sum of every color's units in `_car_inventory[branch_id]`
 * across every published car. Branches with zero stocked units are
 * surfaced too (so the admin notices an empty branch), but as
 * "0/0" with no percentage.
 *
 * @package obsidian-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the widget on wp_dashboard_setup, after the existing one.
 */
function obsidian_add_branch_utilization_widget() {
	wp_add_dashboard_widget(
		'obsidian_branch_utilization',
		__( 'Obsidian Reserve — Branch Utilization', 'obsidian-booking' ),
		'obsidian_render_branch_utilization_widget'
	);
}
add_action( 'wp_dashboard_setup', 'obsidian_add_branch_utilization_widget' );

/**
 * Render the widget.
 */
function obsidian_render_branch_utilization_widget() {

	$today      = wp_date( 'Y-m-d' );
	$bookings_url = admin_url( 'edit.php?post_type=booking' );

	// Collect every published branch grouped by region term.
	$regions = get_terms( array(
		'taxonomy'   => 'region',
		'hide_empty' => false,
		'orderby'    => 'name',
		'order'      => 'ASC',
	) );

	$grouped = array();

	if ( ! is_wp_error( $regions ) ) {
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

			$grouped[] = array(
				'region'     => $region,
				'branch_ids' => array_map( 'intval', $branch_ids ),
			);
		}
	}

	// Surface unassigned branches (no region) as their own group so they
	// don't silently disappear from the widget.
	$orphans = get_posts( array(
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

	if ( ! empty( $orphans ) ) {
		$grouped[] = array(
			'region'     => null,
			'branch_ids' => array_map( 'intval', $orphans ),
		);
	}

	if ( empty( $grouped ) ) {
		echo '<p class="obsidian-bu-empty">';
		printf(
			/* translators: %s: link to add a new Location */
			wp_kses_post( __( 'No branches yet. <a href="%s">Add your first branch</a>.', 'obsidian-booking' ) ),
			esc_url( admin_url( 'post-new.php?post_type=location' ) )
		);
		echo '</p>';
		obsidian_render_branch_utilization_styles();
		return;
	}

	?>
	<div class="obsidian-bu">
	<?php foreach ( $grouped as $group ) :
		$region_label = $group['region'] ? $group['region']->name : __( 'Unassigned', 'obsidian-booking' );
		?>
		<div class="obsidian-bu-region">
			<h4 class="obsidian-bu-region-title"><?php echo esc_html( $region_label ); ?></h4>
			<ul class="obsidian-bu-list">
				<?php foreach ( $group['branch_ids'] as $branch_id ) :
					$total  = obsidian_get_branch_total_units( $branch_id );
					$rented = obsidian_count_branch_active_today( $branch_id, $today );

					$percent       = $total > 0 ? (int) round( ( $rented / $total ) * 100 ) : 0;
					$bar_class     = '';
					if ( $percent >= 90 ) {
						$bar_class = 'is-hot';
					} elseif ( $percent >= 60 ) {
						$bar_class = 'is-warm';
					}

					$status        = get_post_meta( $branch_id, 'location_status', true );
					$status_pill   = ( $status && $status !== 'active' ) ? $status : '';

					$filter_url = add_query_arg( 'booking_location_filter', $branch_id, $bookings_url );
				?>
				<li class="obsidian-bu-item">
					<div class="obsidian-bu-row">
						<a href="<?php echo esc_url( get_edit_post_link( $branch_id ) ); ?>" class="obsidian-bu-name">
							<?php echo esc_html( get_the_title( $branch_id ) ); ?>
						</a>
						<?php if ( $status_pill ) : ?>
							<span class="obsidian-bu-status obsidian-bu-status-<?php echo esc_attr( $status_pill ); ?>">
								<?php echo esc_html( str_replace( '_', ' ', $status_pill ) ); ?>
							</span>
						<?php endif; ?>
						<span class="obsidian-bu-count">
							<?php
							printf(
								/* translators: 1: rented count, 2: total count */
								esc_html__( '%1$d / %2$d rented', 'obsidian-booking' ),
								(int) $rented,
								(int) $total
							);
							?>
							<?php if ( $total > 0 ) : ?>
								<span class="obsidian-bu-percent">(<?php echo (int) $percent; ?>%)</span>
							<?php endif; ?>
						</span>
						<a href="<?php echo esc_url( $filter_url ); ?>" class="obsidian-bu-view" title="<?php esc_attr_e( 'View bookings at this branch', 'obsidian-booking' ); ?>">→</a>
					</div>
					<?php if ( $total > 0 ) : ?>
						<div class="obsidian-bu-bar">
							<div class="obsidian-bu-bar-fill <?php echo esc_attr( $bar_class ); ?>"
								 style="width: <?php echo esc_attr( min( 100, $percent ) ); ?>%;">
							</div>
						</div>
					<?php else : ?>
						<div class="obsidian-bu-bar">
							<div class="obsidian-bu-bar-empty">
								<?php esc_html_e( 'No inventory stocked at this branch yet.', 'obsidian-booking' ); ?>
							</div>
						</div>
					<?php endif; ?>
				</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endforeach; ?>
	</div>

	<div class="obsidian-bu-footer">
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=location' ) ); ?>">
			<?php esc_html_e( 'Manage Branches →', 'obsidian-booking' ); ?>
		</a>
	</div>
	<?php

	obsidian_render_branch_utilization_styles();
}

/**
 * Sum every car's `_car_inventory[branch_id]` units for one branch.
 *
 * Loops once over all published cars; cheap for fleets up to the low
 * hundreds of cars (the dashboard widget only renders for logged-in
 * admins, not on every page load).
 */
function obsidian_get_branch_total_units( $branch_id ) {

	$branch_id = (int) $branch_id;
	if ( $branch_id <= 0 ) {
		return 0;
	}

	$car_ids = get_posts( array(
		'post_type'      => 'car',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	) );

	$total = 0;
	$key   = (string) $branch_id;

	foreach ( $car_ids as $car_id ) {
		$inventory = function_exists( 'obsidian_get_car_inventory' )
			? obsidian_get_car_inventory( $car_id )
			: array();

		if ( ! isset( $inventory[ $key ]['colors'] ) || ! is_array( $inventory[ $key ]['colors'] ) ) {
			continue;
		}

		foreach ( $inventory[ $key ]['colors'] as $color_data ) {
			$total += (int) ( $color_data['units'] ?? 0 );
		}
	}

	return $total;
}

/**
 * Count bookings actively holding a unit at a branch on $today.
 *
 * @param int    $branch_id The branch ID.
 * @param string $today     Y-m-d formatted date.
 */
function obsidian_count_branch_active_today( $branch_id, $today ) {

	$branch_id = (int) $branch_id;
	if ( $branch_id <= 0 ) {
		return 0;
	}

	$blocking = function_exists( 'obsidian_get_blocking_statuses' )
		? obsidian_get_blocking_statuses()
		: array( 'pending_review', 'awaiting_payment', 'paid', 'confirmed', 'active' );

	$query = new WP_Query( array(
		'post_type'      => 'booking',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => false,
		'meta_query'     => array(
			'relation' => 'AND',
			array(
				'key'     => '_booking_location_id',
				'value'   => $branch_id,
				'compare' => '=',
				'type'    => 'NUMERIC',
			),
			array(
				'key'     => '_booking_status',
				'value'   => $blocking,
				'compare' => 'IN',
			),
			array(
				'key'     => '_booking_start_date',
				'value'   => $today,
				'compare' => '<=',
				'type'    => 'DATE',
			),
			array(
				'key'     => '_booking_end_date',
				'value'   => $today,
				'compare' => '>=',
				'type'    => 'DATE',
			),
		),
	) );

	return (int) $query->found_posts;
}

/**
 * Inline styles for the widget — kept here so the widget is fully
 * self-contained (no admin.css enqueue needed on the dashboard).
 */
function obsidian_render_branch_utilization_styles() {
	?>
	<style>
		.obsidian-bu-region + .obsidian-bu-region {
			margin-top: 14px;
			padding-top: 12px;
			border-top: 1px solid #eee;
		}
		.obsidian-bu-region-title {
			margin: 0 0 8px;
			font-size: 11px;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: 0.6px;
			color: #666;
		}
		.obsidian-bu-list {
			margin: 0;
			padding: 0;
			list-style: none;
		}
		.obsidian-bu-item + .obsidian-bu-item {
			margin-top: 10px;
		}
		.obsidian-bu-row {
			display: flex;
			align-items: center;
			gap: 8px;
			font-size: 13px;
			margin-bottom: 4px;
		}
		.obsidian-bu-name {
			font-weight: 600;
			text-decoration: none;
			color: #1d2327;
		}
		.obsidian-bu-name:hover {
			text-decoration: underline;
		}
		.obsidian-bu-status {
			font-size: 10px;
			text-transform: uppercase;
			padding: 2px 6px;
			border-radius: 10px;
			background: #f0b849;
			color: #1d2327;
			font-weight: 600;
			letter-spacing: 0.4px;
		}
		.obsidian-bu-status-coming_soon { background: #f0b849; }
		.obsidian-bu-status-closed     { background: #d63638; color: #fff; }
		.obsidian-bu-count {
			margin-left: auto;
			color: #50575e;
			font-variant-numeric: tabular-nums;
		}
		.obsidian-bu-percent {
			color: #787c82;
			margin-left: 2px;
		}
		.obsidian-bu-view {
			margin-left: 4px;
			color: #2271b1;
			text-decoration: none;
			font-size: 14px;
		}
		.obsidian-bu-view:hover {
			text-decoration: underline;
		}
		.obsidian-bu-bar {
			height: 6px;
			background: #f0f0f1;
			border-radius: 3px;
			overflow: hidden;
		}
		.obsidian-bu-bar-fill {
			height: 100%;
			background: #2271b1;
			transition: width 0.3s ease;
		}
		.obsidian-bu-bar-fill.is-warm { background: #f0b849; }
		.obsidian-bu-bar-fill.is-hot  { background: #d63638; }
		.obsidian-bu-bar-empty {
			padding: 0 8px;
			line-height: 16px;
			font-size: 11px;
			color: #888;
			font-style: italic;
		}
		.obsidian-bu-empty {
			color: #999;
			font-style: italic;
			font-size: 13px;
		}
		.obsidian-bu-footer {
			margin-top: 14px;
			padding-top: 12px;
			border-top: 1px solid #eee;
			text-align: center;
		}
		.obsidian-bu-footer a {
			font-size: 13px;
			font-weight: 500;
			text-decoration: none;
		}
	</style>
	<?php
}
