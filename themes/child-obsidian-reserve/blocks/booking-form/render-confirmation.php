<?php
/**
 * Confirmation Page — render-confirmation.php
 *
 * Review page before final payment. Shows car details, specs, pricing,
 * personal info, and payment info. The "Confirm Reservation" button
 * triggers the actual PayMongo charge via confirmation.js.
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

// Allow both awaiting_payment (pre-confirm) and paid/confirmed (post-confirm).
$is_paid = in_array( $status, array( 'paid', 'confirmed', 'active', 'completed' ), true );

// Booking data
$car_id        = (int) get_post_meta( $booking_id, '_booking_car_id', true );
$car_name      = get_the_title( $car_id );
$color         = get_post_meta( $booking_id, '_booking_color', true );
$start_date    = get_post_meta( $booking_id, '_booking_start_date', true );
$end_date      = get_post_meta( $booking_id, '_booking_end_date', true );
$total         = (float) get_post_meta( $booking_id, '_booking_total_price', true );
$daily_rate    = (float) get_field( 'car_daily_rate', $car_id );
$deposit_amt   = (float) get_post_meta( $booking_id, '_booking_deposit_amount', true );
if ( ! $deposit_amt ) {
	$deposit_amt = max( 10000, $total * 0.40 );
}

$first_name    = get_post_meta( $booking_id, '_booking_first_name', true );
$last_name     = get_post_meta( $booking_id, '_booking_last_name', true );
$address       = get_post_meta( $booking_id, '_booking_address', true );
$birth_date    = get_post_meta( $booking_id, '_booking_birth_date', true );
$license_no    = get_post_meta( $booking_id, '_booking_license_number', true );

$start_dt = DateTime::createFromFormat( 'Y-m-d', $start_date );
$end_dt   = DateTime::createFromFormat( 'Y-m-d', $end_date );
$num_days = ( $start_dt && $end_dt ) ? $start_dt->diff( $end_dt )->days : 0;

// Calculate age from birth date
$age = '';
if ( $birth_date ) {
	$birth_dt = DateTime::createFromFormat( 'Y-m-d', $birth_date );
	if ( $birth_dt ) {
		$age = $birth_dt->diff( new DateTime() )->y;
	}
}

// Format birth date for display
$birth_display = $birth_date;
if ( $birth_date ) {
	$bd = DateTime::createFromFormat( 'Y-m-d', $birth_date );
	if ( $bd ) {
		$birth_display = $bd->format( 'm / d / Y' );
	}
}

// Car specs
$car_specs = '';
if ( function_exists( 'get_field' ) ) {
	$car_specs = get_field( 'car_specs', $car_id );
}
$specs_lines = $car_specs ? array_filter( array_map( 'trim', explode( "\n", $car_specs ) ) ) : array();

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
		<?php if ( $is_paid ) : ?>
			<h1 class="obsidian-bf-title" id="obc-title"><span class="text-gold">Confirmed!</span></h1>
			<p class="obsidian-bf-subtitle" id="obc-subtitle">Your reservation has been confirmed. We'll see you soon.</p>
		<?php else : ?>
			<h1 class="obsidian-bf-title" id="obc-title"><span class="text-gold italic">Complete</span></h1>
			<p class="obsidian-bf-subtitle" id="obc-subtitle">Check your details and confirm to proceed.</p>
		<?php endif; ?>
	</div>

	<!-- Progress Stepper -->
	<div class="obsidian-bf-stepper">
		<div class="obsidian-bf-step completed">
			<span class="obsidian-bf-step-number">&#10003;</span>
		</div>
		<div class="obsidian-bf-step-line obsidian-bf-step-line-done"></div>
		<div class="obsidian-bf-step <?php echo $is_paid ? 'completed' : 'active'; ?>">
			<span class="obsidian-bf-step-number"><?php echo $is_paid ? '&#10003;' : '2'; ?></span>
		</div>
		<div class="obsidian-bf-step-line <?php echo $is_paid ? 'obsidian-bf-step-line-done' : ''; ?>"></div>
		<div class="obsidian-bf-step <?php echo $is_paid ? 'active' : ''; ?>">
			<span class="obsidian-bf-step-number">3</span>
		</div>
	</div>

	<!-- Car Card -->
	<div class="obc-car-card">
		<?php if ( $variant_img ) : ?>
			<img src="<?php echo esc_url( $variant_img ); ?>" alt="<?php echo esc_attr( $car_name ); ?>" class="obc-car-card-img" />
		<?php endif; ?>
		<h3 class="obc-car-card-name"><?php echo esc_html( $car_name ); ?> <span class="text-gold"><?php echo esc_html( $color_display ); ?></span></h3>

		<?php if ( ! empty( $specs_lines ) ) : ?>
		<div class="obc-specs">
			<p class="obc-specs-label"><strong>Specifications</strong></p>
			<?php foreach ( $specs_lines as $line ) :
				$parts = explode( ':', $line, 2 );
				if ( count( $parts ) === 2 ) : ?>
					<p class="obc-specs-line"><strong><?php echo esc_html( trim( $parts[0] ) ); ?>:</strong> <?php echo esc_html( trim( $parts[1] ) ); ?></p>
				<?php else : ?>
					<p class="obc-specs-line"><?php echo esc_html( $line ); ?></p>
				<?php endif;
			endforeach; ?>
		</div>
		<?php endif; ?>
	</div>

	<!-- Total Amount -->
	<div class="obc-totals-card">
		<div class="obc-totals-header">
			<span class="obc-totals-label">Total Amount:</span>
			<span class="obc-totals-value" id="obc-total-display">₱<?php echo esc_html( number_format( $total + $deposit_amt, 2 ) ); ?></span>
		</div>
		<div class="obc-totals-row">
			<span>Rental Price Per Day</span>
			<span>₱<?php echo esc_html( number_format( $daily_rate, 2 ) ); ?></span>
		</div>
		<div class="obc-totals-row">
			<span>Security Deposit</span>
			<span>₱<?php echo esc_html( number_format( $deposit_amt, 2 ) ); ?></span>
		</div>
	</div>

	<!-- Personal Information -->
	<div class="obc-info-card">
		<p class="obc-info-heading text-gold italic">Please Confirm the Information is right and correct</p>

		<div class="obc-info-row">
			<span class="obc-info-label">Full Name :</span>
			<span class="obc-info-value"><?php echo esc_html( "$first_name $last_name" ); ?></span>
		</div>
		<div class="obc-info-row">
			<span class="obc-info-label">Address:</span>
			<span class="obc-info-value"><?php echo esc_html( $address ); ?></span>
		</div>
		<div class="obc-info-row">
			<span class="obc-info-label">Birth Date:</span>
			<span class="obc-info-value"><?php echo esc_html( $birth_display ); ?></span>
			<?php if ( $age ) : ?>
				<span class="obc-info-label" style="margin-left:24px;">Age:</span>
				<span class="obc-info-value"><?php echo esc_html( $age ); ?></span>
			<?php endif; ?>
		</div>
		<div class="obc-info-row">
			<span class="obc-info-label">Driver License No:</span>
			<span class="obc-info-value"><?php echo esc_html( $license_no ); ?></span>
		</div>

		<!-- Payment Information (populated by JS from sessionStorage, or from meta if already paid) -->
		<p class="obc-info-subheading italic">Payment Information</p>

		<?php if ( $is_paid ) :
			$payment_method = get_post_meta( $booking_id, '_booking_payment_method', true );
			$payment_option = get_post_meta( $booking_id, '_booking_payment_option', true );
			$payment_amount = (float) get_post_meta( $booking_id, '_booking_payment_amount', true );
		?>
			<div class="obc-info-row">
				<span class="obc-info-label">Method:</span>
				<span class="obc-info-value"><?php echo esc_html( ucfirst( $payment_method ?: 'Card' ) ); ?></span>
			</div>
			<div class="obc-info-row">
				<span class="obc-info-label">Amount Paid:</span>
				<span class="obc-info-value text-gold">₱<?php echo esc_html( number_format( $payment_amount, 2 ) ); ?></span>
			</div>
		<?php else : ?>
			<div id="obc-payment-info">
				<!-- Filled by confirmation.js from sessionStorage -->
			</div>
		<?php endif; ?>
	</div>

	<?php if ( $is_paid ) : ?>
		<!-- Post-payment success -->
		<div class="obc-booking-id">
			<span>Booking ID</span>
			<strong>#<?php echo esc_html( $booking_id ); ?></strong>
		</div>
		<div class="obc-status">
			<span class="obc-status-dot"></span>
			<span>Status: <strong>Confirmed</strong></span>
		</div>
		<div class="obsidian-bf-actions obc-actions">
			<a href="<?php echo esc_url( home_url( '/fleet/' ) ); ?>" class="obsidian-bf-submit">Back to Fleet</a>
		</div>
	<?php else : ?>
		<!-- Pre-payment: Confirm button -->
		<div class="obsidian-bf-actions obc-actions">
			<button type="button" class="obsidian-bf-submit obc-confirm-btn" id="obc-confirm-btn">
				<span class="obf-submit-text">Confirm Reservation</span>
				<span class="obf-submit-spinner" style="display:none;"></span>
			</button>
		</div>
		<div class="obsidian-bf-message" id="obc-message" style="display:none;"></div>
	<?php endif; ?>

</div>
