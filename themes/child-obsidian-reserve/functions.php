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
 * TML Placeholder Script
 * --------------------------------------------------------------------------
 */
function obsidian_reserve_tml_script() {
	if ( ! function_exists( 'tml_is_action' ) || ! tml_is_action() ) {
		return;
	}
	?>
	<script>
	document.addEventListener('DOMContentLoaded', function() {
		var map = { user_login:'Email', user_pass:'Password', user_email:'Email', pass1:'New Password', pass2:'Confirm Password' };
		for (var id in map) { var el = document.getElementById(id); if (el) el.placeholder = map[id]; }
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
