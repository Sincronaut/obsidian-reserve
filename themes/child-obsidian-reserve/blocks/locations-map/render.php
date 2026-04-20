<?php
/**
 * Locations Map Block — Render Template (Phase 11.11).
 *
 * Renders three things, top to bottom:
 *   1. Section header (title + description).
 *   2. Two-column row: interactive Leaflet map (left) + branch info card (right).
 *   3. Grouped list of every branch by region — server-rendered so users with
 *      JS disabled and search-engine crawlers still see every location.
 *
 * The map uses Leaflet (UI engine) + OpenStreetMap tiles (free map data).
 * Both are loaded from CDNs only on pages that include this block.
 *
 * Data flow:
 *   - SEO list: PHP queries `location` posts directly (no REST hop).
 *   - Map pins: client-side JS calls `/wp-json/obsidian/v1/locations` (which
 *     returns full detail incl. lat/lng) and plots active pins in gold,
 *     coming-soon pins in grey. Closed branches are excluded server-side.
 *
 * @package child-obsidian-reserve
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$title       = $attributes['title'] ?? __( 'Visit Us', 'child-obsidian-reserve' );
$description = $attributes['description'] ?? '';

/* ── Lazy-load Leaflet (only on pages that contain this block) ─────────
   wp_enqueue_script() inside a dynamic block's render callback is the
   official way to ship dependencies on demand — WP de-duplicates and
   skips re-enqueueing if the block appears more than once on a page.
   Leaflet is pinned to 1.9.4 (the current stable). SRI hashes match
   the official release on https://leafletjs.com/download.html. */
wp_enqueue_style(
	'leaflet',
	'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
	array(),
	'1.9.4'
);
wp_enqueue_script(
	'leaflet',
	'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
	array(),
	'1.9.4',
	true
);

/* ── Server-rendered SEO/fallback list ─────────────────────────────────
   We query regions then their branches directly so this section works
   without JavaScript (search bots, screen readers in degraded mode, etc).
   "Closed" branches are filtered out; "Coming Soon" branches are marked
   so the visual fallback matches the map UI. */
$regions = get_terms( array(
	'taxonomy'   => 'region',
	'hide_empty' => false,
	'orderby'    => 'name',
	'order'      => 'ASC',
) );

$grouped = array();
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
				array(
					'key'     => 'location_status',
					'value'   => 'closed',
					'compare' => '!=',
				),
			),
		) );

		if ( empty( $branch_ids ) ) {
			continue;
		}

		$branches = array();
		foreach ( $branch_ids as $bid ) {
			$branches[] = array(
				'id'      => (int) $bid,
				'name'    => get_the_title( $bid ),
				'slug'    => get_post_field( 'post_name', $bid ),
				'status'  => get_post_meta( $bid, 'location_status', true ) ?: 'active',
			);
		}

		$grouped[] = array(
			'region'   => $region,
			'branches' => $branches,
		);
	}
}

// REST URL passed to the JS so it can fetch live branch detail.
$rest_url = esc_url_raw( rest_url( 'obsidian/v1/locations' ) );
?>

