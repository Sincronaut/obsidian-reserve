<?php
/**
 * Render template for the Trending Blogs block.
 */

$section_title        = isset( $attributes['sectionTitle'] ) ? $attributes['sectionTitle'] : 'The Season. The Top Reads.';
$right_panel_subtitle = isset( $attributes['rightPanelSubtitle'] ) ? $attributes['rightPanelSubtitle'] : 'What <span class="text-gold">Obsidian Clients</span> Are Reading This Season Right Now.';

// Fetch top 5 blogs using our engine
$top_blogs_query = function_exists( 'obsidian_reserve_get_top_blogs' ) ? obsidian_reserve_get_top_blogs( 5 ) : null;

$wrapper_attributes = get_block_wrapper_attributes( array(
	'class' => 'obsidian-trending-blogs-block alignwide',
) );

if ( ! $top_blogs_query || ! $top_blogs_query->have_posts() ) {
	echo '<div ' . $wrapper_attributes . '><p>No trending blogs found. Run the seeder script first.</p></div>';
	return;
}

// Extract posts
$posts = $top_blogs_query->posts;
$top_post = array_shift( $posts ); // Get the first post (Top 1)
$list_posts = $posts; // The remaining 4 posts

// Helper to get image
function obsidian_get_trending_image( $post_id, $size = 'large' ) {
	if ( has_post_thumbnail( $post_id ) ) {
		return get_the_post_thumbnail_url( $post_id, $size );
	}
	// Fallback if no thumbnail (shouldn't happen with seeder, but good practice)
	return get_stylesheet_directory_uri() . '/assets/images/featured-fleet/rolls-royce.webp';
}
?>

<div <?php echo $wrapper_attributes; ?>>
	
	<?php if ( $section_title ) : ?>
		<h2 class="trending-section-title"><?php echo esc_html( $section_title ); ?></h2>
	<?php endif; ?>

	<div class="trending-columns">
		
		<!-- LEFT COLUMN (Top 1) -->
		<div class="trending-col trending-col-main">
			<a href="<?php echo esc_url( get_permalink( $top_post->ID ) ); ?>" class="trending-main-card">
				<div class="trending-main-img-wrap">
					<img src="<?php echo esc_url( obsidian_get_trending_image( $top_post->ID, 'large' ) ); ?>" alt="<?php echo esc_attr( get_the_title( $top_post->ID ) ); ?>" class="trending-main-img">
				</div>
				<div class="trending-main-content">
					<?php 
						$categories = get_the_category( $top_post->ID );
						$category_name = ! empty( $categories ) ? esc_html( $categories[0]->name ) : 'Guide';
					?>
					<span class="trending-pill"><?php echo $category_name; ?></span>
					<h3 class="trending-main-title"><?php echo esc_html( get_the_title( $top_post->ID ) ); ?></h3>
					<p class="trending-main-excerpt">
						<?php 
							// Get a short excerpt (approx 12 words)
							$excerpt = get_the_excerpt( $top_post->ID );
							if ( empty( $excerpt ) ) {
								$excerpt = wp_trim_words( $top_post->post_content, 12, '...' );
							}
							echo esc_html( $excerpt );
						?>
					</p>
				</div>
			</a>
		</div>

		<!-- RIGHT COLUMN (Top 2-5) -->
		<div class="trending-col trending-col-list">
			<div class="trending-list-card">
				<?php if ( $right_panel_subtitle ) : ?>
					<p class="trending-list-subtitle"><?php echo wp_kses_post( $right_panel_subtitle ); ?></p>
				<?php endif; ?>

				<div class="trending-list-items">
					<?php foreach ( $list_posts as $list_post ) : ?>
						<a href="<?php echo esc_url( get_permalink( $list_post->ID ) ); ?>" class="trending-list-item">
							<div class="trending-item-img-wrap">
								<img src="<?php echo esc_url( obsidian_get_trending_image( $list_post->ID, 'thumbnail' ) ); ?>" alt="<?php echo esc_attr( get_the_title( $list_post->ID ) ); ?>" class="trending-item-img">
							</div>
							<div class="trending-item-content">
								<h4 class="trending-item-title"><?php echo esc_html( get_the_title( $list_post->ID ) ); ?></h4>
							</div>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
		</div>

	</div>
</div>
