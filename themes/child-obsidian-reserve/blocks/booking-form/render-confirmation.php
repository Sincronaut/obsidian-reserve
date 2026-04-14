<?php
/**
 * Confirmation Page — render-confirmation.php
 *
 * Shows booking confirmation and payment receipt after successful payment.
 *
 * @package child-obsidian-reserve
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! is_user_logged_in() ) {
	printf(
		'<div class="obsidian-booking-form-wrap"><p class="obsidian-bf-error">Please <a href="%s">log in</a> to view your booking.</p></div>',
		esc_url( wp_login_url( home_url( $_SERVER['REQUEST_URI'] ) ) )
	);
	return;
}

$booking_id = isset( $_GET['booking_id'] ) ? (int) $_GET['booking_id'] : 0;
$booking    = $booking_id ? get_post( $booking_id ) : null;

if ( ! $booking || $booking->post_type !== 'booking' ) {
	echo '<div class="obsidian-booking-form-wrap"><p class="obsidian-bf-error">Booking not found. <a href="' . esc_url( home_url( '/fleet/' ) ) . '">Back to Fleet</a></p></div>';
	return;
}

$booking_user = (int) get_post_meta( $booking_id, '_booking_user_id', true );
if ( $booking_user !== get_current_user_id() ) {
	echo '<div class="obsidian-booking-form-wrap"><p class="obsidian-bf-error">You do not have permission to view this booking.</p></div>';
	return;
}

$status = get_post_meta( $booking_id, '_booking_status', true );
if ( ! in_array( $status, array( 'paid', 'confirmed', 'active', 'completed' ), true ) ) {
	echo '<div class="obsidian-booking-form-wrap"><p class="obsidian-bf-error">This booking has not been paid yet.</p></div>';
	return;
}

// Booking data
$car_id        = (int) get_post_meta( $booking_id, '_booking_car_id', true );
$car_name      = get_the_title( $car_id );
$color         = get_post_meta( $booking_id, '_booking_color', true );
$start_date    = get_post_meta( $booking_id, '_booking_start_date', true );
$end_date      = get_post_meta( $booking_id, '_booking_end_date', true );
$total         = (float) get_post_meta( $booking_id, '_booking_total_price', true );
$payment_amt   = (float) get_post_meta( $booking_id, '_booking_payment_amount', true );
$deposit_amt   = (float) get_post_meta( $booking_id, '_booking_deposit_amount', true );

$start_dt = DateTime::createFromFormat( 'Y-m-d', $start_date );
$end_dt   = DateTime::createFromFormat( 'Y-m-d', $end_date );
$num_days = ( $start_dt && $end_dt ) ? $start_dt->diff( $end_dt )->days : 0;

$start_display = $start_dt ? $start_dt->format( 'M j, Y' ) : $start_date;
$end_display   = $end_dt ? $end_dt->format( 'M j, Y' ) : $end_date;

$rental_paid   = $payment_amt - $deposit_amt;
$balance       = $total - $rental_paid;

// Car image
$variant_img = '';
if ( $color && function_exists( 'obsidian_get_color_variants' ) ) {
	$variants = obsidian_get_color_variants( $car_id );
	$c_lower  = strtolower( $color );
	if ( isset( $variants[ $c_lower ]['images'][0] ) ) {
		$variant_img = wp_get_attachment_image_url( (int) $variants[ $c_lower ]['images'][0], 'medium_large' );
	}
}
if ( ! $variant_img ) {
	$variant_img = get_the_post_thumbnail_url( $car_id, 'medium_large' ) ?: '';
}

$color_display = ucfirst( $color );
?>

<div class="obsidian-booking-form-wrap obsidian-confirmation-wrap" id="obsidian-confirmation-wrap">

	<!-- Header -->
	<div class="obsidian-bf-header obc-header">
		<div class="obc-check-circle">
			<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>
		</div>
		<h1 class="obsidian-bf-title">Reservation <span class="text-gold">Confirmed!</span></h1>
		<p class="obsidian-bf-subtitle">Your luxury ride is secured. We'll see you soon.</p>
	</div>

	<!-- Progress Stepper -->
	<div class="obsidian-bf-stepper">
		<div class="obsidian-bf-step completed">
			<span class="obsidian-bf-step-number">&#10003;</span>
		</div>
		<div class="obsidian-bf-step-line obsidian-bf-step-line-done"></div>
		<div class="obsidian-bf-step completed">
			<span class="obsidian-bf-step-number">&#10003;</span>
		</div>
		<div class="obsidian-bf-step-line obsidian-bf-step-line-done"></div>
		<div class="obsidian-bf-step active">
			<span class="obsidian-bf-step-number">3</span>
		</div>
	</div>

	<!-- Booking Details -->
	<div class="obc-booking-card">
		<?php if ( $variant_img ) : ?>
			<img src="<?php echo esc_url( $variant_img ); ?>" alt="<?php echo esc_attr( $car_name ); ?>" class="obc-car-img" />
		<?php endif; ?>
		<div class="obc-booking-info">
			<h3><?php echo esc_html( $car_name ); ?></h3>
			<?php if ( $color_display ) : ?>
				<span class="obc-color">Color: <?php echo esc_html( $color_display ); ?></span>
			<?php endif; ?>
			<span class="obc-dates"><?php echo esc_html( $start_display . ' – ' . $end_display ); ?> (<?php echo esc_html( $num_days ); ?> day<?php echo $num_days !== 1 ? 's' : ''; ?>)</span>
		</div>
	</div>

	<!-- Payment Receipt -->
	<div class="obc-receipt">
		<h3 class="obp-section-title">Payment Receipt</h3>

		<div class="obc-receipt-line">
			<span>Rental paid</span>
			<span>₱<?php echo esc_html( number_format( $rental_paid ) ); ?></span>
		</div>
		<div class="obc-receipt-line">
			<span>Security deposit</span>
			<span>₱<?php echo esc_html( number_format( $deposit_amt ) ); ?></span>
		</div>
		<div class="obc-receipt-line obc-receipt-total">
			<span>Total charged</span>
			<span>₱<?php echo esc_html( number_format( $payment_amt ) ); ?></span>
		</div>
		<?php if ( $balance > 0 ) : ?>
		<div class="obc-receipt-line obc-receipt-balance">
			<span>Balance at pickup</span>
			<span>₱<?php echo esc_html( number_format( $balance ) ); ?></span>
		</div>
		<?php endif; ?>
	</div>

	<!-- Booking ID -->
	<div class="obc-booking-id">
		<span>Booking ID</span>
		<strong>#<?php echo esc_html( $booking_id ); ?></strong>
	</div>

	<!-- Status -->
	<div class="obc-status">
		<span class="obc-status-dot"></span>
		<span>Status: <strong>Confirmed</strong></span>
	</div>

	<!-- CTA -->
	<div class="obsidian-bf-actions obc-actions">
		<a href="<?php echo esc_url( home_url( '/fleet/' ) ); ?>" class="obsidian-bf-submit">Back to Fleet</a>
	</div>
</div>
