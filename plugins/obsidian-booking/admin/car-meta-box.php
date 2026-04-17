<?php
/**
 * Car Inventory + Galleries Meta Boxes (Phase 11).
 *
 * Two distinct meta boxes are rendered on the Car edit screen:
 *
 *   1. INVENTORY  — A tabbed UI, one tab per branch the car is stocked at.
 *                   Inside each tab the admin sets per-color unit counts.
 *                   Saved as JSON into `_car_inventory`:
 *                     { "12": { "orange": {"units":3}, "black": {"units":2} },
 *                       "15": { "orange": {"units":2} } }
 *
 *   2. GALLERIES  — Shared per-color image arrays (6 per color). These are
 *                   identical across all branches because galleries describe
 *                   the *vehicle*, not the branch.
 *                   Saved as JSON into `_car_galleries`:
 *                     { "orange": [101, 102, ...], "black": [201, ...] }
 *
 * The legacy `_car_color_variants` field is intentionally left untouched
 * after Phase 11 — the migration in includes/migrations.php seeded the
 * new fields from it, and the availability engine prefers the new fields
 * when they exist (see obsidian_get_color_variants()).
 *
 * @package obsidian-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'OBSIDIAN_IMAGES_PER_COLOR' ) ) {
	define( 'OBSIDIAN_IMAGES_PER_COLOR', 6 );
}

/**
 * Register both meta boxes on the Car CPT edit screen.
 */
