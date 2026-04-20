<?php
/**
 * Fleet Filters Sidebar — Render Template (Phase 11).
 *
 * Renders a two-section sidebar (Car Class checkboxes + Region/Branch radios)
 * plus a "Clear All" button. The block is wholly self-contained — no PHP
 * coupling to the Car Grid block — and communicates with the grid through
 * a single custom DOM event:
 *
 *   document.dispatchEvent(new CustomEvent('obsidianFleet:change', {
 *       detail: { classes: ['exotic', 'sport'], scope: 'branch_12' }
 *   }));
 *
 * `scope` is a string for easy storage/comparison:
 *   - 'all'              — no location filter
 *   - 'region_<slug>'    — every branch under that region
 *   - 'branch_<id>'      — one specific branch
 *
 * The block also keeps the URL in sync (?class=...&location=... or ?region=...)
 * so deep-links from the header mega-menu and shared URLs work end-to-end.
 *
 * @package child-obsidian-reserve
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$title = $attributes['title'] ?? __( 'Filters', 'child-obsidian-reserve' );

$car_classes = get_terms( array(
	'taxonomy'   => 'car_class',
	'hide_empty' => true,
	'orderby'    => 'name',
	'order'      => 'ASC',
) );

$regions = get_terms( array(
	'taxonomy'   => 'region',
	'hide_empty' => false,
	'orderby'    => 'name',
	'order'      => 'ASC',
) );

// Per-region branch lookup so we can render collapsible groups.
$regions_with_branches = array();

if ( ! is_wp_error( $regions ) ) {
	foreach ( $regions as $region ) {
		$branch_ids = get_posts( array(
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
		) );

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
?>

<aside <?php echo get_block_wrapper_attributes( array( 'class' => 'obsidian-fleet-filters' ) ); ?>>

	<div class="fleet-filters-inner">

		<header class="fleet-filters-header">
			<h2 class="fleet-filters-title"><?php echo esc_html( $title ); ?></h2>
			<button type="button" class="fleet-filters-clear" data-action="clear">
				<?php esc_html_e( 'Clear', 'child-obsidian-reserve' ); ?>
			</button>
		</header>

		<?php if ( ! empty( $car_classes ) && ! is_wp_error( $car_classes ) ) : ?>
		<section class="fleet-filter-group" data-group="class">
			<h3 class="fleet-filter-group-title"><?php esc_html_e( 'Car Class', 'child-obsidian-reserve' ); ?></h3>
			<ul class="fleet-filter-list">
				<?php foreach ( $car_classes as $term ) : ?>
					<li class="fleet-filter-item">
						<label>
							<input type="checkbox"
								   class="fleet-filter-class"
								   name="fleet-class"
								   value="<?php echo esc_attr( $term->slug ); ?>" />
							<span class="fleet-filter-label"><?php echo esc_html( $term->name ); ?></span>
						</label>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
		<?php endif; ?>

		<?php if ( ! empty( $regions_with_branches ) ) : ?>
		<section class="fleet-filter-group" data-group="location">
			<h3 class="fleet-filter-group-title"><?php esc_html_e( 'Location', 'child-obsidian-reserve' ); ?></h3>
			<ul class="fleet-filter-list fleet-filter-list--locations">

				<li class="fleet-filter-item">
					<label>
						<input type="radio"
							   class="fleet-filter-scope"
							   name="fleet-scope"
							   value="all"
							   checked />
						<span class="fleet-filter-label"><?php esc_html_e( 'All Locations', 'child-obsidian-reserve' ); ?></span>
					</label>
				</li>

				<?php foreach ( $regions_with_branches as $entry ) :
					$region   = $entry['region'];
					$branches = $entry['branches'];
				?>
				<li class="fleet-filter-region" data-region="<?php echo esc_attr( $region->slug ); ?>">
					<button type="button" class="fleet-filter-region-toggle" aria-expanded="true">
						<span class="region-caret" aria-hidden="true">▾</span>
						<span class="region-name"><?php echo esc_html( $region->name ); ?></span>
					</button>

					<ul class="fleet-filter-region-list">
						<li class="fleet-filter-item fleet-filter-item--region">
							<label>
								<input type="radio"
									   class="fleet-filter-scope"
									   name="fleet-scope"
									   value="region_<?php echo esc_attr( $region->slug ); ?>" />
								<span class="fleet-filter-label">
									<?php
									printf(
										/* translators: %s: region name */
										esc_html__( 'All in %s', 'child-obsidian-reserve' ),
										esc_html( $region->name )
									);
									?>
								</span>
							</label>
						</li>
						<?php foreach ( $branches as $branch ) : ?>
						<li class="fleet-filter-item fleet-filter-item--branch">
							<label>
								<input type="radio"
									   class="fleet-filter-scope"
									   name="fleet-scope"
									   value="branch_<?php echo esc_attr( $branch['id'] ); ?>"
									   data-slug="<?php echo esc_attr( $branch['slug'] ); ?>" />
								<span class="fleet-filter-label"><?php echo esc_html( $branch['name'] ); ?></span>
							</label>
						</li>
						<?php endforeach; ?>
					</ul>
				</li>
				<?php endforeach; ?>

			</ul>
		</section>
		<?php endif; ?>

	</div>