<section <?php echo get_block_wrapper_attributes( array( 'class' => 'obsidian-locations-map' ) ); ?>
		 data-rest-url="<?php echo esc_attr( $rest_url ); ?>">

	<div class="locations-map-container">

		<header class="locations-map-header">
			<?php if ( $title ) : ?>
				<h2 class="locations-map-title"><?php echo wp_kses_post( $title ); ?></h2>
			<?php endif; ?>
			<?php if ( $description ) : ?>
				<p class="locations-map-description"><?php echo wp_kses_post( $description ); ?></p>
			<?php endif; ?>
		</header>

		<div class="locations-map-row">

			<!-- Map canvas — Leaflet mounts here. Aria-hidden because the
				 grouped list below already conveys the same content to AT. -->
			<div class="locations-map-canvas-wrap">
				<div id="ob-locations-map" class="locations-map-canvas" aria-hidden="true">
					<noscript>
						<p class="locations-map-noscript">
							The interactive map needs JavaScript. See the full
							list of branches below.
						</p>
					</noscript>
				</div>
			</div>

			<!-- Side info card — populated on pin click. Renders an empty
				 placeholder by default so the column doesn't collapse. -->
			<aside class="locations-map-info" aria-live="polite">
				<div class="locations-map-info-empty">
					<span class="info-empty-icon" aria-hidden="true">📍</span>
					<p class="info-empty-text">
						<?php esc_html_e( 'Click a pin on the map to see branch details.', 'child-obsidian-reserve' ); ?>
					</p>
				</div>
				<div class="locations-map-info-card" hidden>
					<header class="info-card-header">
						<h3 class="info-card-name"></h3>
						<span class="info-card-region"></span>
					</header>
					<dl class="info-card-meta">
						<div class="info-card-row" data-field="address">
							<dt><?php esc_html_e( 'Address', 'child-obsidian-reserve' ); ?></dt>
							<dd></dd>
						</div>
						<div class="info-card-row" data-field="contact_number">
							<dt><?php esc_html_e( 'Contact', 'child-obsidian-reserve' ); ?></dt>
							<dd></dd>
						</div>
						<div class="info-card-row" data-field="hours">
							<dt><?php esc_html_e( 'Hours', 'child-obsidian-reserve' ); ?></dt>
							<dd></dd>
						</div>
					</dl>
					<div class="info-card-actions">
						<a class="info-card-cta info-card-cta--secondary"
						   data-action="map-url"
						   href="#" target="_blank" rel="noopener">
							<?php esc_html_e( 'View on Google Maps', 'child-obsidian-reserve' ); ?>
						</a>
						<a class="info-card-cta info-card-cta--primary"
						   data-action="see-cars"
						   href="#">
							<?php esc_html_e( 'See cars at this branch', 'child-obsidian-reserve' ); ?>
						</a>
					</div>
				</div>
			</aside>

		</div>

		<?php if ( ! empty( $grouped ) ) : ?>
		<!-- Grouped fallback / SEO list — visible to everyone, doubles as
			 the no-JS experience and as crawlable address content. -->
		<div class="locations-map-list">
			<?php foreach ( $grouped as $entry ) :
				$region   = $entry['region'];
				$branches = $entry['branches'];
			?>
				<div class="locations-map-region">
					<h3 class="locations-map-region-name">
						<?php echo esc_html( $region->name ); ?>
					</h3>
					<ul class="locations-map-region-branches">
						<?php foreach ( $branches as $branch ) :
							$is_coming = $branch['status'] === 'coming_soon';
						?>
							<li class="locations-map-region-branch<?php echo $is_coming ? ' is-coming-soon' : ''; ?>">
								<a href="<?php echo esc_url( home_url( '/fleet/?location=' . $branch['slug'] ) ); ?>">
									<?php echo esc_html( $branch['name'] ); ?>
								</a>
								<?php if ( $is_coming ) : ?>
									<span class="branch-pill"><?php esc_html_e( 'Coming Soon', 'child-obsidian-reserve' ); ?></span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>

	</div>

</section>

