<?php
/**
 * Child Obsidian Reserve — Functions & Definitions
 *
 * @package child-obsidian-reserve
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * --------------------------------------------------------------------------
 * 1. ENQUEUE STYLES
 * --------------------------------------------------------------------------
 */
function obsidian_reserve_enqueue_styles() {
	wp_enqueue_style(
		'obsidian-reserve-google-fonts',
		'https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500;1,600;1,700&display=swap',
		array(),
		null
	);

	wp_enqueue_style(
		'twentytwentyfive-style',
		get_template_directory_uri() . '/style.css',
		array( 'obsidian-reserve-google-fonts' ),
		wp_get_theme( 'twentytwentyfive' )->get( 'Version' )
	);

	wp_enqueue_style(
		'child-obsidian-reserve-style',
		get_stylesheet_uri(),
		array( 'twentytwentyfive-style' ),
		wp_get_theme()->get( 'Version' )
	);

	wp_enqueue_style(
		'obsidian-reserve-header',
		get_stylesheet_directory_uri() . '/assets/css/header.css',
		array( 'child-obsidian-reserve-style' ),
		wp_get_theme()->get( 'Version' )
	);

	wp_enqueue_style(
		'obsidian-reserve-footer',
		get_stylesheet_directory_uri() . '/assets/css/footer.css',
		array( 'child-obsidian-reserve-style' ),
		wp_get_theme()->get( 'Version' )
	);

	// TML styles — always loaded, scoped via body.tml-action class.
	if ( class_exists( 'Theme_My_Login' ) ) {
		wp_enqueue_style(
			'obsidian-reserve-tml',
			get_stylesheet_directory_uri() . '/assets/css/tml.css',
			array( 'child-obsidian-reserve-style' ),
			wp_get_theme()->get( 'Version' )
		);
	}

	// Single Blog Post styles.
	if ( is_single() && 'post' === get_post_type() ) {
		wp_enqueue_style(
			'obsidian-reserve-single',
			get_stylesheet_directory_uri() . '/assets/css/single.css',
			array( 'child-obsidian-reserve-style' ),
			wp_get_theme()->get( 'Version' )
		);
	}
}
add_action( 'wp_enqueue_scripts', 'obsidian_reserve_enqueue_styles' );

/**
 * --------------------------------------------------------------------------
 * TML REGISTRATION FORM CUSTOMIZATION
 * --------------------------------------------------------------------------
 * WordPress + TML default registration = Username + Email only.
 * We customize it to: Full Name → Email → Password → Confirm Password.
 * The username is auto-generated from the email address.
 *
 * Uses TML's own form API (tml_add_form_field / tml_remove_form_field)
 * to properly integrate with the plugin's rendering pipeline.
 */

/**
 * 1. Configure TML via built-in filters:
 *    - "email" registration type → hides username, auto-generates from email
 *    - User passwords enabled   → adds Password + Confirm Password fields
 *    - Auto-login enabled       → logs user in immediately after registration
 */
add_filter(
	'tml_get_registration_type',
	function () {
		return 'email';
	}
);
add_filter( 'tml_allow_user_passwords', '__return_true' );
add_filter( 'tml_allow_auto_login', '__return_true' );

/**
 * 2. Add the Full Name field to the TML registration form.
 *    Runs AFTER TML registers its default forms (init priority 0).
 */
function obsidian_reserve_customize_tml_register_form() {
	if ( ! function_exists( 'tml_add_form_field' ) ) {
		return;
	}

	// Add Full Name field (priority 12 = after hidden user_login at 10, before email at 15).
	tml_add_form_field(
		'register',
		'full_name',
		array(
			'type'       => 'text',
			'label'      => __( 'Full Name' ),
			'value'      => tml_get_request_value( 'full_name', 'post' ),
			'id'         => 'full_name',
			'attributes' => array(
				'autocomplete' => 'name',
			),
			'priority'   => 12,
		)
	);
}
add_action( 'init', 'obsidian_reserve_customize_tml_register_form', 20 );

/**
 * 3. Validate the Full Name field on registration.
 *    NOTE: Password validation is handled natively by TML
 *    (tml_validate_new_user_password) — do NOT duplicate it here.
 *
 * @param WP_Error $errors               The error object.
 * @param string   $sanitized_user_login The sanitized username.
 * @param string   $user_email           The user email.
 * @return WP_Error
 */
