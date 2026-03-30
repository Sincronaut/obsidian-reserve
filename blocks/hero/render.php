<?php
/**
 * Hero Block Render Template
 *
 * @param   array $attributes The block attributes.
 * @param   string $content The block default content.
 * @param   WP_Block $block The block instance.
 *
 * @package child-obsidian-reserve
 */

$title = $attributes['title'] ?? '';
$description = $attributes['description'] ?? '';
$button_text = $attributes['buttonText'] ?? 'Explore Cars';
$button_url = $attributes['buttonUrl'] ?? '#';
$image_url = $attributes['imageUrl'] ?? '';
$location_label = $attributes['locationLabel'] ?? 'Location';
$date_label = $attributes['dateLabel'] ?? 'Pick-up / Drop-off Dates';

// Handle theme-relative paths (starting with /assets/)
if ( ! empty( $image_url ) && strpos( $image_url, '/assets/' ) === 0 ) {
	$image_url = get_stylesheet_directory_uri() . $image_url;
}

// Default fallback image if none provided
if ( empty( $image_url ) ) {
	$image_url = get_stylesheet_directory_uri() . '/assets/images/placeholder-car.png';
}

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'obsidian-hero' ) );
?>

<section <?php echo $wrapper_attributes; ?>>
	<div class="hero-container">
		
		<!-- Left Side: Content & Form -->
		<div class="hero-content">
			<h1 class="hero-title"><?php echo wp_kses_post( $title ); ?></h1>
			<p class="hero-description"><?php echo esc_html( $description ); ?></p>

			<div class="hero-booking-form">
				<!-- Location Dropdown -->
				<div class="booking-field">
					<div class="field-icon">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M12 2C8.13 2 5 5.13 5 9C5 14.25 12 22 12 22C12 22 19 14.25 19 9C19 5.13 15.87 2 12 2ZM12 11.5C10.62 11.5 9.5 10.38 9.5 9C9.5 7.62 10.62 6.5 12 6.5C13.38 6.5 14.5 7.62 14.5 9C14.5 10.38 13.38 11.5 12 11.5Z" fill="currentColor"/>
						</svg>
					</div>
					<select class="booking-select">
						<option value="" disabled selected><?php echo esc_html( $location_label ); ?></option>
						<!-- Add options dynamically later if needed -->
					</select>
					<div class="select-arrow"></div>
				</div>

				<!-- Date Dropdown -->
				<div class="booking-field">
					<div class="field-icon">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M19 4H18V2H16V4H8V2H6V4H5C3.89 4 3.01 4.9 3.01 6L3 20C3 21.1 3.89 22 5 22H19C20.1 22 21 21.1 21 20V6C21 4.9 20.1 4 19 4ZM19 20H5V9H19V20ZM7 11H12V16H7V11Z" fill="currentColor"/>
						</svg>
					</div>
					<select class="booking-select">
						<option value="" disabled selected><?php echo esc_html( $date_label ); ?></option>
					</select>
					<div class="select-arrow"></div>
				</div>

				<!-- Button (Inheriting Global Style) -->
				<div class="button-wrapper wp-block-button is-style-solid-gold">
					<a href="<?php echo esc_url( $button_url ); ?>" class="wp-block-button__link">
						<?php echo esc_html( $button_text ); ?>
					</a>
				</div>
			</div>
		</div>

		<!-- Right Side: Image -->
		<div class="hero-image-side">
			<?php if ( ! empty( $image_url ) ) : ?>
				<img src="<?php echo esc_url( $image_url ); ?>" alt="Dynamic Car Image" class="hero-image">
			<?php endif; ?>
		</div>

	</div>
</section>
