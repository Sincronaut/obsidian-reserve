<?php
/**
 * Render template for the Top Reads Journal block (Posts 6-8).
 */

$section_title  = isset( $attributes['sectionTitle'] ) ? $attributes['sectionTitle'] : 'Top Reads from the Journal.';

// Fetch top 3 blogs, skipping the first 5 (which are in the trending block)
$top_reads_query = function_exists( 'obsidian_reserve_get_top_blogs' ) ? obsidian_reserve_get_top_blogs( 3, 5 ) : null;

$wrapper_attributes = get_block_wrapper_attributes( array(
	'class' => 'obsidian-top-reads-block alignwide',
) );

if ( ! $top_reads_query || ! $top_reads_query->have_posts() ) {
	// If no posts are found, silently return or show a message in editor
	if ( defined('REST_REQUEST') && REST_REQUEST ) {
		echo '<div ' . $wrapper_attributes . '><p>No additional top reads found.</p></div>';
	}
	return;
}

// Helper to get image
if ( ! function_exists( 'obsidian_get_top_reads_image' ) ) {
	function obsidian_get_top_reads_image( $post_id, $size = 'large' ) {
		if ( has_post_thumbnail( $post_id ) ) {
			return get_the_post_thumbnail_url( $post_id, $size );
		}
		return get_stylesheet_directory_uri() . '/assets/images/featured-fleet/rolls-royce.webp';
	}
}

// Helper to get excerpt
if ( ! function_exists( 'obsidian_get_top_reads_excerpt' ) ) {
	function obsidian_get_top_reads_excerpt( $post ) {
		$excerpt = get_the_excerpt( $post->ID );
		if ( empty( $excerpt ) ) {
			$excerpt = wp_trim_words( $post->post_content, 20, '...' );
		}
		return esc_html( $excerpt );
	}
}
?>

<div <?php echo $wrapper_attributes; ?>>
	
	<div class="top-reads-header">
		<?php if ( $section_title ) : ?>
			<h2 class="top-reads-section-title"><?php echo esc_html( $section_title ); ?></h2>
		<?php endif; ?>
	</div>

	<div class="top-reads-grid">
		<?php while ( $top_reads_query->have_posts() ) : $top_reads_query->the_post(); ?>
			<a href="<?php echo esc_url( get_permalink() ); ?>" class="top-reads-card">
				
				<div class="top-reads-img-wrap">
					<img src="<?php echo esc_url( obsidian_get_top_reads_image( get_the_ID(), 'large' ) ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" class="top-reads-img">
				</div>

				<div class="top-reads-content">
					<h3 class="top-reads-title"><?php echo esc_html( get_the_title() ); ?></h3>
					<p class="top-reads-excerpt"><?php echo obsidian_get_top_reads_excerpt( get_post() ); ?></p>
					
					<div class="top-reads-meta">
						<div class="meta-author">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<circle cx="12" cy="12" r="10"></circle>
								<circle cx="12" cy="10" r="3"></circle>
								<path d="M7 20.662V19a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v1.662"></path>
							</svg>
							<span class="author-name"><?php echo esc_html( get_the_author_meta( 'display_name' ) ); ?></span>
						</div>
						<div class="meta-date">
							<span><?php echo get_the_date( 'F j, Y' ); ?></span>
						</div>
					</div>
				</div>

			</a>
		<?php endwhile; wp_reset_postdata(); ?>
	</div>

</div>
