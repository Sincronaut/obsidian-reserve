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

if (!defined('ABSPATH')) {
	exit;
}

/* ── Require login ── */
if (!is_user_logged_in()) {
	printf(
		'<section class="obsidian-profile-section"><div class="obsidian-profile-wrap"><p class="obsidian-profile-login">Please <a href="%s">log in</a> to view your profile.</p></div></section>',
		esc_url(wp_login_url(get_permalink()))
	);
	return;
}

$ob_current_user = wp_get_current_user();
$user_id = $ob_current_user->ID;

/* ── User meta ── */
$display_name = $ob_current_user->display_name ? $ob_current_user->display_name : $ob_current_user->user_login;
$username = $ob_current_user->user_login;
$first_name = $ob_current_user->first_name;
$last_name = $ob_current_user->last_name;
$full_name = trim($first_name . ' ' . $last_name) ? trim($first_name . ' ' . $last_name) : $display_name;
$email = $ob_current_user->user_email;
$phone = get_user_meta($user_id, '_obsidian_phone', true);
$license = get_user_meta($user_id, '_obsidian_license', true);
$avatar_url = get_avatar_url($user_id, array('size' => 200));
$custom_avatar = get_user_meta($user_id, '_obsidian_avatar', true);
if ($custom_avatar) {
	$avatar_url = $custom_avatar;
}

/* ── User tier (placeholder — can be extended later) ── */
$tier = 'Platinum';

/* ── Most recent completed booking (for Booking History) ── */
$recent_booking = null;
$recent_bookings = get_posts(
	array(
		'post_type' => 'booking',
		'posts_per_page' => 1,
		'meta_query' => array(
			array(
				'key' => '_booking_user_id',
				'value' => $user_id,
			),
		),
		'orderby' => 'date',
		'order' => 'DESC',
	)
);

if (!empty($recent_bookings)) {
	$rb = $recent_bookings[0];
	$recent_booking = array(
		'id' => $rb->ID,
		'date' => get_the_date('F j, Y', $rb),
		'car_name' => get_the_title((int) get_post_meta($rb->ID, '_booking_car_id', true)),
		'total' => (float) get_post_meta($rb->ID, '_booking_total_price', true),
		'status' => get_post_meta($rb->ID, '_booking_status', true),
	);
}

/* ── ALL bookings for the Transaction History modal (exclude awaiting_payment) ── */
$all_bookings_data = array();
$all_bookings_query = get_posts(
	array(
		'post_type' => 'booking',
		'posts_per_page' => -1,
		'meta_query' => array(
			'relation' => 'AND',
			array(
				'key' => '_booking_user_id',
				'value' => $user_id,
			),
		),
		'orderby' => 'date',
		'order' => 'DESC',
	)
);

foreach ($all_bookings_query as $bk) {
	$bk_car_id = (int) get_post_meta($bk->ID, '_booking_car_id', true);
	$bk_color = get_post_meta($bk->ID, '_booking_color', true);
	$bk_status = get_post_meta($bk->ID, '_booking_status', true);

	// Get car image.
	$bk_image = '';
	if ($bk_color && function_exists('obsidian_get_color_variants')) {
		$bk_variants = obsidian_get_color_variants($bk_car_id);
		$bk_c_lower = strtolower($bk_color);
		if (isset($bk_variants[$bk_c_lower]['images'][0])) {
			$bk_image = wp_get_attachment_image_url((int) $bk_variants[$bk_c_lower]['images'][0], 'medium');
		}
	}
	if (!$bk_image) {
		$bk_image = get_the_post_thumbnail_url($bk_car_id, 'medium') ? get_the_post_thumbnail_url($bk_car_id, 'medium') : '';
	}

	$status_labels_all = array(
		'confirmed' => 'Confirmed',
		'paid' => 'Paid',
		'awaiting_payment' => 'Awaiting Payment',
		'pending_review' => 'Pending Review',
		'completed' => 'Completed',
		'cancelled' => 'Cancelled',
		'denied' => 'Denied',
	);

	$all_bookings_data[] = array(
		'id' => $bk->ID,
		'date' => get_the_date('F j, Y', $bk),
		'car_name' => get_the_title($bk_car_id),
		'color' => ucfirst($bk_color),
		'car_image' => $bk_image,
		'status' => $bk_status,
		'status_label' => $status_labels_all[$bk_status] ?? ucfirst($bk_status),
	);
}

