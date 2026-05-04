<?php
/**
 * Hero Block Render Template
 *
 * @param   array $attributes The block attributes.
 * @param   string $content The block default content.
 * @param   WP_Block $block The block instance.
 *
 * @package child-obsidian-reserve
 */

$hero_title     = $attributes['title'] ?? '';
$description    = $attributes['description'] ?? '';
$button_text    = $attributes['buttonText'] ?? 'Explore Cars';
$button_url     = ( empty( $attributes['buttonUrl'] ) || '#' === $attributes['buttonUrl'] ) ? home_url( '/fleet/' ) : $attributes['buttonUrl'];
$image_url      = $attributes['imageUrl'] ?? '';
$location_label = $attributes['locationLabel'] ?? 'Location';
$date_label     = $attributes['dateLabel'] ?? 'Pick-up / Drop-off Dates';

// Handle theme-relative paths (starting with /assets/).
if ( ! empty( $image_url ) && 0 === strpos( $image_url, '/assets/' ) ) {
	$image_url = get_stylesheet_directory_uri() . $image_url;
}

// Default fallback image if none provided.
if ( empty( $image_url ) ) {
	$image_url = get_stylesheet_directory_uri() . '/assets/images/placeholder-car.png';
}

// Fetch Regions & Branches for the dynamic dropdown (same logic as fleet-filters).
$regions = get_terms(
	array(
		'taxonomy'   => 'region',
		'hide_empty' => false,
		'orderby'    => 'name',
		'order'      => 'ASC',
	)
);

// Manual Sort: Luzon -> Visayas -> Mindanao.
if ( ! is_wp_error( $regions ) && ! empty( $regions ) ) {
	usort(
		$regions,
		function ( $a, $b ) {
			$order = array(
				'Luzon'    => 1,
				'Visayas'  => 2,
				'Mindanao' => 3,
			);
			$val_a = $order[ $a->name ] ?? 999;
			$val_b = $order[ $b->name ] ?? 999;
			return $val_a <=> $val_b;
		}
	);
}

$regions_with_branches = array();

if ( ! is_wp_error( $regions ) ) {
	foreach ( $regions as $region ) {
		$branch_ids = get_posts(
			array(
				'post_type'      => 'location',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'title',
				'order'          => 'ASC',
				'tax_query'      => array(
					array(
						'taxonomy' => 'region',
						'field'    => 'term_id',
						'terms'    => $region->term_id,
					),
				),
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => 'location_status',
						'value'   => 'active',
						'compare' => '=',
					),
					array(
						'key'     => 'location_status',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		if ( empty( $branch_ids ) ) {
			continue;
		}

		$regions_with_branches[] = array(
			'region'   => $region,
			'branches' => array_map(
				function ( $id ) {
					return array(
						'id'   => (int) $id,
						'slug' => get_post_field( 'post_name', $id ),
						'name' => get_the_title( $id ),
					);
				},
				$branch_ids
			),
		);
	}
}

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'obsidian-hero' ) );
?>