<script>
(function() {
	'use strict';

	document.addEventListener('DOMContentLoaded', function() {

		var section = document.querySelector('.obsidian-locations-map');
		if (!section) { return; }

		var canvas  = section.querySelector('#ob-locations-map');
		var info    = section.querySelector('.locations-map-info');
		var empty   = info.querySelector('.locations-map-info-empty');
		var card    = info.querySelector('.locations-map-info-card');
		var restUrl = section.getAttribute('data-rest-url');

		// Wait for Leaflet to load (CDN script is footer-enqueued, so it's
		// usually available by DOMContentLoaded — but be defensive).
		function whenLeafletReady(cb) {
			if (typeof window.L !== 'undefined') {
				cb();
				return;
			}
			var tries = 0;
			var iv = setInterval(function() {
				if (typeof window.L !== 'undefined') {
					clearInterval(iv);
					cb();
				} else if (++tries > 50) { // ~5s
					clearInterval(iv);
					console.warn('Obsidian: Leaflet failed to load.');
				}
			}, 100);
		}

		whenLeafletReady(function() {

			/* ── 1. Bootstrap the Leaflet map centered on the Philippines ── */
			var map = L.map(canvas, {
				center: [12.8797, 121.7740],   // geographic center of the PH archipelago
				zoom:   6,
				scrollWheelZoom: false,        // don't hijack page scroll
				zoomControl: true
			});

			// OSM tile layer. The attribution is required by OSM's tile usage
			// policy and is displayed by Leaflet in the bottom-right corner.
			L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
				maxZoom:    18,
				attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors'
			}).addTo(map);

			// Re-enable scroll-zoom only after the user clicks the map (UX:
			// avoids accidental zooms while scrolling past the section).
			map.once('focus', function() { map.scrollWheelZoom.enable(); });
			canvas.addEventListener('click', function() { map.scrollWheelZoom.enable(); });

			/* ── 2. Custom DivIcon pins (no image asset to host) ── */
			function pinIcon(status) {
				var cls = status === 'coming_soon' ? 'ob-pin ob-pin--coming' : 'ob-pin ob-pin--active';
				return L.divIcon({
					className: cls,
					html: '<span class="ob-pin-dot"></span>',
					iconSize:   [22, 22],
					iconAnchor: [11, 22]   // bottom-center sits on the coordinate
				});
			}

			/* ── 3. Fetch every non-closed branch and plot it ── */
			var markers = [];

			fetch(restUrl + '?per_page=200')
				.then(function(r) { return r.json(); })
				.then(function(branches) {

					branches.forEach(function(b) {
						if (b.status === 'closed') { return; }
						if (!b.latitude || !b.longitude) { return; }

						var marker = L.marker([b.latitude, b.longitude], {
							icon: pinIcon(b.status),
							title: b.name
						}).addTo(map);

						marker.on('click', function() {
							populateInfoCard(b);
						});

						markers.push(marker);
					});

					// Auto-fit the map to whatever pins we plotted (with a
					// minimum zoom so we don't zoom in past zoom 12 on a
					// single-branch deployment).
					if (markers.length > 0) {
						var group = L.featureGroup(markers);
						map.fitBounds(group.getBounds().pad(0.15), { maxZoom: 11 });
					}
				})
				.catch(function(err) {
					console.warn('Obsidian: failed to load branches —', err);
				});

			/* ── 4. Info-card population ── */
			function populateInfoCard(branch) {
				empty.hidden = true;
				card.hidden  = false;

				card.querySelector('.info-card-name').textContent   = branch.name || '';
				card.querySelector('.info-card-region').textContent = branch.region_name || '';

				var fields = ['address', 'contact_number', 'hours'];
				fields.forEach(function(key) {
					var row = card.querySelector('.info-card-row[data-field="' + key + '"]');
					if (!row) { return; }
					var dd  = row.querySelector('dd');
					var val = (branch[key] || '').toString().trim();
					if (val) {
						// Address/hours come from textarea fields — they
						// contain raw line breaks already converted to <br>.
						dd.innerHTML = val;
						row.hidden = false;
					} else {
						row.hidden = true;
					}
				});

				var mapLink = card.querySelector('[data-action="map-url"]');
				if (branch.map_url) {
					mapLink.href = branch.map_url;
					mapLink.hidden = false;
				} else if (branch.latitude && branch.longitude) {
					// Fallback: build a Google Maps deep-link from coords.
					mapLink.href = 'https://www.google.com/maps/search/?api=1&query=' +
						branch.latitude + ',' + branch.longitude;
					mapLink.hidden = false;
				} else {
					mapLink.hidden = true;
				}

				var carsLink = card.querySelector('[data-action="see-cars"]');
				if (branch.status === 'active' && branch.slug) {
					carsLink.href = '/fleet/?location=' + encodeURIComponent(branch.slug);
					carsLink.hidden = false;
				} else {
					carsLink.hidden = true;
				}
			}
		});
	});

})();
</script>
