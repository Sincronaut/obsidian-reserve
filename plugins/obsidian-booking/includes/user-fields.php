<?php
/**
 * User Profile Extensions
 *
 * Adds custom fields to WordPress user profiles:
 * - Phone number
 * - Nationality
 * - Driver's license number
 *
 * These are shown on the WP Admin user edit screen and will also
 * be available on the frontend profile page (Phase 8).
 *
 * @package obsidian-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Display extra fields on the user profile edit screen (WP Admin).
 *
 * Fires on both the "Your Profile" and "Edit User" admin pages.
 *
 * @param WP_User $user The user object being edited.
 */
function obsidian_show_user_fields( $user ) {
	?>
	<h3><?php esc_html_e( 'Obsidian Reserve — Customer Details', 'obsidian-booking' ); ?></h3>
	<table class="form-table">
		<tr>
			<th><label for="obsidian_phone"><?php esc_html_e( 'Phone Number', 'obsidian-booking' ); ?></label></th>
			<td>
				<input type="text"
					   name="obsidian_phone"
					   id="obsidian_phone"
					   value="<?php echo esc_attr( get_user_meta( $user->ID, '_obsidian_phone', true ) ); ?>"
					   class="regular-text" />
				<p class="description"><?php esc_html_e( 'Contact number for booking confirmations.', 'obsidian-booking' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><label for="obsidian_nationality"><?php esc_html_e( 'Nationality', 'obsidian-booking' ); ?></label></th>
			<td>
				<input type="text"
					   name="obsidian_nationality"
					   id="obsidian_nationality"
					   value="<?php echo esc_attr( get_user_meta( $user->ID, '_obsidian_nationality', true ) ); ?>"
					   class="regular-text" />
				<p class="description"><?php esc_html_e( 'Used to determine document requirements (local vs. foreigner).', 'obsidian-booking' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><label for="obsidian_license"><?php esc_html_e( "Driver's License Number", 'obsidian-booking' ); ?></label></th>
			<td>
				<input type="text"
					   name="obsidian_license"
					   id="obsidian_license"
					   value="<?php echo esc_attr( get_user_meta( $user->ID, '_obsidian_license', true ) ); ?>"
					   class="regular-text" />
			</td>
		</tr>
	</table>
	<?php
}
add_action( 'show_user_profile', 'obsidian_show_user_fields' );
add_action( 'edit_user_profile', 'obsidian_show_user_fields' );

/**
 * Save the extra fields when the profile is updated.
 *
 * @param int $user_id The user ID being saved.
 */
function obsidian_save_user_fields( $user_id ) {

	// Security: only allow users to edit their own profile, or admins to edit anyone
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}

	if ( isset( $_POST['obsidian_phone'] ) ) {
		update_user_meta( $user_id, '_obsidian_phone', sanitize_text_field( $_POST['obsidian_phone'] ) );
	}

	if ( isset( $_POST['obsidian_nationality'] ) ) {
		update_user_meta( $user_id, '_obsidian_nationality', sanitize_text_field( $_POST['obsidian_nationality'] ) );
	}

	if ( isset( $_POST['obsidian_license'] ) ) {
		update_user_meta( $user_id, '_obsidian_license', sanitize_text_field( $_POST['obsidian_license'] ) );
	}
}
add_action( 'personal_options_update', 'obsidian_save_user_fields' );
add_action( 'edit_user_profile_update', 'obsidian_save_user_fields' );

/**
 * Get all Obsidian-specific user data as a clean array.
 *
 * Used by the booking modal, profile page, and admin booking detail view.
 *
 * @param int $user_id The user ID.
 * @return array User data array.
 */
function obsidian_get_user_data( $user_id ) {

	$user = get_userdata( $user_id );

	if ( ! $user ) {
		return array();
	}

	return array(
		'user_id'     => $user_id,
		'display_name'=> $user->display_name,
		'email'       => $user->user_email,
		'phone'       => get_user_meta( $user_id, '_obsidian_phone', true ),
		'nationality' => get_user_meta( $user_id, '_obsidian_nationality', true ),
		'license'     => get_user_meta( $user_id, '_obsidian_license', true ),
		'registered'  => $user->user_registered,
	);
}
