<?php
/**
 * Car Grid Block — Render Template
 *
 * Queries all published Cars and renders them as booking-ready cards.
 * Each card carries a data-car-id attribute for the booking modal (Phase 5.3).
 *
 * @param array    $attributes The block attributes.
 * @param string   $content    The block default content.
 * @param WP_Block $block      The block instance.
 *
 * @package child-obsidian-reserve
 */

$title       = $attributes['title'] ?? '';
$description = $attributes['description'] ?? '';
$logged_in   = is_user_logged_in();
$login_url   = wp_login_url( get_permalink() );

$cars = get_posts( array(
	'post_type'      => 'car',
	'post_status'    => 'publish',
	'posts_per_page' => -1,
	'orderby'        => 'title',
	'order'          => 'ASC',
) );

$car_classes = get_terms( array(
	'taxonomy'   => 'car_class',
	'hide_empty' => true,
) );
?>

<section <?php echo get_block_wrapper_attributes( array( 'class' => 'obsidian-car-grid' ) ); ?>>

	<div class="car-grid-container">

		<!-- Section Header -->
		<header class="car-grid-header">
			<?php if ( $title ) : ?>
				<h2 class="car-grid-title"><?php echo wp_kses_post( $title ); ?></h2>
			<?php endif; ?>
			<?php if ( $description ) : ?>
				<p class="car-grid-description"><?php echo wp_kses_post( $description ); ?></p>
			<?php endif; ?>
		</header>

		<!-- Class Filter Tabs -->
		<?php if ( ! empty( $car_classes ) && ! is_wp_error( $car_classes ) ) : ?>
		<nav class="car-grid-filters" aria-label="Filter by car class">
			<button class="filter-btn active" data-filter="all">All</button>
			<?php foreach ( $car_classes as $term ) : ?>
				<button class="filter-btn" data-filter="<?php echo esc_attr( $term->slug ); ?>">
					<?php echo esc_html( $term->name ); ?>
				</button>
			<?php endforeach; ?>
		</nav>
		<?php endif; ?>

		<!-- Car Cards Grid -->
		<?php if ( ! empty( $cars ) ) : ?>
		<div class="car-cards">
			<?php foreach ( $cars as $car ) :
				$car_id     = $car->ID;
				$make       = get_field( 'car_make', $car_id ) ?: '';
				$model      = get_field( 'car_model', $car_id ) ?: '';
				$year       = (int) get_field( 'car_year', $car_id );
				$daily_rate = (float) get_field( 'car_daily_rate', $car_id );
				$units      = (int) get_field( 'car_total_units', $car_id );
				$status     = get_field( 'car_status', $car_id ) ?: 'available';
				$thumb      = get_the_post_thumbnail_url( $car_id, 'large' );

				$terms      = wp_get_post_terms( $car_id, 'car_class', array( 'fields' => 'all' ) );
				$class_name = '';
				$class_slug = '';
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					$class_name = $terms[0]->name;
					$class_slug = $terms[0]->slug;
				}

				if ( $status !== 'available' ) {
					continue;
				}
			?>
			<article class="car-card" data-car-id="<?php echo esc_attr( $car_id ); ?>" data-class="<?php echo esc_attr( $class_slug ); ?>">

				<!-- Card Image -->
				<div class="car-card-image">
					<?php if ( $thumb ) : ?>
						<img src="<?php echo esc_url( $thumb ); ?>"
							 alt="<?php echo esc_attr( get_the_title( $car_id ) ); ?>"
							 loading="lazy" />
					<?php else : ?>
						<div class="car-card-placeholder"></div>
					<?php endif; ?>

					<?php if ( $class_name ) : ?>
						<span class="car-class-badge"><?php echo esc_html( $class_name ); ?></span>
					<?php endif; ?>
				</div>

				<!-- Card Body -->
				<div class="car-card-body">
					<h3 class="car-card-name"><?php echo esc_html( get_the_title( $car_id ) ); ?></h3>

					<div class="car-card-meta">
						<?php if ( $year ) : ?>
							<span class="car-meta-item"><?php echo esc_html( $year ); ?></span>
						<?php endif; ?>
						<?php if ( $make ) : ?>
							<span class="car-meta-divider">&middot;</span>
							<span class="car-meta-item"><?php echo esc_html( $make ); ?></span>
						<?php endif; ?>
					</div>

					<div class="car-card-footer">
						<div class="car-card-pricing">
							<span class="car-rate"><?php echo esc_html( '₱' . number_format( $daily_rate, 0 ) ); ?></span>
							<span class="car-rate-label">/ day</span>
						</div>

						<div class="car-card-units">
							<span class="units-dot"></span>
							<span class="units-text"><?php echo esc_html( $units ); ?> available</span>
						</div>
					</div>

					<?php if ( $logged_in ) : ?>
						<button class="car-book-btn" data-car-id="<?php echo esc_attr( $car_id ); ?>">
							Book Now
						</button>
					<?php else : ?>
						<a class="car-book-btn car-book-btn--login" href="<?php echo esc_url( $login_url ); ?>">
							Sign In to Book
						</a>
					<?php endif; ?>
				</div>

			</article>
			<?php endforeach; ?>
		</div>

		<?php else : ?>
			<p class="car-grid-empty">No vehicles are currently available. Please check back soon.</p>
		<?php endif; ?>

	</div>

</section>

<script>
(function() {
	document.addEventListener('DOMContentLoaded', function() {
		var filters = document.querySelectorAll('.car-grid-filters .filter-btn');
		var cards   = document.querySelectorAll('.car-card');

		filters.forEach(function(btn) {
			btn.addEventListener('click', function() {
				filters.forEach(function(b) { b.classList.remove('active'); });
				btn.classList.add('active');

				var filter = btn.getAttribute('data-filter');

				cards.forEach(function(card) {
					if (filter === 'all' || card.getAttribute('data-class') === filter) {
						card.style.display = '';
					} else {
						card.style.display = 'none';
					}
				});
			});
		});
	});
})();
</script>
