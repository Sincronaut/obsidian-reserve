<?php
/**
 * Three Cards Block Template.
 *
 * @param array $attributes The block attributes.
 * @param string $content The block default content.
 * @param WP_Block $block The block instance.
 */

$title       = $attributes['title'] ?? '';
$description = $attributes['description'] ?? '';
$cards       = $attributes['cards'] ?? [];

// Helper to handle relative image paths
$theme_uri = get_stylesheet_directory_uri();
?>

<section <?php echo get_block_wrapper_attributes(['class' => 'obsidian-three-cards']); ?>>
	<!-- Decorative Smudges -->
	<div class="smudge smudge-top-right"></div>
	<div class="smudge smudge-bot-left"></div>

	<div class="three-cards-container">
		<header class="three-cards-header">
			<?php if ( $title ) : ?>
				<h2 class="three-cards-title"><?php echo wp_kses_post( $title ); ?></h2>
			<?php endif; ?>

			<?php if ( $description ) : ?>
				<p class="three-cards-description"><?php echo esc_html( $description ); ?></p>
			<?php endif; ?>
		</header>

		<div class="cards-grid">
			<?php foreach ( $cards as $index => $card ) : 
				$card_icon = strpos($card['iconUrl'], 'http') === 0 ? $card['iconUrl'] : $theme_uri . $card['iconUrl'];
				// Alternate image position: 1st/3rd on left, 2nd on right
				$img_pos = ($index % 2 === 0) ? 'img-left' : 'img-right';
			?>
				<div class="card-item <?php echo esc_attr($img_pos); ?>">
					<div class="card-image-column">
						<img src="<?php echo esc_url( $card_icon ); ?>" alt="" class="card-icon">
					</div>
					<div class="card-text-column">
						<h3 class="card-title"><?php echo esc_html( $card['title'] ); ?></h3>
						<p class="card-description"><?php echo esc_html( $card['description'] ); ?></p>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
