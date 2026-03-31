<?php
/**
 * Image Text Block Template.
 */

$eyebrow     = $attributes['eyebrow'] ?? '';
$title       = $attributes['title'] ?? '';
$description = $attributes['description'] ?? '';
$button_text = $attributes['buttonText'] ?? '';
$button_url  = $attributes['buttonUrl'] ?? '#';
$image_url   = $attributes['imageUrl'] ?? '';

$theme_uri = get_stylesheet_directory_uri();
$image_src = '';
if ( ! empty( $image_url ) ) {
    $image_src = strpos($image_url, 'http') === 0 ? $image_url : $theme_uri . $image_url;
}
?>

<section <?php echo get_block_wrapper_attributes(['class' => 'obsidian-img-text']); ?>>
	
	<div class="img-text-image-col">
		<?php if ( $image_src ) : ?>
			<img src="<?php echo esc_url($image_src); ?>" alt="Section Image" loading="lazy">
		<?php endif; ?>
	</div>
	
	<div class="img-text-content-col">
		<div class="img-text-content-inner">
			<?php if ( $eyebrow ) : ?>
				<h6 class="img-text-eyebrow text-gold"><?php echo esc_html( $eyebrow ); ?></h6>
			<?php endif; ?>

			<?php if ( $title ) : ?>
				<h2 class="img-text-title"><?php echo wp_kses_post( $title ); ?></h2>
			<?php endif; ?>

			<?php if ( $description ) : ?>
				<div class="img-text-description"><?php echo wp_kses_post( $description ); ?></div>
			<?php endif; ?>

			<?php if ( $button_text ) : ?>
				<div class="img-text-button-wrapper wp-block-buttons">
					<div class="wp-block-button is-style-outline-gold">
						<a href="<?php echo esc_url($button_url); ?>" class="wp-block-button__link wp-element-button">
							<?php echo esc_html($button_text); ?>
						</a>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>

</section>
