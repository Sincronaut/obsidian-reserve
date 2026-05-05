<?php
/**
 * Plugin Name: Obsidian Booking
 * Plugin URI:
 * Description: Custom booking and reservation system for Obsidian Reserve luxury car rentals.
 * Version: 1.0.2
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
define('OBSIDIAN_BOOKING_VERSION', '1.0.2');
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

// Car ACF schema (migrated from ACF UI to code)
require_once OBSIDIAN_BOOKING_DIR . 'includes/car-fields.php';

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
   require_once OBSIDIAN_BOOKING_DIR . 'admin/location-map-picker.php';
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
      'homeUrl'        => home_url('/'),
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

   // Load on our CPT screens + dashboard
   $is_our_cpt   = in_array($post_type, array('car', 'booking', 'location'), true);
   $is_dashboard  = ($hook === 'index.php');

   if (!$is_our_cpt && !$is_dashboard) {
      return;
   }

   // WP Media uploader for the color variant image picker
   if ($post_type === 'car') {
      wp_enqueue_media();
   }

   // Google Fonts — Inter
   wp_enqueue_style(
      'obsidian-admin-font',
      'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
      array(),
      null
   );

   wp_enqueue_style(
      'obsidian-admin-dark',
      OBSIDIAN_BOOKING_URL . 'assets/css/admin-dark.css',
      array('obsidian-admin-font'),
      OBSIDIAN_BOOKING_VERSION
   );

   wp_enqueue_style(
      'obsidian-booking-admin',
      OBSIDIAN_BOOKING_URL . 'assets/css/admin.css',
      array('obsidian-admin-dark'),
      OBSIDIAN_BOOKING_VERSION
   );

   // Admin JS — only on CPT screens (not dashboard)
   if ($is_our_cpt) {
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
}
add_action('admin_enqueue_scripts', 'obsidian_booking_admin_assets');

/**
 * Add a body class on our admin screens so CSS stays scoped.
 * Dark-theme styles target `body.obsidian-admin` — they never
 * leak into Posts, Pages, Settings, or any other WP screen.
 */
function obsidian_admin_body_class($classes)
{
   $screen = get_current_screen();
   if (!$screen) {
      return $classes;
   }

   if (in_array($screen->post_type, array('car', 'booking', 'location'), true)) {
      $classes .= ' obsidian-admin';
   }
   if ($screen->id === 'dashboard') {
      $classes .= ' obsidian-admin obsidian-dashboard';
   }
   return $classes;
}
add_filter('admin_body_class', 'obsidian_admin_body_class');

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

            <!-- LEFT COLUMN — Gallery + Branch + Color + Price -->
            <div class="obsidian-modal-left">
               <div class="obsidian-modal-main-img">
                  <img id="obsidian-modal-hero" src="" alt="" />
               </div>
               <div class="obsidian-modal-thumbs" id="obsidian-modal-thumbs"></div>

               <!-- Pickup branch picker (Phase 11.13).
                    Lives above the color swatches because color stock is
                    branch-scoped — picking a branch is the gate that unlocks
                    everything downstream. The JS swaps between `.is-locked`
                    (URL had ?location/?region) and `.is-pickable`. -->
               <div class="obsidian-modal-branch" id="obsidian-modal-branch" hidden>
                  <span class="branch-label">Available Branches:</span>
                  <span class="branch-name" id="obsidian-modal-branch-name"></span>
                  <button type="button"
                          class="branch-change"
                          id="obsidian-modal-branch-change"
                          aria-label="Change pickup location">✏️</button>
                  <select class="branch-select" id="obsidian-modal-branch-select" aria-label="Select pickup branch" hidden>
                     <option value="">Select branch…</option>
                  </select>
               </div>

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
                     <label for="obsidian-pickup-date">Delivery Date</label>
                     <input type="text" id="obsidian-pickup-date" placeholder="Select date" readonly />
                  </div>
                  <div class="obsidian-modal-field">
                     <label for="obsidian-dropoff-date">Return Date</label>
                     <input type="text" id="obsidian-dropoff-date" placeholder="Select date" readonly />
                  </div>
               </div>

               <div class="obsidian-modal-total" id="obsidian-modal-total" style="display:none;">
                  <span class="obsidian-modal-total-label">TOTAL:</span>
                  <span id="obsidian-modal-total-value"></span>
                  <span id="obsidian-modal-total-breakdown" class="obsidian-modal-breakdown"></span>
               </div>

               <!-- UX status hint — explains *why* an action is blocked.
                    Filled in dynamically by validateForm() in modal.js based
                    on which prerequisite is missing (branch / color / dates). -->
               <div class="obsidian-modal-status" id="obsidian-modal-status" hidden>
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                  <span id="obsidian-modal-status-text"></span>
               </div>

               <div class="obsidian-modal-actions">
                  <button id="obsidian-modal-proceed" class="obsidian-modal-cta" disabled>
                     <span id="obsidian-modal-cta-text">Reserve Vehicle</span>
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
   Text Modal (Phase 12)
   Reusable modal for Privacy Policy, Terms, etc.
   ────────────────────────────────────────────── */
