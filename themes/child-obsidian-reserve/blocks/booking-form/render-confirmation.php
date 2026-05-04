<?php
/**
 * Confirmation Page — render-confirmation.php
 *
 * Pre-payment: Review page before final charge (Step 3).
 * Post-payment: "Reserved" success page with car details.
 *
 * @package child-obsidian-reserve
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! is_user_logged_in() ) {
	printf(
		'<section class="obsidian-booking-form-section"><div class="obsidian-booking-form-wrap"><p class="obsidian-bf-error">Please <a href="%s">log in</a> to view your booking.</p></div></section>',
		esc_url( wp_login_url( home_url( $_SERVER['REQUEST_URI'] ) ) )
	);
	return;
}$booking_id = isset( $_GET['booking_id'] ) ? (int) $_GET['booking_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$booking     = $booking_id ? get_post( $booking_id ) : null;

if ( ! $booking || 'booking' !== $booking->post_type ) {
	echo '<section class="obsidian-booking-form-section"><div class="obsidian-booking-form-wrap"><p class="obsidian-bf-error">Booking not found. <a href="' . esc_url( home_url( '/fleet/' ) ) . '">Back to Fleet</a></p></div></section>';
	return;
}

$booking_user = (int) get_post_meta( $booking_id, '_booking_user_id', true );
if ( get_current_user_id() !== $booking_user ) {
	echo '<section class="obsidian-booking-form-section"><div class="obsidian-booking-form-wrap"><p class="obsidian-bf-error">You do not have permission to view this booking.</p></div></section>';
	return;
}

$booking_status = get_post_meta( $booking_id, '_booking_status', true );
$is_paid        = in_array( $booking_status, array( 'paid', 'confirmed', 'active', 'completed' ), true );

// Booking data.
$car_id      = (int) get_post_meta( $booking_id, '_booking_car_id', true );
$car_name    = get_the_title( $car_id );
$color       = get_post_meta( $booking_id, '_booking_color', true );
$start_date  = get_post_meta( $booking_id, '_booking_start_date', true );
$end_date    = get_post_meta( $booking_id, '_booking_end_date', true );
$total       = (float) get_post_meta( $booking_id, '_booking_total_price', true );
$daily_rate  = (float) get_field( 'car_daily_rate', $car_id );
$deposit_amt = (float) get_post_meta( $booking_id, '_booking_deposit_amount', true );
if ( ! $deposit_amt ) {
	$deposit_amt = max( 10000, $total * 0.40 );
}
$need_chauffeur = get_post_meta( $booking_id, '_booking_need_chauffeur', true );

// Car specs.
$car_specs = '';
if ( function_exists( 'get_field' ) ) {
	$car_specs = get_field( 'car_specs', $car_id );
}
$specs_lines = $car_specs ? array_filter( array_map( 'trim', explode( "\n", $car_specs ) ) ) : array();

// Match the payment page showcase specs (engine, power, performance only).
$showcase_specs = array();
$spec_keys      = array( 'engine', 'power', 'performance' );
foreach ( $specs_lines as $line ) {
	$parts = explode( ':', $line, 2 );
	if ( count( $parts ) === 2 ) {
		$key = strtolower( trim( $parts[0] ) );
		if ( in_array( $key, $spec_keys, true ) ) {
			$showcase_specs[] = array(
				'label' => trim( $parts[0] ),
				'value' => trim( $parts[1] ),
			);
		}
	}
}

// Car image.
$variant_img = '';
if ( $color && function_exists( 'obsidian_get_color_variants' ) ) {
	$variants = obsidian_get_color_variants( $car_id );
	$c_lower  = strtolower( $color );
	if ( isset( $variants[ $c_lower ]['images'][0] ) ) {
		$variant_img = wp_get_attachment_image_url( (int) $variants[ $c_lower ]['images'][0], 'medium_large' );
	}
}
if ( ! $variant_img ) {
	$thumbnail_url = get_the_post_thumbnail_url( $car_id, 'medium_large' );
	$variant_img   = $thumbnail_url ? $thumbnail_url : '';
}

// Use a larger image for the confirmation car card (match payment page).
$showcase_img = '';
if ( $color && function_exists( 'obsidian_get_color_variants' ) ) {
	$variants = obsidian_get_color_variants( $car_id );
	$c_lower  = strtolower( $color );
	if ( isset( $variants[ $c_lower ]['images'][0] ) ) {
		$showcase_img = wp_get_attachment_image_url( (int) $variants[ $c_lower ]['images'][0], 'large' );
	}
}
if ( ! $showcase_img ) {
	$large_thumbnail_url = get_the_post_thumbnail_url( $car_id, 'large' );
	$showcase_img        = $large_thumbnail_url ? $large_thumbnail_url : '';
}

$color_display = ucfirst( $color );

// ── If already paid, show the "Reserved" success page. ──
if ( $is_paid ) : ?>

<section class="obsidian-booking-form-section">
<div class="obsidian-booking-form-wrap obsidian-reserved-wrap" id="obsidian-confirmation-wrap">

	<div class="obsidian-bf-header obr-header-centered">
		<h1 class="obsidian-bf-title" id="obr-title"><span class="text-gold italic">Reserved</span></h1>
	</div>

	<!-- Car Card (same as confirmation page) -->
	<div class="obp-vehicle-showcase">
		<div class="obp-showcase-hero">
			<?php if ( $showcase_img ) : ?>
				<img src="<?php echo esc_url( $showcase_img ); ?>" alt="<?php echo esc_attr( $car_name ); ?>" class="obp-showcase-img" />
			<?php endif; ?>
			<h3 class="obp-showcase-name"><?php echo esc_html( $car_name ); ?> <span class="text-gold"><?php echo esc_html( $color_display ); ?></span></h3>
		</div>
		<div class="obp-showcase-bottom">
			<div class="obp-showcase-specs">
				<?php if ( ! empty( $showcase_specs ) ) : ?>
					<p class="obp-showcase-specs-title"><strong>Specifications</strong></p>
					<?php foreach ( $showcase_specs as $spec ) : ?>
						<p class="obp-showcase-spec-line"><strong><?php echo esc_html( $spec['label'] ); ?>:</strong> <?php echo esc_html( $spec['value'] ); ?></p>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<!-- Back to Fleet button -->
	<div class="obsidian-bf-actions obr-actions">
		<a href="<?php echo esc_url( home_url( '/fleet/' ) ); ?>" class="obsidian-bf-submit">Back to Fleet</a>
	</div>

</div>
</section>

	<?php
	return;
endif;

// ── Pre-payment: confirmation / review page (Step 3) ──

$first_name = get_post_meta( $booking_id, '_booking_first_name', true );
$last_name  = get_post_meta( $booking_id, '_booking_last_name', true );
$address    = get_post_meta( $booking_id, '_booking_address', true );
$birth_date = get_post_meta( $booking_id, '_booking_birth_date', true );
$license_no = get_post_meta( $booking_id, '_booking_license_number', true );

$age = '';
if ( $birth_date ) {
	$birth_dt = DateTime::createFromFormat( 'Y-m-d', $birth_date );
	if ( $birth_dt ) {
		$age = $birth_dt->diff( new DateTime() )->y;
	}
}

$birth_display = $birth_date;
if ( $birth_date ) {
	$bd = DateTime::createFromFormat( 'Y-m-d', $birth_date );
	if ( $bd ) {
		$birth_display = $bd->format( 'm / d / Y' );
	}
}
?>

<section class="obsidian-booking-form-section">
<div class="obsidian-booking-form-wrap obsidian-confirmation-wrap" id="obsidian-confirmation-wrap">

	<!-- Hidden data for JS reserved page -->
	<input type="hidden" id="obc-car-img-url" value="<?php echo esc_attr( $variant_img ); ?>" />
	<input type="hidden" id="obc-car-name" value="<?php echo esc_attr( $car_name ); ?>" />
	<input type="hidden" id="obc-car-color" value="<?php echo esc_attr( $color_display ); ?>" />
	<input type="hidden" id="obc-car-specs" value="<?php echo esc_attr( wp_json_encode( $specs_lines ) ); ?>" />

	<!-- Header -->
	<div class="obsidian-bf-header obc-header">
		<h1 class="obsidian-bf-title" id="obc-title"><span class="text-gold italic">Complete</span></h1>
		<p class="obsidian-bf-subtitle" id="obc-subtitle">Check your details and confirm to proceed.</p>
	</div>

	<!-- Progress Stepper — Step 3 active -->
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

	<!-- Car Card (match payment page styling, without total amount) -->
	<div class="obp-vehicle-showcase">
		<div class="obp-showcase-hero">
			<?php if ( $showcase_img ) : ?>
				<img src="<?php echo esc_url( $showcase_img ); ?>" alt="<?php echo esc_attr( $car_name ); ?>" class="obp-showcase-img" />
			<?php endif; ?>
			<h3 class="obp-showcase-name"><?php echo esc_html( $car_name ); ?> <span class="text-gold"><?php echo esc_html( $color_display ); ?></span></h3>
		</div>
		<div class="obp-showcase-bottom">
			<div class="obp-showcase-specs">
				<?php if ( ! empty( $showcase_specs ) ) : ?>
					<p class="obp-showcase-specs-title"><strong>Specifications</strong></p>
					<?php foreach ( $showcase_specs as $spec ) : ?>
						<p class="obp-showcase-spec-line"><strong><?php echo esc_html( $spec['label'] ); ?>:</strong> <?php echo esc_html( $spec['value'] ); ?></p>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<!-- Summary + Personal Information (single card) -->
	<div class="obc-info-card">
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
		<?php if ( 'yes' === $need_chauffeur ) : ?>
		<div class="obc-totals-row">
			<span>Chauffeur Booked</span>
			<span>2000/day</span>
		</div>
		<?php endif; ?>
		<div class="obc-info-divider"></div>

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
		<p class="obc-info-subheading italic">Payment Information</p>
		<div id="obc-payment-info">
			<!-- Filled by confirmation.js from sessionStorage -->
		</div>
	</div>

	<!-- Confirm button -->
	<div class="obsidian-bf-actions obc-actions">
		<button type="button" class="obsidian-bf-submit obc-confirm-btn" id="obc-confirm-btn">
			<span class="obf-submit-text">Confirm Reservation</span>
			<span class="obf-submit-spinner" style="display:none;"></span>
		</button>
	</div>
	<div class="obsidian-bf-message" id="obc-message" style="display:none;"></div>

</div>
</section>
