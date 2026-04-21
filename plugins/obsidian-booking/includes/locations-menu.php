<?php
/**
 * [obsidian_locations_menu] shortcode — Phase 11.12 header mega-menu.
 *
 * Renders a "Locations" dropdown that drops directly into the header's
 * existing nav `<ul>`. Output is structured to plug into the dropdown
 * markup the theme already styles (`.obsidian-dropdown`, `.obsidian-dropdown-toggle`,
 * `.obsidian-dropdown-menu`) so the existing click/outside-close JS in
 * `parts/header.html` works without modification — we only add the
 * `--mega` modifier class for the multi-column layout.
 *
 * Each region becomes a column header that links to `/fleet/?region=<slug>`,
 * each branch is a row that links to `/fleet/?location=<slug>`. The fleet
 * page's sidebar (Step 11.10) reads those query vars on load and applies
 * the filter, so deep-links work end-to-end.
 *
 * Caching:
 *   The HTML is cached in a 1-hour transient. We invalidate it whenever a
 *   `location` post is saved/trashed/deleted or a `region` term is changed,
 *   so admin updates appear immediately without the editor having to wait
 *   for the cache to expire.
 *
 * @package obsidian-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OBSIDIAN_LOCATIONS_MENU_CACHE_KEY = 'obsidian_locations_menu_html_v2';
const OBSIDIAN_LOCATIONS_MENU_CACHE_TTL = HOUR_IN_SECONDS;

/**
 * Shortcode entrypoint.
 */
function obsidian_locations_menu_shortcode() {

	$cached = get_transient( OBSIDIAN_LOCATIONS_MENU_CACHE_KEY );
	if ( false !== $cached ) {
		return $cached;
	}

	$html = obsidian_render_locations_menu_html();
	set_transient( OBSIDIAN_LOCATIONS_MENU_CACHE_KEY, $html, OBSIDIAN_LOCATIONS_MENU_CACHE_TTL );

	return $html;
}
add_shortcode( 'obsidian_locations_menu', 'obsidian_locations_menu_shortcode' );

/**
 * Build the dropdown markup from the database.
 *
 * @return string The full `<li class="obsidian-dropdown obsidian-dropdown--mega">…</li>`
 *                fragment, or a minimal "Fleet" link if no branches exist yet.
 */
function obsidian_render_locations_menu_html() {

	$regions = get_terms( array(
		'taxonomy'   => 'region',
		'hide_empty' => false,
		'orderby'    => 'name',
		'order'      => 'ASC',
	) );

	// Manual Sort: Luzon -> Visayas -> Mindanao.
	if ( ! is_wp_error( $regions ) && ! empty( $regions ) ) {
		usort( $regions, function( $a, $b ) {
			$order = array( 'Luzon' => 1, 'Visayas' => 2, 'Mindanao' => 3 );
			$val_a = $order[ $a->name ] ?? 999;
			$val_b = $order[ $b->name ] ?? 999;
			return $val_a <=> $val_b;
		} );
	}

	if ( is_wp_error( $regions ) || empty( $regions ) ) {
		// Fallback: no regions configured yet — just a plain "Locations"
		// link to the fleet page so the menu doesn't have a dead item.
		return '<li><a href="' . esc_url( home_url( '/fleet/' ) ) . '">Locations</a></li>';
	}

	// Group active + coming-soon branches under each region. Closed branches
	// are excluded so retired pickup spots don't keep appearing in nav.
	$columns = array();
	foreach ( $regions as $region ) {
		$branches = get_posts( array(
			'post_type'      => 'location',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
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

		if ( empty( $branches ) ) {
			continue;
		}

		$columns[] = array(
			'region'   => $region,
			'branches' => $branches,
		);
	}

	if ( empty( $columns ) ) {
		return '<li><a href="' . esc_url( home_url( '/fleet/' ) ) . '">Locations</a></li>';
	}

	ob_start();
	?>
	<li class="obsidian-dropdown obsidian-dropdown--mega obsidian-locations-menu">
		<button class="obsidian-dropdown-toggle" type="button">
			Locations
			<svg class="chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
		</button>

		<div class="obsidian-dropdown-menu obsidian-locations-menu__panel">
			<div class="obsidian-locations-menu__columns" data-columns="<?php echo esc_attr( count( $columns ) ); ?>">
				<?php foreach ( $columns as $col ) :
					$region = $col['region'];
					?>
					<div class="obsidian-locations-menu__column">
						<a class="obsidian-locations-menu__region"
						   href="<?php echo esc_url( home_url( '/fleet/?region=' . rawurlencode( $region->slug ) ) ); ?>">
							<?php echo esc_html( $region->name ); ?>
						</a>
						<ul class="obsidian-locations-menu__branches">
							<?php foreach ( $col['branches'] as $branch ) :
								$status     = get_post_meta( $branch->ID, 'location_status', true ) ?: 'active';
								$is_coming  = ( 'coming_soon' === $status );
								$slug       = $branch->post_name;
								$label      = get_the_title( $branch->ID );
								?>
								<li class="obsidian-locations-menu__branch<?php echo $is_coming ? ' is-coming-soon' : ''; ?>">
									<?php if ( $is_coming ) : ?>
										<span class="branch-link branch-link--disabled">
											<?php echo esc_html( $label ); ?>
											<span class="branch-pill">Coming Soon</span>
										</span>
									<?php else : ?>
										<a class="branch-link"
										   href="<?php echo esc_url( home_url( '/fleet/?location=' . rawurlencode( $slug ) ) ); ?>">
											<?php echo esc_html( $label ); ?>
										</a>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="obsidian-locations-menu__footer">
				<a class="obsidian-locations-menu__view-all"
				   href="<?php echo esc_url( home_url( '/fleet/' ) ); ?>">
					View all branches →
				</a>
			</div>
		</div>
	</li>
	<?php
	return (string) ob_get_clean();
}

/**
 * Bust the cache when location posts or region terms change.
 */
function obsidian_invalidate_locations_menu_cache( $post_id = 0 ) {
	// `save_post_<type>` passes a post ID; taxonomy hooks don't — both branches
	// just delete the transient.
	if ( $post_id && 'location' !== get_post_type( $post_id ) ) {
		return;
	}
	delete_transient( OBSIDIAN_LOCATIONS_MENU_CACHE_KEY );
}
add_action( 'save_post_location',   'obsidian_invalidate_locations_menu_cache' );
add_action( 'trashed_post',         'obsidian_invalidate_locations_menu_cache' );
add_action( 'untrashed_post',       'obsidian_invalidate_locations_menu_cache' );
add_action( 'deleted_post',         'obsidian_invalidate_locations_menu_cache' );
add_action( 'created_region',       'obsidian_invalidate_locations_menu_cache' );
add_action( 'edited_region',        'obsidian_invalidate_locations_menu_cache' );
add_action( 'delete_region',        'obsidian_invalidate_locations_menu_cache' );
add_action( 'set_object_terms',     'obsidian_invalidate_locations_menu_cache' );
