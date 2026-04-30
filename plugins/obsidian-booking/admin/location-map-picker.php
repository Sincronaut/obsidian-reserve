<?php
/**
 * Location Map Picker — Interactive Leaflet map for the Location editor.
 *
 * Adds a meta box with an embedded Leaflet map that lets the admin
 * click-to-place a pin. The pin coordinates sync bidirectionally with
 * the ACF Latitude / Longitude fields so both input methods work:
 *
 *   • Click the map  → lat/lng fields update automatically.
 *   • Type lat/lng   → map pin moves to the typed coordinates.
 *
 * Uses Leaflet + OpenStreetMap (free, no API key required) — the same
 * stack as the front-end locations-map block, so there's zero
 * additional vendor cost.
 *
 * @package obsidian-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the "Map Picker" meta box on the Location editor.
 */
function obsidian_register_map_picker_meta_box() {
	add_meta_box(
		'obsidian_location_map_picker',
		__( '📍 Pin Location on Map', 'obsidian-booking' ),
		'obsidian_render_map_picker_meta_box',
		'location',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes_location', 'obsidian_register_map_picker_meta_box' );

/**
 * Render the meta box HTML.
 *
 * The actual map is initialized client-side by admin-map-picker.js.
 * We output a container div plus a search bar and coordinate readout.
 */
function obsidian_render_map_picker_meta_box( $post ) {
	// Read current coordinates (may be empty for new locations).
	$lat = get_post_meta( $post->ID, 'location_latitude', true );
	$lng = get_post_meta( $post->ID, 'location_longitude', true );
	?>
	<div class="obsidian-map-picker"
		 data-lat="<?php echo esc_attr( $lat ); ?>"
		 data-lng="<?php echo esc_attr( $lng ); ?>">

		<!-- Search bar -->
		<div class="obsidian-map-picker__search">
			<input type="text"
				   id="obsidian-map-search"
				   class="obsidian-map-picker__search-input"
				   placeholder="Search for a place (e.g. BGC, Makati, Cebu IT Park)…"
				   autocomplete="off" />
			<button type="button"
					id="obsidian-map-search-btn"
					class="obsidian-map-picker__search-btn button">
				<span class="dashicons dashicons-search"></span> Search
			</button>
			<div id="obsidian-map-search-results" class="obsidian-map-picker__search-results" hidden></div>
		</div>

		<!-- Leaflet map canvas -->
		<div id="obsidian-map-picker-canvas" class="obsidian-map-picker__canvas"></div>

		<!-- Manual Input Fields -->
		<div class="obsidian-map-picker__inputs">
			<div class="obsidian-map-picker__field">
				<label for="obsidian-map-lat-manual"><?php esc_html_e( 'Latitude', 'obsidian-booking' ); ?></label>
				<input type="number" id="obsidian-map-lat-manual" step="any" value="<?php echo esc_attr( $lat ); ?>" placeholder="e.g. 14.5547" />
				<input type="hidden" name="location_latitude" id="obsidian-map-lat-hidden" value="<?php echo esc_attr( $lat ); ?>" />
			</div>
			<div class="obsidian-map-picker__field">
				<label for="obsidian-map-lng-manual"><?php esc_html_e( 'Longitude', 'obsidian-booking' ); ?></label>
				<input type="number" id="obsidian-map-lng-manual" step="any" value="<?php echo esc_attr( $lng ); ?>" placeholder="e.g. 121.0244" />
				<input type="hidden" name="location_longitude" id="obsidian-map-lng-hidden" value="<?php echo esc_attr( $lng ); ?>" />
			</div>
		</div>

		<!-- Coordinate readout -->
		<div class="obsidian-map-picker__coords">
			<span class="obsidian-map-picker__coords-icon dashicons dashicons-location"></span>
			<span id="obsidian-map-picker-readout" class="obsidian-map-picker__coords-text">
				<?php
				if ( $lat && $lng ) {
					printf( '%s, %s', esc_html( $lat ), esc_html( $lng ) );
				} else {
					esc_html_e( 'Click the map to place a pin, or use the search bar above.', 'obsidian-booking' );
				}
				?>
			</span>
			<?php if ( $lat && $lng ) : ?>
				<button type="button"
						id="obsidian-map-clear-pin"
						class="obsidian-map-picker__clear button-link">
					Clear pin
				</button>
			<?php else : ?>
				<button type="button"
						id="obsidian-map-clear-pin"
						class="obsidian-map-picker__clear button-link"
						style="display:none;">
					Clear pin
				</button>
			<?php endif; ?>
		</div>

		<p class="obsidian-map-picker__hint">
			💡 <strong>Tip:</strong> Click anywhere on the map to drop a pin. The latitude &amp; longitude fields below will update automatically.
			You can also drag the pin to fine-tune its position.
		</p>
	</div>
	<?php
}

/**
 * Enqueue Leaflet + our picker script on the Location editor screen.
 */
function obsidian_enqueue_map_picker_assets( $hook ) {
	// Only on post-new.php / post.php for location CPT
	if ( ! in_array( $hook, array( 'post-new.php', 'post.php' ), true ) ) {
		return;
	}

	global $post_type;
	if ( 'location' !== $post_type ) {
		return;
	}

	// Leaflet CSS
	wp_enqueue_style(
		'leaflet-admin',
		'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
		array(),
		'1.9.4'
	);

	// Leaflet JS
	wp_enqueue_script(
		'leaflet-admin',
		'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
		array(),
		'1.9.4',
		true
	);

	// Our picker script
	wp_enqueue_script(
		'obsidian-map-picker',
		OBSIDIAN_BOOKING_URL . 'assets/js/admin-map-picker.js',
		array( 'leaflet-admin' ),
		OBSIDIAN_BOOKING_VERSION,
		true
	);

	// Map picker CSS
	wp_enqueue_style(
		'obsidian-map-picker',
		OBSIDIAN_BOOKING_URL . 'assets/css/admin-map-picker.css',
		array( 'leaflet-admin', 'obsidian-admin-dark' ),
		OBSIDIAN_BOOKING_VERSION
	);
}
add_action( 'admin_enqueue_scripts', 'obsidian_enqueue_map_picker_assets' );

/**
 * Save the map coordinates when the location post is saved.
 */
function obsidian_save_location_map_picker_data( $post_id ) {
	// Security checks
	if ( ! isset( $_POST['location_latitude'] ) && ! isset( $_POST['location_longitude'] ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// Save coordinates
	if ( isset( $_POST['location_latitude'] ) ) {
		update_post_meta( $post_id, 'location_latitude', sanitize_text_field( $_POST['location_latitude'] ) );
	}
	if ( isset( $_POST['location_longitude'] ) ) {
		update_post_meta( $post_id, 'location_longitude', sanitize_text_field( $_POST['location_longitude'] ) );
	}
}
add_action( 'save_post_location', 'obsidian_save_location_map_picker_data' );
