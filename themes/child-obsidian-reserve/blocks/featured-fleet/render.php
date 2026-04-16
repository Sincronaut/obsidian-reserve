<?php
/**
 * Featured Fleet Showcase Block Template.
 */

$main_title       = $attributes['mainTitle'] ?? '';
$main_description = $attributes['mainDescription'] ?? '';
$main_button_text = $attributes['mainButtonText'] ?? '';
$main_button_url  = $attributes['mainButtonUrl'] ?? '#';
$main_image_url   = $attributes['mainImageUrl'] ?? '';
$grid_items        = $attributes['gridItems'] ?? [];

$theme_uri = get_stylesheet_directory_uri();
$main_img_src = ! empty( $main_image_url ) ? ( strpos($main_image_url, 'http') === 0 ? $main_image_url : $theme_uri . $main_image_url ) : '';

?>

<section <?php echo get_block_wrapper_attributes(['class' => 'featured-fleet-section']); ?>>
	<div class="featured-fleet-container">
		
		<!-- Main Feature Card -->
		<div class="fleet-main-card">
			<div class="fleet-main-header">
				<div class="fleet-main-text">
					<?php if ( $main_title ) : ?>
						<h2 class="fleet-main-title"><?php echo wp_kses_post( $main_title ); ?></h2>
					<?php endif; ?>
					
					<?php if ( $main_description ) : ?>
						<p class="fleet-main-description"><?php echo wp_kses_post( $main_description ); ?></p>
					<?php endif; ?>
				</div>

				<?php if ( $main_button_text ) : ?>
					<div class="fleet-main-cta wp-block-buttons">
						<div class="wp-block-button is-style-solid-gold">
							<a href="<?php echo esc_url($main_button_url); ?>" class="wp-block-button__link wp-element-button">
								<?php echo esc_html($main_button_text); ?>
							</a>
						</div>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( $main_img_src ) : ?>
				<div class="fleet-main-image">
					<img src="<?php echo esc_url($main_img_src); ?>" alt="<?php echo esc_attr(strip_tags($main_title)); ?>" />
				</div>
			<?php endif; ?>
		</div>

		<!-- Grid of Secondary Cards -->
		<?php if ( ! empty( $grid_items ) ) : ?>
			<div class="fleet-grid">
				<?php foreach ( $grid_items as $item ) : 
					$item_img = ! empty( $item['imageUrl'] ) ? ( strpos($item['imageUrl'], 'http') === 0 ? $item['imageUrl'] : $theme_uri . $item['imageUrl'] ) : '';
				?>
					<div class="fleet-grid-item">
						<?php if ( $item_img ) : ?>
							<div class="fleet-item-image">
								<img src="<?php echo esc_url($item_img); ?>" alt="<?php echo esc_attr($item['title']); ?>" />
							</div>
						<?php endif; ?>

						<div class="fleet-item-content">
							<?php if ( $item['title'] ) : ?>
								<h3 class="fleet-item-title"><?php echo wp_kses_post( $item['title'] ); ?></h3>
							<?php endif; ?>

							<?php if ( $item['description'] ) : ?>
								<p class="fleet-item-description"><?php echo wp_kses_post( $item['description'] ); ?></p>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

	</div>
</section>
