<?php
/**
 * Child Obsidian Reserve — Blog Engine
 *
 * Handles blog post view tracking (global and user-specific)
 * and custom queries for the Blog Hub.
 *
 * @package child-obsidian-reserve
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 1. TRACK BLOG VIEWS
 * Triggers on single blog posts to increment global views and user history.
 */
function obsidian_reserve_track_blog_views() {
	// Only track single blog posts
	if ( ! is_singular( 'post' ) ) {
		return;
	}

	$post_id = get_the_ID();

	// A. EXCLUDE BOTS
	$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
	$bots = array( 'bot', 'crawl', 'slurp', 'spider', 'mediapartners' );
	foreach ( $bots as $bot ) {
		if ( stripos( $user_agent, $bot ) !== false ) {
			return;
		}
	}

	// B. GLOBAL VIEW COUNT (Post Meta)
	$current_views = get_post_meta( $post_id, '_obsidian_view_count', true );
	$current_views = ( $current_views ) ? (int) $current_views : 0;
	update_post_meta( $post_id, '_obsidian_view_count', $current_views + 1 );

	// C. USER READING HISTORY (User Meta)
	if ( is_user_logged_in() ) {
		$user_id = get_current_user_id();
		$history = get_user_meta( $user_id, '_obsidian_reading_history', true );

		if ( ! is_array( $history ) ) {
			$history = array();
		}

		// Remove post ID if it already exists (to bring it to the top)
		$history = array_diff( $history, array( $post_id ) );

		// Prepend current post ID
		array_unshift( $history, $post_id );

		// Keep only the last 10 posts
		$history = array_slice( $history, 0, 10 );

		update_user_meta( $user_id, '_obsidian_reading_history', $history );
	}
}
add_action( 'wp_head', 'obsidian_reserve_track_blog_views' );

/**
 * 2. GET TOP READ BLOGS
 * Helper function to fetch posts ordered by view count.
 *
 * @param int   $limit  Number of posts to fetch.
 * @param int   $offset Number of posts to skip.
 * @return WP_Query
 */
function obsidian_reserve_get_top_blogs( $limit = 3, $offset = 0 ) {
	$args = array(
		'post_type'      => 'post',
		'posts_per_page' => $limit,
		'offset'         => $offset,
		'meta_key'       => '_obsidian_view_count',
		'orderby'        => 'meta_value_num',
		'order'          => 'DESC',
		'post_status'    => 'publish',
	);

	return new WP_Query( $args );
}