/* ── Upcoming reservations (confirmed / paid / awaiting_payment) ── */
$upcoming_reservations = array();
$upcoming_query = get_posts(
	array(
		'post_type' => 'booking',
		'posts_per_page' => -1,
		'meta_query' => array(
			'relation' => 'AND',
			array(
				'key' => '_booking_user_id',
				'value' => $user_id,
			),
			array(
				'key' => '_booking_status',
				'value' => array('confirmed', 'paid', 'awaiting_payment', 'pending_review'),
				'compare' => 'IN',
			),
		),
		'orderby' => 'date',
		'order' => 'DESC',
	)
);

foreach ($upcoming_query as $ub) {
	$car_id = (int) get_post_meta($ub->ID, '_booking_car_id', true);
	$color = get_post_meta($ub->ID, '_booking_color', true);
	$start_date = get_post_meta($ub->ID, '_booking_start_date', true);
	$end_date = get_post_meta($ub->ID, '_booking_end_date', true);
	$booking_status = get_post_meta($ub->ID, '_booking_status', true);
	$location = get_post_meta($ub->ID, '_booking_location', true);
	$delivery_address = get_post_meta($ub->ID, '_booking_delivery_address', true);

	// Get car image.
	$car_image = '';
	if ($color && function_exists('obsidian_get_color_variants')) {
		$variants = obsidian_get_color_variants($car_id);
		$c_lower = strtolower($color);
		if (isset($variants[$c_lower]['images'][0])) {
			$car_image = wp_get_attachment_image_url((int) $variants[$c_lower]['images'][0], 'medium');
		}
	}
	if (!$car_image) {
		$car_image = get_the_post_thumbnail_url($car_id, 'medium') ? get_the_post_thumbnail_url($car_id, 'medium') : '';
	}

	// Format dates.
	$start_display = '';
	$end_display = '';
	if ($start_date) {
		$dt = DateTime::createFromFormat('Y-m-d', $start_date);
		$start_display = $dt ? $dt->format('M j') : $start_date;
	}
	if ($end_date) {
		$dt = DateTime::createFromFormat('Y-m-d', $end_date);
		$end_display = $dt ? $dt->format('M j') : $end_date;
	}

	// Location / delivery address.
	$location_name = '';
	if ($delivery_address) {
		$location_name = $delivery_address;
	} elseif ($location) {
		if (is_numeric($location)) {
			$loc_post = get_post((int) $location);
			if ($loc_post) {
				$location_name = $loc_post->post_title;
			}
		} else {
			$location_name = $location;
		}
	}

	// Payment URL for awaiting_payment bookings.
	$payment_url = '';
	if ('awaiting_payment' === $booking_status) {
		$payment_token = get_post_meta($ub->ID, '_booking_payment_token', true);
		if ($payment_token) {
			$payment_url = home_url('/booking/payment/?booking_id=' . $ub->ID . '&token=' . $payment_token);
		}
	}

	// Status label.
	$status_labels = array(
		'confirmed' => 'RESERVED',
		'paid' => 'PAID',
		'awaiting_payment' => 'AWAITING PAYMENT',
		'pending_review' => 'PENDING REVIEW',
	);

	$upcoming_reservations[] = array(
		'id' => $ub->ID,
		'car_name' => get_the_title($car_id),
		'color' => ucfirst($color),
		'car_image' => $car_image,
		'start_date' => $start_display,
		'end_date' => $end_display,
		'status' => $booking_status,
		'status_label' => $status_labels[$booking_status] ?? strtoupper($booking_status),
		'location_name' => $location_name,
		'payment_url' => $payment_url,
	);
}

/* ── Recently viewed article (from user's reading history tracked by blog-engine.php) ── */
$recent_article = null;
$reading_history = get_user_meta($user_id, '_obsidian_reading_history', true);

