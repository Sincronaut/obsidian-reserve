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

	// TML styles — always loaded, scoped via body.tml-action class
	if ( class_exists( 'Theme_My_Login' ) ) {
		wp_enqueue_style(
			'obsidian-reserve-tml',
			get_stylesheet_directory_uri() . '/assets/css/tml.css',
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
add_filter( 'tml_get_registration_type', function() { return 'email'; } );
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

	// Add Full Name field (priority 12 = after hidden user_login at 10, before email at 15)
	tml_add_form_field( 'register', 'full_name', array(
		'type'     => 'text',
		'label'    => __( 'Full Name' ),
		'value'    => tml_get_request_value( 'full_name', 'post' ),
		'id'       => 'full_name',
		'attributes' => array(
			'autocomplete' => 'name',
		),
		'priority' => 12,
	) );
}
add_action( 'init', 'obsidian_reserve_customize_tml_register_form', 20 );

/**
 * 3. Validate the Full Name field on registration.
 *    NOTE: Password validation is handled natively by TML
 *    (tml_validate_new_user_password) — do NOT duplicate it here.
 */
function obsidian_reserve_register_validate( $errors, $sanitized_user_login, $user_email ) {
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
 */
function obsidian_reserve_register_save_user( $user_id ) {
	if ( ! empty( $_POST['full_name'] ) ) {
		$full_name = sanitize_text_field( $_POST['full_name'] );
		$parts     = explode( ' ', $full_name, 2 );
		wp_update_user( array(
			'ID'           => $user_id,
			'display_name' => $full_name,
			'first_name'   => $parts[0],
			'last_name'    => isset( $parts[1] ) ? $parts[1] : '',
		) );
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
 */
function obsidian_reserve_preconnect_google_fonts( $urls, $relation_type ) {
	if ( 'preconnect' === $relation_type ) {
		$urls[] = array(
			'href' => 'https://fonts.googleapis.com',
			'crossorigin' => 'anonymous',
		);
		$urls[] = array(
			'href' => 'https://fonts.gstatic.com',
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
}
add_action( 'init', 'obsidian_reserve_register_blocks' );

/* Disable the WordPress Admin Bar on the front-end */
add_filter( 'show_admin_bar', '__return_false' );

/**
 * --------------------------------------------------------------------------
 * 5. LOGIN / REGISTER REDIRECTS
 * --------------------------------------------------------------------------
 * Non-admin users go to the homepage after login.
 * Block non-admin users from accessing wp-admin.
 */
function obsidian_reserve_login_redirect( $redirect_to, $request, $user ) {
	if ( isset( $user->roles ) && ! in_array( 'administrator', (array) $user->roles, true ) ) {
		return home_url( '/' );
	}
	return $redirect_to;
}
add_filter( 'login_redirect', 'obsidian_reserve_login_redirect', 10, 3 );

function obsidian_reserve_registration_redirect( $redirect_to ) {
	return home_url( '/' );
}
add_filter( 'registration_redirect', 'obsidian_reserve_registration_redirect' );

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
		$display_name = esc_html( $current_user->display_name );
		$logout_url   = wp_logout_url( home_url( '/' ) );
		?>
		<div class="obsidian-dropdown obsidian-user-menu" data-auth="logged-in">
			<button class="obsidian-dropdown-toggle" aria-label="User Menu">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
					<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 4c1.93 0 3.5 1.57 3.5 3.5S13.93 13 12 13s-3.5-1.57-3.5-3.5S10.07 6 12 6zm0 14c-2.03 0-3.92-.81-5.32-2.13.06-1.74 3.51-2.7 5.32-2.7s5.26.96 5.32 2.7C15.92 19.19 14.03 20 12 20z"/>
				</svg>
				<svg class="chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
			</button>
			<ul class="obsidian-dropdown-menu user-dropdown-menu">
				<li class="user-name"><?php echo $display_name; ?></li>
				<li><a href="<?php echo esc_url( $logout_url ); ?>">Log Out <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg></a></li>
			</ul>
		</div>
		<?php
	} else {
		// Use Theme My Login slugs — default: /login/ and /register/
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
 */
function obsidian_reserve_html_block_shortcodes( $block_content, $block ) {
	if ( 'core/html' !== $block['blockName'] ) {
		return $block_content;
	}

	// Whitelist of plugin-provided shortcodes that may appear inside core/html
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
