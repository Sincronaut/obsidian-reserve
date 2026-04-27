<?php
/**
 * Dashboard Widget
 *
 * Shows booking pipeline stats and items needing attention
 * on the WP Dashboard home screen.
 *
 * @package obsidian-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the dashboard widget.
 */
function obsidian_add_dashboard_widget() {
	wp_add_dashboard_widget(
		'obsidian_booking_dashboard',
		__( 'Obsidian Reserve — Bookings', 'obsidian-booking' ),
		'obsidian_render_dashboard_widget'
	);
}
add_action( 'wp_dashboard_setup', 'obsidian_add_dashboard_widget' );

/**
 * Render the dashboard widget content.
 */
function obsidian_render_dashboard_widget() {

	$statuses = array(
		'pending_review'   => array( 'label' => 'Pending Review',   'icon' => '📋', 'color' => '#f57f17' ),
		'awaiting_payment' => array( 'label' => 'Awaiting Payment', 'icon' => '💳', 'color' => '#1565c0' ),
		'confirmed'        => array( 'label' => 'Confirmed',        'icon' => '✅', 'color' => '#1b5e20' ),
		'active'           => array( 'label' => 'Active Rentals',   'icon' => '🚗', 'color' => '#00695c' ),
	);

	$counts = array();
	foreach ( array_keys( $statuses ) as $status ) {
		$query = new WP_Query( array(
			'post_type'      => 'booking',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => '_booking_status',
					'value' => $status,
				),
			),
		) );
		$counts[ $status ] = $query->found_posts;
	}

	// Get bookings that need attention (pending_review + upcoming active)
	$attention_query = new WP_Query( array(
		'post_type'      => 'booking',
		'post_status'    => 'any',
		'posts_per_page' => 5,
		'meta_key'       => '_booking_start_date',
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
		'meta_query'     => array(
			array(
				'key'     => '_booking_status',
				'value'   => array( 'pending_review', 'awaiting_payment', 'confirmed' ),
				'compare' => 'IN',
			),
		),
	) );

	$today = new DateTime( 'today', wp_timezone() );
	?>

	<div class="obsidian-dw">

		<!-- Stats -->
		<div class="obsidian-dw-stats">
			<?php foreach ( $statuses as $key => $info ) : ?>
				<div class="obsidian-dw-stat" style="border-left: 3px solid <?php echo esc_attr( $info['color'] ); ?>">
					<span class="obsidian-dw-stat-count" style="color:<?php echo esc_attr( $info['color'] ); ?>">
						<?php echo esc_html( $counts[ $key ] ); ?>
					</span>
					<span class="obsidian-dw-stat-label"><?php echo esc_html( $info['label'] ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>

		<!-- Needs Attention -->
		<?php if ( $attention_query->have_posts() ) : ?>
		<div class="obsidian-dw-attention">
			<h4 class="obsidian-dw-heading">Needs Attention</h4>
			<ul class="obsidian-dw-list">
				<?php while ( $attention_query->have_posts() ) : $attention_query->the_post();
					$bid        = get_the_ID();
					$car_id     = (int) get_post_meta( $bid, '_booking_car_id', true );
					$first      = get_post_meta( $bid, '_booking_first_name', true );
					$last       = get_post_meta( $bid, '_booking_last_name', true );
					$status     = get_post_meta( $bid, '_booking_status', true );
					$start_date = get_post_meta( $bid, '_booking_start_date', true );
					$start_dt   = DateTime::createFromFormat( 'Y-m-d', $start_date );

					$car_name = $car_id ? get_the_title( $car_id ) : 'Unknown Car';
					$customer = trim( $first . ' ' . substr( $last, 0, 1 ) . '.' );

					// Determine context label
					$context = '';
					$urgent  = false;
					if ( $status === 'pending_review' ) {
						$context = 'Docs submitted';
					} elseif ( $status === 'awaiting_payment' ) {
						$context = 'Awaiting payment';
					} elseif ( $status === 'confirmed' && $start_dt ) {
						$diff = $today->diff( $start_dt )->days;
						$is_future = $start_dt > $today;
						if ( $is_future && $diff <= 1 ) {
							$context = 'Starts TOMORROW';
							$urgent = true;
						} elseif ( $is_future && $diff <= 3 ) {
							$context = 'Starts in ' . $diff . ' days';
						} else {
							$context = 'Starts ' . $start_dt->format( 'M j' );
						}
					}
				?>
				<li class="obsidian-dw-item <?php echo $urgent ? 'obsidian-dw-urgent' : ''; ?>">
					<div class="obsidian-dw-item-main">
						<strong><?php echo esc_html( $car_name ); ?></strong>
						<span class="obsidian-dw-sep">—</span>
						<span><?php echo esc_html( $customer ); ?></span>
					</div>
					<div class="obsidian-dw-item-meta">
						<span><?php echo esc_html( $context ); ?></span>
						<?php if ( $urgent ) : ?>
							<span class="obsidian-dw-urgent-badge">⚠️ Urgent</span>
						<?php endif; ?>
						<a href="<?php echo esc_url( get_edit_post_link( $bid ) ); ?>" class="obsidian-dw-review">Review →</a>
					</div>
				</li>
				<?php endwhile; wp_reset_postdata(); ?>
			</ul>
		</div>
		<?php else : ?>
			<p class="obsidian-dw-empty">No bookings need attention right now.</p>
		<?php endif; ?>

		<div class="obsidian-dw-footer">
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=booking' ) ); ?>">View All Bookings →</a>
		</div>
	</div>
	<?php
}
