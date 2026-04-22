<?php
/**
 * Render template for the Blog Grid block.
 * Displays all blog posts in a 3-column paginated grid.
 */

$section_title  = isset( $attributes['sectionTitle'] ) ? $attributes['sectionTitle'] : 'The Obsidian Journal.';
$posts_per_page = isset( $attributes['postsPerPage'] ) ? absint( $attributes['postsPerPage'] ) : 12;

// Handle current page for pagination
$current_page = get_query_var( 'paged' ) ? absint( get_query_var( 'paged' ) ) : 1;

// Handle category filter
$current_category = isset( $_GET['category'] ) ? sanitize_text_field( $_GET['category'] ) : '';

// Handle sort
$current_sort = isset( $_GET['sort'] ) && 'asc' === strtolower( $_GET['sort'] ) ? 'ASC' : 'DESC';

$args = array(
	'post_type'      => 'post',
	'post_status'    => 'publish',
	'posts_per_page' => $posts_per_page,
	'paged'          => $current_page,
	'orderby'        => 'date',
	'order'          => $current_sort,
);

if ( ! empty( $current_category ) && 'all' !== $current_category ) {
	$args['category_name'] = $current_category;
}

$blog_query = new WP_Query( $args );

$wrapper_attributes = get_block_wrapper_attributes( array(
	'class' => 'obsidian-blog-grid-block alignwide',
) );

// Helper: get featured image URL
if ( ! function_exists( 'obsidian_blog_grid_img' ) ) {
	function obsidian_blog_grid_img( $post_id ) {
		if ( has_post_thumbnail( $post_id ) ) {
			return get_the_post_thumbnail_url( $post_id, 'large' );
		}
		return get_stylesheet_directory_uri() . '/assets/images/featured-fleet/rolls-royce.webp';
	}
}
?>

<div <?php echo $wrapper_attributes; ?>>

	<div class="blog-grid-header">
		<?php if ( $section_title ) : ?>
			<h2 class="blog-grid-section-title"><?php echo esc_html( $section_title ); ?></h2>
		<?php endif; ?>

		<div class="blog-grid-controls">
			<?php
				$categories = get_categories( array(
					'orderby' => 'name',
					'order'   => 'ASC'
				) );
				if ( ! empty( $categories ) ) :
					$all_url = remove_query_arg( array('category', 'paged') );
			?>
				<div class="blog-grid-filters">
					<a href="<?php echo esc_url( $all_url ); ?>" class="blog-filter-btn <?php echo empty( $current_category ) || 'all' === $current_category ? 'active' : ''; ?>">All</a>
					<?php foreach ( $categories as $category ) : 
						$cat_url = add_query_arg( 'category', $category->slug, remove_query_arg( 'paged' ) );
					?>
						<a href="<?php echo esc_url( $cat_url ); ?>" class="blog-filter-btn <?php echo $current_category === $category->slug ? 'active' : ''; ?>"><?php echo esc_html( $category->name ); ?></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="blog-grid-sorter">
				<span class="sorter-label">Sort:</span>
				<?php 
					$desc_url = add_query_arg( 'sort', 'desc', remove_query_arg( 'paged' ) );
					$asc_url  = add_query_arg( 'sort', 'asc', remove_query_arg( 'paged' ) );
				?>
				<a href="<?php echo esc_url( $desc_url ); ?>" class="blog-sort-btn <?php echo 'DESC' === $current_sort ? 'active' : ''; ?>">Newest</a>
				<a href="<?php echo esc_url( $asc_url ); ?>" class="blog-sort-btn <?php echo 'ASC' === $current_sort ? 'active' : ''; ?>">Oldest</a>
			</div>
		</div>
	</div>

	<?php if ( ! $blog_query->have_posts() ) : ?>
		<p class="blog-grid-no-posts">No blog posts found matching this category.</p>
	<?php else : ?>

	<div class="blog-grid">
		<?php while ( $blog_query->have_posts() ) : $blog_query->the_post(); ?>
			<a href="<?php the_permalink(); ?>" class="blog-grid-card">

				<div class="blog-grid-img-wrap">
					<img
						src="<?php echo esc_url( obsidian_blog_grid_img( get_the_ID() ) ); ?>"
						alt="<?php echo esc_attr( get_the_title() ); ?>"
						class="blog-grid-img"
						loading="lazy"
					>
					<?php
						$categories = get_the_category();
						if ( ! empty( $categories ) ) :
					?>
					<span class="blog-grid-category-pill"><?php echo esc_html( $categories[0]->name ); ?></span>
					<?php endif; ?>
				</div>

				<div class="blog-grid-content">
					<h3 class="blog-grid-title"><?php the_title(); ?></h3>

					<p class="blog-grid-excerpt">
						<?php
							$excerpt = get_the_excerpt();
							if ( empty( $excerpt ) ) {
								$excerpt = wp_trim_words( get_the_content(), 18, '...' );
							}
							echo esc_html( $excerpt );
						?>
					</p>

					<div class="blog-grid-meta">
						<div class="blog-grid-author">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
								<circle cx="12" cy="12" r="10"></circle>
								<circle cx="12" cy="10" r="3"></circle>
								<path d="M7 20.662V19a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v1.662"></path>
							</svg>
							<span><?php echo esc_html( get_the_author_meta( 'display_name' ) ); ?></span>
						</div>
						<span class="blog-grid-date"><?php echo get_the_date( 'M j, Y' ); ?></span>
					</div>
				</div>

			</a>
		<?php endwhile; wp_reset_postdata(); ?>
	</div>

	<?php
	// Pagination
	$total_pages = $blog_query->max_num_pages;
	if ( $total_pages > 1 ) :
		$pagination = paginate_links( array(
			'base'      => str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ),
			'format'    => '?paged=%#%',
			'current'   => $current_page,
			'total'     => $total_pages,
			'prev_text' => '&larr;',
			'next_text' => '&rarr;',
			'type'      => 'array',
		) );
		if ( $pagination ) :
	?>
		<nav class="blog-grid-pagination" aria-label="Blog pages">
			<?php foreach ( $pagination as $page_link ) : ?>
				<?php echo $page_link; ?>
			<?php endforeach; ?>
		</nav>
	<?php
		endif;
	endif;
	?>
	<?php endif; ?>

</div>
