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
$location_id   = isset( $_GET['location_id'] ) ? (int) $_GET['location_id'] : 0;

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

/* ───────────────────────────────────────────────────────────
   Phase 11.14 — Pickup branches (dynamic, grouped by region)
   The legacy hard-coded $locations array (Main Office / Airport /
   Hotel) is gone. Branches now come from the `location` CPT, only
   `active` ones, grouped by their `region` taxonomy term so the
   <select> can render <optgroup>s.

   When a `location_id` was passed in the URL (from the modal) we
   render the field as read-only with a "Change location" link that
   goes back to the modal so the user can re-pick.
   ─────────────────────────────────────────────────────────── */

$branches_by_region = array();
$branch_lookup      = array();

$branch_query = new WP_Query( array(
	'post_type'      => 'location',
	'post_status'    => 'publish',
	'posts_per_page' => -1,
	'orderby'        => 'title',
	'order'          => 'ASC',
	'meta_query'     => array(
		array(
			'key'     => 'location_status',
			'value'   => 'active',
			'compare' => '=',
		),
	),
) );

if ( $branch_query->have_posts() ) {
	foreach ( $branch_query->posts as $b ) {
		$regions = wp_get_post_terms( $b->ID, 'region', array( 'number' => 1 ) );
		$region  = ( ! is_wp_error( $regions ) && ! empty( $regions ) ) ? $regions[0] : null;
		$rkey    = $region ? $region->term_id : 0;
		$rlabel  = $region ? $region->name : __( 'Other', 'obsidian-booking' );

		if ( ! isset( $branches_by_region[ $rkey ] ) ) {
			$branches_by_region[ $rkey ] = array(
				'label'    => $rlabel,
				'branches' => array(),
			);
		}
		$branches_by_region[ $rkey ]['branches'][] = $b;
		$branch_lookup[ $b->ID ] = array(
			'name'   => $b->post_title,
			'region' => $rlabel,
		);
	}
}

// Validate the URL-provided branch (must exist and be active).
$preselected_branch = ( $location_id && isset( $branch_lookup[ $location_id ] ) )
	? $branch_lookup[ $location_id ]
	: null;
$location_locked    = ( $preselected_branch !== null );

// Build a "Change location" link that re-opens the modal with the
// car & dates intact but without `location_id`, so the modal's branch
// picker comes back up.
$change_location_url = add_query_arg(
	array(
		'car_id' => $car_id,
		'start'  => $start_date,
		'end'    => $end_date,
		'color'  => $color,
	),
	home_url( '/fleet/' )
);
?>

