<?php
/**
 * Plugin Name: Obsidian Booking
 * Plugin URI:
 * Description: Custom booking and reservation system for Obsidian Reserve luxury car rentals.
 * Version: 1.0.0
 * Author: Obsidian Reserve
 * Author URI:
 * Text Domain: obsidian-booking
 * Requires at least: 6.9
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) {
   exit;
}

/* ──────────────────────────────────────────────
   Constants
   ────────────────────────────────────────────── */
define('OBSIDIAN_BOOKING_VERSION', '1.0.0');
define('OBSIDIAN_BOOKING_DIR', plugin_dir_path(__FILE__));
define('OBSIDIAN_BOOKING_URL', plugin_dir_url(__FILE__));

/* ──────────────────────────────────────────────
   Includes — loaded in dependency order
   Phase 2 will fill these files with real code.
   ────────────────────────────────────────────── */

// Data model (Phase 2)
require_once OBSIDIAN_BOOKING_DIR . 'includes/post-types.php';
require_once OBSIDIAN_BOOKING_DIR . 'includes/meta-fields.php';
require_once OBSIDIAN_BOOKING_DIR . 'includes/taxonomies.php';

// Core logic (Phase 3)
require_once OBSIDIAN_BOOKING_DIR . 'includes/availability.php';
require_once OBSIDIAN_BOOKING_DIR . 'includes/booking-handler.php';
require_once OBSIDIAN_BOOKING_DIR . 'includes/rest-api.php';

// Notifications (Phase 7)
require_once OBSIDIAN_BOOKING_DIR . 'includes/notifications.php';

// User profile extensions (Phase 4)
require_once OBSIDIAN_BOOKING_DIR . 'includes/user-fields.php';

// Admin UI — only load in dashboard (Phase 6)
if (is_admin()) {
   require_once OBSIDIAN_BOOKING_DIR . 'admin/car-meta-box.php';
   require_once OBSIDIAN_BOOKING_DIR . 'admin/booking-meta-box.php';
   require_once OBSIDIAN_BOOKING_DIR . 'admin/booking-columns.php';
   require_once OBSIDIAN_BOOKING_DIR . 'admin/dashboard-widget.php';
}

/* ──────────────────────────────────────────────
   Front-end Assets (Phase 5)
   ────────────────────────────────────────────── */
function obsidian_booking_enqueue_assets()
{
   if (is_admin()) {
      return;
   }

   // Flatpickr CSS
   wp_enqueue_style(
      'flatpickr',
      OBSIDIAN_BOOKING_URL . 'vendor/flatpickr/flatpickr.min.css',
      array(),
      '4.6.13'
   );

   // Flatpickr JS
   wp_enqueue_script(
      'flatpickr',
      OBSIDIAN_BOOKING_URL . 'vendor/flatpickr/flatpickr.min.js',
      array(),
      '4.6.13',
      true
   );

   // Booking modal styles
   wp_enqueue_style(
      'obsidian-booking-modal',
      OBSIDIAN_BOOKING_URL . 'assets/css/modal.css',
      array('flatpickr'),
      OBSIDIAN_BOOKING_VERSION
   );

   // Booking modal + form JS
   wp_enqueue_script(
      'obsidian-booking-modal',
      OBSIDIAN_BOOKING_URL . 'assets/js/modal.js',
      array('flatpickr'),
      OBSIDIAN_BOOKING_VERSION,
      true
   );

   // Pass PHP data to JavaScript
   wp_localize_script('obsidian-booking-modal', 'obsidianBooking', array(
      'restUrl' => esc_url_raw(rest_url('obsidian-booking/v1/')),
      'nonce' => wp_create_nonce('wp_rest'),
      'loggedIn' => is_user_logged_in(),
      'loginUrl' => wp_login_url(get_permalink()),
   ));
}
add_action('wp_enqueue_scripts', 'obsidian_booking_enqueue_assets');

/* ──────────────────────────────────────────────
   Admin Assets (Phase 6)
   ────────────────────────────────────────────── */
function obsidian_booking_admin_assets($hook)
{
   global $post_type;

   // Only load on our CPT screens
   if (!in_array($post_type, array('car', 'booking'), true)) {
      return;
   }

   wp_enqueue_style(
      'obsidian-booking-admin',
      OBSIDIAN_BOOKING_URL . 'assets/css/admin.css',
      array(),
      OBSIDIAN_BOOKING_VERSION
   );

   wp_enqueue_script(
      'obsidian-booking-admin',
      OBSIDIAN_BOOKING_URL . 'assets/js/admin-booking.js',
      array('jquery'),
      OBSIDIAN_BOOKING_VERSION,
      true
   );

   wp_localize_script('obsidian-booking-admin', 'obsidianAdmin', array(
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('obsidian_admin_nonce'),
   ));
}
add_action('admin_enqueue_scripts', 'obsidian_booking_admin_assets');

/* ──────────────────────────────────────────────
   Activation / Deactivation
   ────────────────────────────────────────────── */
function obsidian_booking_activate()
{
   // Flush rewrite rules so CPT URLs work immediately
   if (function_exists('obsidian_register_car_post_type')) {
      obsidian_register_car_post_type();
   }
   if (function_exists('obsidian_register_booking_post_type')) {
      obsidian_register_booking_post_type();
   }
   flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'obsidian_booking_activate');

function obsidian_booking_deactivate()
{
   flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'obsidian_booking_deactivate');
