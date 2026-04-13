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
      'restUrl'        => esc_url_raw(rest_url('obsidian-booking/v1/')),
      'nonce'          => wp_create_nonce('wp_rest'),
      'loggedIn'       => is_user_logged_in(),
      'loginUrl'       => wp_login_url(get_permalink()),
      'bookingPageUrl' => home_url('/booking/'),
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

   // WP Media uploader for the color variant image picker
   if ($post_type === 'car') {
      wp_enqueue_media();
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
   Booking Modal (Phase 5)
   Injected into the footer so it's available on every front-end page.
   ────────────────────────────────────────────── */
function obsidian_booking_render_modal()
{
   if (is_admin() || !is_user_logged_in()) {
      return;
   }
   ?>
   <div id="obsidian-booking-modal" class="obsidian-modal" aria-hidden="true">
      <div class="obsidian-modal-overlay"></div>
      <div class="obsidian-modal-panel" role="dialog" aria-modal="true" aria-label="Book this car">
         <button class="obsidian-modal-close" aria-label="Close">&times;</button>

         <div class="obsidian-modal-loader" id="obsidian-modal-loader">
            <span class="obsidian-modal-spinner"></span>
         </div>

         <div class="obsidian-modal-content" id="obsidian-modal-content">
            <!-- Gallery -->
            <div class="obsidian-modal-gallery">
               <div class="obsidian-modal-main-img">
                  <img id="obsidian-modal-hero" src="" alt="" />
               </div>
               <div class="obsidian-modal-thumbs" id="obsidian-modal-thumbs"></div>
            </div>

            <!-- Car info -->
            <div class="obsidian-modal-info">
               <h2 id="obsidian-modal-name"></h2>
               <div class="obsidian-modal-specs">
                  <span id="obsidian-modal-class" class="obsidian-modal-badge"></span>
                  <span class="obsidian-modal-divider">|</span>
                  <span id="obsidian-modal-year"></span>
               </div>
               <div class="obsidian-modal-rate">
                  <span id="obsidian-modal-rate-value"></span>
                  <span class="obsidian-modal-rate-label">/ day</span>
               </div>
            </div>

            <!-- Date Selection -->
            <div class="obsidian-modal-section">
               <h4 class="obsidian-modal-section-title">Select Dates</h4>
               <div class="obsidian-modal-dates">
                  <div class="obsidian-modal-field">
                     <label for="obsidian-pickup-date">Pick-up Date</label>
                     <input type="text" id="obsidian-pickup-date" placeholder="Select date" readonly />
                  </div>
                  <div class="obsidian-modal-field">
                     <label for="obsidian-dropoff-date">Drop-off Date</label>
                     <input type="text" id="obsidian-dropoff-date" placeholder="Select date" readonly />
                  </div>
               </div>
            </div>

            <!-- Color Selection -->
            <div class="obsidian-modal-section" id="obsidian-modal-color-section">
               <h4 class="obsidian-modal-section-title">Select Color</h4>
               <div id="obsidian-modal-colors" class="obsidian-modal-colors"></div>
            </div>

            <!-- Pickup Location -->
            <div class="obsidian-modal-section">
               <h4 class="obsidian-modal-section-title">Pickup Location</h4>
               <div class="obsidian-modal-field">
                  <input type="text" id="obsidian-pickup-location" placeholder="Airport / Hotel / Address" />
               </div>
            </div>

            <!-- Total -->
            <div class="obsidian-modal-total">
               <div class="obsidian-modal-total-row">
                  <span class="obsidian-modal-total-label">TOTAL:</span>
                  <span id="obsidian-modal-total-value">₱0</span>
               </div>
               <span id="obsidian-modal-total-breakdown" class="obsidian-modal-breakdown"></span>
            </div>

            <!-- CTA -->
            <button id="obsidian-modal-proceed" class="obsidian-modal-cta" disabled>
               Proceed to Booking
            </button>
         </div>
      </div>
   </div>
   <?php
}
add_action('wp_footer', 'obsidian_booking_render_modal');

/* ──────────────────────────────────────────────
   Activation / Deactivation
   ────────────────────────────────────────────── */
function obsidian_booking_activate()
{
   // Register CPTs + taxonomies so rewrite rules and terms exist
   if (function_exists('obsidian_register_car_post_type')) {
      obsidian_register_car_post_type();
   }
   if (function_exists('obsidian_register_booking_post_type')) {
      obsidian_register_booking_post_type();
   }
   if (function_exists('obsidian_register_taxonomies')) {
      obsidian_register_taxonomies();
   }

   // Seed default car classes
   if (function_exists('obsidian_seed_car_classes')) {
      obsidian_seed_car_classes();
   }

   flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'obsidian_booking_activate');

function obsidian_booking_deactivate()
{
   flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'obsidian_booking_deactivate');
