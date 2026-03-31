<?php
/**
 * Text Image Background Block Template.
 */

$title       = $attributes['title'] ?? '';
$description = $attributes['description'] ?? '';
$button_text = $attributes['buttonText'] ?? '';
$button_url  = $attributes['buttonUrl'] ?? '#';
$bg_url      = $attributes['bgUrl'] ?? '';
$alignment    = $attributes['alignment'] ?? 'left';
$has_smudges  = $attributes['hasGoldSmudges'] ?? false;
$title_tag    = $attributes['titleTag'] ?? 'h2';
$overlay_type = $attributes['overlayType'] ?? 'gradient';

$theme_uri = get_stylesheet_directory_uri();
$bg_image  = '';
if ( ! empty( $bg_url ) ) {
    $bg_image = strpos($bg_url, 'http') === 0 ? $bg_url : $theme_uri . $bg_url;
}

$classes = 'obsidian-text-img-bg cta-layout-' . esc_attr($alignment);
if ( $has_smudges ) {
    $classes .= ' has-gold-smudges';
}

$style_attr = '';
if ( $bg_image ) {
    $style_attr = 'background-image: url(\'' . esc_url($bg_image) . '\');';
} else {
    $style_attr = 'background-color: #0B0B0B;';
}
?>

<section <?php echo get_block_wrapper_attributes(['class' => $classes]); ?> style="<?php echo $style_attr; ?>">
	<?php if ( $bg_image && $overlay_type !== 'none' ) : ?>
		<div class="cta-overlay <?php echo esc_attr($overlay_type); ?>"></div>
	<?php endif; ?>
	
	<div class="cta-container">
		<div class="cta-content">
			<?php if ( $title ) : ?>
				<<?php echo esc_attr($title_tag); ?> class="cta-title"><?php echo wp_kses_post( $title ); ?></<?php echo esc_attr($title_tag); ?>>
			<?php endif; ?>

			<?php if ( $description ) : ?>
				<p class="cta-description"><?php echo wp_kses_post( $description ); ?></p>
			<?php endif; ?>

			<?php if ( $button_text ) : ?>
				<div class="cta-button-wrapper wp-block-buttons">
					<div class="wp-block-button is-style-solid-gold">
						<a href="<?php echo esc_url($button_url); ?>" class="wp-block-button__link wp-element-button">
							<?php echo esc_html($button_text); ?>
						</a>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
