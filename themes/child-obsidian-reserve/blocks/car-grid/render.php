<?php
/**
 * Car Grid Block — Render Template
 *
 * Queries all published Cars and renders them as booking-ready cards
 * with per-color swatches, image swapping, and per-color unit counts.
 *
 * Phase 11 additions:
 *   - Each card carries `data-branches="12,15"` and `data-regions="luzon,visayas"`
 *     so the Fleet Filters block can hide/show without a server round-trip.
 *   - Each color swatch carries a `data-units-by-scope` JSON map of
 *     scope → units (`all`, `branch_<id>`, `region_<slug>`), so when the user
 *     picks a location the displayed unit count flips to that scope.
 *   - A small "Available at: …" badge shows every branch the car is stocked at.
 *   - Listens for `obsidianFleet:change` from the Fleet Filters block; the
 *     internal class-button row is hidden when `showInternalFilters` is false
 *     (set on the Fleet page so the sidebar is the only filter).
 *
 * @param array    $attributes The block attributes.
 * @param string   $content    The block default content.
 * @param WP_Block $block      The block instance.
 *
 * @package child-obsidian-reserve
 */

$title                 = $attributes['title'] ?? '';
$description           = $attributes['description'] ?? '';
$show_internal_filters = ! isset( $attributes['showInternalFilters'] ) || $attributes['showInternalFilters'];
$show_header           = ! isset( $attributes['showHeader'] ) || $attributes['showHeader'];
$logged_in             = is_user_logged_in();
$login_url             = wp_login_url( get_permalink() );

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

/**
 * Build the per-color "units by scope" map used by the JS to flip numbers
 * when a filter is selected.
 *
 * Returned shape:
 *   [
 *     'orange' => [ 'all' => 5, 'branch_12' => 3, 'branch_15' => 2,
 *                   'region_luzon' => 5 ],
 *     ...
 *   ]
 *
 * Plus we return the flat list of branch IDs and region slugs for the
 * card-level data attributes.
 */
$build_scope_data = function ( $car_id ) {

	$result = array(
		'colors'   => array(),
		'branches' => array(),
		'regions'  => array(),
		'branch_names' => array(), // id => name (for the badge)
	);

	// Aggregated (default scope) — units summed across every active branch.
	$agg = obsidian_get_color_variants( $car_id, 0 );
	foreach ( $agg as $color_key => $data ) {
		$result['colors'][ $color_key ]['all'] = (int) ( $data['units'] ?? 0 );
	}

	// Per-branch.
	$branch_ids = obsidian_get_car_branches( $car_id );
	foreach ( $branch_ids as $branch_id ) {
		$result['branches'][]                       = (int) $branch_id;
		$result['branch_names'][ (int) $branch_id ] = get_the_title( $branch_id );

		$branch_variants = obsidian_get_color_variants( $car_id, (int) $branch_id );
		foreach ( $branch_variants as $color_key => $data ) {
			$result['colors'][ $color_key ][ 'branch_' . (int) $branch_id ] = (int) ( $data['units'] ?? 0 );
		}
	}

	// Per-region: sum branch units per color across all branches in that region.
	$region_to_branches = array();
	foreach ( $branch_ids as $branch_id ) {
		$slugs = wp_get_object_terms( $branch_id, 'region', array( 'fields' => 'slugs' ) );
		if ( is_wp_error( $slugs ) ) {
			continue;
		}
		foreach ( $slugs as $slug ) {
			if ( ! in_array( $slug, $result['regions'], true ) ) {
				$result['regions'][] = $slug;
			}
			$region_to_branches[ $slug ][] = (int) $branch_id;
		}
	}

	foreach ( $region_to_branches as $region_slug => $bids ) {
		foreach ( $bids as $bid ) {
			$bvariants = obsidian_get_color_variants( $car_id, $bid );
			foreach ( $bvariants as $color_key => $data ) {
				if ( ! isset( $result['colors'][ $color_key ][ 'region_' . $region_slug ] ) ) {
					$result['colors'][ $color_key ][ 'region_' . $region_slug ] = 0;
				}
				$result['colors'][ $color_key ][ 'region_' . $region_slug ] += (int) ( $data['units'] ?? 0 );
			}
		}
	}

	return $result;
};
?>

