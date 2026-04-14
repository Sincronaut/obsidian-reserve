<?php
/**
 * Booking Form Block — render.php
 *
 * Routes between three steps based on ob_step query var:
 *   (none)        → Booking form (local / international)
 *   payment       → Payment page (PayMongo)
 *   confirmation  → Confirmation receipt
 *
 * @package child-obsidian-reserve
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ob_step = get_query_var( 'ob_step', '' );

/* ═══════════════════════════════════════════════════════════
   STEP: PAYMENT
   ═══════════════════════════════════════════════════════════ */
if ( $ob_step === 'payment' ) {
	require __DIR__ . '/render-payment.php';
	return;
}

/* ═══════════════════════════════════════════════════════════
   STEP: CONFIRMATION
   ═══════════════════════════════════════════════════════════ */
if ( $ob_step === 'confirmation' ) {
	require __DIR__ . '/render-confirmation.php';
	return;
}

/* ═══════════════════════════════════════════════════════════
   STEP: BOOKING FORM (default)
   ═══════════════════════════════════════════════════════════ */

if ( ! is_user_logged_in() ) {
	printf(
		'<div class="obsidian-booking-form-wrap"><p class="obsidian-bf-error">Please <a href="%s">log in</a> to complete your booking.</p></div>',
		esc_url( wp_login_url( get_permalink() ) )
	);
	return;
}

// Read URL params
$car_id        = isset( $_GET['car_id'] ) ? (int) $_GET['car_id'] : 0;
$start_date    = isset( $_GET['start'] ) ? sanitize_text_field( $_GET['start'] ) : '';
$end_date      = isset( $_GET['end'] ) ? sanitize_text_field( $_GET['end'] ) : '';
$color         = isset( $_GET['color'] ) ? sanitize_text_field( $_GET['color'] ) : '';
$customer_type = isset( $_GET['customer_type'] ) ? sanitize_text_field( $_GET['customer_type'] ) : 'local';

$is_international = ( $customer_type === 'international' );

// Validate car
$car = $car_id ? get_post( $car_id ) : null;
if ( ! $car || $car->post_type !== 'car' || $car->post_status !== 'publish' ) {
	echo '<div class="obsidian-booking-form-wrap"><p class="obsidian-bf-error">Invalid vehicle. Please go back to the <a href="' . esc_url( home_url( '/fleet/' ) ) . '">fleet page</a> and try again.</p></div>';
	return;
}

// Get car data
$car_name   = get_the_title( $car_id );
$daily_rate = (float) get_field( 'car_daily_rate', $car_id );
$make       = get_field( 'car_make', $car_id ) ?: '';
$model      = get_field( 'car_model', $car_id ) ?: '';

// Calculate booking summary
$start_dt  = DateTime::createFromFormat( 'Y-m-d', $start_date );
$end_dt    = DateTime::createFromFormat( 'Y-m-d', $end_date );
$num_days  = ( $start_dt && $end_dt ) ? $start_dt->diff( $end_dt )->days : 0;
$total     = $daily_rate * $num_days;
$color_display = ucfirst( $color );

// Format dates for display
$start_display = $start_dt ? $start_dt->format( 'M j, Y' ) : $start_date;
$end_display   = $end_dt ? $end_dt->format( 'M j, Y' ) : $end_date;

// Get color variant image for the summary
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

// Government ID options for local renters
$gov_id_options = array(
	''                       => 'Select Government ID',
	'philippine_passport'    => 'Philippine Passport',
	'drivers_license'        => "Driver's License",
	'sss_id'                 => 'SSS ID',
	'gsis_id'                => 'GSIS ID',
	'umid'                   => 'UMID',
	'philhealth'             => 'PhilHealth ID',
	'voters_id'              => "Voter's ID",
	'prc_id'                 => 'PRC ID',
	'postal_id'              => 'Postal ID',
	'national_id'            => 'Philippine National ID (PhilSys)',
);

// Obsidian locations
$locations = array(
	''              => 'Select Location',
	'main_office'   => 'Main Office',
	'airport'       => 'Airport Pickup',
	'hotel'         => 'Hotel Delivery',
);
?>

