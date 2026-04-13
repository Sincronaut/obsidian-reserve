<?php
/**
 * Car Color Variants Meta Box
 *
 * Renders per-color inventory (units + 5 gallery images) inputs on the Car edit screen.
 * Reads the ACF car_colors checkbox to determine which colors to show.
 * Saves data as JSON into _car_color_variants post meta.
 *
 * @package obsidian-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OBSIDIAN_IMAGES_PER_COLOR', 6 );

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

	$total_units = (int) get_field( 'car_total_units', $post->ID );
	$allocated   = 0;
	foreach ( $colors as $c ) {
		$allocated += (int) ( $variants[ strtolower( $c ) ]['units'] ?? 0 );
	}

	?>
	<p class="obsidian-meta-description">
		<?php esc_html_e( 'Distribute your total units across colors and upload up to 5 images per color. The sum must not exceed Total Units.', 'obsidian-booking' ); ?>
	</p>

	<div class="obsidian-units-counter<?php echo $allocated > $total_units ? ' over-limit' : ''; ?>"
		 data-total="<?php echo esc_attr( $total_units ); ?>">
		<span class="counter-allocated"><?php echo esc_html( $allocated ); ?></span>
		/ <span class="counter-total"><?php echo esc_html( $total_units ); ?></span>
		<?php esc_html_e( 'units allocated', 'obsidian-booking' ); ?>
		<span class="counter-warning"><?php esc_html_e( '— exceeds Total Units!', 'obsidian-booking' ); ?></span>
	</div>

	<div class="obsidian-color-variants">
	<?php

	foreach ( $colors as $color ) {
		$key   = strtolower( $color );
		$hex   = obsidian_get_color_hex( $key );
		$units = (int) ( $variants[ $key ]['units'] ?? 0 );

		// Support both old (image_id) and new (images[]) format
		$images = array();
		if ( ! empty( $variants[ $key ]['images'] ) && is_array( $variants[ $key ]['images'] ) ) {
			$images = $variants[ $key ]['images'];
		} elseif ( ! empty( $variants[ $key ]['image_id'] ) ) {
			$images = array( (int) $variants[ $key ]['image_id'] );
		}

		?>
		<div class="obsidian-variant-row">
			<div class="variant-header">
				<div class="variant-swatch" style="background-color: <?php echo esc_attr( $hex ); ?>;"></div>
				<div class="variant-label"><?php echo esc_html( ucfirst( $color ) ); ?></div>
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
						   class="small-text variant-units-input" />
				</div>
			</div>

			<div class="variant-images-grid">
				<?php for ( $i = 0; $i < OBSIDIAN_IMAGES_PER_COLOR; $i++ ) :
					$img_id  = (int) ( $images[ $i ] ?? 0 );
					$img_url = $img_id > 0 ? wp_get_attachment_image_url( $img_id, 'thumbnail' ) : '';
				?>
				<div class="variant-image-slot" data-index="<?php echo esc_attr( $i ); ?>">
					<span class="variant-image-label"><?php echo esc_html( $i === 0 ? 'Card Thumbnail' : 'Image ' . $i ); ?></span>

					<input type="hidden"
						   class="variant-image-id"
						   name="obsidian_variant[<?php echo esc_attr( $key ); ?>][images][<?php echo esc_attr( $i ); ?>]"
						   value="<?php echo esc_attr( $img_id ); ?>" />

					<div class="variant-image-preview">
						<?php if ( $img_url ) : ?>
							<img src="<?php echo esc_url( $img_url ); ?>" alt="" />
						<?php endif; ?>
					</div>

					<div class="variant-image-actions">
						<button type="button" class="button button-small obsidian-upload-image">
							<?php echo $img_url ? esc_html__( 'Change', 'obsidian-booking' ) : esc_html__( 'Upload', 'obsidian-booking' ); ?>
						</button>
						<?php if ( $img_url ) : ?>
							<button type="button" class="button button-small obsidian-remove-image">&times;</button>
						<?php endif; ?>
					</div>
				</div>
				<?php endfor; ?>
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

	$total_units = (int) get_field( 'car_total_units', $post_id );
	$variants    = array();
	$running_sum = 0;

	foreach ( $_POST['obsidian_variant'] as $color => $data ) {
		$color_key = sanitize_text_field( strtolower( $color ) );
		$requested = absint( $data['units'] ?? 0 );

		$allowed = min( $requested, max( 0, $total_units - $running_sum ) );
		$running_sum += $allowed;

		$images = array();
		if ( isset( $data['images'] ) && is_array( $data['images'] ) ) {
			for ( $i = 0; $i < OBSIDIAN_IMAGES_PER_COLOR; $i++ ) {
				$images[] = absint( $data['images'][ $i ] ?? 0 );
			}
		}

		$variants[ $color_key ] = array(
			'units'  => $allowed,
			'images' => $images,
		);
	}

	update_post_meta( $post_id, '_car_color_variants', wp_json_encode( $variants ) );
}
add_action( 'save_post', 'obsidian_save_color_variants' );
