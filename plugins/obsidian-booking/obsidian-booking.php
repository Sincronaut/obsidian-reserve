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
define('OBSIDIAN_BOOKING_FILE', __FILE__);
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

// Location/Branch ACF schema (Phase 11)
require_once OBSIDIAN_BOOKING_DIR . 'includes/location-fields.php';

// One-time data migrations (Phase 11)
require_once OBSIDIAN_BOOKING_DIR . 'includes/migrations.php';

// Core logic (Phase 3)
require_once OBSIDIAN_BOOKING_DIR . 'includes/availability.php';
require_once OBSIDIAN_BOOKING_DIR . 'includes/booking-handler.php';
require_once OBSIDIAN_BOOKING_DIR . 'includes/rest-api.php';

// Notifications (Phase 7)
require_once OBSIDIAN_BOOKING_DIR . 'includes/notifications.php';

// Payment (Phase 6)
require_once OBSIDIAN_BOOKING_DIR . 'includes/payment.php';

// Booking page routing (Phase 6)
require_once OBSIDIAN_BOOKING_DIR . 'includes/booking-pages.php';

// User profile extensions (Phase 4)
require_once OBSIDIAN_BOOKING_DIR . 'includes/user-fields.php';

// Locations header mega-menu shortcode (Phase 11.12)
require_once OBSIDIAN_BOOKING_DIR . 'includes/locations-menu.php';

// Admin UI — only load in dashboard (Phase 6)
if (is_admin()) {
   require_once OBSIDIAN_BOOKING_DIR . 'admin/car-meta-box.php';
   require_once OBSIDIAN_BOOKING_DIR . 'admin/booking-meta-box.php';
   require_once OBSIDIAN_BOOKING_DIR . 'admin/booking-columns.php';
   require_once OBSIDIAN_BOOKING_DIR . 'admin/dashboard-widget.php';
   require_once OBSIDIAN_BOOKING_DIR . 'admin/branch-utilization-widget.php';
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

   // Booking form JS — only on the booking page
   if (is_page('booking')) {
      $ob_step = get_query_var('ob_step', '');

      if ($ob_step === 'payment') {
         wp_enqueue_script(
            'obsidian-payment-form',
            OBSIDIAN_BOOKING_URL . 'assets/js/payment-form.js',
            array(),
            OBSIDIAN_BOOKING_VERSION,
            true
         );

         $current_user = wp_get_current_user();
         wp_localize_script('obsidian-payment-form', 'obsidianPayment', array(
            'restUrl'         => esc_url_raw(rest_url('obsidian-booking/v1/')),
            'nonce'           => wp_create_nonce('wp_rest'),
            'publicKey'       => defined('PAYMONGO_PUBLIC_KEY') ? PAYMONGO_PUBLIC_KEY : '',
            'confirmationUrl' => home_url('/booking/confirmation/'),
            'userEmail'       => $current_user->user_email,
         ));
      } else if ($ob_step === 'confirmation') {
         wp_enqueue_script(
            'obsidian-confirmation',
            OBSIDIAN_BOOKING_URL . 'assets/js/confirmation.js',
            array(),
            OBSIDIAN_BOOKING_VERSION,
            true
         );

         wp_localize_script('obsidian-confirmation', 'obsidianPayment', array(
            'publicKey'       => defined('PAYMONGO_PUBLIC_KEY') ? PAYMONGO_PUBLIC_KEY : '',
            'confirmationUrl' => home_url('/booking/confirmation/'),
            'restUrl'         => esc_url_raw(rest_url('obsidian-booking/v1/')),
            'nonce'           => wp_create_nonce('wp_rest'),
         ));
      } else {
         wp_enqueue_script(
            'obsidian-booking-form',
            OBSIDIAN_BOOKING_URL . 'assets/js/booking-form.js',
            array('flatpickr'),
            OBSIDIAN_BOOKING_VERSION,
            true
         );
      }
   }
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

            <!-- LEFT COLUMN — Gallery + Color + Price -->
            <div class="obsidian-modal-left">
               <div class="obsidian-modal-main-img">
                  <img id="obsidian-modal-hero" src="" alt="" />
               </div>
               <div class="obsidian-modal-thumbs" id="obsidian-modal-thumbs"></div>

               <div id="obsidian-modal-colors" class="obsidian-modal-colors"></div>

               <div class="obsidian-modal-left-meta">
                  <span id="obsidian-modal-class" class="obsidian-modal-badge"></span>
                  <div class="obsidian-modal-rate">
                     <span id="obsidian-modal-rate-value"></span>
                     <span class="obsidian-modal-rate-label">/ day</span>
                  </div>
               </div>
            </div>

            <!-- RIGHT COLUMN — Info + Form -->
            <div class="obsidian-modal-right">
               <h3 id="obsidian-modal-name"></h3>

               <div id="obsidian-modal-specs" class="obsidian-modal-specs"></div>

               <div class="obsidian-modal-customer-section">
                  <h4 class="obsidian-modal-section-heading">Tell us who you are. We handle the rest.</h4>
                  <div class="obsidian-modal-customer-type">
                     <label class="obsidian-modal-radio">
                        <input type="radio" name="obsidian_customer_type" value="local" checked />
                        <span class="radio-circle"></span>
                        <span>Local Renters</span>
                     </label>
                     <label class="obsidian-modal-radio">
                        <input type="radio" name="obsidian_customer_type" value="international" />
                        <span class="radio-circle"></span>
                        <span>International Renters</span>
                     </label>
                  </div>
               </div>

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

               <div class="obsidian-modal-total" id="obsidian-modal-total" style="display:none;">
                  <span class="obsidian-modal-total-label">TOTAL:</span>
                  <span id="obsidian-modal-total-value"></span>
                  <span id="obsidian-modal-total-breakdown" class="obsidian-modal-breakdown"></span>
               </div>

               <div class="obsidian-modal-actions">
                  <button id="obsidian-modal-proceed" class="obsidian-modal-cta" disabled>
                     <span id="obsidian-modal-cta-text">Reserve Vehicle</span>
                  </button>
                  <button id="obsidian-modal-check-avail" class="obsidian-modal-cta-outline" type="button">
                     <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                     Check Vehicle Availability
                  </button>
               </div>
            </div>

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
   if (function_exists('obsidian_register_location_post_type')) {
      obsidian_register_location_post_type();
   }
   if (function_exists('obsidian_register_taxonomies')) {
      obsidian_register_taxonomies();
   }
   if (function_exists('obsidian_register_region_taxonomy')) {
      obsidian_register_region_taxonomy();
   }

   // Seed default taxonomy terms
   if (function_exists('obsidian_seed_car_classes')) {
      obsidian_seed_car_classes();
   }
   if (function_exists('obsidian_seed_regions')) {
      obsidian_seed_regions();
   }

   // Register booking page rewrite rules before flushing
   if (function_exists('obsidian_register_booking_rewrites')) {
      obsidian_register_booking_rewrites();
   }

   flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'obsidian_booking_activate');

function obsidian_booking_deactivate()
{
   flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'obsidian_booking_deactivate');
