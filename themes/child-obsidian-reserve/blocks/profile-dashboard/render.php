<?php
/**
 * Profile Dashboard Block — render.php
 *
 * Dynamic profile page showing:
 * - User header (avatar, name, tier, contact info, actions)
 * - Booking history (most recent transaction)
 * - Upcoming reservations (active/confirmed bookings)
 * - Recently viewed article (latest published blog post as placeholder)
 *
 * @package child-obsidian-reserve
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ── Require login ── */
if ( ! is_user_logged_in() ) {
	printf(
		'<div class="obsidian-profile-wrap"><p class="obsidian-profile-login">Please <a href="%s">log in</a> to view your profile.</p></div>',
		esc_url( wp_login_url( get_permalink() ) )
	);
	return;
}

$current_user = wp_get_current_user();
$user_id      = $current_user->ID;

/* ── User meta ── */
$display_name = $current_user->display_name ?: $current_user->user_login;
$username     = $current_user->user_login;
$first_name   = $current_user->first_name;
$last_name    = $current_user->last_name;
$full_name    = trim( $first_name . ' ' . $last_name ) ?: $display_name;
$email        = $current_user->user_email;
$phone        = get_user_meta( $user_id, '_obsidian_phone', true );
$license      = get_user_meta( $user_id, '_obsidian_license', true );
$avatar_url   = get_avatar_url( $user_id, array( 'size' => 200 ) );
$custom_avatar = get_user_meta( $user_id, '_obsidian_avatar', true );
if ( $custom_avatar ) {
	$avatar_url = $custom_avatar;
}

/* ── User tier (placeholder — can be extended later) ── */
$tier = 'Platinum';

/* ── Most recent completed booking (for Booking History) ── */
$recent_booking = null;
$recent_bookings = get_posts( array(
	'post_type'      => 'booking',
	'posts_per_page' => 1,
	'meta_query'     => array(
		array(
			'key'   => '_booking_user_id',
			'value' => $user_id,
		),
	),
	'orderby'  => 'date',
	'order'    => 'DESC',
) );

if ( ! empty( $recent_bookings ) ) {
	$rb              = $recent_bookings[0];
	$recent_booking  = array(
		'id'         => $rb->ID,
		'date'       => get_the_date( 'F j, Y', $rb ),
		'car_name'   => get_the_title( (int) get_post_meta( $rb->ID, '_booking_car_id', true ) ),
		'total'      => (float) get_post_meta( $rb->ID, '_booking_total_price', true ),
		'status'     => get_post_meta( $rb->ID, '_booking_status', true ),
	);
}

/* ── Upcoming reservations (confirmed / paid / awaiting_payment) ── */
$upcoming_reservations = array();
$upcoming_query = get_posts( array(
	'post_type'      => 'booking',
	'posts_per_page' => 3,
	'meta_query'     => array(
		'relation' => 'AND',
		array(
			'key'   => '_booking_user_id',
			'value' => $user_id,
		),
		array(
			'key'     => '_booking_status',
			'value'   => array( 'confirmed', 'paid', 'awaiting_payment', 'pending_review' ),
			'compare' => 'IN',
		),
	),
	'orderby'  => 'date',
	'order'    => 'DESC',
) );

