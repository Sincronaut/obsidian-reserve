<?php
/**
 * 404 Error Block Template.
 *
 * @package child-obsidian-reserve
 */

$title       = $attributes['title'] ?? '404';
$subtitle    = $attributes['subtitle'] ?? 'Page Not Found';
$description = $attributes['description'] ?? 'The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.';
$button_text = $attributes['buttonText'] ?? 'Return to Home';
$button_url  = $attributes['buttonUrl'] ?? '/';

?>

<section <?php echo get_block_wrapper_attributes( array( 'class' => 'obsidian-404' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="obsidian-404-container">
		<div class="obsidian-404-content reveal slide-up">
			<h1 class="obsidian-404-title"><?php echo esc_html( $title ); ?></h1>
			<h2 class="obsidian-404-subtitle"><?php echo esc_html( $subtitle ); ?></h2>
			<p class="obsidian-404-description"><?php echo wp_kses_post( $description ); ?></p>
			
			<div class="obsidian-404-button-wrapper wp-block-buttons">
				<div class="wp-block-button is-style-solid-gold">
					<a href="<?php echo esc_url( $button_url ); ?>" class="wp-block-button__link wp-element-button">
						<?php echo esc_html( $button_text ); ?>
					</a>
				</div>
			</div>
		</div>
	</div>
</section>