<div class="obsidian-booking-form-wrap" id="obsidian-booking-form-wrap">

	<!-- Hidden fields for JS -->
	<input type="hidden" id="obf-car-id" value="<?php echo esc_attr( $car_id ); ?>" />
	<input type="hidden" id="obf-start-date" value="<?php echo esc_attr( $start_date ); ?>" />
	<input type="hidden" id="obf-end-date" value="<?php echo esc_attr( $end_date ); ?>" />
	<input type="hidden" id="obf-color" value="<?php echo esc_attr( $color ); ?>" />
	<input type="hidden" id="obf-customer-type" value="<?php echo esc_attr( $customer_type ); ?>" />
	<input type="hidden" id="obf-location-id" value="<?php echo esc_attr( $location_id ); ?>" />

	<!-- Header -->
	<div class="obsidian-bf-header" id="obf-header">
		<?php if ( $is_international ) : ?>
			<h1 class="obsidian-bf-title" id="obf-title"><span class="text-gold">International</span> Renters Form</h1>
			<p class="obsidian-bf-subtitle" id="obf-subtitle">Land and drive. Fill in the details below.</p>
		<?php else : ?>
			<h1 class="obsidian-bf-title" id="obf-title"><span class="text-gold">Local</span> Renters Form</h1>
			<p class="obsidian-bf-subtitle" id="obf-subtitle">Your exact vehicle starts with this form.</p>
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

	<!-- Form -->
	<form id="obsidian-booking-form" class="obsidian-bf-form" novalidate>

		<!-- ═══════════════════════════════════════════════
		     SUB-STEP 1A: RENTER FORM
		     ═══════════════════════════════════════════════ -->
		<div id="obf-step-renter">

			<!-- View Documents Requirements -->
			<?php if ( $is_international ) : ?>
				<a href="#" class="obsidian-bf-docs-toggle" data-modal="text" data-page-slug="international-requirements" style="display:inline-block; text-decoration:none;">View Documents Requirements</a>
			<?php else : ?>
				<a href="#" class="obsidian-bf-docs-toggle" data-modal="text" data-page-slug="local-requirements" style="display:inline-block; text-decoration:none;">View Documents Requirements</a>
			<?php endif; ?>

			<div class="obsidian-bf-fields-group">

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
							<span>Upload Proof of Arrival</span>
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

				<!-- Two distinct gov IDs (NOT front/back of one ID). The
				     `data-mirror-from` attribute tells the JS which gov-id
				     <select> drives the upload zone's label text — when the
				     user picks "SSS ID" in the dropdown, the upload zone's
				     "Upload ID" caption becomes "Upload SSS ID". -->
				<div class="obsidian-bf-upload-row">
					<div class="obsidian-bf-upload-group">
						<div class="obsidian-bf-upload-zone" data-doc-key="gov_id_1" data-mirror-from="obf-gov-id-type">
							<input type="file" accept="image/jpeg,image/png,image/webp,application/pdf" class="obf-file-input" />
							<div class="obf-upload-placeholder">
								<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
								<span class="obf-upload-label">Upload ID</span>
							</div>
							<div class="obf-upload-preview" style="display:none;">
								<img src="" alt="Preview" />
								<button type="button" class="obf-upload-remove">&times;</button>
							</div>
						</div>
					</div>
					<div class="obsidian-bf-upload-group">
						<div class="obsidian-bf-upload-zone" data-doc-key="gov_id_2" data-mirror-from="obf-gov-id-type-2">
							<input type="file" accept="image/jpeg,image/png,image/webp,application/pdf" class="obf-file-input" />
							<div class="obf-upload-placeholder">
								<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
								<span class="obf-upload-label">Upload ID</span>
							</div>
							<div class="obf-upload-preview" style="display:none;">
								<img src="" alt="Preview" />
								<button type="button" class="obf-upload-remove">&times;</button>
							</div>
						</div>
					</div>
				</div>
				<?php endif; ?>

			</div><!-- .obsidian-bf-fields-group -->

			<!-- UX status hint — explains *why* the Next button is disabled
			     (e.g. "Missing: Last Name"). Filled in by validateRenter(). -->
			<div class="obsidian-bf-status" id="obf-renter-status" hidden>
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
				<span class="obf-status-text" id="obf-renter-status-text"></span>
			</div>

			<!-- Renter step "Next" button -->
			<div class="obsidian-bf-actions">
				<button type="button" class="obsidian-bf-submit" id="obf-next" disabled>
					<span>Next</span>
				</button>
			</div>

		</div><!-- #obf-step-renter -->

		<!-- ═══════════════════════════════════════════════
		     SUB-STEP 1B: DELIVERY FORM
		     ═══════════════════════════════════════════════ -->
		<div id="obf-step-delivery" style="display:none;">

			<div class="obsidian-bf-fields-group">

				<!-- ── Phase 11.14: Pickup Branch ──
				     If location_id was passed via URL we render this as
				     read-only with a "Change location" link back to the
				     fleet/modal. Otherwise, full dropdown grouped by region. -->
				<?php if ( $location_locked ) : ?>
				<div class="obsidian-bf-field obsidian-bf-location-field obsidian-bf-location-locked">
					<label>Pickup Location</label>
					<div class="obsidian-bf-location-display">
						<div class="obsidian-bf-location-name">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
							<strong><?php echo esc_html( $preselected_branch['name'] ); ?></strong>
							<span class="obsidian-bf-location-region">— <?php echo esc_html( $preselected_branch['region'] ); ?></span>
						</div>
						<a href="<?php echo esc_url( $change_location_url ); ?>" class="obsidian-bf-location-change">Change location</a>
					</div>
					<input type="hidden" id="obf-pickup-location" name="pickup_location" value="<?php echo esc_attr( $location_id ); ?>" required />
				</div>
				<?php else : ?>
				<div class="obsidian-bf-field obsidian-bf-location-field">
					<label for="obf-pickup-location">Pickup Location</label>
					<select id="obf-pickup-location" name="pickup_location" required>
						<option value="">Select a branch&hellip;</option>
						<?php foreach ( $branches_by_region as $group ) : ?>
							<optgroup label="<?php echo esc_attr( $group['label'] ); ?>">
								<?php foreach ( $group['branches'] as $b ) : ?>
									<option value="<?php echo esc_attr( $b->ID ); ?>"><?php echo esc_html( $b->post_title ); ?></option>
								<?php endforeach; ?>
							</optgroup>
						<?php endforeach; ?>
					</select>
				</div>
				<?php endif; ?>

				<!-- ── Contact Number (pre-filled from renter form for local) ── -->
				<div class="obsidian-bf-field">
					<label for="obf-delivery-contact">Contact Number</label>
					<input type="tel" id="obf-delivery-contact" name="delivery_contact" placeholder="ex : +639234-2312-4345" required />
				</div>

				<!-- ── Delivery Type (we deliver only — no self-pickup) ── -->
				<div class="obsidian-bf-field">
					<label for="obf-delivery-dropoff">Delivery Type</label>
					<select id="obf-delivery-dropoff" name="delivery_dropoff" required>
						<option value="">Select delivery type&hellip;</option>
						<option value="home_delivery">Home Delivery</option>
						<option value="airport_delivery">Airport Delivery</option>
					</select>
				</div>

				<!-- ── Delivery Date and Time ── -->
				<div class="obsidian-bf-field">
					<label>Delivery Date and Time</label>
					<div class="obf-datetime-row">
						<div class="obf-datetime-field">
							<svg class="obf-datetime-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
							<input type="text" id="obf-delivery-date" name="delivery_date" placeholder="MM / DD / YR" required readonly />
						</div>
						<div class="obf-datetime-field">
							<svg class="obf-datetime-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
							<input type="text" id="obf-delivery-time" name="delivery_time" placeholder="00 : 00 : 00 AM / PM" required />
						</div>
					</div>
				</div>

				<!-- ── Return Address (where we'll pick the car up at the end of the booking) ── -->
				<div class="obsidian-bf-field">
					<label for="obf-return-address"><svg class="obf-label-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg> Return Pickup Address</label>
					<input type="text" id="obf-return-address" name="return_address" placeholder="ex : 123 Street, City of Manila" required />
					<p class="obsidian-bf-field-helper">Where we'll collect the vehicle once your booking ends.</p>
				</div>

				<!-- ── Return Date and Time (auto-set) ──
				     Return date = booking end date, return time mirrors the
				     delivery time the user just picked. Displayed read-only;
				     hidden inputs carry the values to the API. -->
				<div class="obsidian-bf-field obsidian-bf-return-schedule">
					<label>Return Schedule</label>
					<div class="obsidian-bf-return-card">
						<div class="obsidian-bf-return-row">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
							<span>We'll pick up the car on:</span>
							<strong id="obf-return-date-display"><?php echo esc_html( $end_display ); ?></strong>
						</div>
						<div class="obsidian-bf-return-row">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
							<span>at:</span>
							<strong id="obf-return-time-display">—</strong>
							<span class="obsidian-bf-return-hint">(matches your delivery time)</span>
						</div>
					</div>
					<input type="hidden" id="obf-return-date" name="return_date" value="<?php echo esc_attr( $end_date ); ?>" />
					<input type="hidden" id="obf-return-time" name="return_time" value="" />
				</div>

				<!-- ── Special Requests ── -->
				<div class="obsidian-bf-field">
					<label for="obf-special-requests">Special Requests</label>
					<textarea id="obf-special-requests" name="special_requests" rows="4" placeholder="Write Special Request Here :"></textarea>
				</div>

			</div><!-- .obsidian-bf-fields-group -->

			<!-- ── Agreements ── -->
			<div class="obsidian-bf-agreements">
				<label class="obsidian-bf-checkbox">
					<input type="checkbox" name="agree_terms" required />
					<span class="obf-check-box"></span>
					<span>I confirm all details are accurate and I agree to the <strong><a href="#" data-modal="text" data-page-slug="terms-and-conditions" style="color: inherit; text-decoration: underline;">Obsidian Reserve Terms and Conditions</a></strong> prior to making my reservation.</span>
				</label>
				<label class="obsidian-bf-checkbox">
					<input type="checkbox" name="agree_privacy" required />
					<span class="obf-check-box"></span>
					<span>I have read and agree to the <strong><a href="#" data-modal="text" data-page-slug="privacy-policy" style="color: inherit; text-decoration: underline;">Obsidian Reserve Privacy Policy</a></strong> and consent to the processing of my personal data accordingly.</span>
				</label>
			</div>

			<!-- UX status hint — explains *why* the Submit button is disabled. -->
			<div class="obsidian-bf-status" id="obf-delivery-status" hidden>
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
				<span class="obf-status-text" id="obf-delivery-status-text"></span>
			</div>

			<!-- Delivery step actions -->
			<div class="obsidian-bf-actions obf-delivery-actions">
				<button type="button" class="obsidian-bf-back" id="obf-back">Back</button>
				<button type="submit" class="obsidian-bf-submit" id="obf-submit" disabled>
					<span class="obf-submit-text">Submit for Review</span>
					<span class="obf-submit-spinner" style="display:none;"></span>
				</button>
			</div>

		</div><!-- #obf-step-delivery -->

		<div class="obsidian-bf-message" id="obf-message" style="display:none;"></div>

	</form>
</div>