<section <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="hero-container">
		
		<!-- Left Side: Content & Form -->
		<div class="hero-content">
			<h1 class="hero-title"><?php echo wp_kses_post( $hero_title ); ?></h1>
			<p class="hero-description"><?php echo esc_html( $description ); ?></p>

			<div class="hero-booking-form">
				<!-- Location Dropdown (Custom implementation for premium feel & forced downward opening) -->
				<div class="booking-field custom-dropdown" id="hero-location-dropdown">
					<div class="field-icon">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M12 2C8.13 2 5 5.13 5 9C5 14.25 12 22 12 22C12 22 19 14.25 19 9C19 5.13 15.87 2 12 2ZM12 11.5C10.62 11.5 9.5 10.38 9.5 9C9.5 7.62 10.62 6.5 12 6.5C13.38 6.5 14.5 7.62 14.5 9C14.5 10.38 13.38 11.5 12 11.5Z" fill="currentColor"/>
						</svg>
					</div>
					<div class="dropdown-selected">
						<span class="selected-text"><?php echo esc_html( $location_label ); ?></span>
					</div>
					<div class="select-arrow"></div>
					<ul class="dropdown-list">
						<li data-value="all"><?php esc_html_e( 'All Locations', 'child-obsidian-reserve' ); ?></li>
						<?php
						foreach ( $regions_with_branches as $entry ) :
							$region   = $entry['region'];
							$branches = $entry['branches'];
							?>
							<li class="dropdown-optgroup"><?php echo esc_html( $region->name ); ?></li>
							<li data-value="region_<?php echo esc_attr( $region->slug ); ?>" class="dropdown-region-option">
								<?php
								/* translators: %s: region name */
								printf( esc_html__( 'All in %s', 'child-obsidian-reserve' ), esc_html( $region->name ) );
								?>
							</li>
							<?php foreach ( $branches as $branch ) : ?>
								<li data-value="location_<?php echo esc_attr( $branch['slug'] ); ?>" class="dropdown-branch-option">
									<?php echo esc_html( $branch['name'] ); ?>
								</li>
							<?php endforeach; ?>
						<?php endforeach; ?>
					</ul>
					<input type="hidden" id="hero-location-value" value="">
				</div>

				<!-- Date Dropdown (Static for now) -->
				<div class="booking-field">
					<div class="field-icon">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M19 4H18V2H16V4H8V2H6V4H5C3.89 4 3.01 4.9 3.01 6L3 20C3 21.1 3.89 22 5 22H19C20.1 22 21 21.1 21 20V6C21 4.9 20.1 4 19 4ZM19 20H5V9H19V20ZM7 11H12V16H7V11Z" fill="currentColor"/>
						</svg>
					</div>
					<select class="booking-select">
						<option value="" disabled selected><?php echo esc_html( $date_label ); ?></option>
					</select>
					<div class="select-arrow"></div>
				</div>

				<!-- Button (Inheriting Global Style) -->
				<div class="button-wrapper wp-block-button is-style-solid-gold">
					<a href="<?php echo esc_url( $button_url ); ?>" class="wp-block-button__link" id="hero-explore-btn">
						<?php echo esc_html( $button_text ); ?>
					</a>
				</div>
			</div>
		</div>

		<!-- Right Side: Image -->
		<div class="hero-image-side">
			<?php if ( ! empty( $image_url ) ) : ?>
				<img src="<?php echo esc_url( $image_url ); ?>" alt="Dynamic Car Image" class="hero-image">
			<?php endif; ?>
		</div>

	</div>
</section>

<script>
(function() {
	'use strict';
	document.addEventListener('DOMContentLoaded', function() {
		var btn = document.getElementById('hero-explore-btn');
		var dropdown = document.getElementById('hero-location-dropdown');
		var valInput = document.getElementById('hero-location-value');
		var selectedText = dropdown.querySelector('.selected-text');
		var list = dropdown.querySelector('.dropdown-list');
		
		if (!btn || !dropdown) return;

		// Toggle dropdown
		dropdown.addEventListener('click', function(e) {
			e.stopPropagation();
			dropdown.classList.toggle('is-open');
		});

		// Close when clicking outside
		document.addEventListener('click', function() {
			dropdown.classList.remove('is-open');
		});

		// Handle selection
		list.addEventListener('click', function(e) {
			var li = e.target.closest('li');
			if (!li || li.classList.contains('dropdown-optgroup')) return;

			var val = li.getAttribute('data-value');
			var text = li.textContent.trim();

			valInput.value = val;
			selectedText.textContent = text;
			dropdown.classList.add('has-value');
			
			// Close after selection
			dropdown.classList.remove('is-open');
		});

		// Handle "Explore Cars" button
		btn.addEventListener('click', function(e) {
			var val = valInput.value;
			if (!val || val === 'all') return; // Just follow the normal link

			e.preventDefault();
			var url = btn.getAttribute('href');
			var separator = url.indexOf('?') !== -1 ? '&' : '?';

			if (val.indexOf('region_') === 0) {
				url += separator + 'region=' + encodeURIComponent(val.substring(7));
			} else if (val.indexOf('location_') === 0) {
				url += separator + 'location=' + encodeURIComponent(val.substring(9));
			}

			window.location.href = url;
		});
	});
})();
</script>