function obsidian_add_car_meta_boxes() {
	add_meta_box(
		'obsidian_car_inventory',
		__( 'Inventory — by Branch', 'obsidian-booking' ),
		'obsidian_render_inventory_meta_box',
		'car',
		'normal',
		'high'
	);

	add_meta_box(
		'obsidian_car_galleries',
		__( 'Color Galleries — shared across branches', 'obsidian-booking' ),
		'obsidian_render_galleries_meta_box',
		'car',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'obsidian_add_car_meta_boxes' );

/* ══════════════════════════════════════════════════════════════
   META BOX 1 — INVENTORY (tabbed by branch)
   ══════════════════════════════════════════════════════════════ */

/**
 * Render the tabbed inventory UI.
 *
 * The list of selectable colors is driven by the ACF `car_colors` checkbox.
 * Branches that aren't yet stocked appear in the "+ Add Branch" dropdown.
 */
function obsidian_render_inventory_meta_box( $post ) {

	wp_nonce_field( 'obsidian_save_car_inventory', 'obsidian_car_inventory_nonce' );

	$colors = get_field( 'car_colors', $post->ID );
	if ( empty( $colors ) || ! is_array( $colors ) ) {
		echo '<p class="obsidian-meta-notice">';
		esc_html_e( 'Select colors in the "Car Details" field group above first, then save the post. Inventory will appear here.', 'obsidian-booking' );
		echo '</p>';
		return;
	}

	$all_branches = obsidian_admin_get_active_branches();
	if ( empty( $all_branches ) ) {
		echo '<p class="obsidian-meta-notice">';
		printf(
			/* translators: %s: link to add a new Location */
			wp_kses_post( __( 'No active branches exist yet. <a href="%s">Add your first branch</a>, then return here to allocate inventory.', 'obsidian-booking' ) ),
			esc_url( admin_url( 'post-new.php?post_type=location' ) )
		);
		echo '</p>';
		return;
	}

	$inventory = obsidian_get_car_inventory( $post->ID );

	// Determine which branches already have inventory recorded for this car.
	$active_tab_ids = array();
	foreach ( array_keys( $inventory ) as $branch_id ) {
		$branch_id = (int) $branch_id;
		if ( $branch_id > 0 && get_post_status( $branch_id ) === 'publish' ) {
			$active_tab_ids[] = $branch_id;
		}
	}

	// If the car has no inventory yet, default to showing the first available
	// branch as an empty tab so the admin has something to fill in immediately.
	if ( empty( $active_tab_ids ) ) {
		$active_tab_ids[] = (int) $all_branches[0]['id'];
	}

	$unused_branches = array_filter(
		$all_branches,
		function ( $b ) use ( $active_tab_ids ) {
			return ! in_array( (int) $b['id'], $active_tab_ids, true );
		}
	);

	?>
	<p class="obsidian-meta-description">
		<?php esc_html_e( 'Add this vehicle to one or more branches. Set how many units of each color are stocked at each branch (0 = not available there). Galleries are managed once for the whole vehicle in the box below.', 'obsidian-booking' ); ?>
	</p>

	<div class="obsidian-inventory"
		 data-colors='<?php echo esc_attr( wp_json_encode( array_values( $colors ) ) ); ?>'
		 data-images-per-color="<?php echo esc_attr( OBSIDIAN_IMAGES_PER_COLOR ); ?>">

		<div class="obsidian-inventory-toolbar">
			<label for="obsidian-add-branch" class="screen-reader-text">
				<?php esc_html_e( 'Add a branch', 'obsidian-booking' ); ?>
			</label>
			<select id="obsidian-add-branch" class="obsidian-add-branch-select">
				<option value=""><?php esc_html_e( '+ Add branch…', 'obsidian-booking' ); ?></option>
				<?php foreach ( $unused_branches as $b ) : ?>
					<option value="<?php echo esc_attr( $b['id'] ); ?>" data-name="<?php echo esc_attr( $b['name'] ); ?>">
						<?php
						echo esc_html( $b['name'] );
						if ( ! empty( $b['region_name'] ) ) {
							echo ' — ' . esc_html( $b['region_name'] );
						}
						?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="obsidian-tabs" role="tablist">
		<?php
		$is_first = true;
		foreach ( $active_tab_ids as $branch_id ) :
			$branch_post = get_post( $branch_id );
			if ( ! $branch_post ) {
				continue;
			}
			$region_terms = wp_get_post_terms( $branch_id, 'region', array( 'number' => 1 ) );
			$region_name  = ! is_wp_error( $region_terms ) && ! empty( $region_terms ) ? $region_terms[0]->name : '';
			$status       = get_post_meta( $branch_id, 'location_status', true );
			?>
			<button type="button"
					class="obsidian-tab<?php echo $is_first ? ' is-active' : ''; ?>"
					role="tab"
					data-branch-id="<?php echo esc_attr( $branch_id ); ?>"
					aria-selected="<?php echo $is_first ? 'true' : 'false'; ?>">
				<span class="tab-label"><?php echo esc_html( $branch_post->post_title ); ?></span>
				<?php if ( $region_name ) : ?>
					<span class="tab-region"><?php echo esc_html( $region_name ); ?></span>
				<?php endif; ?>
				<?php if ( $status && $status !== 'active' ) : ?>
					<span class="tab-status-pill tab-status-<?php echo esc_attr( $status ); ?>">
						<?php echo esc_html( str_replace( '_', ' ', $status ) ); ?>
					</span>
				<?php endif; ?>
				<span class="tab-remove" title="<?php esc_attr_e( 'Remove this branch from the car', 'obsidian-booking' ); ?>" aria-label="<?php esc_attr_e( 'Remove branch', 'obsidian-booking' ); ?>">&times;</span>
			</button>
			<?php
			$is_first = false;
		endforeach;
		?>
		</div>

		<div class="obsidian-tab-panels">
		<?php
		$is_first = true;
		foreach ( $active_tab_ids as $branch_id ) :
			$branch_inventory = isset( $inventory[ (string) $branch_id ] ) && is_array( $inventory[ (string) $branch_id ] )
				? $inventory[ (string) $branch_id ]
				: array();
			?>
			<div class="obsidian-tab-panel<?php echo $is_first ? ' is-active' : ''; ?>"
				 role="tabpanel"
				 data-branch-id="<?php echo esc_attr( $branch_id ); ?>">
				<?php obsidian_render_branch_inventory_table( $branch_id, $colors, $branch_inventory ); ?>
			</div>
			<?php
			$is_first = false;
		endforeach;
		?>
		</div>

		<?php
		// Hidden template used by JS when adding a new branch tab — keeps
		// markup in PHP rather than a giant string in JavaScript.
		$template_inventory = array();
		foreach ( $colors as $c ) {
			$template_inventory[ strtolower( $c ) ] = array( 'units' => 0 );
		}
		?>
		<template id="obsidian-branch-panel-template">
			<div class="obsidian-tab-panel" role="tabpanel" data-branch-id="__BRANCH_ID__">
				<?php obsidian_render_branch_inventory_table( 0, $colors, $template_inventory, true ); ?>
			</div>
		</template>
	</div>
	<?php
}

/**
 * Render the per-branch units table that goes inside one tab panel.
 *
 * @param int   $branch_id The branch this table belongs to (0 = template).
 * @param array $colors    The master ACF car_colors list.
 * @param array $units_for Existing units keyed by color (lowercased).
 * @param bool  $is_template When true, name attributes use __BRANCH_ID__
 *                            placeholders for the JS clone path.
 */
function obsidian_render_branch_inventory_table( $branch_id, $colors, $units_for, $is_template = false ) {

	$branch_attr = $is_template ? '__BRANCH_ID__' : (int) $branch_id;
	$total       = 0;
	?>
	<table class="widefat obsidian-branch-units-table">
		<thead>
			<tr>
				<th class="col-color"><?php esc_html_e( 'Color', 'obsidian-booking' ); ?></th>
				<th class="col-units"><?php esc_html_e( 'Units at this branch', 'obsidian-booking' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $colors as $color ) :
				$key   = strtolower( $color );
				$hex   = obsidian_get_color_hex( $key );
				$units = (int) ( $units_for[ $key ]['units'] ?? 0 );
				$total += $units;
				?>
				<tr>
					<td class="col-color">
						<span class="branch-swatch" style="background-color: <?php echo esc_attr( $hex ); ?>;"></span>
						<span class="branch-color-name"><?php echo esc_html( ucfirst( $color ) ); ?></span>
					</td>
					<td class="col-units">
						<input type="number"
							   name="obsidian_branch_inventory[<?php echo esc_attr( $branch_attr ); ?>][<?php echo esc_attr( $key ); ?>][units]"
							   value="<?php echo esc_attr( $units ); ?>"
							   min="0"
							   step="1"
							   class="small-text branch-units-input" />
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
		<tfoot>
			<tr>
				<th class="col-color"><?php esc_html_e( 'Total at this branch', 'obsidian-booking' ); ?></th>
				<th class="col-units">
					<span class="branch-units-total"><?php echo esc_html( $total ); ?></span>
				</th>
			</tr>
		</tfoot>
	</table>
	<?php
}

/* ══════════════════════════════════════════════════════════════
   META BOX 2 — SHARED COLOR GALLERIES
   ══════════════════════════════════════════════════════════════ */

/**
 * Render the shared color-gallery uploaders (one block per master color).
 */
function obsidian_render_galleries_meta_box( $post ) {

	wp_nonce_field( 'obsidian_save_car_galleries', 'obsidian_car_galleries_nonce' );

	$colors = get_field( 'car_colors', $post->ID );
	if ( empty( $colors ) || ! is_array( $colors ) ) {
		echo '<p class="obsidian-meta-notice">';
		esc_html_e( 'Select colors in the "Car Details" field group above first, then save the post. Galleries will appear here.', 'obsidian-booking' );
		echo '</p>';
		return;
	}

	$galleries = obsidian_get_car_galleries( $post->ID );

	?>
	<p class="obsidian-meta-description">
		<?php esc_html_e( 'Upload up to 6 images per color. The first image is used as the card thumbnail; the remaining 5 fill the modal gallery. These galleries are shared across every branch that stocks this color.', 'obsidian-booking' ); ?>
	</p>

	<div class="obsidian-color-variants">
	<?php
	foreach ( $colors as $color ) {
		$key    = strtolower( $color );
		$hex    = obsidian_get_color_hex( $key );
		$images = isset( $galleries[ $key ] ) && is_array( $galleries[ $key ] ) ? $galleries[ $key ] : array();
		?>
		<div class="obsidian-variant-row">
			<div class="variant-header">
				<div class="variant-swatch" style="background-color: <?php echo esc_attr( $hex ); ?>;"></div>
				<div class="variant-label"><?php echo esc_html( ucfirst( $color ) ); ?></div>
			</div>

			<div class="variant-images-grid">
				<?php for ( $i = 0; $i < OBSIDIAN_IMAGES_PER_COLOR; $i++ ) :
					$img_id  = (int) ( $images[ $i ] ?? 0 );
					$img_url = $img_id > 0 ? wp_get_attachment_image_url( $img_id, 'thumbnail' ) : '';
				?>
				<div class="variant-image-slot" data-index="<?php echo esc_attr( $i ); ?>">
					<span class="variant-image-label">
						<?php echo esc_html( $i === 0 ? __( 'Card Thumbnail', 'obsidian-booking' ) : sprintf( __( 'Image %d', 'obsidian-booking' ), $i ) ); ?>
					</span>

					<input type="hidden"
						   class="variant-image-id"
						   name="obsidian_gallery[<?php echo esc_attr( $key ); ?>][<?php echo esc_attr( $i ); ?>]"
						   value="<?php echo esc_attr( $img_id ); ?>" />

					<div class="variant-image-preview">
						<?php if ( $img_url ) : ?>
							<img src="<?php echo esc_url( $img_url ); ?>" alt="" />
						<?php endif; ?>
					</div>

					<div class="variant-image-actions">
						<button type="button" class="button button-small obsidian-upload-image">
							<?php echo $img_url ? esc_html__( 'Change', 'obsidian-booking' ) : esc_html__( 'Upload', 'obsidian-booking' ); ?>
						</button>
						<?php if ( $img_url ) : ?>
							<button type="button" class="button button-small obsidian-remove-image">&times;</button>
						<?php endif; ?>
					</div>
				</div>
				<?php endfor; ?>
			</div>
		</div>
		<?php
	}
	echo '</div>';
}

/* ══════════════════════════════════════════════════════════════
   SAVE HANDLERS
   ══════════════════════════════════════════════════════════════ */

/**
 * Save handler — runs once on save_post and writes both meta fields
 * (inventory + galleries) atomically.
 *
 * Two separate nonces are checked so that a partial render of the screen
 * (e.g. quick-edit, REST update) can't accidentally wipe one of the fields.
 */
function obsidian_save_car_inventory_and_galleries( $post_id ) {

	if ( get_post_type( $post_id ) !== 'car' ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	/* ── Inventory ── */
	if ( isset( $_POST['obsidian_car_inventory_nonce'] )
		&& wp_verify_nonce( $_POST['obsidian_car_inventory_nonce'], 'obsidian_save_car_inventory' )
	) {
		$inventory = array();

		if ( isset( $_POST['obsidian_branch_inventory'] ) && is_array( $_POST['obsidian_branch_inventory'] ) ) {

			foreach ( $_POST['obsidian_branch_inventory'] as $raw_branch_id => $colors_data ) {

				$branch_id = (int) $raw_branch_id;
				if ( $branch_id <= 0 || ! is_array( $colors_data ) ) {
					continue;
				}

				// Validate the branch is a real published Location.
				if ( get_post_type( $branch_id ) !== 'location'
					|| get_post_status( $branch_id ) !== 'publish'
				) {
					continue;
				}

				$branch_payload = array();
				foreach ( $colors_data as $color => $data ) {
					$color_key = sanitize_text_field( strtolower( $color ) );
					$units     = absint( $data['units'] ?? 0 );
					$branch_payload[ $color_key ] = array( 'units' => $units );
				}

				if ( ! empty( $branch_payload ) ) {
					$inventory[ (string) $branch_id ] = $branch_payload;
				}
			}
		}

		if ( ! empty( $inventory ) ) {
			update_post_meta( $post_id, '_car_inventory', wp_json_encode( $inventory ) );
		} else {
			// Admin removed every branch — clear the field rather than leaving stale data.
			delete_post_meta( $post_id, '_car_inventory' );
		}

		// Make sure this car is flagged as v2 so the migration won't re-touch it.
		update_post_meta( $post_id, '_migrated_v2', 1 );
	}

	/* ── Galleries ── */
	if ( isset( $_POST['obsidian_car_galleries_nonce'] )
		&& wp_verify_nonce( $_POST['obsidian_car_galleries_nonce'], 'obsidian_save_car_galleries' )
	) {
		$galleries = array();

		if ( isset( $_POST['obsidian_gallery'] ) && is_array( $_POST['obsidian_gallery'] ) ) {

			foreach ( $_POST['obsidian_gallery'] as $color => $images ) {
				$color_key = sanitize_text_field( strtolower( $color ) );
				if ( ! is_array( $images ) ) {
					continue;
				}

				$ids = array();
				for ( $i = 0; $i < OBSIDIAN_IMAGES_PER_COLOR; $i++ ) {
					$id = absint( $images[ $i ] ?? 0 );
					if ( $id > 0 ) {
						$ids[] = $id;
					} else {
						// Preserve trailing empties so position 0 stays the thumbnail
						// even when an early slot is cleared.
						$ids[] = 0;
					}
				}

				// Trim trailing zeros so we don't store padding forever.
				while ( ! empty( $ids ) && end( $ids ) === 0 ) {
					array_pop( $ids );
				}

				if ( ! empty( $ids ) ) {
					$galleries[ $color_key ] = $ids;
				}
			}
		}

		if ( ! empty( $galleries ) ) {
			update_post_meta( $post_id, '_car_galleries', wp_json_encode( $galleries ) );
		} else {
			delete_post_meta( $post_id, '_car_galleries' );
		}
	}
}
add_action( 'save_post', 'obsidian_save_car_inventory_and_galleries' );

/* ══════════════════════════════════════════════════════════════
   HELPERS
   ══════════════════════════════════════════════════════════════ */

/**
 * All published "active" Locations, lightly formatted for admin dropdowns.
 *
 * @return array<int,array{id:int,name:string,region_name:string}>
 */
function obsidian_admin_get_active_branches() {

	$ids = get_posts( array(
		'post_type'      => 'location',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'orderby'        => 'title',
		'order'          => 'ASC',
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

	$out = array();
	foreach ( $ids as $id ) {
		$region_terms = wp_get_post_terms( $id, 'region', array( 'number' => 1 ) );
		$region_name  = ! is_wp_error( $region_terms ) && ! empty( $region_terms ) ? $region_terms[0]->name : '';
		$out[] = array(
			'id'          => (int) $id,
			'name'        => get_the_title( $id ),
			'region_name' => $region_name,
		);
	}

	return $out;
}