foreach ( $upcoming_query as $ub ) {
	$car_id     = (int) get_post_meta( $ub->ID, '_booking_car_id', true );
	$color      = get_post_meta( $ub->ID, '_booking_color', true );
	$start_date = get_post_meta( $ub->ID, '_booking_start_date', true );
	$end_date   = get_post_meta( $ub->ID, '_booking_end_date', true );
	$status     = get_post_meta( $ub->ID, '_booking_status', true );
	$location   = get_post_meta( $ub->ID, '_booking_location', true );

	// Get car image
	$car_image = '';
	if ( $color && function_exists( 'obsidian_get_color_variants' ) ) {
		$variants = obsidian_get_color_variants( $car_id );
		$c_lower  = strtolower( $color );
		if ( isset( $variants[ $c_lower ]['images'][0] ) ) {
			$car_image = wp_get_attachment_image_url( (int) $variants[ $c_lower ]['images'][0], 'medium' );
		}
	}
	if ( ! $car_image ) {
		$car_image = get_the_post_thumbnail_url( $car_id, 'medium' ) ?: '';
	}

	// Format dates
	$start_display = '';
	$end_display   = '';
	if ( $start_date ) {
		$dt = DateTime::createFromFormat( 'Y-m-d', $start_date );
		$start_display = $dt ? $dt->format( 'M j' ) : $start_date;
	}
	if ( $end_date ) {
		$dt = DateTime::createFromFormat( 'Y-m-d', $end_date );
		$end_display = $dt ? $dt->format( 'M j' ) : $end_date;
	}

	// Location name
	$location_name = '';
	if ( $location ) {
		$loc_post = get_post( (int) $location );
		if ( $loc_post ) {
			$location_name = $loc_post->post_title;
		}
	}

	// Status label
	$status_labels = array(
		'confirmed'        => 'RESERVED',
		'paid'             => 'PAID',
		'awaiting_payment' => 'AWAITING PAYMENT',
		'pending_review'   => 'PENDING REVIEW',
	);

	$upcoming_reservations[] = array(
		'id'            => $ub->ID,
		'car_name'      => get_the_title( $car_id ),
		'color'         => ucfirst( $color ),
		'car_image'     => $car_image,
		'start_date'    => $start_display,
		'end_date'      => $end_display,
		'status'        => $status,
		'status_label'  => $status_labels[ $status ] ?? strtoupper( $status ),
		'location_name' => $location_name,
	);
}

/* ── Recently viewed article (from user's reading history tracked by blog-engine.php) ── */
$recent_article = null;
$reading_history = get_user_meta( $user_id, '_obsidian_reading_history', true );

if ( is_array( $reading_history ) && ! empty( $reading_history ) ) {
	// The first item in the array is the most recently viewed post
	$most_recent_id = (int) $reading_history[0];
	$rp = get_post( $most_recent_id );

	if ( $rp && $rp->post_status === 'publish' && $rp->post_type === 'post' ) {
		$recent_article = array(
			'title'     => get_the_title( $rp ),
			'excerpt'   => wp_trim_words( get_the_excerpt( $rp ), 30, '…' ),
			'image'     => get_the_post_thumbnail_url( $rp, 'medium_large' ),
			'permalink' => get_permalink( $rp ),
		);
	}
}

$logout_url = wp_logout_url( home_url( '/' ) );
?>