<section <?php echo get_block_wrapper_attributes( array( 'class' => 'obsidian-car-grid' ) ); ?>>

	<div class="car-grid-container">

		<?php if ( $show_header && ( $title || $description ) ) : ?>
		<header class="car-grid-header">
			<?php if ( $title ) : ?>
				<h2 class="car-grid-title"><?php echo wp_kses_post( $title ); ?></h2>
			<?php endif; ?>
			<?php if ( $description ) : ?>
				<p class="car-grid-description"><?php echo wp_kses_post( $description ); ?></p>
			<?php endif; ?>
		</header>
		<?php endif; ?>

		<?php if ( $show_internal_filters && ! empty( $car_classes ) && ! is_wp_error( $car_classes ) ) : ?>
		<nav class="car-grid-filters" aria-label="Filter by car class">
			<button class="filter-btn active" data-filter="all">All</button>
			<?php foreach ( $car_classes as $term ) : ?>
				<button class="filter-btn" data-filter="<?php echo esc_attr( $term->slug ); ?>">
					<?php echo esc_html( $term->name ); ?>
				</button>
			<?php endforeach; ?>
		</nav>
		<?php endif; ?>

		<?php if ( ! empty( $cars ) ) : ?>
		<div class="car-cards">
			<?php foreach ( $cars as $car ) :
				$car_id     = $car->ID;
				$make       = get_field( 'car_make', $car_id ) ?: '';
				$model      = get_field( 'car_model', $car_id ) ?: '';
				$year       = (int) get_field( 'car_year', $car_id );
				$daily_rate = (float) get_field( 'car_daily_rate', $car_id );
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

				// Per-color variant data (aggregated — what we render by default).
				$variants    = obsidian_get_color_variants( $car_id );
				$total_units = obsidian_get_total_units( $car_id );
				$first_color = true;
				$default_img = $thumb;

				// Phase 11: scope map for branches/regions and the "Available at" badge.
				$scope_data = $build_scope_data( $car_id );
				$branches_csv = implode( ',', $scope_data['branches'] );
				$regions_csv  = implode( ',', $scope_data['regions'] );

				// Determine the first color's image for the default card display
				if ( ! empty( $variants ) ) {
					$first_variant = reset( $variants );
					$first_images  = isset( $first_variant['images'] ) && is_array( $first_variant['images'] ) ? $first_variant['images'] : array();
					$first_img_id  = (int) ( $first_images[0] ?? 0 );
					if ( $first_img_id > 0 ) {
						$first_img_url = wp_get_attachment_image_url( $first_img_id, 'large' );
						if ( $first_img_url ) {
							$default_img = $first_img_url;
						}
					}
				}
			?>
			<article class="car-card"
					 data-car-id="<?php echo esc_attr( $car_id ); ?>"
					 data-class="<?php echo esc_attr( $class_slug ); ?>"
					 data-branches="<?php echo esc_attr( $branches_csv ); ?>"
					 data-regions="<?php echo esc_attr( $regions_csv ); ?>">

				<div class="car-card-top-bar">
					<?php if ( $class_name ) : ?>
						<span class="car-class-badge"><?php echo esc_html( $class_name ); ?></span>
					<?php endif; ?>

					<div class="car-card-pricing">
						<span class="car-rate"><?php echo esc_html( '₱' . number_format( $daily_rate, 0 ) ); ?></span>
						<span class="car-rate-label">/ day</span>
					</div>
				</div>

				<div class="car-card-image">
					<?php if ( $default_img ) : ?>
						<img src="<?php echo esc_url( $default_img ); ?>"
							 alt="<?php echo esc_attr( get_the_title( $car_id ) ); ?>"
							 loading="lazy"
							 class="car-card-img" />
					<?php else : ?>
						<div class="car-card-placeholder"></div>
					<?php endif; ?>
				</div>

				<div class="car-card-body">
					<h3 class="car-card-name"><?php echo esc_html( get_the_title( $car_id ) ); ?></h3>

					<div class="car-colors-units-wrap">
						<?php if ( ! empty( $variants ) ) : ?>
						<div class="car-color-swatches">
						<?php foreach ( $variants as $color_name => $data ) :
							$hex        = obsidian_get_color_hex( $color_name );
							$units      = (int) ( $data['units'] ?? 0 );
							$color_imgs = isset( $data['images'] ) && is_array( $data['images'] ) ? $data['images'] : array();
							$card_img_id = (int) ( $color_imgs[0] ?? 0 );
							$img_url    = $card_img_id > 0 ? wp_get_attachment_image_url( $card_img_id, 'large' ) : $thumb;
							$is_first   = $first_color;
							$first_color = false;

							$scope_units = isset( $scope_data['colors'][ $color_name ] )
								? $scope_data['colors'][ $color_name ]
								: array( 'all' => $units );
						?>
								<button class="color-swatch<?php echo $is_first ? ' active' : ''; ?>"
										data-color="<?php echo esc_attr( $color_name ); ?>"
										data-image="<?php echo esc_url( $img_url ?: $thumb ); ?>"
										data-units="<?php echo esc_attr( $units ); ?>"
										data-units-by-scope='<?php echo esc_attr( wp_json_encode( $scope_units ) ); ?>'
										style="background-color: <?php echo esc_attr( $hex ); ?>;"
										aria-label="<?php echo esc_attr( ucfirst( $color_name ) ); ?>"
										title="<?php echo esc_attr( ucfirst( $color_name ) . ' — ' . $units . ' available' ); ?>">
								</button>
							<?php endforeach; ?>
						</div>
						<?php endif; ?>

						<div class="car-card-units">
							<span class="units-dot"></span>
							<span class="units-text">
								<?php
								if ( ! empty( $variants ) ) {
									$first_v = reset( $variants );
									echo esc_html( (int) ( $first_v['units'] ?? 0 ) );
								} else {
									echo esc_html( $total_units );
								}
								?> available
							</span>
						</div>
					</div>

					<div class="car-card-actions">
						<?php if ( $logged_in ) : ?>
							<button class="car-book-btn" data-car-id="<?php echo esc_attr( $car_id ); ?>">
								Book <?php $make_word = strtok(get_the_title( $car_id ), ' '); echo esc_html( $make_word ); ?>
							</button>
						<?php else : ?>
							<a class="car-book-btn car-book-btn--login" href="<?php echo esc_url( $login_url ); ?>">
								Sign In to Book
							</a>
						<?php endif; ?>
					</div>
				</div>

			</article>
			<?php endforeach; ?>
		</div>

		<p class="car-grid-no-results" hidden>
			No vehicles match the current filters. Try clearing them or picking a different location.
		</p>
		<div class="car-grid-pagination" hidden></div>

		<?php else : ?>
			<p class="car-grid-empty">No vehicles are currently available. Please check back soon.</p>
		<?php endif; ?>

	</div>

