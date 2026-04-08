<?php
/**
 * Car Color Variants Meta Box
 *
 * Renders per-color inventory (units + image) inputs on the Car edit screen.
 * Reads the ACF car_colors checkbox to determine which colors to show.
 * Saves data as JSON into _car_color_variants post meta.
 *
 * @package obsidian-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the meta box on the Car CPT edit screen.
 */
function obsidian_add_color_variants_meta_box() {
	add_meta_box(
		'obsidian_color_variants',
		__( 'Color Variants — Inventory & Images', 'obsidian-booking' ),
		'obsidian_render_color_variants_meta_box',
		'car',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'obsidian_add_color_variants_meta_box' );

/**
 * Render the meta box content.
 *
 * @param WP_Post $post The current Car post.
 */
function obsidian_render_color_variants_meta_box( $post ) {

	wp_nonce_field( 'obsidian_save_color_variants', 'obsidian_color_variants_nonce' );

	$colors   = get_field( 'car_colors', $post->ID );
	$variants = obsidian_get_color_variants( $post->ID );

	if ( empty( $colors ) || ! is_array( $colors ) ) {
		echo '<p class="obsidian-meta-notice">';
		esc_html_e( 'Select colors in the "Car Details" field group above first, then save the post. Color variant options will appear here.', 'obsidian-booking' );
		echo '</p>';
		return;
	}

	echo '<p class="obsidian-meta-description">';
	esc_html_e( 'Set how many units you have of each color and upload a card image for each variant.', 'obsidian-booking' );
	echo '</p>';

	echo '<div class="obsidian-color-variants">';

	foreach ( $colors as $color ) {
		$key      = strtolower( $color );
		$hex      = obsidian_get_color_hex( $key );
		$units    = (int) ( $variants[ $key ]['units'] ?? 0 );
		$image_id = (int) ( $variants[ $key ]['image_id'] ?? 0 );
		$img_url  = $image_id > 0 ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';

		?>
		<div class="obsidian-variant-row">
			<div class="variant-swatch" style="background-color: <?php echo esc_attr( $hex ); ?>;"></div>

			<div class="variant-label">
				<?php echo esc_html( ucfirst( $color ) ); ?>
			</div>

			<div class="variant-units">
				<label for="variant_units_<?php echo esc_attr( $key ); ?>">
					<?php esc_html_e( 'Units:', 'obsidian-booking' ); ?>
				</label>
				<input type="number"
					   id="variant_units_<?php echo esc_attr( $key ); ?>"
					   name="obsidian_variant[<?php echo esc_attr( $key ); ?>][units]"
					   value="<?php echo esc_attr( $units ); ?>"
					   min="0"
					   step="1"
					   class="small-text" />
			</div>

			<div class="variant-image">
				<input type="hidden"
					   class="variant-image-id"
					   name="obsidian_variant[<?php echo esc_attr( $key ); ?>][image_id]"
					   value="<?php echo esc_attr( $image_id ); ?>" />

				<div class="variant-image-preview">
					<?php if ( $img_url ) : ?>
						<img src="<?php echo esc_url( $img_url ); ?>" alt="" />
					<?php endif; ?>
				</div>

				<button type="button" class="button obsidian-upload-image">
					<?php echo $img_url ? esc_html__( 'Change Image', 'obsidian-booking' ) : esc_html__( 'Choose Image', 'obsidian-booking' ); ?>
				</button>

				<?php if ( $img_url ) : ?>
					<button type="button" class="button obsidian-remove-image">
						<?php esc_html_e( 'Remove', 'obsidian-booking' ); ?>
					</button>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	echo '</div>';
}

/**
 * Save the color variants when the Car post is saved.
 *
 * @param int $post_id The Car post ID.
 */
function obsidian_save_color_variants( $post_id ) {

	if ( ! isset( $_POST['obsidian_color_variants_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( $_POST['obsidian_color_variants_nonce'], 'obsidian_save_color_variants' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( get_post_type( $post_id ) !== 'car' ) {
		return;
	}

	if ( ! isset( $_POST['obsidian_variant'] ) || ! is_array( $_POST['obsidian_variant'] ) ) {
		return;
	}

	$variants = array();

	foreach ( $_POST['obsidian_variant'] as $color => $data ) {
		$color_key = sanitize_text_field( strtolower( $color ) );
		$variants[ $color_key ] = array(
			'units'    => absint( $data['units'] ?? 0 ),
			'image_id' => absint( $data['image_id'] ?? 0 ),
		);
	}

	update_post_meta( $post_id, '_car_color_variants', wp_json_encode( $variants ) );
}
add_action( 'save_post', 'obsidian_save_color_variants' );