<div <?php echo get_block_wrapper_attributes( array( 'class' => 'obsidian-profile-wrap' ) ); ?>>

	<!-- ═══════════════════════════════════════════════
	     PROFILE HEADER
	     ═══════════════════════════════════════════════ -->
	<div class="opd-header">
		<div class="opd-header-left">
			<div class="opd-avatar" id="opd-avatar-trigger" title="Change profile picture">
				<img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $full_name ); ?>" id="opd-avatar-img" />
				<div class="opd-avatar-overlay">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
				</div>
				<div class="opd-avatar-loading" id="opd-avatar-loading" style="display:none;">
					<span class="opd-avatar-spinner"></span>
					<span class="opd-avatar-loading-text">Saving...</span>
				</div>
				<input type="file" id="opd-avatar-input" accept="image/jpeg,image/png,image/webp" style="display:none;" />
			</div>
			<div class="opd-user-info">
				<div class="opd-user-top">
					<h1 class="opd-user-name"><?php echo esc_html( $full_name ); ?></h1>
					<span class="opd-tier-badge"><?php echo esc_html( $tier ); ?></span>
					<button class="opd-notification-btn" aria-label="Notifications">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
					</button>
				</div>
				<div class="opd-user-meta">
					<span>Username : <strong>@<?php echo esc_html( $username ); ?></strong></span>
				</div>
				<div class="opd-user-meta">
					<span>Email : <a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a></span>
					<?php if ( $phone ) : ?>
						<span>Mobile Number : <?php echo esc_html( $phone ); ?></span>
					<?php endif; ?>
				</div>
				<?php if ( $license ) : ?>
					<div class="opd-user-meta">
						<span>Driver License Status : <a href="#"><?php echo esc_html( $license ); ?></a></span>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<div class="opd-header-actions">
			<button type="button" class="opd-btn opd-btn-edit" id="opd-edit-profile-trigger">Edit Profile</button>
			<a href="<?php echo esc_url( $logout_url ); ?>" class="opd-btn opd-btn-logout">Log Out</a>
		</div>
	</div>

	<!-- ═══════════════════════════════════════════════
	     CONTENT GRID (2-column)
	     ═══════════════════════════════════════════════ -->
	<div class="opd-grid">

		<!-- LEFT COLUMN -->
		<div class="opd-col-left">

			<!-- Booking History -->
			<div class="opd-card opd-booking-history">
				<h2 class="opd-card-title"><span class="text-white">Booking</span> <span class="text-gold">History</span> 📋</h2>

				<?php if ( $recent_booking ) : ?>
					<div class="opd-history-row">
						<span class="opd-history-label">Recent</span>
						<div class="opd-history-detail">
							<span class="opd-history-date"><?php echo esc_html( $recent_booking['date'] ); ?></span>
							<span class="opd-history-txn">(Transac No: <?php echo esc_html( $recent_booking['id'] ); ?>)</span>
						</div>
					</div>
				<?php else : ?>
					<p class="opd-empty">No booking history yet.</p>
				<?php endif; ?>

				<a href="#" class="opd-history-link">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
					View all Transaction History
				</a>
			</div>

			<!-- Upcoming Reservations -->
			<div class="opd-card opd-upcoming">
				<h2 class="opd-card-title"><span class="text-white">Upcoming</span> <span class="text-gold">Reservations</span></h2>

				<?php if ( ! empty( $upcoming_reservations ) ) : ?>
					<?php foreach ( $upcoming_reservations as $res ) : ?>
						<div class="opd-reservation-card">
							<span class="opd-reservation-status"><?php echo esc_html( $res['status_label'] ); ?></span>

							<?php if ( $res['car_image'] ) : ?>
								<div class="opd-reservation-img">
									<img src="<?php echo esc_url( $res['car_image'] ); ?>" alt="<?php echo esc_attr( $res['car_name'] ); ?>" />
								</div>
							<?php endif; ?>

							<h3 class="opd-reservation-name"><?php echo esc_html( $res['car_name'] ); ?> <span class="text-gold"><?php echo esc_html( $res['color'] ); ?></span></h3>

							<div class="opd-reservation-meta">
								<span>Dates: <?php echo esc_html( $res['start_date'] . ' - ' . $res['end_date'] ); ?></span>
								<?php if ( $res['location_name'] ) : ?>
									<span>Delivery Location: <?php echo esc_html( $res['location_name'] ); ?></span>
								<?php endif; ?>
							</div>

							<a href="#" class="opd-btn opd-btn-agreement">View Rental Agreement</a>
						</div>
					<?php endforeach; ?>
				<?php else : ?>
					<p class="opd-empty">No upcoming reservations.</p>
				<?php endif; ?>
			</div>

		</div>

		<!-- RIGHT COLUMN -->
		<div class="opd-col-right">

			<!-- Recently Viewed Article -->
			<div class="opd-card opd-recent-article">
				<div class="opd-article-header">
					<h2 class="opd-card-title-plain">Recently Viewed Article</h2>
					<?php if ( $recent_article ) : ?>
						<a href="<?php echo esc_url( home_url( '/journal/' ) ); ?>" class="opd-article-readmore">Read More</a>
					<?php endif; ?>
				</div>

				<?php if ( $recent_article ) : ?>
					<a href="<?php echo esc_url( $recent_article['permalink'] ); ?>" class="opd-article-link">
						<?php if ( $recent_article['image'] ) : ?>
							<div class="opd-article-img">
								<img src="<?php echo esc_url( $recent_article['image'] ); ?>" alt="<?php echo esc_attr( $recent_article['title'] ); ?>" />
							</div>
						<?php endif; ?>
						<h3 class="opd-article-title"><?php echo esc_html( $recent_article['title'] ); ?></h3>
						<p class="opd-article-excerpt"><?php echo esc_html( $recent_article['excerpt'] ); ?></p>
					</a>
				<?php else : ?>
					<p class="opd-empty">No articles viewed yet.</p>
				<?php endif; ?>
			</div>

		</div>

	</div><!-- .opd-grid -->

	<!-- ═══════════════════════════════════════════════
	     EDIT PROFILE MODAL
	     ═══════════════════════════════════════════════ -->
	<div class="opd-modal" id="opd-edit-modal" aria-hidden="true">
		<div class="opd-modal-overlay"></div>
		<div class="opd-modal-panel" role="dialog" aria-modal="true" aria-label="Edit Profile">
			<button class="opd-modal-close" aria-label="Close">&times;</button>

			<h2 class="opd-modal-title">Edit Profile</h2>

			<form id="opd-edit-form" class="opd-edit-form" novalidate>

				<div class="opd-edit-field">
					<label for="opd-full-name">Full Name</label>
					<input type="text" id="opd-full-name" name="full_name" value="<?php echo esc_attr( $full_name ); ?>" placeholder="ex: Juan Miguel Dela Cruz" />
				</div>

				<div class="opd-edit-field">
					<label for="opd-email">Email Address</label>
					<input type="email" id="opd-email" name="email" value="<?php echo esc_attr( $email ); ?>" />
				</div>

				<div class="opd-edit-field">
					<label for="opd-phone">Mobile Number</label>
					<input type="tel" id="opd-phone" name="phone" value="<?php echo esc_attr( $phone ); ?>" placeholder="+63" />
				</div>

				<div class="opd-edit-field">
					<label for="opd-license">Driver's License Number</label>
					<input type="text" id="opd-license" name="license" value="<?php echo esc_attr( $license ); ?>" placeholder="N04-000-000-000" />
				</div>

				<div class="opd-edit-field">
					<label for="opd-password">New Password <span class="opd-field-hint">(leave blank to keep current)</span></label>
					<input type="password" id="opd-password" name="password" placeholder="••••••••" autocomplete="new-password" />
				</div>

				<div class="opd-edit-message" id="opd-edit-message" style="display:none;"></div>

				<div class="opd-edit-actions">
					<button type="button" class="opd-btn opd-btn-cancel" id="opd-edit-cancel">Cancel</button>
					<button type="submit" class="opd-btn opd-btn-save" id="opd-edit-save">
						<span class="opd-save-text">Save Changes</span>
						<span class="opd-save-spinner" style="display:none;"></span>
					</button>
				</div>

			</form>
		</div>
	</div>

	<!-- ═══════════════════════════════════════════════
	     AVATAR CROP MODAL
	     ═══════════════════════════════════════════════ -->
	<div class="opd-modal opd-crop-modal" id="opd-crop-modal" aria-hidden="true">
		<div class="opd-modal-overlay"></div>
		<div class="opd-modal-panel opd-crop-panel" role="dialog" aria-modal="true" aria-label="Crop Profile Picture">
			<button class="opd-modal-close" id="opd-crop-close" aria-label="Close">&times;</button>
			<h2 class="opd-modal-title">Position Your Photo</h2>
			<p class="opd-crop-hint">Drag to reposition, scroll or pinch to zoom.</p>
			<div class="opd-crop-container">
				<img id="opd-crop-image" src="" alt="Crop preview" />
			</div>
			<div class="opd-edit-actions">
				<button type="button" class="opd-btn opd-btn-cancel" id="opd-crop-cancel">Cancel</button>
				<button type="button" class="opd-btn opd-btn-save" id="opd-crop-apply">Apply</button>
			</div>
		</div>
	</div>

