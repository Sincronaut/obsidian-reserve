<?php
/**
 * Booking Admin Meta Box
 *
 * Shows booking details, uploaded documents, approve/deny actions,
 * and admin notes when editing a booking in wp-admin.
 *
 * @package obsidian-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the meta box.
 */
function obsidian_register_booking_meta_box() {
	add_meta_box(
		'obsidian_booking_details',
		__( 'Booking Details', 'obsidian-booking' ),
		'obsidian_render_booking_meta_box',
		'booking',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'obsidian_register_booking_meta_box' );

/**
 * Render the meta box content.
 *
 * @param WP_Post $post Booking post object.
 * @return void
 */
function obsidian_render_booking_meta_box( $post ) {

	$booking_id    = $post->ID;
	$car_id        = (int) get_post_meta( $booking_id, '_booking_car_id', true );
	$status        = get_post_meta( $booking_id, '_booking_status', true );
	$customer_type = get_post_meta( $booking_id, '_booking_customer_type', true );
	$first_name    = get_post_meta( $booking_id, '_booking_first_name', true );
	$last_name     = get_post_meta( $booking_id, '_booking_last_name', true );
	$email         = get_post_meta( $booking_id, '_booking_email', true );
	$phone         = get_post_meta( $booking_id, '_booking_phone', true );
	$address       = get_post_meta( $booking_id, '_booking_address', true );
	$birth_date    = get_post_meta( $booking_id, '_booking_birth_date', true );
	$license_num   = get_post_meta( $booking_id, '_booking_license_number', true );
	$location      = get_post_meta( $booking_id, '_booking_location', true );
	$color         = get_post_meta( $booking_id, '_booking_color', true );
	$start_date    = get_post_meta( $booking_id, '_booking_start_date', true );
	$end_date      = get_post_meta( $booking_id, '_booking_end_date', true );
	$total         = (float) get_post_meta( $booking_id, '_booking_total_price', true );
	$documents     = get_post_meta( $booking_id, '_booking_documents', true );
	$admin_notes   = get_post_meta( $booking_id, '_booking_admin_notes', true );
	$denial_reason = get_post_meta( $booking_id, '_booking_denial_reason', true );

	// Gov ID types (local).
	$gov_id_type   = get_post_meta( $booking_id, '_booking_gov_id_type', true );
	$gov_id_type_2 = get_post_meta( $booking_id, '_booking_gov_id_type_2', true );

	$delivery_contact = get_post_meta( $booking_id, '_booking_delivery_contact', true );
	$delivery_dropoff = get_post_meta( $booking_id, '_booking_delivery_dropoff', true );
	$delivery_address = get_post_meta( $booking_id, '_booking_delivery_address', true );
	$delivery_date    = get_post_meta( $booking_id, '_booking_delivery_date', true );
	$delivery_time    = get_post_meta( $booking_id, '_booking_delivery_time', true );
	$return_address   = get_post_meta( $booking_id, '_booking_return_address', true );
	$return_date      = get_post_meta( $booking_id, '_booking_return_date', true );
	$return_time      = get_post_meta( $booking_id, '_booking_return_time', true );
	$special_requests = get_post_meta( $booking_id, '_booking_special_requests', true );

	// Passport (international).
	$passport_num = get_post_meta( $booking_id, '_booking_passport_number', true );

	$docs = ! empty( $documents ) ? json_decode( $documents, true ) : array();
	if ( ! is_array( $docs ) ) {
		$docs = array();
	}

	// Calculate days.
	$start_dt    = DateTime::createFromFormat( 'Y-m-d', $start_date );
	$end_dt      = DateTime::createFromFormat( 'Y-m-d', $end_date );
	$days        = ( $start_dt && $end_dt ) ? $start_dt->diff( $end_dt )->days : 0;
	$days        = absint( $days );
	$days_suffix = '';
	if ( 1 !== $days ) {
		$days_suffix = 's';
	}

	// Status labels.
	$status_labels = array(
		'pending_review'   => 'Pending Review',
		'awaiting_payment' => 'Awaiting Payment',
		'paid'             => 'Paid',
		'confirmed'        => 'Confirmed',
		'active'           => 'Active',
		'completed'        => 'Completed',
		'denied'           => 'Denied',
		'cancelled'        => 'Cancelled',
	);
	if ( isset( $status_labels[ $status ] ) ) {
		$status_label = $status_labels[ $status ];
	} else {
		$status_label = ucfirst( $status );
	}

	wp_nonce_field( 'obsidian_booking_actions', 'obsidian_booking_nonce' );
	?>

	<div class="obsidian-booking-detail">

		<!-- Status Banner -->
		<div class="obm-status-banner obm-status-<?php echo esc_attr( $status ); ?>">
			<span class="obm-status-dot"></span>
			<strong><?php echo esc_html( $status_label ); ?></strong>
			<?php if ( 'denied' === $status && $denial_reason ) : ?>
			<span class="obm-denial-reason">— <?php echo esc_html( $denial_reason ); ?></span>
		<?php endif; ?>
		<?php
		$cancellation_reason = get_post_meta( $booking_id, '_booking_cancellation_reason', true );
		if ( 'cancelled' === $status && $cancellation_reason ) :
			?>
			<span class="obm-denial-reason">— <?php echo esc_html( $cancellation_reason ); ?></span>
		<?php endif; ?>
		</div>

		<!-- Two-column grid: Summary + Customer -->
		<div class="obm-grid">

		<!-- Booking Summary -->
		<div class="obm-card">
			<h4 class="obm-section-title"><span class="dashicons dashicons-car"></span> Booking Summary</h4>
			<table class="obm-table">
				<tr>
					<th>Car</th>
					<td>
						<?php if ( $car_id && get_post( $car_id ) ) : ?>
							<a href="<?php echo esc_url( get_edit_post_link( $car_id ) ); ?>"><?php echo esc_html( get_the_title( $car_id ) ); ?></a>
						<?php else : ?>
							<span class="obm-muted">Unknown (ID: <?php echo esc_html( $car_id ); ?>)</span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th>Color</th>
					<td>
						<?php if ( $color ) : ?>
							<span class="obsidian-col-color">
								<span class="obsidian-col-swatch" style="background:<?php echo esc_attr( function_exists( 'obsidian_get_color_hex' ) ? obsidian_get_color_hex( $color ) : '#888' ); ?>;"></span>
								<?php echo esc_html( ucfirst( $color ) ); ?>
							</span>
						<?php else : ?>
							—
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th>Dates</th>
					<td>
						<?php
						echo esc_html(
							( $start_dt ? $start_dt->format( 'M j, Y' ) : $start_date ) .
							' – ' .
							( $end_dt ? $end_dt->format( 'M j, Y' ) : $end_date )
						);
						?>
						<span class="obm-muted"><?php echo esc_html( $days ); ?> day<?php echo esc_html( $days_suffix ); ?></span>
					</td>
				</tr>
				<tr>
					<th>Location</th>
					<td>
						<?php
						$location_label = ucwords( str_replace( '_', ' ', $location ) );
						if ( '' === $location_label ) {
							$location_label = '—';
						}
						echo esc_html( $location_label );
						?>
					</td>
				</tr>
				<tr>
					<th>Total</th>
					<td><strong>₱<?php echo esc_html( number_format( $total ) ); ?></strong></td>
				</tr>
			</table>
		</div>

		<!-- Customer Info -->
		<div class="obm-card">
			<h4 class="obm-section-title"><span class="dashicons dashicons-admin-users"></span> Customer Info</h4>
			<table class="obm-table">
				<tr>
					<th>Name</th>
					<td>
						<?php
						$full_name = trim( $first_name . ' ' . $last_name );
						if ( '' === $full_name ) {
							$full_name = '—';
						}
						echo esc_html( $full_name );
						?>
					</td>
				</tr>
				<tr>
					<th>Email</th>
					<td>
						<?php if ( $email ) : ?>
							<a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
						<?php else : ?>
							—
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th>Type</th>
					<td>
						<?php if ( 'international' === $customer_type ) : ?>
							<span class="obsidian-badge obsidian-badge-intl">International</span>
						<?php else : ?>
							<span class="obsidian-badge obsidian-badge-local">Local</span>
						<?php endif; ?>
					</td>
				</tr>
				<?php if ( $phone ) : ?>
				<tr>
					<th>Phone</th>
					<td><?php echo esc_html( $phone ); ?></td>
				</tr>
				<?php endif; ?>
				<tr>
					<th>Address</th>
					<td>
						<?php
						$address_label = $address;
						if ( '' === $address_label ) {
							$address_label = '—';
						}
						echo esc_html( $address_label );
						?>
					</td>
				</tr>
				<tr>
					<th>Birth Date</th>
					<td>
						<?php
						$dob         = DateTime::createFromFormat( 'Y-m-d', $birth_date );
						$birth_label = $birth_date;
						if ( '' === $birth_label ) {
							$birth_label = '—';
						}
						echo esc_html( $dob ? $dob->format( 'M j, Y' ) : $birth_label );
						if ( $dob ) {
							$age = $dob->diff( new DateTime() )->y;
							echo ' <span class="obm-muted">(' . esc_html( $age ) . ' years old)</span>';
						}
						?>
					</td>
				</tr>
				<tr>
					<th>License #</th>
					<td>
						<?php
						$license_label = $license_num;
						if ( '' === $license_label ) {
							$license_label = '—';
						}
						echo esc_html( $license_label );
						?>
					</td>
				</tr>
				<?php if ( 'local' === $customer_type && ( $gov_id_type || $gov_id_type_2 ) ) : ?>
				<tr>
					<th>Gov ID Types</th>
					<td>
						<?php echo esc_html( ucwords( str_replace( '_', ' ', $gov_id_type ) ) ); ?>
						<?php if ( $gov_id_type_2 ) : ?>
							, <?php echo esc_html( ucwords( str_replace( '_', ' ', $gov_id_type_2 ) ) ); ?>
						<?php endif; ?>
					</td>
				</tr>
				<?php endif; ?>
				<?php if ( 'international' === $customer_type && $passport_num ) : ?>
				<tr>
					<th>Passport #</th>
					<td><?php echo esc_html( $passport_num ); ?></td>
				</tr>
				<?php endif; ?>
			</table>
		</div>
		</div><!-- /.obm-grid -->

		<!-- Delivery Info -->
		<?php if ( $delivery_dropoff || $delivery_date || $return_address ) : ?>
		<div class="obm-section">
			<h4 class="obm-section-title"><span class="dashicons dashicons-location"></span> Delivery Info</h4>
			<table class="obm-table">
				<?php if ( $delivery_contact ) : ?>
				<tr>
					<th>Contact</th>
					<td><?php echo esc_html( $delivery_contact ); ?></td>
				</tr>
				<?php endif; ?>
				<?php if ( $delivery_dropoff ) : ?>
				<tr>
					<th>Drop Off</th>
					<td><?php echo esc_html( ucwords( str_replace( '_', ' ', $delivery_dropoff ) ) ); ?></td>
				</tr>
				<?php endif; ?>
				<?php if ( $delivery_address ) : ?>
				<tr>
					<th>Delivery Address</th>
					<td><?php echo esc_html( $delivery_address ); ?></td>
				</tr>
				<?php endif; ?>
				<?php if ( $delivery_date ) : ?>
				<tr>
					<th>Delivery</th>
					<td>
						<?php
						$del_dt = DateTime::createFromFormat( 'Y-m-d', $delivery_date );
						echo esc_html( $del_dt ? $del_dt->format( 'M j, Y' ) : $delivery_date );
						if ( $delivery_time ) {
							echo ' at ' . esc_html( $delivery_time );
						}
						?>
					</td>
				</tr>
				<?php endif; ?>
				<?php if ( $return_address ) : ?>
				<tr>
					<th>Return Address</th>
					<td><?php echo esc_html( $return_address ); ?></td>
				</tr>
				<?php endif; ?>
				<?php if ( $return_date ) : ?>
				<tr>
					<th>Return</th>
					<td>
						<?php
						$ret_dt = DateTime::createFromFormat( 'Y-m-d', $return_date );
						echo esc_html( $ret_dt ? $ret_dt->format( 'M j, Y' ) : $return_date );
						if ( $return_time ) {
							echo ' at ' . esc_html( $return_time );
						}
						?>
					</td>
				</tr>
				<?php endif; ?>
				<?php if ( $special_requests ) : ?>
				<tr>
					<th>Special Requests</th>
					<td><?php echo nl2br( esc_html( $special_requests ) ); ?></td>
				</tr>
				<?php endif; ?>
			</table>
		</div>
		<?php endif; ?>

		<!-- Uploaded Documents -->
		<div class="obm-section">
			<h4 class="obm-section-title"><span class="dashicons dashicons-media-document"></span> Uploaded Documents</h4>
			<?php if ( empty( $docs ) ) : ?>
				<p class="obm-muted">No documents uploaded.</p>
			<?php else : ?>
				<div class="obm-documents">
					<?php
					// Friendly labels for each doc key. The two gov-ID slots.
					// Pull the actual ID type the renter chose (SSS, PhilHealth).
					// This way the admin sees what they are looking at.
					$gov_id_label_1 = $gov_id_type
						? 'Government ID #1 — ' . ucwords( str_replace( '_', ' ', $gov_id_type ) )
						: 'Government ID #1';
					$gov_id_label_2 = $gov_id_type_2
						? 'Government ID #2 — ' . ucwords( str_replace( '_', ' ', $gov_id_type_2 ) )
						: 'Government ID #2';

					$doc_labels = array(
						'license'          => "Driver's License",
						'gov_id_1'         => $gov_id_label_1,
						'gov_id_2'         => $gov_id_label_2,
						// Legacy keys (older bookings created before the rename).
						'gov_id_front'     => $gov_id_label_1,
						'gov_id_back'      => $gov_id_label_2,
						'passport'         => 'Passport',
						'proof_of_arrival' => 'Proof of Arrival',
					);
					foreach ( $docs as $key => $attachment_id ) :
						$attachment_id = (int) $attachment_id;
						if ( $attachment_id <= 0 ) {
							continue;
						}

						$url   = wp_get_attachment_url( $attachment_id );
						$mime  = get_post_mime_type( $attachment_id );
						$label = $doc_labels[ $key ] ?? ucwords( str_replace( '_', ' ', $key ) );

						if ( ! $url ) {
							continue;
						}
						?>
						<div class="obm-doc-item">
							<span class="obm-doc-label"><?php echo esc_html( $label ); ?></span>
							<?php if ( $mime && 0 === strpos( $mime, 'image/' ) ) : ?>
								<a href="<?php echo esc_url( $url ); ?>" target="_blank" class="obm-doc-preview">
									<img src="<?php echo esc_url( $url ); ?>" alt="<?php echo esc_attr( $label ); ?>" />
									<span class="obm-doc-overlay"><span class="dashicons dashicons-visibility"></span> View Full Size</span>
								</a>
							<?php else : ?>
								<a href="<?php echo esc_url( $url ); ?>" target="_blank" class="button button-small"><span class="dashicons dashicons-pdf"></span> View PDF</a>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<!-- Admin Notes -->
		<div class="obm-section">
			<h4 class="obm-section-title"><span class="dashicons dashicons-edit"></span> Admin Notes <span class="obm-muted">(internal only)</span></h4>
			<textarea id="obm-admin-notes" class="obm-textarea" rows="3" placeholder="Add internal notes about this booking..."><?php echo esc_textarea( $admin_notes ); ?></textarea>
			<button type="button" id="obm-save-notes" class="button" data-booking-id="<?php echo esc_attr( $booking_id ); ?>">Save Notes</button>
		</div>

		<!-- Booking Activity -->
		<div class="obm-section">
			<h4 class="obm-section-title"><span class="dashicons dashicons-list-view"></span> Booking Activity</h4>
			<?php
			if ( function_exists( 'obsidian_get_booking_audit_entries' ) ) {
				$audit_entries = obsidian_get_booking_audit_entries( $booking_id, 25 );
			} else {
				$audit_entries = array();
			}
			?>
			<?php if ( empty( $audit_entries ) ) : ?>
				<p class="obm-muted">No activity logged yet.</p>
			<?php else : ?>
				<table class="obm-table">
					<tr>
						<th>Date</th>
						<th>User</th>
						<th>IP</th>
						<th>Change</th>
					</tr>
					<?php foreach ( $audit_entries as $entry ) : ?>
						<?php
							$user_label = 'System';
							if ( ! empty( $entry['user_id'] ) ) {
								$user = get_userdata( (int) $entry['user_id'] );
								if ( $user ) {
									$user_label = $user->display_name;
								}
							}

							$from_status = $entry['from_status'] ? $entry['from_status'] : '—';
							$to_status   = $entry['to_status'] ? $entry['to_status'] : '—';
							$ip_label    = $entry['ip'] ? $entry['ip'] : '—';
							$date_label  = $entry['created_at'] ? get_date_from_gmt( $entry['created_at'], 'M j, Y g:i A' ) : '—';
						?>
						<tr>
							<td><?php echo esc_html( $date_label ); ?></td>
							<td><?php echo esc_html( $user_label ); ?></td>
							<td><?php echo esc_html( $ip_label ); ?></td>
							<td><?php echo esc_html( $from_status ); ?> → <?php echo esc_html( $to_status ); ?></td>
						</tr>
					<?php endforeach; ?>
				</table>
			<?php endif; ?>
		</div>

		<!-- Actions -->
		<?php if ( 'pending_review' === $status ) : ?>
		<div class="obm-section obm-actions-section">
			<h4 class="obm-section-title"><span class="dashicons dashicons-yes-alt"></span> Actions</h4>
			<div class="obm-actions">
				<button type="button" id="obm-approve" class="obm-btn obm-btn-approve" data-booking-id="<?php echo esc_attr( $booking_id ); ?>">
					<span class="dashicons dashicons-yes"></span> Approve Documents
				</button>
				<div class="obm-deny-group">
					<input type="text" id="obm-denial-reason" class="regular-text" placeholder="Reason for denial (required)" />
					<button type="button" id="obm-deny" class="obm-btn obm-btn-deny" data-booking-id="<?php echo esc_attr( $booking_id ); ?>" disabled>
						<span class="dashicons dashicons-no"></span> Deny
					</button>
				</div>
			</div>
		</div>
		<?php endif; ?>

		<?php if ( 'awaiting_payment' === $status ) : ?>
		<div class="obm-section obm-actions-section">
			<h4 class="obm-section-title"><span class="dashicons dashicons-yes-alt"></span> Actions</h4>
			<div class="obm-actions">
				<div class="obm-deny-group">
					<input type="text" id="obm-cancel-reason" class="regular-text" placeholder="Reason for cancellation (required)" />
					<button type="button" id="obm-cancel" class="obm-btn obm-btn-deny" data-booking-id="<?php echo esc_attr( $booking_id ); ?>" disabled>
						<span class="dashicons dashicons-no"></span> Cancel Booking
					</button>
				</div>
			</div>
		</div>
		<?php endif; ?>

		<?php if ( 'confirmed' === $status ) : ?>
		<div class="obm-section obm-actions-section">
			<h4 class="obm-section-title"><span class="dashicons dashicons-yes-alt"></span> Actions</h4>
			<button type="button" id="obm-mark-active" class="obm-btn obm-btn-approve" data-booking-id="<?php echo esc_attr( $booking_id ); ?>">
				<span class="dashicons dashicons-controls-play"></span> Mark as Active
			</button>
		</div>
		<?php endif; ?>

		<?php if ( 'active' === $status ) : ?>
		<div class="obm-section obm-actions-section">
			<h4 class="obm-section-title"><span class="dashicons dashicons-yes-alt"></span> Actions</h4>
			<button type="button" id="obm-mark-completed" class="obm-btn obm-btn-approve" data-booking-id="<?php echo esc_attr( $booking_id ); ?>">
				<span class="dashicons dashicons-saved"></span> Mark as Completed
			</button>
			<p class="obm-muted" style="margin-top:8px;"><span class="dashicons dashicons-warning" style="font-size:14px;width:14px;height:14px;"></span> Remember to process the security deposit refund.</p>
		</div>
		<?php endif; ?>

	</div>
	<?php
}

/**
 * AJAX handler for booking actions (approve, deny, status change, save notes).
 */
function obsidian_handle_booking_action() {

	check_ajax_referer( 'obsidian_admin_nonce', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => 'Permission denied.' ) );
	}

	$booking_id = (int) ( $_POST['booking_id'] ?? 0 );
	$action     = sanitize_text_field( $_POST['booking_action'] ?? '' );

	if ( ! $booking_id || ! get_post( $booking_id ) ) {
		wp_send_json_error( array( 'message' => 'Invalid booking.' ) );
	}

	$current_status = get_post_meta( $booking_id, '_booking_status', true );

	switch ( $action ) {

		case 'approve':
			if ( 'pending_review' !== $current_status ) {
				wp_send_json_error( array( 'message' => 'Booking is not pending review.' ) );
			}
			update_post_meta( $booking_id, '_booking_status', 'awaiting_payment' );

			// Notification system handles token generation + email.
			do_action( 'obsidian_booking_status_changed', $booking_id, $current_status, 'awaiting_payment' );
			wp_send_json_success(
				array(
					'message'    => 'Documents approved. Payment link emailed to customer.',
					'new_status' => 'awaiting_payment',
				)
			);
			break;

		case 'deny':
			if ( 'pending_review' !== $current_status ) {
				wp_send_json_error( array( 'message' => 'Booking is not pending review.' ) );
			}
			$reason = sanitize_text_field( $_POST['reason'] ?? '' );
			if ( empty( $reason ) ) {
				wp_send_json_error( array( 'message' => 'A denial reason is required.' ) );
			}
			update_post_meta( $booking_id, '_booking_status', 'denied' );
			update_post_meta( $booking_id, '_booking_denial_reason', $reason );
			do_action( 'obsidian_booking_status_changed', $booking_id, $current_status, 'denied' );
			wp_send_json_success(
				array(
					'message'    => 'Booking denied.',
					'new_status' => 'denied',
				)
			);
			break;

		case 'mark_active':
			if ( 'confirmed' !== $current_status ) {
				wp_send_json_error( array( 'message' => 'Booking is not confirmed.' ) );
			}
			update_post_meta( $booking_id, '_booking_status', 'active' );
			do_action( 'obsidian_booking_status_changed', $booking_id, $current_status, 'active' );
			wp_send_json_success(
				array(
					'message'    => 'Booking marked as active.',
					'new_status' => 'active',
				)
			);
			break;

		case 'mark_completed':
			if ( 'active' !== $current_status ) {
				wp_send_json_error( array( 'message' => 'Booking is not active.' ) );
			}
			update_post_meta( $booking_id, '_booking_status', 'completed' );
			do_action( 'obsidian_booking_status_changed', $booking_id, $current_status, 'completed' );
			wp_send_json_success(
				array(
					'message'    => 'Booking completed.',
					'new_status' => 'completed',
				)
			);
			break;

		case 'save_notes':
			$notes = sanitize_textarea_field( $_POST['notes'] ?? '' );
			update_post_meta( $booking_id, '_booking_admin_notes', $notes );
			wp_send_json_success( array( 'message' => 'Notes saved.' ) );
			break;

		case 'cancel':
			if ( ! in_array( $current_status, array( 'pending_review', 'awaiting_payment' ), true ) ) {
				wp_send_json_error( array( 'message' => 'Booking cannot be cancelled in its current status.' ) );
			}
			$reason = sanitize_text_field( $_POST['reason'] ?? '' );
			if ( empty( $reason ) ) {
				wp_send_json_error( array( 'message' => 'A cancellation reason is required.' ) );
			}
			update_post_meta( $booking_id, '_booking_status', 'cancelled' );
			update_post_meta( $booking_id, '_booking_cancelled_by', 'admin' );
			update_post_meta( $booking_id, '_booking_cancellation_reason', $reason );
			update_post_meta( $booking_id, '_booking_cancellation_date', current_time( 'mysql' ) );
			do_action( 'obsidian_booking_status_changed', $booking_id, $current_status, 'cancelled' );
			wp_send_json_success(
				array(
					'message'    => 'Booking cancelled. Customer has been notified.',
					'new_status' => 'cancelled',
				)
			);
			break;

		default:
			wp_send_json_error( array( 'message' => 'Unknown action.' ) );
	}
}
add_action( 'wp_ajax_obsidian_booking_action', 'obsidian_handle_booking_action' );