if (is_array($reading_history) && !empty($reading_history)) {
	// The first item in the array is the most recently viewed post.
	$most_recent_id = (int) $reading_history[0];
	$rp = get_post($most_recent_id);

	if ($rp && 'publish' === $rp->post_status && 'post' === $rp->post_type) {
		$recent_article = array(
			'title' => get_the_title($rp),
			'excerpt' => wp_trim_words(get_the_excerpt($rp), 30, '…'),
			'image' => get_the_post_thumbnail_url($rp, 'medium_large'),
			'permalink' => get_permalink($rp),
		);
	}
}

$logout_url = wp_logout_url(home_url('/'));
?>

<section class="obsidian-profile-section">
	<div <?php echo get_block_wrapper_attributes(array('class' => 'obsidian-profile-wrap')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

		<!-- ═══════════════════════════════════════════════
		PROFILE HEADER
		═══════════════════════════════════════════════ -->
		<div class="opd-header">
			<div class="opd-header-left">
				<div class="opd-avatar" id="opd-avatar-trigger" title="Change profile picture">
					<img src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr($full_name); ?>"
						id="opd-avatar-img" />
					<div class="opd-avatar-overlay">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
							stroke-width="1.5">
							<path
								d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" />
							<circle cx="12" cy="13" r="4" />
						</svg>
					</div>
					<div class="opd-avatar-loading" id="opd-avatar-loading" style="display:none;">
						<span class="opd-avatar-spinner"></span>
						<span class="opd-avatar-loading-text">Saving...</span>
					</div>
					<input type="file" id="opd-avatar-input" accept="image/jpeg,image/png,image/webp"
						style="display:none;" />
				</div>
				<div class="opd-user-info">
					<h1 class="opd-user-name"><?php echo esc_html($full_name); ?></h1>
					<div class="opd-user-top">
						<span class="opd-tier-badge"><?php echo esc_html($tier); ?></span>
						<button class="opd-notification-btn" aria-label="Notifications">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
								stroke-width="1.5">
								<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
								<path d="M13.73 21a2 2 0 0 1-3.46 0" />
							</svg>
						</button>
					</div>
					<div class="opd-user-meta">
						<span>Username : <strong class="text-gold">@<?php echo esc_html($username); ?></strong></span>
					</div>
					<div class="opd-user-meta">
						<span>Email : <a href="mailto:<?php echo esc_attr($email); ?>"
								class="text-gold"><?php echo esc_html($email); ?></a></span>
						<?php if ($phone): ?>
							<span>Mobile Number : <span class="text-gold"><?php echo esc_html($phone); ?></span></span>
						<?php endif; ?>
					</div>
					<?php if ($license): ?>
						<div class="opd-user-meta">
							<span>Driver License Status : <a href="#"
									class="text-gold"><?php echo esc_html($license); ?></a></span>
						</div>
					<?php endif; ?>
				</div>
			</div>
			<div class="opd-header-actions wp-block-buttons">
				<div class="wp-block-button is-style-solid-gold">
					<button type="button" class="wp-block-button__link wp-element-button"
						id="opd-edit-profile-trigger">Edit Profile</button>
				</div>
				<div class="wp-block-button is-style-outline-gold">
					<a href="<?php echo esc_url($logout_url); ?>" class="wp-block-button__link wp-element-button">Log
						Out</a>
				</div>
			</div>
		</div>

		<!-- ═══════════════════════════════════════════════
		CONTENT GRID (2-column)
		═══════════════════════════════════════════════ -->
		<div class="opd-grid">

			<!-- LEFT COLUMN -->
			<div class="opd-col-left">

				<div class="opd-card opd-booking-history">
					<h2 class="opd-card-title" style="display:flex; align-items:center; gap:8px;">
						<div><span class="text-white">Booking</span> <span class="text-gold">History</span></div>
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="2">
							<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
							<polyline points="14 2 14 8 20 8" />
							<line x1="16" y1="13" x2="8" y2="13" />
							<line x1="16" y1="17" x2="8" y2="17" />
							<polyline points="10 9 9 9 8 9" />
						</svg>
					</h2>

					<?php if ($recent_booking): ?>
						<div class="opd-history-row">
							<span class="opd-history-label">Recent</span>
							<div class="opd-history-detail">
								<span class="opd-history-date"><?php echo esc_html($recent_booking['date']); ?></span>
								<span class="opd-history-txn">(Transac No:
									<?php echo esc_html($recent_booking['id']); ?>)</span>
							</div>
						</div>
					<?php else: ?>
						<p class="opd-empty">No booking history yet.</p>
					<?php endif; ?>

					<a href="#" class="opd-history-link" id="opd-history-trigger">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
							stroke-width="2">
							<rect x="3" y="3" width="18" height="18" rx="2" />
							<line x1="3" y1="9" x2="21" y2="9" />
							<line x1="9" y1="21" x2="9" y2="9" />
						</svg>
						View all Transaction History
					</a>
				</div>

				<!-- Upcoming Reservations -->
				<div class="opd-card opd-upcoming">
					<h2 class="opd-card-title"><span class="text-white">Upcoming</span> <span
							class="text-gold">Reservations</span></h2>

					<?php if (!empty($upcoming_reservations)): ?>
						<?php $first_res = $upcoming_reservations[0]; ?>
						<div class="opd-reservation-card">
							<span class="opd-reservation-status"><?php echo esc_html($first_res['status_label']); ?></span>

							<?php if ($first_res['car_image']): ?>
								<div class="opd-reservation-img">
									<img src="<?php echo esc_url($first_res['car_image']); ?>"
										alt="<?php echo esc_attr($first_res['car_name']); ?>" />
								</div>
							<?php endif; ?>

							<h3 class="opd-reservation-name"><?php echo esc_html($first_res['car_name']); ?> <span
									class="text-gold"><?php echo esc_html($first_res['color']); ?></span></h3>

							<div class="opd-reservation-meta">
								<span>Dates:
									<?php echo esc_html($first_res['start_date'] . ' - ' . $first_res['end_date']); ?></span>
								<?php if ($first_res['location_name']): ?>
									<span>Delivery Location: <?php echo esc_html($first_res['location_name']); ?></span>
								<?php endif; ?>
							</div>

							<div class="opd-reservation-actions">
								<?php if ('awaiting_payment' === $first_res['status'] && !empty($first_res['payment_url'])): ?>
									<a href="<?php echo esc_url($first_res['payment_url']); ?>"
										class="opd-btn opd-btn-payment">Proceed to Payment</a>
								<?php endif; ?>

								<a href="#" class="opd-btn opd-btn-outline opd-btn-agreement">View Rental Agreement</a>
							</div>
						</div>

						<?php if (count($upcoming_reservations) > 1): ?>
							<a href="#" class="opd-history-link" id="opd-upcoming-trigger"
								style="display: block; text-align: center; margin-top: 16px;">
								View all Upcoming Reservations
							</a>
						<?php endif; ?>
					<?php else: ?>
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
						<?php if ($recent_article): ?>
							<a href="<?php echo esc_url(home_url('/blog/')); ?>" class="opd-article-readmore">Read
								More</a>
						<?php endif; ?>
					</div>

					<?php if ($recent_article): ?>
						<a href="<?php echo esc_url($recent_article['permalink']); ?>" class="opd-article-link">
							<?php if ($recent_article['image']): ?>
								<div class="opd-article-img">
									<img src="<?php echo esc_url($recent_article['image']); ?>"
										alt="<?php echo esc_attr($recent_article['title']); ?>" />
								</div>
							<?php endif; ?>
							<h3 class="opd-article-title"><?php echo esc_html($recent_article['title']); ?></h3>
							<p class="opd-article-excerpt"><?php echo esc_html($recent_article['excerpt']); ?></p>
						</a>
					<?php else: ?>
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
						<input type="text" id="opd-full-name" name="full_name"
							value="<?php echo esc_attr($full_name); ?>" placeholder="ex: Juan Miguel Dela Cruz" />
					</div>

					<div class="opd-edit-field">
						<label for="opd-email">Email Address</label>
						<input type="email" id="opd-email" name="email" value="<?php echo esc_attr($email); ?>" />
					</div>

					<div class="opd-edit-field">
						<label for="opd-phone">Mobile Number</label>
						<input type="tel" id="opd-phone" name="phone" value="<?php echo esc_attr($phone); ?>"
							placeholder="+63" />
					</div>

					<div class="opd-edit-field">
						<label for="opd-license">Driver's License Number</label>
						<input type="text" id="opd-license" name="license" value="<?php echo esc_attr($license); ?>"
							placeholder="N04-000-000-000" />
					</div>

					<div class="opd-edit-field">
						<label for="opd-password">New Password <span class="opd-field-hint">(leave blank to keep
								current)</span></label>
						<input type="password" id="opd-password" name="password" placeholder="••••••••"
							autocomplete="new-password" />
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
			<div class="opd-modal-panel opd-crop-panel" role="dialog" aria-modal="true"
				aria-label="Crop Profile Picture">
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

		<!-- ═══════════════════════════════════════════════
		TRANSACTION HISTORY MODAL
		═══════════════════════════════════════════════ -->
		<div class="opd-modal" id="opd-history-modal" aria-hidden="true">
			<div class="opd-modal-overlay"></div>
			<div class="opd-modal-panel opd-history-panel" role="dialog" aria-modal="true"
				aria-label="Transaction History">
				<button class="opd-modal-close" id="opd-history-close" aria-label="Close">&times;</button>
				<h2 class="opd-card-title" style="display:flex; align-items:center; gap:8px; margin-bottom:28px;">
					<div><span class="text-white">Booking</span> <span class="text-gold">History</span></div>
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="2">
						<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
						<polyline points="14 2 14 8 20 8" />
						<line x1="16" y1="13" x2="8" y2="13" />
						<line x1="16" y1="17" x2="8" y2="17" />
						<polyline points="10 9 9 9 8 9" />
					</svg>
				</h2>

				<?php if (!empty($all_bookings_data)): ?>
					<div class="opd-txn-list">
						<?php foreach ($all_bookings_data as $txn): ?>
							<div class="opd-txn-card">
								<div class="opd-txn-info">
									<h3 class="opd-txn-car"><?php echo esc_html($txn['car_name']); ?> <span
											class="text-gold"><?php echo esc_html($txn['color']); ?></span></h3>
									<div class="opd-txn-meta">
										<span class="opd-txn-date"><?php echo esc_html($txn['date']); ?></span>
										<span class="opd-txn-id">(Transac No: <?php echo esc_html($txn['id']); ?>)</span>
									</div>
									<span
										class="opd-txn-status opd-txn-status--<?php echo esc_attr($txn['status']); ?>"><?php echo esc_html($txn['status_label']); ?></span>
								</div>
								<?php if ($txn['car_image']): ?>
									<div class="opd-txn-img-wrap">
										<img src="<?php echo esc_url($txn['car_image']); ?>"
											alt="<?php echo esc_attr($txn['car_name']); ?>" />
										<span
											class="opd-txn-img-label"><?php echo esc_html($txn['car_name'] . ' ' . $txn['color']); ?></span>
									</div>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php else: ?>
					<p class="opd-empty">No transaction history yet.</p>
				<?php endif; ?>
			</div>
		</div>

		<!-- ═══════════════════════════════════════════════
		UPCOMING RESERVATIONS MODAL
		═══════════════════════════════════════════════ -->
		<div class="opd-modal" id="opd-upcoming-modal" aria-hidden="true">
			<div class="opd-modal-overlay"></div>
			<div class="opd-modal-panel opd-history-panel" role="dialog" aria-modal="true"
				aria-label="Upcoming Reservations">
				<button class="opd-modal-close" id="opd-upcoming-close" aria-label="Close modal">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
						stroke-width="1.5">
						<line x1="18" y1="6" x2="6" y2="18" />
						<line x1="6" y1="6" x2="18" y2="18" />
					</svg>
				</button>
				<h2 class="opd-card-title" style="margin-bottom:28px;"><span class="text-white">Upcoming</span> <span
						class="text-gold">Reservations</span></h2>

				<?php if (!empty($upcoming_reservations)): ?>
					<div class="opd-txn-list">
						<?php foreach ($upcoming_reservations as $txn): ?>
							<div class="opd-txn-card">
								<div class="opd-txn-info">
									<h3 class="opd-txn-car"><?php echo esc_html($txn['car_name']); ?> <span
											class="text-gold"><?php echo esc_html($txn['color']); ?></span></h3>
									<div class="opd-txn-meta">
										<span
											class="opd-txn-date"><?php echo esc_html($txn['start_date'] . ' - ' . $txn['end_date']); ?></span>
										<span class="opd-txn-id">Location:
											<?php echo esc_html($txn['location_name']); ?></span>
									</div>
									<span
										class="opd-txn-status opd-txn-status--<?php echo esc_attr($txn['status']); ?>"><?php echo esc_html($txn['status_label']); ?></span>
									<?php if ('awaiting_payment' === $txn['status'] && !empty($txn['payment_url'])): ?>
										<a href="<?php echo esc_url($txn['payment_url']); ?>" class="opd-btn opd-btn-small"
											style="margin-top:8px;">Proceed to Payment</a>
									<?php endif; ?>
								</div>
								<?php if ($txn['car_image']): ?>
									<div class="opd-txn-img-wrap">
										<img src="<?php echo esc_url($txn['car_image']); ?>"
											alt="<?php echo esc_attr($txn['car_name']); ?>" />
										<span
											class="opd-txn-img-label"><?php echo esc_html($txn['car_name'] . ' ' . $txn['color']); ?></span>
									</div>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>

<script>
	(function () {
		'use strict';

		const modal = document.getElementById('opd-edit-modal');
		const trigger = document.getElementById('opd-edit-profile-trigger');
		const closeBtn = modal.querySelector('.opd-modal-close');
		const overlay = modal.querySelector('.opd-modal-overlay');
		const cancelBtn = document.getElementById('opd-edit-cancel');
		const form = document.getElementById('opd-edit-form');
		const msgEl = document.getElementById('opd-edit-message');
		const saveBtn = document.getElementById('opd-edit-save');
		const saveText = saveBtn.querySelector('.opd-save-text');
		const saveSpinner = saveBtn.querySelector('.opd-save-spinner');

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
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && modal.getAttribute('aria-hidden') === 'false') closeModal();
			if (e.key === 'Escape' && historyModal.getAttribute('aria-hidden') === 'false') closeHistoryModal();
			if (e.key === 'Escape' && upcomingModal.getAttribute('aria-hidden') === 'false') closeUpcomingModal();
		});

		// ── Transaction History modal ──
		const historyModal = document.getElementById('opd-history-modal');
		const historyTrigger = document.getElementById('opd-history-trigger');
		const historyClose = document.getElementById('opd-history-close');
		const historyOverlay = historyModal.querySelector('.opd-modal-overlay');

		function openHistoryModal() {
			historyModal.setAttribute('aria-hidden', 'false');
			document.body.classList.add('opd-modal-open');
			document.documentElement.classList.add('opd-modal-open');
		}

		function closeHistoryModal() {
			historyModal.setAttribute('aria-hidden', 'true');
			document.body.classList.remove('opd-modal-open');
			document.documentElement.classList.remove('opd-modal-open');
		}

		historyTrigger.addEventListener('click', function (e) { e.preventDefault(); openHistoryModal(); });
		historyClose.addEventListener('click', closeHistoryModal);
		historyOverlay.addEventListener('click', closeHistoryModal);

		// ── Upcoming Reservations modal ──
		const upcomingModal = document.getElementById('opd-upcoming-modal');
		const upcomingTrigger = document.getElementById('opd-upcoming-trigger');
		const upcomingClose = document.getElementById('opd-upcoming-close');
		const upcomingOverlay = upcomingModal.querySelector('.opd-modal-overlay');

		function openUpcomingModal() {
			upcomingModal.setAttribute('aria-hidden', 'false');
			document.body.classList.add('opd-modal-open');
			document.documentElement.classList.add('opd-modal-open');
		}

		function closeUpcomingModal() {
			upcomingModal.setAttribute('aria-hidden', 'true');
			document.body.classList.remove('opd-modal-open');
			document.documentElement.classList.remove('opd-modal-open');
		}

		if (upcomingTrigger) {
			upcomingTrigger.addEventListener('click', function (e) { e.preventDefault(); openUpcomingModal(); });
		}
		if (upcomingClose) upcomingClose.addEventListener('click', closeUpcomingModal);
		if (upcomingOverlay) upcomingOverlay.addEventListener('click', closeUpcomingModal);

		// ── Avatar upload with cropper ──
		const avatarTrigger = document.getElementById('opd-avatar-trigger');
		const avatarInput = document.getElementById('opd-avatar-input');
		const avatarImg = document.getElementById('opd-avatar-img');
		const avatarLoading = document.getElementById('opd-avatar-loading');
		const restNonce = '<?php echo wp_create_nonce('wp_rest'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>';
		const restBase = '<?php echo esc_url_raw(rest_url()); ?>';
		let pageLeaving = false;
		let isUploading = false;

		// Crop modal elements
		const cropModal = document.getElementById('opd-crop-modal');
		const cropImage = document.getElementById('opd-crop-image');
		const cropApply = document.getElementById('opd-crop-apply');
		const cropCancel = document.getElementById('opd-crop-cancel');
		const cropClose = document.getElementById('opd-crop-close');
		const cropOverlay = cropModal.querySelector('.opd-modal-overlay');
		let cropper = null;

		window.addEventListener('beforeunload', function (e) {
			pageLeaving = true;
			if (isUploading) {
				e.preventDefault();
				e.returnValue = 'Your profile picture is still saving. Are you sure you want to leave?';
			}
		});

		avatarTrigger.addEventListener('click', function () {
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
		avatarInput.addEventListener('change', function () {
			const file = this.files[0];
			if (!file) return;

			if (file.size > 5 * 1024 * 1024) {
				alert('Image must be under 5MB.');
				return;
			}

			// Downscale the image before feeding it to the cropper (max 1200px)
			const reader = new FileReader();
			reader.onload = function (e) {
				const img = new Image();
				img.onload = function () {
					const MAX = 1200;
					let w = img.width;
					let h = img.height;

					if (w > MAX || h > MAX) {
						if (w > h) { h = Math.round(h * MAX / w); w = MAX; }
						else { w = Math.round(w * MAX / h); h = MAX; }
					}

					const c = document.createElement('canvas');
					c.width = w; c.height = h;
					c.getContext('2d').drawImage(img, 0, 0, w, h);

					cropImage.src = c.toDataURL('image/jpeg', 0.92);
					openCropModal();

					cropImage.onload = function () {
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
		cropApply.addEventListener('click', function () {
			if (!cropper) return;

			const canvas = cropper.getCroppedCanvas({
				width: 400,
				height: 400,
				imageSmoothingQuality: 'high',
			});

			closeCropModal();

			// Show cropped preview + loading on avatar
			avatarImg.src = canvas.toDataURL();
			avatarLoading.style.display = '';
			isUploading = true;

			canvas.toBlob(function (blob) {
				const fd = new FormData();
				fd.append('file', blob, 'avatar.jpg');

				fetch(restBase + 'wp/v2/media', {
					method: 'POST',
					headers: { 'X-WP-Nonce': restNonce },
					body: fd
				})
					.then(function (r) {
						if (!r.ok) throw new Error('Upload failed');
						return r.json();
					})
					.then(function (media) {
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
					.then(function (r) {
						if (!r.ok) throw new Error('Save failed');
						avatarLoading.style.display = 'none';
						isUploading = false;
					})
					.catch(function (err) {
						avatarLoading.style.display = 'none';
						isUploading = false;
						if (pageLeaving) return;
						alert('Could not update avatar: ' + err.message);
					});
			}, 'image/jpeg', 0.92);
		});

		// ── Edit form submit ──
		form.addEventListener('submit', function (e) {
			e.preventDefault();

			const fullName = document.getElementById('opd-full-name').value.trim();
			const email = document.getElementById('opd-email').value.trim();
			const phone = document.getElementById('opd-phone').value.trim();
			const license = document.getElementById('opd-license').value.trim();
			const password = document.getElementById('opd-password').value;

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
			const nameParts = fullName.split(/\s+/);
			const firstName = nameParts[0];
			const lastName = nameParts.length > 1 ? nameParts.slice(1).join(' ') : '';

			// Show loading
			saveText.style.display = 'none';
			saveSpinner.style.display = 'inline-block';
			saveBtn.disabled = true;

			// Build payload for WP REST API
			const payload = {
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
				.then(function (r) {
					if (!r.ok) return r.json().then(function (err) { throw new Error(err.message || 'Update failed'); });
					return r.json();
				})
				.then(function () {
					showMsg('Profile updated successfully!', 'success');
					setTimeout(function () { window.location.reload(); }, 1200);
				})
				.catch(function (err) {
					showMsg(err.message || 'Something went wrong. Please try again.', 'error');
				})
				.finally(function () {
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