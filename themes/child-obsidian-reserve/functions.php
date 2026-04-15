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
}
add_action( 'wp_enqueue_scripts', 'obsidian_reserve_enqueue_styles' );

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
	register_block_type( get_stylesheet_directory() . '/blocks/faq' );
}
add_action( 'init', 'obsidian_reserve_register_blocks' );

/* Disable the WordPress Admin Bar on the front-end */
add_filter( 'show_admin_bar', '__return_false' );

/**
 * --------------------------------------------------------------------------
 * 5. CUSTOM LOGIN PAGE
 * --------------------------------------------------------------------------
 */
function obsidian_reserve_login_scripts() {
	// Enqueue our custom login stylesheet
	wp_enqueue_style(
		'obsidian-login-style',
		get_stylesheet_directory_uri() . '/assets/css/login.css',
		array(),
		wp_get_theme()->get( 'Version' )
	);

	// Enqueue Google Fonts
	wp_enqueue_style(
		'obsidian-login-fonts',
		'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap',
		array(),
		null
	);
}
add_action( 'login_enqueue_scripts', 'obsidian_reserve_login_scripts' );

/**
 * JS to modify the login form DOM to match the design.
 */
function obsidian_reserve_login_js() {
	?>
	<script>
	document.addEventListener("DOMContentLoaded", function() {
		// 1. Add placeholders to inputs
		var userLogin = document.getElementById("user_login");
		if (userLogin) userLogin.placeholder = "Email";
		
		var userPass = document.getElementById("user_pass");
		if (userPass) userPass.placeholder = "Password";
		
		// 2. Hide labels for the main inputs
		var labels = document.querySelectorAll("#loginform label");
		labels.forEach(function(label) {
			if (label.getAttribute("for") === "user_login" || label.getAttribute("for") === "user_pass") {
				label.style.display = "none";
			}
		});

		// 3. Change button text
		var btn = document.getElementById("wp-submit");
		if (btn) btn.value = "Login";

		// 4. Wrap form in a new structure for the right pane
		var loginDiv = document.getElementById("login");
		var loginForm = document.getElementById("loginform");
		
		if (loginDiv && loginForm) {
			// Create wrapper
			var rightPane = document.createElement("div");
			rightPane.className = "login-right-pane";
			
			// Add title
			var signinTitle = document.createElement("h2");
			signinTitle.className = "signin-title";
			signinTitle.textContent = "Sign-in";
			
			// Add signup link below
			var signupText = document.createElement("p");
			signupText.className = "signup-text";
			signupText.innerHTML = "Don't have an account? <a href='/register/'>Signup Here</a>";

			// Move elements
			rightPane.appendChild(signinTitle);
			loginDiv.insertBefore(rightPane, loginForm);
			rightPane.appendChild(loginForm);
			rightPane.appendChild(signupText);
		}
	});
	</script>
	<?php
}
add_action( 'login_head', 'obsidian_reserve_login_js' );
