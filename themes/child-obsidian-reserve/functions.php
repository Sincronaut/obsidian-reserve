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
 * - Google Fonts: Montserrat (weights 400–700)
 * - Parent theme stylesheet
 * - Child theme stylesheet
 */
function obsidian_reserve_enqueue_styles() {
	// Montserrat from Google Fonts — weights 400, 500, 600, 700
	wp_enqueue_style(
		'obsidian-reserve-google-fonts',
		'https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500;1,600;1,700&display=swap',
		array(),
		null
	);

	// Parent theme stylesheet
	wp_enqueue_style(
		'twentytwentyfive-style',
		get_template_directory_uri() . '/style.css',
		array( 'obsidian-reserve-google-fonts' ),
		wp_get_theme( 'twentytwentyfive' )->get( 'Version' )
	);

	// Child theme stylesheet
	wp_enqueue_style(
		'child-obsidian-reserve-style',
		get_stylesheet_uri(),
		array( 'twentytwentyfive-style' ),
		wp_get_theme()->get( 'Version' )
	);

	// Enqueue specific part styles
	wp_enqueue_style(
		'obsidian-reserve-header',
		get_stylesheet_directory_uri() . '/assets/css/header.css',
		array('child-obsidian-reserve-style'),
		wp_get_theme()->get( 'Version' )
	);
	
	wp_enqueue_style(
		'obsidian-reserve-footer',
		get_stylesheet_directory_uri() . '/assets/css/footer.css',
		array('child-obsidian-reserve-style'),
		wp_get_theme()->get( 'Version' )
	);

	// Theme My Login Split Screen Styles
	if ( class_exists( 'Theme_My_Login' ) ) {
		$action = theme_my_login()->get_request_action();
		if ( $action ) {
			wp_enqueue_style(
				'obsidian-reserve-tml',
				get_stylesheet_directory_uri() . '/assets/css/tml.css',
				array('child-obsidian-reserve-style'),
				wp_get_theme()->get( 'Version' )
			);
		}
	}
}
add_action( 'wp_enqueue_scripts', 'obsidian_reserve_enqueue_styles' );

/**
 * --------------------------------------------------------------------------
 * TML Custom Placeholders Script
 * --------------------------------------------------------------------------
 */
function obsidian_reserve_tml_script() {
	if ( class_exists( 'Theme_My_Login' ) && theme_my_login()->get_request_action() ) {
		?>
		<script>
			document.addEventListener('DOMContentLoaded', function() {
				const inputs = {
					'user_login': 'Email',
					'user_pass': 'Password',
					'user_email': 'Email',
					'pass1': 'New Password',
					'pass2': 'Confirm Password'
				};
				for (const [id, ph] of Object.entries(inputs)) {
					const el = document.getElementById(id);
					if (el) el.placeholder = ph;
				}
			});
		</script>
		<?php
	}
}
add_action( 'wp_footer', 'obsidian_reserve_tml_script' );

/**
 * --------------------------------------------------------------------------
 * 2. ENQUEUE GOOGLE FONTS IN THE BLOCK EDITOR
 * --------------------------------------------------------------------------
 * Ensures Montserrat renders correctly in the Site Editor / Block Editor.
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
 * Speeds up font loading by establishing early connections.
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
}
add_action( 'init', 'obsidian_reserve_register_blocks' );

/**
 * --------------------------------------------------------------------------
 * 5. THEME MY LOGIN INTEGRATION
 * --------------------------------------------------------------------------
 */
function obsidian_reserve_tml_body_class( $classes ) {
	if ( class_exists( 'Theme_My_Login' ) ) {
		$action = theme_my_login()->get_request_action();
		if ( $action ) {
			$classes[] = 'is-tml-page';
			$classes[] = 'tml-action-' . $action;
		}
	}
	return $classes;
}
add_filter( 'body_class', 'obsidian_reserve_tml_body_class' );

/* Disable the WordPress Admin Bar on the front-end */
add_filter( 'show_admin_bar', '__return_false' );
