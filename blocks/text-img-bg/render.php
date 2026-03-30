<?php
/**
 * Text Image Background Block Template.
 */

$title       = $attributes['title'] ?? '';
$description = $attributes['description'] ?? '';
$button_text = $attributes['buttonText'] ?? '';
$button_url  = $attributes['buttonUrl'] ?? '#';
$bg_url      = $attributes['bgUrl'] ?? '';

$theme_uri = get_stylesheet_directory_uri();
$bg_image  = strpos($bg_url, 'http') === 0 ? $bg_url : $theme_uri . $bg_url;
?>

<section <?php echo get_block_wrapper_attributes(['class' => 'obsidian-text-img-bg']); ?> style="background-image: url('<?php echo esc_url($bg_image); ?>');">
	<div class="cta-overlay"></div>
	
	<div class="cta-container">
		<div class="cta-content">
			<?php if ( $title ) : ?>
				<h2 class="cta-title"><?php echo wp_kses_post( $title ); ?></h2>
			<?php endif; ?>

			<?php if ( $description ) : ?>
				<p class="cta-description"><?php echo esc_html( $description ); ?></p>
			<?php endif; ?>

			<?php if ( $button_text ) : ?>
				<div class="cta-button-wrapper">
					<a href="<?php echo esc_url($button_url); ?>" class="wp-element-button text-img-bg-button">
						<?php echo esc_html($button_text); ?>
					</a>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