function obsidian_reserve_register_validate( $errors, $sanitized_user_login, $user_email ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	// Nonce is verified by TML/WordPress core during registration.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( empty( $_POST['full_name'] ) || strlen( trim( $_POST['full_name'] ) ) < 2 ) {
		$errors->add( 'full_name_error', '<strong>ERROR</strong>: Please enter your full name.' );
	}
	return $errors;
}
add_filter( 'registration_errors', 'obsidian_reserve_register_validate', 10, 3 );

/**
 * 4. After user is created, save Full Name as display_name + first/last name.
 *    NOTE: Password saving is handled natively by TML
 *    (tml_set_new_user_password) — do NOT duplicate it here.
 *
 * @param int $user_id The user ID.
 */
function obsidian_reserve_register_save_user( $user_id ) {
	// Nonce is verified by TML/WordPress core during registration.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( ! empty( $_POST['full_name'] ) ) {
		$full_name = sanitize_text_field( wp_unslash( $_POST['full_name'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$parts     = explode( ' ', $full_name, 2 );
		wp_update_user(
			array(
				'ID'           => $user_id,
				'display_name' => $full_name,
				'first_name'   => $parts[0],
				'last_name'    => isset( $parts[1] ) ? $parts[1] : '',
			)
		);
	}
}
add_action( 'user_register', 'obsidian_reserve_register_save_user' );

/**
 * 6. TML Placeholder Script — sets placeholders for all form fields.
 */
function obsidian_reserve_tml_script() {
	if ( ! function_exists( 'tml_is_action' ) || ! tml_is_action() ) {
		return;
	}
	?>
	<script>
	document.addEventListener('DOMContentLoaded', function() {
		var map = {
			user_login: 'Email',
			user_pass:  'Password',
			user_email: 'Email',
			full_name:  'Full Name',
			pass1:      'Password',
			pass2:      'Confirm Password'
		};
		for (var id in map) {
			var el = document.getElementById(id);
			if (el) el.placeholder = map[id];
		}
	});
	</script>
	<?php
}
add_action( 'wp_footer', 'obsidian_reserve_tml_script' );

/**
 * --------------------------------------------------------------------------
 * 2. ENQUEUE GOOGLE FONTS IN THE BLOCK EDITOR
 * --------------------------------------------------------------------------
 */
function obsidian_reserve_editor_styles() {
	wp_enqueue_style(
		'obsidian-reserve-google-fonts-editor',
		'https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500;1,600;1,700&display=swap',
		array(),
		null
	);
	add_editor_style( 'style.css' );
}
add_action( 'admin_init', 'obsidian_reserve_editor_styles' );

/**
 * --------------------------------------------------------------------------
 * 3. PRECONNECT TO GOOGLE FONTS
 * --------------------------------------------------------------------------
 *
 * @param array  $urls          The resource hint URLs.
 * @param string $relation_type The relation type.
 * @return array
 */
function obsidian_reserve_preconnect_google_fonts( $urls, $relation_type ) {
	if ( 'preconnect' === $relation_type ) {
		$urls[] = array(
			'href'        => 'https://fonts.googleapis.com',
			'crossorigin' => 'anonymous',
		);
		$urls[] = array(
			'href'        => 'https://fonts.gstatic.com',
			'crossorigin' => 'anonymous',
		);
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'obsidian_reserve_preconnect_google_fonts', 10, 2 );

/**
 * --------------------------------------------------------------------------
 * 4. REGISTER CUSTOM BLOCKS
 * --------------------------------------------------------------------------
 */
function obsidian_reserve_register_blocks() {
	register_block_type( get_stylesheet_directory() . '/blocks/hero' );
	register_block_type( get_stylesheet_directory() . '/blocks/three-cards' );
	register_block_type( get_stylesheet_directory() . '/blocks/text-img-bg' );
	register_block_type( get_stylesheet_directory() . '/blocks/slider' );
	register_block_type( get_stylesheet_directory() . '/blocks/logo-slider' );
	register_block_type( get_stylesheet_directory() . '/blocks/img-text' );
	register_block_type( get_stylesheet_directory() . '/blocks/standard' );
	register_block_type( get_stylesheet_directory() . '/blocks/contact' );
	register_block_type( get_stylesheet_directory() . '/blocks/car-grid' );
	register_block_type( get_stylesheet_directory() . '/blocks/booking-form' );
	register_block_type( get_stylesheet_directory() . '/blocks/faq' );
	register_block_type( get_stylesheet_directory() . '/blocks/featured-fleet' );
	register_block_type( get_stylesheet_directory() . '/blocks/fleet-filters' );
	register_block_type( get_stylesheet_directory() . '/blocks/locations-map' );
	register_block_type( get_stylesheet_directory() . '/blocks/trending-blogs' );
	register_block_type( get_stylesheet_directory() . '/blocks/top-reads-journal' );
	register_block_type( get_stylesheet_directory() . '/blocks/blog-grid' );
	register_block_type( get_stylesheet_directory() . '/blocks/profile-dashboard' );
}
add_action( 'init', 'obsidian_reserve_register_blocks' );

/**
 * Enqueue Cropper.js (CDN) only on the Profile page for avatar cropping.
 */
function obsidian_reserve_enqueue_cropper() {
	if ( ! is_page( 'profile' ) ) {
		return;
	}
	wp_enqueue_style(
		'cropperjs',
		'https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css',
		array(),
		'1.6.2'
	);
	wp_enqueue_script(
		'cropperjs',
		'https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js',
		array(),
		'1.6.2',
		true
	);
}
add_action( 'wp_enqueue_scripts', 'obsidian_reserve_enqueue_cropper' );

/* Disable the WordPress Admin Bar on the front-end */
add_filter( 'show_admin_bar', '__return_false' );

/**
 * --------------------------------------------------------------------------
 * 5. LOGIN / REGISTER REDIRECTS
 * --------------------------------------------------------------------------
 * Non-admin users go to the homepage after login.
 * Block non-admin users from accessing wp-admin.
 *
 * @param string  $redirect_to The redirect URL.
 * @param string  $request     The requested URL.
 * @param WP_User $user        The user object.
 * @return string
 */
function obsidian_reserve_login_redirect( $redirect_to, $request, $user ) {
	if ( isset( $user->roles ) && ! in_array( 'administrator', (array) $user->roles, true ) ) {
		return home_url( '/' );
	}
	return $redirect_to;
}
add_filter( 'login_redirect', 'obsidian_reserve_login_redirect', 10, 3 );

/**
 * Redirects user after registration.
 *
 * @param string $redirect_to The redirect URL.
 * @return string
 */
function obsidian_reserve_registration_redirect( $redirect_to ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	return home_url( '/' );
}
add_filter( 'registration_redirect', 'obsidian_reserve_registration_redirect' );

/**
 * Blocks admin access for non-admin users.
 */
function obsidian_reserve_block_admin_access() {
	if ( is_admin() && ! current_user_can( 'edit_posts' ) && ! wp_doing_ajax() ) {
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}
}
add_action( 'admin_init', 'obsidian_reserve_block_admin_access' );

/**
 * --------------------------------------------------------------------------
 * 6. DYNAMIC USER MENU SHORTCODE
 * --------------------------------------------------------------------------
 * Renders the header user dropdown dynamically.
 * Logged-in:  Username  |  Log Out
 * Logged-out: Login     |  Sign Up
 */
function obsidian_reserve_user_menu_shortcode() {
	ob_start();

	if ( is_user_logged_in() ) {
		$current_user = wp_get_current_user();
		$user_id      = $current_user->ID;
		$display_name = $current_user->display_name ? $current_user->display_name : $current_user->user_login;
		$username     = $current_user->user_login;
		$first_name   = $current_user->first_name;
		$last_name    = $current_user->last_name;
		$full_name    = trim( $first_name . ' ' . $last_name ) ? trim( $first_name . ' ' . $last_name ) : $display_name;
		$email        = $current_user->user_email;
		$phone        = get_user_meta( $user_id, '_obsidian_phone', true );
		$license      = get_user_meta( $user_id, '_obsidian_license', true );
		$logout_url   = wp_logout_url( home_url( '/' ) );
		$profile_url  = home_url( '/profile/' );

		/* Custom avatar or Gravatar */
		$avatar_url    = get_avatar_url( $user_id, array( 'size' => 200 ) );
		$custom_avatar = get_user_meta( $user_id, '_obsidian_avatar', true );
		if ( $custom_avatar ) {
			$avatar_url = $custom_avatar;
		}

		$tier = 'Platinum';
		?>
		<div class="obsidian-dropdown obsidian-user-menu" data-auth="logged-in">
			<button class="obsidian-dropdown-toggle" aria-label="User Menu">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
					<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 4c1.93 0 3.5 1.57 3.5 3.5S13.93 13 12 13s-3.5-1.57-3.5-3.5S10.07 6 12 6zm0 14c-2.03 0-3.92-.81-5.32-2.13.06-1.74 3.51-2.7 5.32-2.7s5.26.96 5.32 2.7C15.92 19.19 14.03 20 12 20z"/>
				</svg>
				<svg class="chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
			</button>

			<!-- Desktop mega profile card -->
			<div class="obsidian-dropdown-menu obsidian-user-card">
				<div class="ouc-header">
					<a href="#" class="ouc-back" aria-label="Close Menu">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
					</a>
					<a href="<?php echo esc_url( $logout_url ); ?>" class="ouc-logout">Log Out</a>
				</div>

				<div class="ouc-avatar">
					<img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $full_name ); ?>" />
				</div>

				<span class="ouc-tier"><?php echo esc_html( $tier ); ?></span>

				<h3 class="ouc-name"><?php echo esc_html( $full_name ); ?></h3>

				<div class="ouc-details">
					<div class="ouc-detail-row">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="1.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
						<span>Email : <a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a></span>
					</div>
					<?php if ( $phone ) : ?>
					<div class="ouc-detail-row">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="1.5"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12" y2="18"/></svg>
						<span>Mobile Number : <a href="tel:<?php echo esc_attr( $phone ); ?>"><?php echo esc_html( $phone ); ?></a></span>
					</div>
					<?php endif; ?>
					<?php if ( $license ) : ?>
					<div class="ouc-detail-row">
						<span class="ouc-license-label">Driver License Status : <a href="#"><?php echo esc_html( $license ); ?></a></span>
					</div>
					<?php endif; ?>
				</div>

				<a href="<?php echo esc_url( $profile_url ); ?>" class="ouc-view-profile">View Profile</a>
			</div>

			<!-- Mobile simple items (inside the drawer) -->
			<ul class="obsidian-dropdown-menu user-dropdown-menu obsidian-user-mobile-menu">
				<li class="user-name"><?php echo esc_html( $display_name ); ?></li>
				<li><a href="<?php echo esc_url( $profile_url ); ?>">View Profile <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></a></li>
				<li><a href="<?php echo esc_url( $logout_url ); ?>">Log Out <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg></a></li>
			</ul>
		</div>
		<?php
	} else {
		// Use Theme My Login slugs — default: /login/ and /register/.
		$login_url    = home_url( '/login/' );
		$register_url = home_url( '/register/' );
		?>
		<div class="obsidian-dropdown obsidian-user-menu" data-auth="logged-out">
			<button class="obsidian-dropdown-toggle" aria-label="User Menu">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
					<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 4c1.93 0 3.5 1.57 3.5 3.5S13.93 13 12 13s-3.5-1.57-3.5-3.5S10.07 6 12 6zm0 14c-2.03 0-3.92-.81-5.32-2.13.06-1.74 3.51-2.7 5.32-2.7s5.26.96 5.32 2.7C15.92 19.19 14.03 20 12 20z"/>
				</svg>
				<svg class="chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
			</button>
			<ul class="obsidian-dropdown-menu user-dropdown-menu">
				<li><a href="<?php echo esc_url( $login_url ); ?>">Login <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg></a></li>
				<li><a href="<?php echo esc_url( $register_url ); ?>">Sign Up <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg></a></li>
			</ul>
		</div>
		<?php
	}

	return ob_get_clean();
}
add_shortcode( 'obsidian_user_menu', 'obsidian_reserve_user_menu_shortcode' );

/**
 * --------------------------------------------------------------------------
 * 7. PROCESS SHORTCODES INSIDE HTML BLOCKS
 * --------------------------------------------------------------------------
 * Block themes render core/html blocks as raw output, so embedded
 * shortcodes like [obsidian_user_menu] won't fire automatically.
 * This filter intercepts HTML block output and runs do_shortcode().
 *
 * @param string $block_content The block content.
 * @param array  $block         The block details.
 * @return string
 */
function obsidian_reserve_html_block_shortcodes( $block_content, $block ) {
	if ( 'core/html' !== $block['blockName'] ) {
		return $block_content;
	}

	// Whitelist of plugin-provided shortcodes that may appear inside core/html.
	// blocks in templates. Add new entries here when introducing more.
	$shortcodes = array( 'obsidian_user_menu', 'obsidian_locations_menu' );

	foreach ( $shortcodes as $tag ) {
		if ( has_shortcode( $block_content, $tag ) ) {
			$block_content = do_shortcode( $block_content );
			break; // do_shortcode runs every registered tag in one pass.
		}
	}

	return $block_content;
}
add_filter( 'render_block', 'obsidian_reserve_html_block_shortcodes', 10, 2 );

/**
 * --------------------------------------------------------------------------
 * 8. MODULAR INCLUDES
 * --------------------------------------------------------------------------
 * Load specialized logic from the inc/ directory.
 */
require get_stylesheet_directory() . '/inc/blog-engine.php';