</div>

<script>
(function() {
	'use strict';

	var modal      = document.getElementById('opd-edit-modal');
	var trigger    = document.getElementById('opd-edit-profile-trigger');
	var closeBtn   = modal.querySelector('.opd-modal-close');
	var overlay    = modal.querySelector('.opd-modal-overlay');
	var cancelBtn  = document.getElementById('opd-edit-cancel');
	var form       = document.getElementById('opd-edit-form');
	var msgEl      = document.getElementById('opd-edit-message');
	var saveBtn    = document.getElementById('opd-edit-save');
	var saveText   = saveBtn.querySelector('.opd-save-text');
	var saveSpinner= saveBtn.querySelector('.opd-save-spinner');

	function openModal() {
		modal.setAttribute('aria-hidden', 'false');
		document.body.classList.add('opd-modal-open');
		document.documentElement.classList.add('opd-modal-open');
		msgEl.style.display = 'none';
	}

	function closeModal() {
		modal.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('opd-modal-open');
		document.documentElement.classList.remove('opd-modal-open');
	}

	trigger.addEventListener('click', openModal);
	closeBtn.addEventListener('click', closeModal);
	overlay.addEventListener('click', closeModal);
	cancelBtn.addEventListener('click', closeModal);
	document.addEventListener('keydown', function(e) {
		if (e.key === 'Escape' && modal.getAttribute('aria-hidden') === 'false') closeModal();
	});

	// ── Avatar upload with cropper ──
	var avatarTrigger = document.getElementById('opd-avatar-trigger');
	var avatarInput   = document.getElementById('opd-avatar-input');
	var avatarImg     = document.getElementById('opd-avatar-img');
	var avatarLoading = document.getElementById('opd-avatar-loading');
	var restNonce     = '<?php echo wp_create_nonce( 'wp_rest' ); ?>';
	var restBase      = '<?php echo esc_url_raw( rest_url() ); ?>';
	var pageLeaving   = false;
	var isUploading   = false;

	// Crop modal elements
	var cropModal     = document.getElementById('opd-crop-modal');
	var cropImage     = document.getElementById('opd-crop-image');
	var cropApply     = document.getElementById('opd-crop-apply');
	var cropCancel    = document.getElementById('opd-crop-cancel');
	var cropClose     = document.getElementById('opd-crop-close');
	var cropOverlay   = cropModal.querySelector('.opd-modal-overlay');
	var cropper       = null;

	window.addEventListener('beforeunload', function(e) {
		pageLeaving = true;
		if (isUploading) {
			e.preventDefault();
			e.returnValue = 'Your profile picture is still saving. Are you sure you want to leave?';
		}
	});

	avatarTrigger.addEventListener('click', function() {
		if (!isUploading) avatarInput.click();
	});

	function openCropModal() {
		cropModal.setAttribute('aria-hidden', 'false');
		document.body.classList.add('opd-modal-open');
		document.documentElement.classList.add('opd-modal-open');
	}

	function closeCropModal() {
		cropModal.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('opd-modal-open');
		document.documentElement.classList.remove('opd-modal-open');
		if (cropper) { cropper.destroy(); cropper = null; }
		avatarInput.value = '';
	}

	cropCancel.addEventListener('click', closeCropModal);
	cropClose.addEventListener('click', closeCropModal);
	cropOverlay.addEventListener('click', closeCropModal);

	// Step 1: User picks a file → resize for performance → open crop modal
	avatarInput.addEventListener('change', function() {
		var file = this.files[0];
		if (!file) return;

		if (file.size > 5 * 1024 * 1024) {
			alert('Image must be under 5MB.');
			return;
		}

		// Downscale the image before feeding it to the cropper (max 1200px)
		var reader = new FileReader();
		reader.onload = function(e) {
			var img = new Image();
			img.onload = function() {
				var MAX = 1200;
				var w = img.width;
				var h = img.height;

				if (w > MAX || h > MAX) {
					if (w > h) { h = Math.round(h * MAX / w); w = MAX; }
					else       { w = Math.round(w * MAX / h); h = MAX; }
				}

				var c = document.createElement('canvas');
				c.width = w; c.height = h;
				c.getContext('2d').drawImage(img, 0, 0, w, h);

				cropImage.src = c.toDataURL('image/jpeg', 0.92);
				openCropModal();

				cropImage.onload = function() {
					if (cropper) cropper.destroy();
					cropper = new Cropper(cropImage, {
						aspectRatio: 1,
						viewMode: 1,
						dragMode: 'move',
						guides: false,
						center: false,
						highlight: false,
						cropBoxMovable: false,
						cropBoxResizable: false,
						background: false,
						minCropBoxWidth: 200,
						minCropBoxHeight: 200,
					});
				};
			};
			img.src = e.target.result;
		};
		reader.readAsDataURL(file);
	});

	// Step 2: User clicks Apply → crop, upload, save
	cropApply.addEventListener('click', function() {
		if (!cropper) return;

		var canvas = cropper.getCroppedCanvas({
			width: 400,
			height: 400,
			imageSmoothingQuality: 'high',
		});

		closeCropModal();

		// Show cropped preview + loading on avatar
		avatarImg.src = canvas.toDataURL();
		avatarLoading.style.display = '';
		isUploading = true;

		canvas.toBlob(function(blob) {
			var fd = new FormData();
			fd.append('file', blob, 'avatar.jpg');

			fetch(restBase + 'wp/v2/media', {
				method: 'POST',
				headers: { 'X-WP-Nonce': restNonce },
				body: fd
			})
			.then(function(r) {
				if (!r.ok) throw new Error('Upload failed');
				return r.json();
			})
			.then(function(media) {
				return fetch(restBase + 'wp/v2/users/me', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': restNonce
					},
					body: JSON.stringify({ meta: { _obsidian_avatar: media.source_url } }),
					keepalive: true
				});
			})
			.then(function(r) {
				if (!r.ok) throw new Error('Save failed');
				avatarLoading.style.display = 'none';
				isUploading = false;
			})
			.catch(function(err) {
				avatarLoading.style.display = 'none';
				isUploading = false;
				if (pageLeaving) return;
				alert('Could not update avatar: ' + err.message);
			});
		}, 'image/jpeg', 0.92);
	});

	// ── Edit form submit ──
	form.addEventListener('submit', function(e) {
		e.preventDefault();

		var fullName  = document.getElementById('opd-full-name').value.trim();
		var email     = document.getElementById('opd-email').value.trim();
		var phone     = document.getElementById('opd-phone').value.trim();
		var license   = document.getElementById('opd-license').value.trim();
		var password  = document.getElementById('opd-password').value;

		// Basic validation
		if (!fullName) {
			showMsg('Please enter your full name.', 'error');
			return;
		}
		if (!email || email.indexOf('@') === -1) {
			showMsg('Please enter a valid email address.', 'error');
			return;
		}

		// Split full name into first/last
		var nameParts = fullName.split(/\s+/);
		var firstName = nameParts[0];
		var lastName  = nameParts.length > 1 ? nameParts.slice(1).join(' ') : '';

		// Show loading
		saveText.style.display = 'none';
		saveSpinner.style.display = 'inline-block';
		saveBtn.disabled = true;

		// Build payload for WP REST API
		var payload = {
			first_name: firstName,
			last_name: lastName,
			name: fullName,
			email: email,
			meta: {
				_obsidian_phone: phone,
				_obsidian_license: license
			}
		};
		if (password) {
			payload.password = password;
		}

		fetch(restBase + 'wp/v2/users/me', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': restNonce
			},
			body: JSON.stringify(payload)
		})
		.then(function(r) {
			if (!r.ok) return r.json().then(function(err) { throw new Error(err.message || 'Update failed'); });
			return r.json();
		})
		.then(function() {
			showMsg('Profile updated successfully!', 'success');
			setTimeout(function() { window.location.reload(); }, 1200);
		})
		.catch(function(err) {
			showMsg(err.message || 'Something went wrong. Please try again.', 'error');
		})
		.finally(function() {
			saveText.style.display = '';
			saveSpinner.style.display = 'none';
			saveBtn.disabled = false;
		});
	});

	function showMsg(text, type) {
		msgEl.textContent = text;
		msgEl.className = 'opd-edit-message opd-msg-' + type;
		msgEl.style.display = '';
	}
})();
</script>
