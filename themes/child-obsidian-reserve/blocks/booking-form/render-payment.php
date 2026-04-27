<?php
/**
 * Payment Page — render-payment.php
 *
 * Renders the PayMongo payment form. Validates booking_id + token.
 *
 * @package child-obsidian-reserve
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! is_user_logged_in() ) {
	printf(
		'<div class="obsidian-booking-form-wrap"><p class="obsidian-bf-error">Please <a href="%s">log in</a> to complete your payment.</p></div>',
		esc_url( wp_login_url( home_url( $_SERVER['REQUEST_URI'] ) ) )
	);
	return;
}

$booking_id = isset( $_GET['booking_id'] ) ? (int) $_GET['booking_id'] : 0;
$token      = isset( $_GET['token'] ) ? sanitize_text_field( $_GET['token'] ) : '';

// Validate
$error = '';
if ( ! $booking_id || ! get_post( $booking_id ) ) {
	$error = 'Invalid booking. Please check your payment link.';
} elseif ( ! function_exists( 'obsidian_verify_payment_token' ) || ! obsidian_verify_payment_token( $booking_id, $token ) ) {
	$error = 'Invalid or expired payment link. Please contact support.';
} else {
	$status = get_post_meta( $booking_id, '_booking_status', true );
	if ( $status !== 'awaiting_payment' ) {
		if ( in_array( $status, array( 'paid', 'confirmed', 'active', 'completed' ), true ) ) {
			$error = 'This booking has already been paid.';
		} else {
			$error = 'This booking is not ready for payment. Current status: ' . esc_html( ucwords( str_replace( '_', ' ', $status ) ) );
		}
	}

	$booking_user = (int) get_post_meta( $booking_id, '_booking_user_id', true );
	if ( ! $error && $booking_user !== get_current_user_id() ) {
		$error = 'You do not have permission to pay for this booking.';
	}
}

if ( $error ) {
	echo '<div class="obsidian-booking-form-wrap"><p class="obsidian-bf-error">' . esc_html( $error ) . ' <a href="' . esc_url( home_url( '/fleet/' ) ) . '">Back to Fleet</a></p></div>';
	return;
}

// Booking data
$car_id     = (int) get_post_meta( $booking_id, '_booking_car_id', true );
$car_name   = get_the_title( $car_id );
$color      = get_post_meta( $booking_id, '_booking_color', true );
$start_date = get_post_meta( $booking_id, '_booking_start_date', true );
$end_date   = get_post_meta( $booking_id, '_booking_end_date', true );
$total      = (float) get_post_meta( $booking_id, '_booking_total_price', true );
$daily_rate = (float) get_field( 'car_daily_rate', $car_id );

$start_dt = DateTime::createFromFormat( 'Y-m-d', $start_date );
$end_dt   = DateTime::createFromFormat( 'Y-m-d', $end_date );
$num_days = ( $start_dt && $end_dt ) ? $start_dt->diff( $end_dt )->days : 0;

$start_display = $start_dt ? $start_dt->format( 'M j, Y' ) : $start_date;
$end_display   = $end_dt ? $end_dt->format( 'M j, Y' ) : $end_date;

// Payment calculations
$deposit_rate  = 0.40;
$min_deposit   = 10000;
$deposit       = max( $min_deposit, $total * $deposit_rate );
$half_payment  = round( $total * 0.50 );
$charge_total  = $total + $deposit;
$charge_half   = $half_payment + $deposit;

// Color variant image
$variant_img = '';
if ( $color && function_exists( 'obsidian_get_color_variants' ) ) {
	$variants = obsidian_get_color_variants( $car_id );
	$c_lower  = strtolower( $color );
	if ( isset( $variants[ $c_lower ]['images'][0] ) ) {
		$variant_img = wp_get_attachment_image_url( (int) $variants[ $c_lower ]['images'][0], 'medium' );
	}
}
if ( ! $variant_img ) {
	$variant_img = get_the_post_thumbnail_url( $car_id, 'medium' ) ?: '';
}

$color_display = ucfirst( $color );
?>

<div class="obsidian-booking-form-wrap obsidian-payment-wrap" id="obsidian-payment-wrap">

	<!-- Hidden fields for JS -->
	<input type="hidden" id="obp-booking-id" value="<?php echo esc_attr( $booking_id ); ?>" />
	<input type="hidden" id="obp-token" value="<?php echo esc_attr( $token ); ?>" />
	<input type="hidden" id="obp-total" value="<?php echo esc_attr( $total ); ?>" />
	<input type="hidden" id="obp-deposit" value="<?php echo esc_attr( $deposit ); ?>" />

	<!-- Header -->
	<div class="obsidian-bf-header">
		<h1 class="obsidian-bf-title"><span class="text-gold">Payment Form</span></h1>
	</div>

	<!-- Progress Stepper -->
	<div class="obsidian-bf-stepper">
		<div class="obsidian-bf-step completed">
			<span class="obsidian-bf-step-number">&#10003;</span>
		</div>
		<div class="obsidian-bf-step-line obsidian-bf-step-line-done"></div>
		<div class="obsidian-bf-step active">
			<span class="obsidian-bf-step-number">2</span>
		</div>
		<div class="obsidian-bf-step-line"></div>
		<div class="obsidian-bf-step">
			<span class="obsidian-bf-step-number">3</span>
		</div>
	</div>

	<!-- Booking Summary -->
	<div class="obsidian-bf-summary">
		<?php if ( $variant_img ) : ?>
			<img src="<?php echo esc_url( $variant_img ); ?>" alt="<?php echo esc_attr( $car_name ); ?>" class="obsidian-bf-summary-img" />
		<?php endif; ?>
		<div class="obsidian-bf-summary-info">
			<strong><?php echo esc_html( $car_name ); ?> <?php if ( $color_display ) echo '— ' . esc_html( $color_display ); ?></strong>
			<span><?php echo esc_html( $start_display . ' – ' . $end_display ); ?> (<?php echo esc_html( $num_days ); ?> day<?php echo $num_days !== 1 ? 's' : ''; ?>)</span>
			<span>Daily Rate: ₱<?php echo esc_html( number_format( $daily_rate ) ); ?> | Total: <strong class="text-gold">₱<?php echo esc_html( number_format( $total ) ); ?></strong></span>
		</div>
	</div>

	<!-- Payment Form -->
	<form id="obsidian-payment-form" class="obsidian-bf-form" novalidate>



		<!-- Security Deposit -->
		<div class="obp-section obp-deposit-info">
			<h3 class="obp-section-title">Security Deposit</h3>
			<p>₱<?php echo esc_html( number_format( $deposit ) ); ?> hold on your card <em>(refundable)</em></p>
			<span class="obp-deposit-note">Released within 7 days after vehicle return</span>
		</div>

		<!-- Pay With -->
		<div class="obp-section">
			<h3 class="obp-section-title">Pay With</h3>
			<div class="obp-payment-methods" id="obp-payment-methods">
				<button type="button" class="obp-method-btn active" data-method="card">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
					Visa / Mastercard
				</button>
				<button type="button" class="obp-method-btn" data-method="dob_ubp">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 21h18M3 10h18M5 6l7-3 7 3M4 10v11M20 10v11M8 14v3M12 14v3M16 14v3"/></svg>
					BPI
				</button>
				<button type="button" class="obp-method-btn" data-method="dob">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 21h18M3 10h18M5 6l7-3 7 3M4 10v11M20 10v11M8 14v3M12 14v3M16 14v3"/></svg>
					BDO
				</button>
				<button type="button" class="obp-method-btn" data-method="grab_pay">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
					GrabPay
				</button>
			</div>
		</div>

		<!-- Bank redirect notice (hidden by default) -->
		<div class="obp-bank-notice" id="obp-bank-notice" style="display:none;">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
			<span>You will be redirected to <strong id="obp-bank-name">your bank's</strong> secure portal to authorize payment.</span>
		</div>

		<!-- Card Fields -->
		<div class="obp-card-fields" id="obp-card-fields">
			<div class="obsidian-bf-field">
				<label for="obp-card-number">Card Number</label>
				<input type="text" id="obp-card-number" placeholder="4242 4242 4242 4242" maxlength="19" autocomplete="cc-number" required />
			</div>

			<div class="obp-card-row">
				<div class="obsidian-bf-field">
					<label for="obp-card-expiry">Expiry</label>
					<input type="text" id="obp-card-expiry" placeholder="MM / YY" maxlength="7" autocomplete="cc-exp" required />
				</div>
				<div class="obsidian-bf-field">
					<label for="obp-card-cvc">CVV</label>
					<input type="text" id="obp-card-cvc" placeholder="123" maxlength="4" autocomplete="cc-csc" required />
				</div>
			</div>

			<div class="obsidian-bf-field">
				<label for="obp-card-name">Cardholder Name</label>
				<input type="text" id="obp-card-name" placeholder="JUAN DELA CRUZ" autocomplete="cc-name" required />
			</div>
		</div>

		<!-- Charge Summary -->
		<div class="obp-charge-summary">
			<div class="obp-charge-line">
				<span>Rental (<span id="obp-payment-label">100% full prepayment</span>)</span>
				<span id="obp-rental-amount">₱<?php echo esc_html( number_format( $total ) ); ?></span>
			</div>
			<div class="obp-charge-line">
				<span>Security deposit (refundable)</span>
				<span>₱<?php echo esc_html( number_format( $deposit ) ); ?></span>
			</div>
			<div class="obp-charge-total">
				<span>You will be charged</span>
				<span id="obp-charge-total">₱<?php echo esc_html( number_format( $total + $deposit ) ); ?></span>
			</div>
		</div>

		<!-- Submit -->
		<div class="obsidian-bf-actions">
			<button type="submit" class="obsidian-bf-submit obp-submit" id="obp-submit">
				<span class="obf-submit-text">Continue to Review</span>
				<span class="obf-submit-spinner" style="display:none;"></span>
			</button>
		</div>

		<div class="obsidian-bf-message" id="obp-message" style="display:none;"></div>

	</form>
</div>
