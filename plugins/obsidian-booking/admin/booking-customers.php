<?php
/**
 * Booking Customers Admin Page
 *
 * @package obsidian-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the Customers submenu under Users.
 *
 * @return void
 */
function obsidian_register_booking_customers_page() {
	add_users_page(
		__( 'Obsidian Customers', 'obsidian-booking' ),
		__( 'Obsidian Customers', 'obsidian-booking' ),
		'manage_options',
		'obsidian-booking-customers',
		'obsidian_render_booking_customers_page'
	);
}
add_action( 'admin_menu', 'obsidian_register_booking_customers_page' );

/**
 * Handle blacklisting a user.
 *
 * @return void
 */
function obsidian_handle_blacklist_user() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'obsidian-booking' ) );
	}

	check_admin_referer( 'obsidian_blacklist_user', 'obsidian_blacklist_nonce' );

	$user_id = (int) ( $_POST['user_id'] ?? 0 );
	$reason  = sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) );

	if ( $user_id > 0 ) {
		update_user_meta( $user_id, '_obsidian_blacklisted', 1 );
		update_user_meta( $user_id, '_obsidian_blacklist_reason', $reason );
		update_user_meta( $user_id, '_obsidian_blacklist_date', current_time( 'mysql' ) );
	}

	wp_safe_redirect( add_query_arg( array( 'page' => 'obsidian-booking-customers', 'updated' => 'blacklisted' ), admin_url( 'users.php' ) ) );
	exit;
}
add_action( 'admin_post_obsidian_blacklist_user', 'obsidian_handle_blacklist_user' );

/**
 * Handle removing a user from the blacklist.
 *
 * @return void
 */
function obsidian_handle_unblacklist_user() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'obsidian-booking' ) );
	}

	check_admin_referer( 'obsidian_unblacklist_user', 'obsidian_unblacklist_nonce' );

	$user_id = (int) ( $_POST['user_id'] ?? 0 );
	if ( $user_id > 0 ) {
		delete_user_meta( $user_id, '_obsidian_blacklisted' );
		delete_user_meta( $user_id, '_obsidian_blacklist_reason' );
		delete_user_meta( $user_id, '_obsidian_blacklist_date' );
	}

	wp_safe_redirect( add_query_arg( array( 'page' => 'obsidian-booking-customers', 'updated' => 'unblacklisted' ), admin_url( 'users.php' ) ) );
	exit;
}
add_action( 'admin_post_obsidian_unblacklist_user', 'obsidian_handle_unblacklist_user' );

/**
 * Render the Customers admin page.
 *
 * @return void
 */