<div class="obsidian-booking-form-wrap" id="obsidian-booking-form-wrap">

	<!-- Hidden fields for JS -->
	<input type="hidden" id="obf-car-id" value="<?php echo esc_attr( $car_id ); ?>" />
	<input type="hidden" id="obf-start-date" value="<?php echo esc_attr( $start_date ); ?>" />
	<input type="hidden" id="obf-end-date" value="<?php echo esc_attr( $end_date ); ?>" />
	<input type="hidden" id="obf-color" value="<?php echo esc_attr( $color ); ?>" />
	<input type="hidden" id="obf-customer-type" value="<?php echo esc_attr( $customer_type ); ?>" />

	<!-- Header -->
	<div class="obsidian-bf-header">
		<?php if ( $is_international ) : ?>
			<h1 class="obsidian-bf-title"><span class="text-gold">International</span> Renters Form</h1>
			<p class="obsidian-bf-subtitle">Land and drive. Fill in the details below.</p>
		<?php else : ?>
			<h1 class="obsidian-bf-title"><span class="text-gold">Local</span> Renters Form</h1>
			<p class="obsidian-bf-subtitle">Your exact vehicle starts with this form.</p>
		<?php endif; ?>
	</div>

	<!-- Progress Stepper -->
	<div class="obsidian-bf-stepper">
		<div class="obsidian-bf-step active">
			<span class="obsidian-bf-step-number">1</span>
		</div>
		<div class="obsidian-bf-step-line"></div>
		<div class="obsidian-bf-step">
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
			<span class="obsidian-bf-summary-total">₱<?php echo esc_html( number_format( $total ) ); ?></span>
		</div>
	</div>

	<!-- View Documents Requirements -->
	<button type="button" class="obsidian-bf-docs-toggle" id="obf-docs-toggle">View Documents Requirements</button>
	<div class="obsidian-bf-docs-info" id="obf-docs-info" style="display:none;">
		<?php if ( $is_international ) : ?>
			<ul>
				<li>Valid Driver's License (held for at least 2 years)</li>
				<li>Passport ID (photo page)</li>
				<li>Proof of Arrival (e-ticket, airline booking, or arrival stamp)</li>
			</ul>
		<?php else : ?>
			<ul>
				<li>Valid Driver's License (held for at least 2 years)</li>
				<li>Two (2) valid Government-issued IDs (front &amp; back)</li>
			</ul>
		<?php endif; ?>
	</div>

	<!-- Form -->
	<form id="obsidian-booking-form" class="obsidian-bf-form" novalidate>

		<!-- ── SHARED: Contact Fields ── -->
		<div class="obsidian-bf-field">
			<label for="obf-first-name">First Name</label>
			<input type="text" id="obf-first-name" name="first_name" placeholder="ex : Juan Miguel" required />
		</div>

		<div class="obsidian-bf-field">
			<label for="obf-last-name">Last Name:</label>
			<input type="text" id="obf-last-name" name="last_name" placeholder="ex : Dela Cruz" required />
		</div>

		<div class="obsidian-bf-field">
			<label for="obf-address">Address</label>
			<input type="text" id="obf-address" name="address" placeholder="ex : 123 Street, City of Manila" required />
		</div>

		<div class="obsidian-bf-field obsidian-bf-field-split">
			<div>
				<label for="obf-birth-date">Birth Date</label>
				<input type="text" id="obf-birth-date" name="birth_date" placeholder="Month / Day / Year" required readonly />
			</div>
			<span class="obsidian-bf-field-note">Age must be 21 years old above*</span>
		</div>

		<!-- ── LOCAL ONLY: Mobile Number ── -->
		<?php if ( ! $is_international ) : ?>
		<div class="obsidian-bf-field obsidian-bf-local-only">
			<label for="obf-phone">Mobile Number</label>
			<input type="tel" id="obf-phone" name="phone" placeholder="+63" required />
		</div>
		<?php endif; ?>

		<!-- ── SHARED: Driver License ── -->
		<div class="obsidian-bf-field obsidian-bf-field-split">
			<div>
				<label for="obf-license-number">Enter Driver License Number</label>
				<input type="text" id="obf-license-number" name="license_number"
					placeholder="<?php echo $is_international ? '000-000-000-000' : 'N04-000-000-000'; ?>" required />
			</div>
			<span class="obsidian-bf-field-note"><em>License Held must be full 2 years</em></span>
		</div>

		<div class="obsidian-bf-upload-group">
			<label>Upload Drivers License</label>
			<div class="obsidian-bf-upload-zone" data-doc-key="license">
				<input type="file" accept="image/jpeg,image/png,image/webp,application/pdf" class="obf-file-input" />
				<div class="obf-upload-placeholder">
					<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
					<span>Upload Drivers License</span>
				</div>
				<div class="obf-upload-preview" style="display:none;">
					<img src="" alt="Preview" />
					<button type="button" class="obf-upload-remove">&times;</button>
				</div>
			</div>
		</div>

		<?php if ( $is_international ) : ?>
		<!-- ── INTERNATIONAL: 90-Day Rule ── -->
		<div class="obsidian-bf-notice">
			<strong>90-Day Rule:</strong> Your foreign driver's license is valid for driving in the Philippines for up to 90 days from your date of arrival. If you plan to stay or drive beyond 90 days, you must visit the Land Transportation Office (LTO) to convert your license or obtain a Philippine driver's license.
		</div>

		<!-- ── INTERNATIONAL: Passport ── -->
		<div class="obsidian-bf-field">
			<label for="obf-passport-number">Passport ID Number</label>
			<input type="text" id="obf-passport-number" name="passport_number" placeholder="000-000-000-000" required />
		</div>

		<div class="obsidian-bf-upload-group">
			<label>Upload Passport ID</label>
			<div class="obsidian-bf-upload-zone" data-doc-key="passport">
				<input type="file" accept="image/jpeg,image/png,image/webp,application/pdf" class="obf-file-input" />
				<div class="obf-upload-placeholder">
					<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
					<span>Upload Passport ID</span>
				</div>
				<div class="obf-upload-preview" style="display:none;">
					<img src="" alt="Preview" />
					<button type="button" class="obf-upload-remove">&times;</button>
				</div>
			</div>
		</div>

		<!-- ── INTERNATIONAL: Proof of Arrival ── -->
		<div class="obsidian-bf-upload-group">
			<label>Proof of Arrival</label>
			<div class="obsidian-bf-upload-zone" data-doc-key="proof_of_arrival">
				<input type="file" accept="image/jpeg,image/png,image/webp,application/pdf" class="obf-file-input" />
				<div class="obf-upload-placeholder">
					<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
					<span>Upload Passport ID</span>
				</div>
				<div class="obf-upload-preview" style="display:none;">
					<img src="" alt="Preview" />
					<button type="button" class="obf-upload-remove">&times;</button>
				</div>
			</div>
			<p class="obsidian-bf-upload-help">your e-ticket, airline booking confirmation, or arrival stamp confirming your date of entry to the Philippines (relevant for verifying the 90-day driving window).</p>
		</div>

		<?php else : ?>
		<!-- ── LOCAL: Government ID ── -->
		<div class="obsidian-bf-section-label">Government Identification Card</div>

		<div class="obsidian-bf-field">
			<select id="obf-gov-id-type" name="gov_id_type" required>
				<?php foreach ( $gov_id_options as $val => $label ) : ?>
					<option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="obsidian-bf-field">
			<select id="obf-gov-id-type-2" name="gov_id_type_2" required>
				<?php foreach ( $gov_id_options as $val => $label ) : ?>
					<option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="obsidian-bf-upload-row">
			<div class="obsidian-bf-upload-group">
				<div class="obsidian-bf-upload-zone" data-doc-key="gov_id_front">
					<input type="file" accept="image/jpeg,image/png,image/webp,application/pdf" class="obf-file-input" />
					<div class="obf-upload-placeholder">
						<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
						<span>Upload ID</span>
					</div>
					<div class="obf-upload-preview" style="display:none;">
						<img src="" alt="Preview" />
						<button type="button" class="obf-upload-remove">&times;</button>
					</div>
				</div>
			</div>
			<div class="obsidian-bf-upload-group">
				<div class="obsidian-bf-upload-zone" data-doc-key="gov_id_back">
					<input type="file" accept="image/jpeg,image/png,image/webp,application/pdf" class="obf-file-input" />
					<div class="obf-upload-placeholder">
						<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
						<span>Upload ID</span>
					</div>
					<div class="obf-upload-preview" style="display:none;">
						<img src="" alt="Preview" />
						<button type="button" class="obf-upload-remove">&times;</button>
					</div>
				</div>
			</div>
		</div>
		<?php endif; ?>

		<!-- ── SHARED: Location ── -->
		<div class="obsidian-bf-field">
			<label for="obf-location">Select Obsidian Location</label>
			<select id="obf-location" name="location" required>
				<?php foreach ( $locations as $val => $label ) : ?>
					<option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<!-- ── SHARED: Agreements ── -->
		<div class="obsidian-bf-agreements">
			<label class="obsidian-bf-checkbox">
				<input type="checkbox" name="agree_terms" required />
				<span class="obf-check-box"></span>
				<span>I confirm all details are accurate and I agree to the <strong>Obsidian Reserve Terms and Conditions</strong> prior to making my reservation.</span>
			</label>
			<label class="obsidian-bf-checkbox">
				<input type="checkbox" name="agree_privacy" required />
				<span class="obf-check-box"></span>
				<span>I have read and agree to the <strong>Obsidian Reserve Privacy Policy</strong> and consent to the processing of my personal data accordingly.</span>
			</label>
		</div>

		<!-- ── Submit ── -->
		<div class="obsidian-bf-actions">
			<button type="submit" class="obsidian-bf-submit" id="obf-submit" disabled>
				<span class="obf-submit-text">Submit for Review</span>
				<span class="obf-submit-spinner" style="display:none;"></span>
			</button>
		</div>

		<div class="obsidian-bf-message" id="obf-message" style="display:none;"></div>

	</form>
</div>