</aside>

<script>
(function() {
	'use strict';

	document.addEventListener('DOMContentLoaded', function() {

		var root = document.querySelector('.obsidian-fleet-filters');
		if (!root) {
			return;
		}

		/* ── Read filters from the URL ───────────────────────────
		   Supported params:
		     ?class=exotic,sport
		     ?location=<branch-slug>     (one branch)
		     ?region=<region-slug>       (whole region)
		   `location` wins over `region` if both are present.
		   ──────────────────────────────────────────────────────── */
		function readUrl() {
			var params      = new URLSearchParams(window.location.search);
			var classes     = (params.get('class') || '').split(',').filter(Boolean);
			var locationSlug = params.get('location') || '';
			var regionSlug   = params.get('region') || '';

			var scope = 'all';
			if (locationSlug) {
				var branchInput = root.querySelector('.fleet-filter-scope[data-slug="' + cssEscape(locationSlug) + '"]');
				if (branchInput) {
					scope = branchInput.value;
				}
			} else if (regionSlug) {
				scope = 'region_' + regionSlug;
			}

			return { classes: classes, scope: scope };
		}

		// Tiny CSS.escape polyfill for environments without it.
		function cssEscape(s) {
			if (typeof CSS !== 'undefined' && CSS.escape) {
				return CSS.escape(s);
			}
			return String(s).replace(/[^a-zA-Z0-9_-]/g, '\\$&');
		}

		/* ── Apply state to the inputs (used on load) ── */
		function applyState(state) {
			root.querySelectorAll('.fleet-filter-class').forEach(function(cb) {
				cb.checked = state.classes.indexOf(cb.value) !== -1;
			});
			var scopeInput = root.querySelector('.fleet-filter-scope[value="' + cssEscape(state.scope) + '"]');
			if (scopeInput) {
				scopeInput.checked = true;
			} else {
				var fallback = root.querySelector('.fleet-filter-scope[value="all"]');
				if (fallback) { fallback.checked = true; }
			}
		}

		/* ── Read state back from the inputs ── */
		function readState() {
			var classes = Array.prototype.slice
				.call(root.querySelectorAll('.fleet-filter-class:checked'))
				.map(function(cb) { return cb.value; });
			var scopeInput = root.querySelector('.fleet-filter-scope:checked');
			var scope = scopeInput ? scopeInput.value : 'all';
			return { classes: classes, scope: scope };
		}

		/* ── Push state into the URL (replaceState — no history spam) ── */
		function writeUrl(state) {
			var params = new URLSearchParams(window.location.search);

			if (state.classes.length) {
				params.set('class', state.classes.join(','));
			} else {
				params.delete('class');
			}

			params.delete('location');
			params.delete('region');

			if (state.scope.indexOf('branch_') === 0) {
				var input = root.querySelector('.fleet-filter-scope[value="' + cssEscape(state.scope) + '"]');
				var slug  = input ? input.getAttribute('data-slug') : '';
				if (slug) { params.set('location', slug); }
			} else if (state.scope.indexOf('region_') === 0) {
				params.set('region', state.scope.substring('region_'.length));
			}

			var qs = params.toString();
			var url = window.location.pathname + (qs ? '?' + qs : '') + window.location.hash;
			window.history.replaceState({}, '', url);
		}

		/* ── Broadcast changes ── */
		function publish(state) {
			document.dispatchEvent(new CustomEvent('obsidianFleet:change', {
				detail: state
			}));
		}

		function refresh() {
			var state = readState();
			writeUrl(state);
			publish(state);
		}

		/* ── Wire input changes ── */
		root.addEventListener('change', function(e) {
			if (e.target.classList.contains('fleet-filter-class') ||
				e.target.classList.contains('fleet-filter-scope')) {
				refresh();
			}
		});

		/* ── Clear button ── */
		root.querySelector('.fleet-filters-clear').addEventListener('click', function() {
			applyState({ classes: [], scope: 'all' });
			refresh();
		});

		/* ── Region group collapse/expand ── */
		root.querySelectorAll('.fleet-filter-region-toggle').forEach(function(btn) {
			btn.addEventListener('click', function() {
				var region   = btn.closest('.fleet-filter-region');
				var expanded = btn.getAttribute('aria-expanded') === 'true';
				btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
				region.classList.toggle('is-collapsed', expanded);
			});
		});

		/* ── Initialise from URL on load and broadcast immediately so the
		     car-grid renders the correct subset on first paint. ── */
		var initialState = readUrl();
		applyState(initialState);
		// Defer the publish so the grid's listener has time to attach.
		window.requestAnimationFrame(function() {
			publish(readState());
		});
	});

})();
</script>