function obsidian_render_booking_customers_page() {
	global $wpdb;

	$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$per_page = 20;
	$paged    = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
	$offset   = ( $paged - 1 ) * $per_page;

	$booking_user_ids = $wpdb->get_col(
		"SELECT DISTINCT pm.meta_value
		 FROM {$wpdb->postmeta} pm
		 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		 WHERE pm.meta_key = '_booking_user_id'
		 AND p.post_type = 'booking'"
	);

	if ( empty( $booking_user_ids ) ) {
		$booking_user_ids = array();
	}

	$users       = array();
	$total       = 0;
	$total_pages = 0;

	if ( ! empty( $booking_user_ids ) ) {
		$user_query_args = array(
			'include'        => $booking_user_ids,
			'number'         => $per_page,
			'offset'         => $offset,
			'orderby'        => 'display_name',
			'order'          => 'ASC',
			'count_total'    => true,
			'fields'         => 'all',
		);

		if ( $search ) {
			$user_query_args['search']         = '*' . $search . '*';
			$user_query_args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}

		$user_query = new WP_User_Query( $user_query_args );
		$users      = $user_query->get_results();
		$total      = (int) $user_query->get_total();
		$total_pages = (int) ceil( $total / $per_page );
	}

	$user_ids = array();
	foreach ( $users as $user ) {
		$user_ids[] = (int) $user->ID;
	}

	$booking_counts = array();
	if ( ! empty( $user_ids ) ) {
		$placeholders = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );
		$sql          = $wpdb->prepare(
			"SELECT pm.meta_value AS user_id, COUNT(*) AS booking_count
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = '_booking_user_id'
			 AND p.post_type = 'booking'
			 AND pm.meta_value IN ({$placeholders})
			 GROUP BY pm.meta_value",
			$user_ids
		);
		$rows         = $wpdb->get_results( $sql, ARRAY_A );
		foreach ( $rows as $row ) {
			$booking_counts[ (int) $row['user_id'] ] = (int) $row['booking_count'];
		}
	}

	$notice = '';
	if ( isset( $_GET['updated'] ) ) {
		$updated = sanitize_text_field( wp_unslash( $_GET['updated'] ) );
		if ( 'blacklisted' === $updated ) {
			$notice = __( 'Customer has been blacklisted.', 'obsidian-booking' );
		} elseif ( 'unblacklisted' === $updated ) {
			$notice = __( 'Customer has been removed from the blacklist.', 'obsidian-booking' );
		}
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'Obsidian Customers', 'obsidian-booking' ); ?></h1>

		<?php if ( $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>

		<form method="get" class="search-form" style="margin: 16px 0;">
			<input type="hidden" name="page" value="obsidian-booking-customers" />
			<label for="obm-customer-search" class="screen-reader-text"><?php echo esc_html__( 'Search customers', 'obsidian-booking' ); ?></label>
			<input type="search" id="obm-customer-search" name="s" value="<?php echo esc_attr( $search ); ?>" />
			<button class="button" type="submit"><?php echo esc_html__( 'Search', 'obsidian-booking' ); ?></button>
		</form>

		<table class="widefat fixed striped">
			<thead>
			<tr>
				<th><?php echo esc_html__( 'Customer', 'obsidian-booking' ); ?></th>
				<th><?php echo esc_html__( 'Email', 'obsidian-booking' ); ?></th>
				<th><?php echo esc_html__( 'Bookings', 'obsidian-booking' ); ?></th>
				<th><?php echo esc_html__( 'Blacklist', 'obsidian-booking' ); ?></th>
				<th><?php echo esc_html__( 'Reason', 'obsidian-booking' ); ?></th>
				<th><?php echo esc_html__( 'Actions', 'obsidian-booking' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php if ( empty( $users ) ) : ?>
				<tr>
					<td colspan="6"><?php echo esc_html__( 'No customers found.', 'obsidian-booking' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $users as $user ) : ?>
					<?php
						$user_id    = (int) $user->ID;
						$blacklisted = (int) get_user_meta( $user_id, '_obsidian_blacklisted', true );
						$reason     = get_user_meta( $user_id, '_obsidian_blacklist_reason', true );
						$booking_count = $booking_counts[ $user_id ] ?? 0;
					?>
					<tr>
						<td>
							<strong><?php echo esc_html( $user->display_name ); ?></strong><br />
							<span class="description"><?php echo esc_html( $user->user_login ); ?></span>
						</td>
						<td><?php echo esc_html( $user->user_email ); ?></td>
						<td><?php echo esc_html( (string) $booking_count ); ?></td>
						<td>
							<?php if ( $blacklisted ) : ?>
								<span class="dashicons dashicons-warning" style="color:#d63638;"></span>
								<?php echo esc_html__( 'Blacklisted', 'obsidian-booking' ); ?>
							<?php else : ?>
								<?php echo esc_html__( 'Active', 'obsidian-booking' ); ?>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $reason ? $reason : '—' ); ?></td>
						<td>
							<?php if ( $blacklisted ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<?php wp_nonce_field( 'obsidian_unblacklist_user', 'obsidian_unblacklist_nonce' ); ?>
									<input type="hidden" name="action" value="obsidian_unblacklist_user" />
									<input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>" />
									<button type="submit" class="button"><?php echo esc_html__( 'Remove', 'obsidian-booking' ); ?></button>
								</form>
							<?php else : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<?php wp_nonce_field( 'obsidian_blacklist_user', 'obsidian_blacklist_nonce' ); ?>
									<input type="hidden" name="action" value="obsidian_blacklist_user" />
									<input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>" />
									<input type="text" name="reason" placeholder="<?php echo esc_attr__( 'Reason (optional)', 'obsidian-booking' ); ?>" />
									<button type="submit" class="button button-primary"><?php echo esc_html__( 'Blacklist', 'obsidian-booking' ); ?></button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav">
				<div class="tablenav-pages">
					<?php
						$base_args = array(
							'page'  => 'obsidian-booking-customers',
							'paged' => '%#%',
						);
						if ( $search ) {
							$base_args['s'] = $search;
						}
						echo paginate_links(
							array(
								'base'      => add_query_arg( $base_args, admin_url( 'users.php' ) ),
								'format'    => '',
								'current'   => $paged,
								'total'     => $total_pages,
								'prev_text' => __( '&laquo;', 'obsidian-booking' ),
								'next_text' => __( '&raquo;', 'obsidian-booking' ),
							)
						);
					?>
				</div>
			</div>
		<?php endif; ?>
	</div>
	<?php
}