</section>

<script>
(function() {
	'use strict';

	document.addEventListener('DOMContentLoaded', function() {

		var grid       = document.querySelector('.obsidian-car-grid');
		if (!grid) {
			return;
		}
		var cards      = Array.prototype.slice.call(grid.querySelectorAll('.car-card'));
		var noResults  = grid.querySelector('.car-grid-no-results');

		/* ── Local state, mirrored from either the internal class buttons or
		     the external Fleet Filters block. ── */
		var state = {
			classes: [],     // multi-select; empty = all
			scope: 'all',    // 'all' | 'branch_<id>' | 'region_<slug>'
			page: 1          // Pagination
		};

		var CARS_PER_PAGE = 6;
		var paginationWrap = grid.querySelector('.car-grid-pagination');

		/* ── Internal class-button row (only present if showInternalFilters) ── */
		var internalFilters = grid.querySelectorAll('.car-grid-filters .filter-btn');
		internalFilters.forEach(function(btn) {
			btn.addEventListener('click', function() {
				internalFilters.forEach(function(b) { b.classList.remove('active'); });
				btn.classList.add('active');
				var filter = btn.getAttribute('data-filter');
				state.classes = filter === 'all' ? [] : [filter];
				state.page = 1;
				applyFilters();
			});
		});

		/* ── External fleet-filters block (Phase 11) ── */
		document.addEventListener('obsidianFleet:change', function(e) {
			if (!e.detail) { return; }
			state.classes = Array.isArray(e.detail.classes) ? e.detail.classes : [];
			state.scope   = e.detail.scope || 'all';
			state.page = 1;
			applyFilters();
		});

		/* ── Card matcher ── */
		function cardMatches(card) {
			// Class filter — multi-select OR.
			if (state.classes.length > 0) {
				var cls = card.getAttribute('data-class') || '';
				if (state.classes.indexOf(cls) === -1) {
					return false;
				}
			}

			// Scope filter — single-select.
			if (state.scope.indexOf('branch_') === 0) {
				var branchId  = state.scope.substring('branch_'.length);
				var branches  = (card.getAttribute('data-branches') || '').split(',').filter(Boolean);
				if (branches.indexOf(branchId) === -1) {
					return false;
				}
			} else if (state.scope.indexOf('region_') === 0) {
				var regionSlug = state.scope.substring('region_'.length);
				var regions    = (card.getAttribute('data-regions') || '').split(',').filter(Boolean);
				if (regions.indexOf(regionSlug) === -1) {
					return false;
				}
			}

			return true;
		}

		/* ── Image Swiping Animation ── */
		function swipeImage(img, newSrc) {
			if (!img || !newSrc || img.src.indexOf(newSrc) !== -1) return;
			
			img.classList.add('is-swiping-out');
			setTimeout(function() {
				img.src = newSrc;
				img.classList.remove('is-swiping-out');
				img.classList.add('is-swiping-in');
				
				// Force reflow so browser registers the starting position
				void img.offsetWidth;
				
				img.classList.remove('is-swiping-in');
			}, 200);
		}

		/* ── Update each swatch for the current scope. ── */
		function updateScopedUnits(card) {
			var swatches = card.querySelectorAll('.color-swatch');
			var firstVisible = null;
			var activeStillVisible = false;

			swatches.forEach(function(swatch) {
				var raw = swatch.getAttribute('data-units-by-scope');
				if (!raw) { return; }
				var map;
				try { map = JSON.parse(raw); } catch (e) { return; }

				var isScopedFilter = (state.scope.indexOf('branch_') === 0
				                   || state.scope.indexOf('region_') === 0);
				var hasScopeKey    = (typeof map[state.scope] !== 'undefined');

				if (isScopedFilter && !hasScopeKey) {
					swatch.hidden = true;
					swatch.classList.remove('active');
					return;
				}

				var u = isScopedFilter
					? Number(map[state.scope] || 0)
					: Number((typeof map['all'] !== 'undefined') ? map['all'] : 0);

				swatch.hidden = false;
				swatch.setAttribute('data-units', String(u));

				var color = swatch.getAttribute('data-color') || '';
				swatch.setAttribute('title',
					color.charAt(0).toUpperCase() + color.slice(1) + ' — ' + u + ' available');

				if (!firstVisible) { firstVisible = swatch; }
				if (swatch.classList.contains('active')) { activeStillVisible = true; }
			});

			if (!activeStillVisible && firstVisible) {
				swatches.forEach(function(s) { s.classList.remove('active'); });
				firstVisible.classList.add('active');

				var img     = card.querySelector('.car-card-img');
				var unitsEl = card.querySelector('.units-text');
				var newImg  = firstVisible.getAttribute('data-image');
				var newU    = firstVisible.getAttribute('data-units');
				if (img && newImg) { swipeImage(img, newImg); }
				if (unitsEl)       { unitsEl.textContent = newU + ' available'; }
			} else {
				var activeSwatch = card.querySelector('.color-swatch.active');
				if (activeSwatch) {
					var unitsEl2 = card.querySelector('.units-text');
					if (unitsEl2) {
						unitsEl2.textContent = activeSwatch.getAttribute('data-units') + ' available';
					}
				}
			}
		}

		/* ── Apply filters: hide non-matching cards, recompute units, apply pagination. ── */
		function applyFilters() {
			var matchingCards = [];
			cards.forEach(function(card) {
				var ok = cardMatches(card);
				if (ok) {
					updateScopedUnits(card);
					matchingCards.push(card);
				} else {
					card.style.display = 'none';
				}
			});
			
			if (noResults) {
				noResults.hidden = matchingCards.length !== 0;
			}

			// Pagination
			var totalPages = Math.ceil(matchingCards.length / CARS_PER_PAGE);
			if (state.page > totalPages) { state.page = Math.max(1, totalPages); }

			var startIndex = (state.page - 1) * CARS_PER_PAGE;
			var endIndex = startIndex + CARS_PER_PAGE;

			matchingCards.forEach(function(card, index) {
				if (index >= startIndex && index < endIndex) {
					card.style.display = '';
				} else {
					card.style.display = 'none';
				}
			});

			renderPagination(totalPages);
		}

		function renderPagination(totalPages) {
			if (!paginationWrap) return;
			if (totalPages <= 1) {
				paginationWrap.hidden = true;
				paginationWrap.innerHTML = '';
				return;
			}

			paginationWrap.hidden = false;
			var html = '';

			if (state.page > 1) {
				html += '<button class="pagination-btn prev-btn" data-page="' + (state.page - 1) + '">&laquo; Prev</button>';
			}

			for (var i = 1; i <= totalPages; i++) {
				var activeClass = i === state.page ? ' active' : '';
				html += '<button class="pagination-btn page-num' + activeClass + '" data-page="' + i + '">' + i + '</button>';
			}

			if (state.page < totalPages) {
				html += '<button class="pagination-btn next-btn" data-page="' + (state.page + 1) + '">Next &raquo;</button>';
			}

			paginationWrap.innerHTML = html;
		}

		if (paginationWrap) {
			paginationWrap.addEventListener('click', function(e) {
				var btn = e.target.closest('.pagination-btn');
				if (!btn) return;
				var newPage = parseInt(btn.getAttribute('data-page'), 10);
				if (newPage && newPage !== state.page) {
					state.page = newPage;
					applyFilters();
					grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
				}
			});
		}

		/* ── Color swatch clicks: swap image + update units (active swatch wins). ── */
		grid.addEventListener('click', function(e) {
			var swatch = e.target.closest('.color-swatch');
			if (!swatch) { return; }

			var card     = swatch.closest('.car-card');
			var img      = card.querySelector('.car-card-img');
			var unitsEl  = card.querySelector('.units-text');
			var newImage = swatch.getAttribute('data-image');
			var newUnits = swatch.getAttribute('data-units');

			card.querySelectorAll('.color-swatch').forEach(function(s) {
				s.classList.remove('active');
			});
			swatch.classList.add('active');

			if (img && newImage) { swipeImage(img, newImage); }
			if (unitsEl)         { unitsEl.textContent = newUnits + ' available'; }
		});

		/* ── Initial pass — handles direct loads of the page where the
		     fleet-filters block hasn't dispatched yet (and pages that don't
		     even include the sidebar). ── */
		applyFilters();
	});

})();
</script>