function obsidian_booking_render_text_modal()
{
   if (is_admin()) {
      return;
   }
   ?>
   <div id="obsidian-text-modal" class="obsidian-text-modal-wrapper" aria-hidden="true">
      <div class="obsidian-text-modal-overlay"></div>
      <div class="obsidian-text-modal-panel" role="dialog" aria-modal="true">
         <button class="obsidian-text-modal-close" aria-label="Close">&times;</button>
         
         <div class="obsidian-text-modal-loader" id="obsidian-text-modal-loader" style="display: none;">
            <span class="obsidian-modal-spinner"></span>
         </div>

         <div class="obsidian-text-modal-content" id="obsidian-text-modal-content">
            <h2 id="obsidian-text-modal-title"></h2>
            <div id="obsidian-text-modal-body"></div>
         </div>
      </div>
   </div>
   <?php
}
add_action('wp_footer', 'obsidian_booking_render_text_modal');

/**
 * Global Logout Confirmation Modal (Phase 11.16)
 */
function obsidian_booking_render_logout_modal()
{
   if (is_admin() || !is_user_logged_in()) {
      return;
   }
   ?>
   <div id="obsidian-logout-modal" class="obf-modal-overlay" style="display:none;">
      <div class="obf-modal-content">
         <div class="obf-modal-icon" style="border-color: #C5A059; color: #C5A059; background: rgba(197, 160, 89, 0.1);">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
         </div>
         <h2 class="obf-modal-title">Log Out?</h2>
         <p class="obf-modal-text">Are you sure you want to log out of your Obsidian account?</p>
         <div style="display: flex; gap: 16px; justify-content: center; width: 100%;">
            <button type="button" id="obf-logout-cancel" class="obf-modal-btn" style="background: rgba(255,255,255,0.05); color: #fff; border: 1px solid rgba(255,255,255,0.1); flex: 1;">Cancel</button>
            <button type="button" id="obf-logout-confirm" class="obf-modal-btn" style="background: #C5A059; color: #0B0B0B; flex: 1;">Yes, Log Out</button>
         </div>
      </div>
   </div>
   <?php
}
add_action('wp_footer', 'obsidian_booking_render_logout_modal');


/* ──────────────────────────────────────────────
   ACF Title Synchronization
   Allows editing the Car/Location name directly from the meta box.
   ────────────────────────────────────────────── */

/**
 * Sync ACF name field to the WP Post Title when saved.
 */
function obsidian_sync_acf_to_post_title( $value, $post_id, $field ) {
   if ( ! $value ) {
      return $value;
   }

   // Update the post title directly in the DB
   // We use wp_update_post to ensure standard WP logic (sluggification, etc.) runs.
   // We remove the filter temporarily to avoid an infinite loop if we were
   // listening to save_post, but here we are in acf/update_value which is fine.
   global $wpdb;
   $wpdb->update(
      $wpdb->posts,
      array( 'post_title' => $value ),
      array( 'ID' => $post_id )
   );

   // Clear the cache for this post
   clean_post_cache( $post_id );

   return $value;
}
add_filter( 'acf/update_value/name=location_name', 'obsidian_sync_acf_to_post_title', 10, 3 );
add_filter( 'acf/update_value/name=car_name', 'obsidian_sync_acf_to_post_title', 10, 3 );

/**
 * Sync WP Post Title back to the ACF name field when loaded.
 */
function obsidian_sync_post_title_to_acf( $value, $post_id, $field ) {
   // If the post exists, use its actual title
   $post = get_post( $post_id );
   if ( $post && ( $post->post_type === 'car' || $post->post_type === 'location' ) ) {
      // Don't override if the title is "Auto Draft" (new post)
      if ( $post->post_title !== 'Auto Draft' && ! empty( $post->post_title ) ) {
         return $post->post_title;
      }
   }
   return $value;
}
add_filter( 'acf/load_value/name=location_name', 'obsidian_sync_post_title_to_acf', 10, 3 );
add_filter( 'acf/load_value/name=car_name', 'obsidian_sync_post_title_to_acf', 10, 3 );

/**
 * Admin JS to sync the ACF fields with the WP post title in real-time.
 * This solves the issue of not being able to save a new post because the title is "empty".
 */
function obsidian_sync_acf_title_js() {
   $screen = get_current_screen();
   if ( ! $screen || ! in_array( $screen->post_type, array( 'location', 'car' ), true ) ) {
      return;
   }
   ?>
   <script>
   jQuery(document).ready(function($) {
      // Find the ACF inputs for location_name or car_name
      var $acfInput = $('.acf-field[data-name="location_name"] input[type="text"], .acf-field[data-name="car_name"] input[type="text"]');

      if ( $acfInput.length ) {
         $acfInput.on('input', function() {
            var val = $(this).val();
            
            // Classic Editor Sync
            var $titleClassic = $('#title');
            if ( $titleClassic.length ) {
               $titleClassic.val(val).trigger('change');
               if(val) {
                  $('#title-prompt-text').addClass('screen-reader-text');
               } else {
                  $('#title-prompt-text').removeClass('screen-reader-text');
               }
            }
            
            // Gutenberg Editor Sync
            if ( typeof wp !== 'undefined' && wp.data && wp.data.dispatch && wp.data.dispatch('core/editor') ) {
               wp.data.dispatch('core/editor').editPost({ title: val });
            }
         });
      }
   });
   </script>
   <?php
}
add_action( 'admin_footer', 'obsidian_sync_acf_title_js' );
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
