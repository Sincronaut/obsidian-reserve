# Obsidian Reserve — Booking System Master Plan

> **Corrected & enhanced version of the Gemini roadmap.**  
> Changes from the original are marked with 🔧 (correction) or ➕ (addition).
> 
> **Last updated:** April 8, 2026 — Added multi-step booking page, PayMongo payment integration, admin document review gate, and security deposit.

---

## Review of Gemini's Plan: What's Right & What's Wrong

### ✅ Correct Decisions
| Item | Verdict |
|---|---|
| Custom plugin in `wp-content/plugins/` | Correct — keeps functionality separate from theme |
| Car + Booking as Custom Post Types | Correct — standard WordPress pattern |
| Flatpickr as the date picker | Correct — lightweight, no plugin bloat |
| Custom REST API for AJAX calls | Correct — modern WordPress approach |
| `wp_handle_upload()` for document uploads | Correct — uses WordPress's native secure upload handler |
| Staff review in WP Admin | Correct — no need for a custom admin panel |

### 🔧 Corrections Needed

| Gemini Says | Problem | Correction |
|---|---|---|
| Use ACF for **Booking** fields | Bookings are created/managed by code, not humans typing in the admin. ACF makes sense for Car fields (admin fills them in), but Booking meta should be registered with `register_post_meta()` — no plugin needed. | **Use ACF for Cars only. Register Booking meta with PHP.** |
| Status: "Draft, Pending, Approved" | Too few statuses. Doesn't account for document review, payment, or trip lifecycle. | **Use: pending_review → docs_approved → awaiting_payment → paid → confirmed → active → completed → denied** |
| "Query Loop block" for car cards | Query Loop blocks are fragile and hard to customize. You can't easily add a "Book Now" button with a `data-car-id` attribute through the block editor. | **Build a custom `car-grid` block in your theme (like your existing blocks).** |
| Redirect to `/requirements/?booking_id=XXX` | The booking_id in the URL can be tampered with. User could change it to view/submit against someone else's booking. | **Validate ownership server-side: check that the booking belongs to the logged-in user.** |
| Use a "shortcode" for the Requirements page | You're building a block theme. Shortcodes are the old way. | **Use a custom block or a custom page template.** |
| Availability math is Phase 6 (last) | This is **foundational** — the modal depends on it to disable dates. Building it last means you'd have a broken modal for weeks. | **Build availability engine in Phase 3, before the modal.** |
| Everything inside the modal | Too much crammed into a popup. Documents, payment, confirmation — all in one modal is bad UX. | **Modal shows car details + date selection. Booking form is a full multi-step page.** |
| Collect raw credit card data | PCI DSS violation. Storing card numbers on your server is illegal without Level 1 compliance ($50K–$500K/year). | **Use PayMongo payment gateway. Card data never touches your server.** |

### ➕ Missing from Gemini's Plan

| Missing Item | Why It Matters |
|---|---|
| **Race condition handling** | Two users booking the last unit simultaneously → overbooking |
| **Email notifications** | No one gets told when bookings are submitted/approved/denied |
| **User booking history** | Users can't see their own bookings after submitting |
| ~~Cancellation flow~~ | ~~Removed — business policy: no cancellations, no refunds~~ |
| **Expired pending cleanup** | Pending bookings that staff never review will block inventory forever |
| **Security: nonce verification** | AJAX calls need `wp_nonce` to prevent CSRF attacks |
| **Document file type validation** | Without it, someone could upload a .php file |
| **Price calculation** | No mention of daily rate × number of days |
| **Mobile responsive modal** | Modal needs to work on phones — luxury sites get mobile traffic |
| **Payment gateway** | No payment processing at all |
| **Admin document review** | No gate between user submitting docs and proceeding to payment |
| **Security deposit** | No mechanism for holding a refundable deposit |
| **Down payment option** | No option for partial payment |
| **User profile page** | No place for users to view/edit their info |

---

## The Corrected Master Plan

### What You Need to Install

| What | Type | Purpose |
|---|---|---|
| **Advanced Custom Fields (ACF) Free** | WP Plugin | Admin UI for entering Car data (specs, units, colors). Makes it easy for staff to manage cars without touching code. |
| **Theme My Login** | WP Plugin | Frontend login/registration pages. Optional — you can use WordPress built-in registration with custom styling instead. |
| **Flatpickr** | JS/CSS Library (bundled in your plugin, NOT a WP plugin) | Date picker in the booking modal |
| **PayMongo PHP SDK** | Composer package or REST API calls | Payment gateway for Visa, Mastercard, BPI, BDO, MetroBank |

### What You'll Build With Custom Code

| Component | Language | Where It Lives |
|---|---|---|
| Plugin bootstrap | PHP | `plugins/obsidian-booking/obsidian-booking.php` |
| Car + Booking CPT registration | PHP | `plugins/obsidian-booking/includes/post-types.php` |
| Booking meta fields | PHP | `plugins/obsidian-booking/includes/meta-fields.php` |
| Car class taxonomy | PHP | `plugins/obsidian-booking/includes/taxonomies.php` |
| Availability engine | PHP | `plugins/obsidian-booking/includes/availability.php` |
| REST API endpoints | PHP | `plugins/obsidian-booking/includes/rest-api.php` |
| Booking CRUD handler | PHP | `plugins/obsidian-booking/includes/booking-handler.php` |
| PayMongo integration | PHP | `plugins/obsidian-booking/includes/payment.php` |
| Email notifications | PHP | `plugins/obsidian-booking/includes/notifications.php` |
| Admin meta boxes + columns | PHP | `plugins/obsidian-booking/admin/` |
| Booking modal | HTML/CSS/JS | `plugins/obsidian-booking/assets/` + `templates/` |
| Multi-step booking page | PHP/CSS/JS | `plugins/obsidian-booking/templates/booking-page.php` + assets |
| Car grid block | PHP/CSS | `themes/child-obsidian-reserve/blocks/car-grid/` |
| Car single page template | HTML | `themes/child-obsidian-reserve/templates/single-car.html` |

### Booking Statuses (Full Lifecycle)

```
pending_review ──► awaiting_payment ──► paid ──► confirmed ──► active ──► completed
      │                    │                        │
      └──► denied          └──► denied              └──► denied (emergency: car issue)
```

| Status | Set By | Meaning | Blocks Inventory? |
|---|---|---|---|
| `pending_review` | System (Step 1 submit) | User submitted docs, waiting for admin review | ✅ Yes |
| `awaiting_payment` | Admin (approves docs) | Documents approved, user can proceed to payment | ✅ Yes |
| `paid` | PayMongo webhook | Payment received, auto-transitions to confirmed | ✅ Yes |
| `confirmed` | System (auto after payment) | Booking is locked in, car reserved | ✅ Yes |
| `active` | Cron (auto on start date) or Admin | Car is currently with the customer | ✅ Yes |
| `completed` | Admin | Car returned, trip done. Admin should release security deposit. | ❌ No |
| `denied` | Admin | Rejected at any stage (with reason) | ❌ No |

> [!NOTE]
> **`paid → confirmed` is automatic** — the PayMongo webhook handler transitions the status immediately after successful payment. No manual admin step needed.
>
> **`confirmed → denied` is an emergency override** — only used if a car breaks down or an exceptional situation arises. Admin must issue a PayMongo refund manually in this case.
>
> **`confirmed → active` is automatic** — a cron job transitions bookings to "active" at midnight on the start date. Admin can also do it manually from the dashboard.

### Payment Structure

| Item | Amount | When Charged |
|---|---|---|
| **Rental Payment** (user chooses one) | | |
| ↳ 50% Down Payment | 50% of total rental cost | Step 2 (online via PayMongo) |
| ↳ Full Prepayment | 100% of total rental cost | Step 2 (online via PayMongo) |
| **Security Deposit** | ₱10,000 or 40% of total, whichever is higher | Held on credit card (refundable) |
| **Balance (if 50% down)** | Remaining 50% | Collected at vehicle pickup (cash or card on-site) |

> [!IMPORTANT]
> **Deposit refund lifecycle:** After admin marks booking as "completed" (car returned), the admin meta box shows a reminder: _"Release security deposit via PayMongo dashboard."_ Track with `_booking_payment_status` = `deposit_released`.

### Booking Flow (User's Perspective)

```
FLEET PAGE
  └── Browse cars → Click "Book Now"

MODAL (on Fleet page)
  ├── Car image gallery + specs
  ├── Select dates on Flatpickr (unavailable dates grayed out)
  ├── Choose color variant
  ├── See price: ₱850/day × 5 days = ₱4,250
  └── Click [Proceed to Booking] → Redirects to /booking/ page

BOOKING PAGE — Step 1: Requirements
  ├── Local or Foreigner
  ├── Upload documents (ID, license, etc.)
  ├── Phone, contact info
  └── Submit → Status: PENDING_REVIEW
      ⏸️ WAIT — Admin reviews documents manually

  ✅ Admin approves docs → Status: AWAITING_PAYMENT → Email to user
  ❌ Admin denies docs → Status: DENIED → Email with reason

BOOKING PAGE — Step 2: Payment (only accessible when status = awaiting_payment)
  ├── Choose: 50% Down Payment or Full Prepayment
  ├── Security deposit notice (₱10,000 or 40%)
  ├── Pay via PayMongo: Visa, Mastercard, BPI, BDO, MetroBank
  └── PayMongo webhook → Status: PAID → auto → CONFIRMED

BOOKING PAGE — Step 3: Confirmation
  ├── ✅ "Your reservation is confirmed!"
  ├── Booking summary (car, dates, payment receipt)
  ├── If 50% down: "Balance of ₱2,125 due at pickup"
  └── CTA: [View My Reservations]
```

---

## Phase 1: Scaffolding & Plugin Setup ✅ COMPLETE
**⏱ Time: ~1 hour**  
**Goal: Plugin appears in WP Admin and activates without errors.**

### Step 1.1 — Create the plugin folder structure

Navigate to your WordPress plugins directory and create this structure.

> [!NOTE]
> **Directories start empty.** Only the bootstrap file and Flatpickr go in now. You'll create files inside `includes/`, `admin/`, `assets/`, and `templates/` in later phases as you need them. Each phase tells you exactly which file to create and which `require_once` line to add to the bootstrap.

```
wp-content/plugins/obsidian-booking/
├── obsidian-booking.php          ← You'll write this now
├── includes/                     ← Empty (Phase 2-3 adds files here)
├── admin/                        ← Empty (Phase 7 adds files here)
├── templates/
│   └── emails/                   ← Empty (Phase 9 adds files here)
├── assets/
│   ├── css/                      ← Empty (Phase 5 adds files here)
│   └── js/                       ← Empty (Phase 5 adds files here)
└── vendor/
    └── flatpickr/                ← Download these now
        ├── flatpickr.min.css
        └── flatpickr.min.js
```

### Step 1.2 — Write the plugin bootstrap

Create `obsidian-booking.php` with just the plugin header and constants. **No `require_once` statements yet** — you'll add those one at a time as you create each file in later phases.

```php
<?php
/**
 * Plugin Name: Obsidian Booking
 * Description: Custom booking system for Obsidian Reserve luxury car rentals.
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: obsidian-booking
 * Requires at least: 6.9
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Constants
define( 'OBSIDIAN_BOOKING_VERSION', '1.0.0' );
define( 'OBSIDIAN_BOOKING_DIR', plugin_dir_path( __FILE__ ) );
define( 'OBSIDIAN_BOOKING_URL', plugin_dir_url( __FILE__ ) );

// Uncomment each line as you create the file in its phase.
```

### Step 1.3 — Initialize Git

Navigate to `wp-content/` and run:
```bash
git init
git add .
git commit -m "Phase 1: Plugin scaffolding"
```

### ✅ Phase 1 Done When:
- [x] Plugin shows in WP Admin → Plugins list
- [x] Activating the plugin causes NO errors
- [x] Flatpickr files exist in `vendor/flatpickr/`
- [x] Git commit: "Phase 1: Plugin scaffolding"

---

## Phase 2: Data Model ✅ COMPLETE
**⏱ Time: ~3-4 hours**  
**Goal: Cars and Bookings exist as post types with all required fields.**

### Step 2.1 — Register Custom Post Types

Create `includes/post-types.php`:
- **Car** CPT: public, has archive at `/cars/`, supports title + editor + thumbnail + custom-fields, REST-enabled
- **Booking** CPT: NOT public (no front-end URL), shows in admin, REST-enabled, supports title + custom-fields

### Step 2.2 — Configure ACF fields for Cars

Install ACF (free) from Plugins → Add New.

Go to **ACF → Field Groups → Add New**:

**Field Group: "Car Details"**  
Assign to: Post Type = Car

| Field Label | Field Name | Type | Notes |
|---|---|---|---|
| Make | `car_make` | Text | e.g. "Nissan" |
| Model | `car_model` | Text | e.g. "GTR" |
| Year | `car_year` | Number | e.g. 2024 |
| Daily Rate | `car_daily_rate` | Number | In PHP peso, e.g. 850.00 |
| Total Units | `car_total_units` | Number | **DEPRECATED** — now derived from color variants. Kept as fallback. |
| Colors | `car_colors` | Checkbox | Orange, Red, Black, Blue (expandable) — source of truth for which colors exist |
| Car Status | `car_status` | Select | Choices: available, maintenance, retired |
| Image - Exterior | `car_img_exterior` | Image | Return: Image URL (modal gallery) |
| Image - Interior | `car_img_interior` | Image | Return: Image URL (modal gallery) |
| Image - Engine | `car_img_engine` | Image | Return: Image URL (modal gallery) |
| Image - Detail | `car_img_detail` | Image | Return: Image URL (modal gallery) |

### Step 2.2b — Color Variant Data (added post-Phase 4)

Per-color inventory (units + card image) is stored as a JSON meta field on the Car CPT:

**Meta field:** `_car_color_variants` (registered via `register_post_meta()` in `includes/meta-fields.php`)

```json
{
  "orange": { "units": 3, "image_id": 456 },
  "black":  { "units": 2, "image_id": 789 }
}
```

**Why not ACF?** ACF Free has no Repeater field, and the color list is dynamic. A single JSON field scales to any number of colors without creating new ACF fields.

**Admin UI:** A custom meta box in `admin/car-meta-box.php` reads the ACF `car_colors` checkbox and renders per-color inputs (units + WP Media image picker). Saving serializes to JSON.

**Helpers** (in `includes/availability.php`):
- `obsidian_get_color_variants( $car_id )` — reads + decodes the JSON (with backwards-compat fallback)
- `obsidian_get_color_hex( $color_name )` — maps color name → hex code for swatch rendering
- `obsidian_get_total_units( $car_id )` — sum of all color variant units (replaces `car_total_units`)
- `obsidian_get_available_units_by_color( $car_id, $color, $start, $end )` — per-color availability check

### Step 2.3 — Register Booking meta fields with PHP (NOT ACF)

> [!IMPORTANT]
> 🔧 **Why not ACF for Bookings?** Because your code creates and updates bookings. ACF is for humans typing in the admin. Booking fields are set programmatically via your REST API. Using `register_post_meta()` is simpler and has zero plugin dependency.

Create `includes/meta-fields.php`:

```php
<?php
function obsidian_register_booking_meta() {
    $fields = [
        '_booking_car_id'           => 'integer',
        '_booking_user_id'          => 'integer',
        '_booking_start_date'       => 'string',   // Y-m-d
        '_booking_end_date'         => 'string',   // Y-m-d
        '_booking_pickup_location'  => 'string',
        '_booking_customer_type'    => 'string',   // local | international
        '_booking_status'           => 'string',   // pending_review | docs_approved | awaiting_payment | paid | confirmed | active | completed | denied
        '_booking_documents'        => 'string',   // JSON array of attachment IDs
        '_booking_admin_notes'      => 'string',
        '_booking_total_price'      => 'number',
        '_booking_color'            => 'string',
        '_booking_payment_type'     => 'string',   // down_payment | full_prepayment
        '_booking_payment_amount'   => 'number',   // Amount actually paid (50% or 100%)
        '_booking_deposit_amount'   => 'number',   // Security deposit held
        '_booking_payment_id'       => 'string',   // PayMongo payment intent ID
        '_booking_payment_status'   => 'string',   // unpaid | paid | refunded (deposit)
        '_booking_denial_reason'    => 'string',   // Reason for denial (shown to user)
    ];

    foreach ( $fields as $key => $type ) {
        register_post_meta( 'booking', $key, [
            'show_in_rest' => true,
            'single'       => true,
            'type'         => $type,
        ]);
    }
}
add_action( 'init', 'obsidian_register_booking_meta' );
```

> [!WARNING]
> ⚡ **CODE CONFLICT:** The current `meta-fields.php` in your codebase uses the OLD status list and is missing the payment fields. You will need to update it with these new fields before proceeding to Phase 6.

### Step 2.4 — Register a Car Class taxonomy

Create `includes/taxonomies.php`:

Categories: **Exotic**, **Executive**, **SUV**, **Sport**

This lets you filter cars by class on the frontend and in admin.

### Step 2.5 — Add test data

In WP Admin, go to **Cars → Add New**:
1. **Nissan GTR Katsura Orange** — Exotic, ₱850/day, 3 units
2. **Porsche 911 GTS** — Sport, ₱950/day, 2 units
3. **Cadillac Escalade** — SUV, ₱650/day, 4 units

Upload featured images and fill in all ACF fields.

### ✅ Phase 2 Done When:
- [x] "Cars" menu in WP Admin sidebar with car icon
- [x] "Bookings" menu in WP Admin sidebar with calendar icon
- [x] You can create a car with all fields (make, model, rate, units, specs)
- [x] ACF fields display correctly on the Car edit screen
- [x] 3 test cars exist with full data
- [x] Git commit: "Add Car and Booking data model"

---

## Phase 3: Availability Engine + REST API ✅ COMPLETE
**⏱ Time: ~4-5 hours**  
**Goal: You can hit API endpoints in your browser and see real data.**

> [!WARNING]
> 🔧 **Gemini put this as Phase 6 (last). That's backwards.** The modal can't work without availability data. Build the engine BEFORE the UI.

### Step 3.1 — Write the availability functions

Create `includes/availability.php`:

**Function 1: `obsidian_get_available_units( $car_id, $start_date, $end_date )`**
- Counts how many units are still free for a car during a date range
- Uses date-overlap detection: `existing_start < requested_end AND existing_end > requested_start`
- Only counts bookings with blocking statuses (see status table above)
- Returns an integer (0 = fully booked)

**Function 2: `obsidian_is_car_available( $car_id, $start_date, $end_date )`**
- Simple boolean wrapper: returns true if available_units > 0

**Function 3: `obsidian_get_unavailable_dates( $car_id, $days_ahead = 90 )`**
- Loops through the next 90 days
- For each day, counts overlapping bookings
- Returns array of 'Y-m-d' strings where ALL units are booked
- This array feeds directly into Flatpickr's `disable` option

> [!WARNING]
> ⚡ **CODE CONFLICT:** The current `availability.php` uses `['pending', 'confirmed', 'active']` as blocking statuses. You need to update this to `['pending_review', 'docs_approved', 'awaiting_payment', 'paid', 'confirmed', 'active']` — all statuses before "completed" or "denied" should block inventory.

### Step 3.2 — Write the REST API endpoints

Create `includes/rest-api.php`:

| Endpoint | Method | Auth | Purpose | Test URL |
|---|---|---|---|---|
| `/obsidian-booking/v1/cars` | GET | Public | List available cars | `yoursite.com/wp-json/obsidian-booking/v1/cars` |
| `/obsidian-booking/v1/cars/{id}` | GET | Public | Single car + specs | `yoursite.com/wp-json/obsidian-booking/v1/cars/105` |
| `/obsidian-booking/v1/availability/{car_id}` | GET | Public | Unavailable dates | `yoursite.com/wp-json/obsidian-booking/v1/availability/105` |
| `/obsidian-booking/v1/bookings` | POST | Logged in | Create booking (Step 1 submit) | (test with Postman or JS) |
| `/obsidian-booking/v1/bookings/mine` | GET | Logged in | User's bookings | (test when logged in) |
| `/obsidian-booking/v1/bookings/{id}/payment` | POST | Owner only | Initiate PayMongo payment (Step 2) | (test with JS) |
| `/obsidian-booking/v1/upload-document` | POST | Logged in | Upload ID/passport | (test with form) |
| `/obsidian-booking/v1/webhook/paymongo` | POST | PayMongo | Payment confirmation webhook | (auto-called by PayMongo) |

> [!WARNING]
> ⚡ **CODE CONFLICT:** The current `rest-api.php` is missing the `/bookings/{id}/payment` and `/webhook/paymongo` endpoints. The `/bookings` POST endpoint also needs to be updated — it currently sets status to "pending" but should set it to "pending_review". These updates happen in Phase 6.

### Step 3.3 — Write the booking handler

Create `includes/booking-handler.php`:

**Function: `obsidian_update_booking_status( $booking_id, $new_status, $notes )`**
- Validates the status transition
- Updates the meta
- Fires a custom action hook: `do_action( 'obsidian_booking_status_changed', ... )`
- This hook is what triggers email notifications later

> [!WARNING]
> ⚡ **CODE CONFLICT:** The current `booking-handler.php` uses the OLD status transition map. You need to update `obsidian_get_status_transitions()` with the new statuses before proceeding to Phase 7. New transitions:
> ```
> pending_review   → [docs_approved, denied]
> docs_approved    → [awaiting_payment, denied]
> awaiting_payment → [paid, denied]
> paid             → [confirmed, denied]
> confirmed        → [active, denied]
> active           → [completed]
> completed        → []
> denied           → []
> ```

### Step 3.4 — Test your API

Open your browser and navigate to:
```
http://yoursite.local/wp-json/obsidian-booking/v1/cars
```

You should see JSON data for your test cars. If you see this, your engine works.

### Step 3.5 — Test edge cases

Create test bookings, then re-check the availability endpoint:
1. Create a booking for Nissan GTR, May 10–15, status: "confirmed"
2. Create another for same car, May 12–18, status: "pending_review"
3. Create a third for same car, May 10–15, status: "denied"

Check availability for May 10–15:
- Should show 1 unit available (3 total - 1 confirmed - 1 pending_review = 1, denied doesn't count)

### ✅ Phase 3 Done When:
- [x] `/cars` endpoint returns your test cars with all fields
- [x] `/availability/{id}` returns correct unavailable dates
- [x] Manually created bookings correctly reduce availability
- [x] Denied bookings do NOT reduce availability
- [x] Overlapping date ranges are correctly detected
- [x] Git commit: "Add availability engine and REST API"

---

## Phase 4: User Authentication ✅ COMPLETE
**⏱ Time: ~2-3 hours**  
**Goal: Users can register, log in, and be gated from booking until authenticated.**

### Step 4.1 — Set up login/registration pages

**Option A — Theme My Login plugin (easiest):**
- Install the plugin
- It auto-creates `/login/` and `/register/` pages
- Style them to match your dark theme with gold accents

**Option B — Built-in WordPress (no plugin):**
- Enable registration: Settings → General → check "Anyone can register"
- Create custom page templates with `wp_login_form()` in your theme
- Style the forms

### Step 4.2 — Gate the booking button

In your car card template (you'll build in Phase 5):

```php
if ( is_user_logged_in() ) {
    // Show "Book Now" button that opens the modal
} else {
    // Show "Sign In to Book" link pointing to login page
    // Pass redirect URL so after login they return to this car
}
```

### Step 4.3 — Add user meta fields

Create `includes/user-fields.php`:

Add these fields to user profiles:
- Phone number
- Nationality
- Driver's license number

Use `show_user_profile` + `edit_user_profile` + `personal_options_update` hooks.

### ✅ Phase 4 Done When:
- [x] A visitor can register for an account
- [x] A registered user can log in on the frontend
- [x] Logged-in users have extra profile fields (phone, nationality, license)
- [ ] Login/register pages match your theme design (can be styled later)
- [ ] Git commit: "Add user authentication flow"

---

## Phase 5: Car Display + Booking Modal
**⏱ Time: ~6-8 hours**  
**Goal: Users can see cars, open the modal, pick dates, and proceed to the booking page.**

> [!NOTE]
> The modal is now **lighter** than before — it shows car details and date selection only. The requirements form, payment, and confirmation happen on a separate multi-step booking page (Phase 6).

### Step 5.1 — Build a car-grid block in your theme

Create `themes/child-obsidian-reserve/blocks/car-grid/`:
- `block.json` — block registration
- `render.php` — uses `WP_Query` to loop through published Cars
- `style.css` — card styling matching your existing design language

Each card displays:
- Featured image (swaps when user clicks a color swatch)
- Car name
- **Color swatches** — small colored circles for each variant. Clicking a swatch swaps the card image and updates the per-color unit count. First color is active by default.
- Daily rate
- Per-color available units badge (updates with swatch selection)
- Car class tag (Exotic, SUV, etc.)
- "Book Now" button with `data-car-id` attribute (or "Sign In to Book" if logged out)
- **Class filter tabs** — All / Exotic / Executive / SUV / Sport (client-side JS filtering)

Register the block in your theme's `functions.php` alongside your other blocks.

### Step 5.2 — Create a Fleet/Inventory page template

Create `templates/page-fleet.html` in your theme.

Uses your header part, the car-grid block, and your footer part.

### Step 5.3 — Build the booking modal

Create the modal as HTML injected via `wp_footer` hook from your plugin. Uses a **two-column layout**:

```
┌─────────────────────────────────────────────────────────────────────────┐
│  ✕                                                                     │
│                                                                        │
│  LEFT COLUMN                   │  RIGHT COLUMN                         │
│                                │                                       │
│  [Main Image ────────────]     │  Nissan GTR Katsura Orange (R35)      │
│  [img1][img2][img3][img4][img5]│                                       │
│                                │  • Engine: 3.8L V6 Twin-Turbo         │
│  [Orange] [Black] ← swatches  │  • Power: 565 hp                      │
│                                │  • Torque: 637 Nm                     │
│  Exotic        ₱850 / day     │  • Transmission: 6-speed DCT          │
│                                │  ...more specs from car_specs ACF...  │
│                                │                                       │
│                                │  Tell us who you are.                 │
│                                │  We handle the rest.                  │
│                                │                                       │
│                                │  (●) Local Renters                    │
│                                │  (○) International Renters            │
│                                │                                       │
│                                │  [Pick-up Date] [Drop-off Date]       │
│                                │                                       │
│                                │  TOTAL: ₱4,250 (5 days × ₱850/day)   │
│                                │                                       │
│                                │  [Reserve GTR]  [Check Availability]  │
└─────────────────────────────────────────────────────────────────────────┘
```

Left column contains:
- Hero image (image 1 from color variant — the card thumbnail)
- 5 gallery thumbnails (images 2–6 from color variant)
- Color variant buttons (swatch + name + availability count)
- Class badge + daily rate at the bottom

Right column contains:
- Car name (H3, gold)
- Specifications (bulleted list from ACF `car_specs` textarea, one spec per line)
- Customer type section: "Tell us who you are. We handle the rest." + Local/International radio buttons
- Flatpickr date pickers (pick-up + drop-off)
- Dynamic total price (appears after both dates selected)
- Two action buttons: "Reserve [Car Name]" (gold, submits) + "Check Vehicle Availability" (outline, opens date picker)

> [!NOTE]
> No documents, no payment in the modal. Those happen on separate pages (Phase 6). The modal captures: car selection, color, dates, customer type, and the price preview.

> [!IMPORTANT]
> If the selected color variant has 0 available units, the "Reserve" button stays disabled.

### Step 5.4 — Write the modal JavaScript

Create `assets/js/modal.js`:

```
User clicks "Book Now" →
  1. Get car_id from the button's data attribute
  2. Fetch car details:   GET /obsidian-booking/v1/cars/{id}
  3. Fetch availability:  GET /obsidian-booking/v1/availability/{id}
  4. Populate modal: gallery, specs, color swatches, car info
  5. Initialize Flatpickr with unavailable dates grayed out
  6. Show modal (fade in, lock body scroll)

User picks a color →
  7. Swap all 6 gallery images to that color's set (image 1 = hero, 2–6 = thumbs)
  8. Re-validate form (block if 0 units for that color)

User picks dates →
  9. Calculate total: days × daily_rate
  10. Show "TOTAL: ₱X,XXX (N days × ₱rate/day)" dynamically

User clicks "Reserve [Car Name]" →
  11. Validate: dates selected, color chosen, color has units > 0
  12. Redirect to /booking/?car_id=X&start=YYYY-MM-DD&end=YYYY-MM-DD&color=orange&customer_type=local
```

### Step 5.5 — Style the modal

Create `assets/css/modal.css`:

- Match your theme: dark background (#0B0B0B), gold accents (#C5A059), Montserrat font
- Full-screen overlay with backdrop blur
- Two-column panel, max-width ~1100px
- Left column: gallery + colors + price/class
- Right column: specs + form + actions
- Custom Flatpickr dark theme override (gold selected dates)
- Mobile responsive (stacks to single column on ≤860px, full-screen on ≤600px)
- Smooth open/close animations

### Step 5.6 — Enqueue assets from the plugin

In `obsidian-booking.php`, add a `wp_enqueue_scripts` hook that loads:
- Flatpickr CSS + JS (from `vendor/`)
- Your `modal.css` and `modal.js`
- `wp_localize_script` to pass the REST URL and nonce to JavaScript

```php
wp_localize_script( 'obsidian-booking-modal', 'obsidianBooking', [
    'restUrl'        => rest_url( 'obsidian-booking/v1/' ),
    'nonce'          => wp_create_nonce( 'wp_rest' ),
    'loggedIn'       => is_user_logged_in(),
    'loginUrl'       => wp_login_url( get_permalink() ),
    'bookingPageUrl' => home_url( '/booking/' ),
]);
```

> [!IMPORTANT]
> The nonce is critical. Without it, your REST endpoints would accept requests from anywhere (CSRF vulnerability).

### ✅ Phase 5 Done When:
- [x] Car grid displays all available cars with correct data
- [x] Color swatches on cards swap the card image + show per-color units
- [x] Clicking "Book Now" opens the two-column modal with gallery + specs
- [x] Selecting a color in the modal swaps all 6 gallery images for that color
- [x] Flatpickr shows disabled dates for fully-booked days
- [x] Price calculates dynamically as dates are selected
- [x] 0-unit colors block the Reserve button
- [x] "Reserve [Car Name]" redirects to the booking page with URL params
- [x] Modal works on mobile (stacks to single column)
- [ ] Git commit: "Add car grid and booking modal"

---

## Phase 6: Booking Form, Payment & Confirmation (Separate Pages)
**⏱ Time: ~10-12 hours (largest phase)**  
**Goal: Users can submit requirements, get admin approval, pay, and receive confirmation — each on its own page.**

> [!IMPORTANT]
> This is a **multi-page flow**, NOT a single-page wizard. Each step has its own URL. This makes the flow
> easier to reason about, allows direct linking from emails, and keeps each page focused.
>
> **Page structure:**
> | Page | URL | When accessible |
> |---|---|---|
> | Booking Form | `/booking/` | After clicking "Reserve" in the modal |
> | Payment | `/booking/payment/` | After admin approves documents |
> | Confirmation | `/booking/confirmation/` | After successful payment |

### Step 6.1 — Create the Booking Form page (`/booking/`)

Create a WordPress page at `/booking/`. Use a custom page template rendered by the plugin.

The page reads URL parameters passed from the modal redirect:
- `car_id`, `start`, `end`, `color`, `customer_type`

Server-side: validate that the car exists and the dates are available. If invalid → redirect back to fleet with an error notice.

> [!IMPORTANT]
> The form displayed depends on the `customer_type` URL parameter (`local` or `international`).
> Both forms share the same booking summary header and submit to the same endpoint,
> but they collect **different fields and documents**.

Both forms are rendered by a **single `booking-form` block** (`blocks/booking-form/`). The block reads
`customer_type` from the URL and conditionally shows/hides the relevant fields. Email is pulled from the
logged-in user's account — no email field in the form.

#### 6.1a — Local Renter Form (`customer_type=local`)

```
┌──────────────────────────────────────────────────────┐
│  Local Renters Form                                  │
│  Your exact vehicle starts with this form.           │
│  (1)───────(2)───────(3)   ← progress stepper       │
│                                                      │
│  [View Documents Requirements]                       │
│                                                      │
│  ── BOOKING SUMMARY (car image + details) ──         │
│                                                      │
│  First Name       [ex : Juan Miguel          ]       │
│  Last Name        [ex : Dela Cruz            ]       │
│  Address          [ex : 123 Street, Manila   ]       │
│  Birth Date       [Month / Day / Year]  21+*         │
│  Mobile Number    [+63                       ]       │
│  Driver License # [N04-000-000-000]  2yr hold*       │
│  [Upload Drivers License     ]                       │
│                                                      │
│  ── GOVERNMENT ID ────────────────                   │
│  [Select Government ID     ▼]                        │
│  [Select Government ID     ▼]                        │
│  [Upload ID (front)]  [Upload ID (back)]             │
│                                                      │
│  ── LOCATION ────────────────                        │
│  [Select Obsidian Location  ▼]                       │
│                                                      │
│  ☐ I agree to the Terms and Conditions               │
│  ☐ I agree to the Privacy Policy                     │
│                                                      │
│  [    Submit for Review    ]                         │
└──────────────────────────────────────────────────────┘
```

#### 6.1b — International Renter Form (`customer_type=international`)

```
┌──────────────────────────────────────────────────────┐
│  International Renters Form                          │
│  Land and drive. Fill in the details below.          │
│  (1)───────(2)───────(3)   ← progress stepper       │
│                                                      │
│  [View Documents Requirements]                       │
│                                                      │
│  ── BOOKING SUMMARY (car image + details) ──         │
│                                                      │
│  First Name       [ex : Juan Miguel          ]       │
│  Last Name        [ex : Dela Cruz            ]       │
│  Address          [ex : 123 Street, Manila   ]       │
│  Birth Date       [Month / Day / Year]  21+*         │
│  Driver License # [000-000-000-000]  2yr hold*       │
│  [Upload Drivers License     ]                       │
│                                                      │
│  ⚠ 90-Day Rule: Foreign license valid 90 days...     │
│                                                      │
│  Passport ID #    [000-000-000-000           ]       │
│  [Upload Passport ID        ]                        │
│  [Upload Proof of Arrival   ]                        │
│  (e-ticket, airline booking, arrival stamp)           │
│                                                      │
│  ── LOCATION ────────────────                        │
│  [Select Obsidian Location  ▼]                       │
│                                                      │
│  ☐ I agree to the Terms and Conditions               │
│  ☐ I agree to the Privacy Policy                     │
│                                                      │
│  [    Submit for Review    ]                         │
└──────────────────────────────────────────────────────┘
```

> [!NOTE]
> Both forms share: First Name, Last Name, Address, Birth Date (21+), Driver License Number (2-year hold),
> Upload Driver's License, Select Location, Terms + Privacy checkboxes, Submit for Review.
>
> **Local-only:** Mobile Number, Government ID (2 type dropdowns + 2 upload zones front/back).
>
> **International-only:** Passport ID Number, Upload Passport ID, Proof of Arrival upload, 90-Day Rule notice.

On submit (both forms):
1. Each document is uploaded individually via `POST /obsidian-booking/v1/upload-document`
2. Once all uploads complete, JS calls `POST /obsidian-booking/v1/bookings` with form data + attachment IDs
3. Server validates fields, re-checks availability, creates booking with status `pending_review`
4. Email is pulled from `wp_get_current_user()->user_email` (no email field needed)
5. User sees: "Your documents have been submitted for review!"
6. Admin gets email notification

### Step 6.2 — Admin reviews documents (happens in WP Admin)

This is handled by Phase 7 (Admin UI). The admin:
1. Opens the booking in WP Admin
2. Views uploaded documents inline
3. Clicks **[Approve Documents]** → status changes to `awaiting_payment`
4. Or clicks **[Deny]** with a reason → status changes to `denied`
5. User gets email either way

### Step 6.3 — Create the Payment page (`/booking/payment/`)

User receives email: "Your documents are approved! Click here to complete your payment."

The email link goes to:
```
/booking/payment/?booking_id=XXX&token=SECURE_TOKEN
```

> [!IMPORTANT]
> The `token` is a one-time secure hash generated when the booking is approved. It proves the user
> owns this booking without requiring login. This keeps the URL clean and prevents unauthorized access.

Server verifies:
- Token is valid for this booking ID
- Status is `awaiting_payment`
- If not → redirect to `/booking/` with error

```
┌──────────────────────────────────────────────────────┐
│  PAYMENT                                             │
│                                                      │
│  ── BOOKING SUMMARY ──────────────────               │
│  Nissan GTR Katsura Orange                           │
│  May 10-15, 2026 (5 days)                            │
│  Daily Rate: ₱850  |  Total: ₱4,250                 │
│                                                      │
│  ── PAYMENT OPTION ───────────────────               │
│  (●) Down Payment — ₱2,125 (50%)                    │
│      Balance of ₱2,125 due at pickup                 │
│  (○) Full Prepayment — ₱4,250 (100%)                │
│                                                      │
│  ── SECURITY DEPOSIT ─────────────────               │
│  ₱10,000 hold on your card (refundable)              │
│  Released within 7 days after vehicle return          │
│                                                      │
│  ── PAY WITH ─────────────────────────               │
│  [Visa] [Mastercard] [BPI] [BDO] [MetroBank]        │
│                                                      │
│  Card Number    [                          ]         │
│  Expiry         [     ]   CVV  [    ]                │
│  Cardholder     [                          ]         │
│                                                      │
│  ─────────────────────────────────────               │
│  You will be charged: ₱12,125                        │
│  (₱2,125 rental + ₱10,000 deposit)                  │
│                                                      │
│  [    Complete Payment    ]                          │
│                                                      │
└──────────────────────────────────────────────────────┘
```

On submit:
1. JavaScript calls PayMongo API to create a Payment Intent
2. PayMongo handles card data securely (card data never hits your server)
3. PayMongo webhook fires → your server receives payment confirmation
4. Status → `paid` → auto-transitions to `confirmed`
5. Server redirects to `/booking/confirmation/?booking_id=XXX`

### Step 6.4 — Create the Confirmation page (`/booking/confirmation/`)

After successful payment, the user lands on this page. It also serves as the receipt.

```
┌──────────────────────────────────────────────────────┐
│  Reservation Confirmed!                              │
│                                                      │
│  ── YOUR BOOKING ─────────────────────               │
│  [Car Image]                                         │
│  Nissan GTR Katsura Orange                           │
│  May 10 – May 15, 2026  (5 days)                     │
│  Color: Orange                                       │
│                                                      │
│  ── PAYMENT RECEIPT ──────────────────               │
│  Rental (50% down):    ₱2,125                        │
│  Security deposit:     ₱10,000                       │
│  Total charged:        ₱12,125                       │
│  Balance at pickup:    ₱2,125                        │
│                                                      │
│  Booking ID: #301                                    │
│  Status: Confirmed                                   │
│                                                      │
│  [  View My Reservations  ]                          │
│                                                      │
└──────────────────────────────────────────────────────┘
```

### Step 6.5 — PayMongo Integration ✅

Created `includes/payment.php`:

- `obsidian_generate_payment_token( $booking_id )` — generates a secure SHA-256 token, stored as `_booking_payment_token`
- `obsidian_verify_payment_token( $booking_id, $token )` — constant-time comparison
- `obsidian_get_payment_url( $booking_id, $token )` — builds `/booking/payment/?booking_id=X&token=Y`
- `obsidian_create_payment_intent( $booking_id )` — creates PayMongo Payment Intent (amount = rental + security deposit in centavos)
- REST endpoint `POST /obsidian-booking/v1/create-payment-intent` — verifies token + status + user, then calls `obsidian_create_payment_intent()`
- REST endpoint `POST /obsidian-booking/v1/paymongo-webhook` — receives PayMongo `payment.paid` event, finds booking by `_booking_payment_id`, transitions `awaiting_payment` → `paid` → `confirmed`

Created `assets/js/payment-form.js`:

Client-side PIPM (Payment Intent + Payment Method) flow:
1. Creates Payment Intent via our REST API (`create-payment-intent`)
2. Creates Payment Method directly with PayMongo (card data never touches our server)
3. Attaches Payment Method to Payment Intent
4. Handles 3D Secure redirect if needed (`awaiting_next_action`)
5. On success → redirects to `/booking/confirmation/?booking_id=X`

PayMongo API keys stored in `wp-config.php`:
```php
define( 'PAYMONGO_SECRET_KEY', 'sk_test_...' );
define( 'PAYMONGO_PUBLIC_KEY', 'pk_test_...' );
```

### Step 6.6 — Page template routing (Option A) ✅

Created `includes/booking-pages.php`:

Uses WordPress rewrite rules to create clean sub-URLs under `/booking/`:

```php
add_rewrite_rule( '^booking/payment/?$', 'index.php?pagename=booking&ob_step=payment', 'top' );
add_rewrite_rule( '^booking/confirmation/?$', 'index.php?pagename=booking&ob_step=confirmation', 'top' );
```

The existing `booking-form` block's `render.php` checks `get_query_var('ob_step')` and routes:
- No step → booking form (Step 6.1) — existing `render.php` logic
- `payment` → `render-payment.php` (Step 6.3) — validates token + status + user ownership
- `confirmation` → `render-confirmation.php` (Step 6.4) — shows receipt

All three are rendered within the same `page-booking.html` FSE template (header/footer).
Single WordPress page, three distinct URLs, one block with routing logic.

### Step 6.7 — Approval triggers payment link ✅

When admin clicks "Approve Documents" in `admin/booking-meta-box.php`:
1. Status changes to `awaiting_payment`
2. `obsidian_generate_payment_token()` creates a secure token
3. `wp_mail()` sends the payment link to the customer
4. Booking's `_booking_status` auto-updates — user sees "Awaiting Payment" in their account

### ✅ Phase 6 Done When:
- [x] `/booking/` renders the booking form with correct car info from URL params
- [x] Form submits documents and creates booking (status: `pending_review`)
- [x] `/booking/payment/` only loads if docs are approved (server-side token check)
- [ ] PayMongo test payment works (use test card 4242 4242 4242 4242)
- [ ] Webhook updates booking status to `paid`
- [x] `/booking/confirmation/` shows confirmation with payment receipt
- [ ] All three pages work on mobile
- [ ] Git commit: "Add booking form, payment, and confirmation pages"

---

## Phase 7: Admin Approval Workflow
**⏱ Time: ~4-5 hours**  
**Goal: Staff can review documents, approve/deny bookings, and manage the pipeline.**

### Step 7.1 — Custom admin columns for Bookings list

Create `admin/booking-columns.php`:

The default Bookings list in admin shows "Title" and "Date". Useless. Override it to show:

| Column | What It Shows |
|---|---|
| Car | Name of the reserved car (linked) |
| Customer | User's display name + email |
| Dates | May 10 – May 15, 2026 |
| Type | Badge: "Local" or "Foreigner" |
| Status | Color-coded: 🟡 pending_review, 🟢 confirmed, 🔴 denied, 🔵 paid, ✅ completed |
| Payment | ₱2,125 / ₱4,250 (paid / total) |

### Step 7.2 — Booking detail meta box

Create `admin/booking-meta-box.php`:

When admin clicks into a booking, they see a custom panel with:
- Car details summary
- Customer info (name, email, phone)
- Date range
- **Uploaded documents** (viewable inline — so staff can verify IDs without downloading)
- **Action buttons** (context-dependent):
  - Status = `pending_review`: **[Approve Documents]** **[Deny]**
  - Status = `confirmed`: **[Mark as Active]** (if manual override needed)
  - Status = `active`: **[Mark as Completed]** (car returned) → shows deposit refund reminder
- **Notes field** for internal staff comments
- **Denial reason** field (shown to user in email)

### Step 7.3 — Handle approve/deny actions

When admin clicks **Approve Documents**:
1. Status changes to `awaiting_payment`
2. Custom hook `obsidian_booking_status_changed` fires
3. Email sent to user: "Documents approved — complete your payment"

When admin clicks **Deny**:
1. Staff enters a reason
2. Status changes to `denied`
3. Email sent to user with reason
4. Inventory released

### Step 7.4 — Dashboard widget

Create `admin/dashboard-widget.php`:

On the WP Dashboard home screen, show a widget:
```
┌──────────────────────────────────────┐
│  📋 Pending Review: 3                │
│  💳 Awaiting Payment: 1              │
│  ✅ Active Rentals: 2                │
│                                      │
│  NEEDS ATTENTION:                    │
│  • Nissan GTR — John D.              │
│    Docs submitted  |  [Review →]     │
│                                      │
│  • Porsche 911 — Jane S.             │
│    Payment received |  [Confirm →]   │
│                                      │
│  • Escalade — Bob M.                 │
│    Starts TOMORROW  |  ⚠️ Urgent     │
│                                      │
└──────────────────────────────────────┘
```

### ✅ Phase 7 Done When:
- [ ] Bookings list table shows all custom columns
- [ ] Admin can click into a booking and see full details + documents
- [ ] Approve Documents button changes status + sends email to user
- [ ] Deny button with reason changes status + sends email
- [ ] Dashboard widget shows booking pipeline with quick links
- [ ] Git commit: "Add admin booking management UI"

---

## Phase 8: Email Notifications
**⏱ Time: ~3-4 hours**  
**Goal: Automated emails at every stage of the booking lifecycle.**

### Step 8.1 — Create email templates

Create HTML email templates in `templates/emails/`:

| File | Trigger | Recipient | Subject |
|---|---|---|---|
| `booking-submitted.php` | Step 1 submitted | Admin | "[New Booking] John Doe — Nissan GTR — Docs to Review" |
| `booking-received.php` | Step 1 submitted | User | "Your Reservation Request Has Been Received" |
| `docs-approved.php` | Admin approves docs | User | "Documents Approved — Complete Your Payment" |
| `booking-denied.php` | Admin denies | User | "Update on Your Reservation Request" |
| `payment-received.php` | PayMongo webhook | Admin | "[Payment Received] John Doe — Nissan GTR" |
| `booking-confirmed.php` | Booking confirmed | User | "Your Nissan GTR Reservation is Confirmed ✅" |
| `booking-reminder.php` | 24h before pickup | User | "Your Nissan GTR pickup is tomorrow!" |

### Step 8.2 — Hook into the status change action

```php
add_action( 'obsidian_booking_status_changed', function( $booking_id, $old, $new ) {
    if ( $new === 'pending_review' )    → notify admin (new submission) + receipt to user
    if ( $new === 'awaiting_payment' )  → send "docs approved, now pay" email to user
    if ( $new === 'denied' )            → send denial email with reason
    if ( $new === 'paid' )              → notify admin (payment received)
    if ( $new === 'confirmed' )         → send confirmation email to user
}, 10, 3 );
```

### Step 8.3 — Style the emails

- Simple, clean HTML emails
- Your logo at the top
- Dark background matching your brand
- Gold accent for CTAs
- Test in Gmail, Outlook, Apple Mail

> [!TIP]
> For local development, emails won't actually send. Install the **WP Mail Log** plugin to capture and preview emails, or use **MailHog** if your Local Sites setup supports it.

### ✅ Phase 8 Done When:
- [ ] Step 1 submit → admin gets email, user gets receipt
- [ ] Docs approved → user gets "proceed to payment" email with link
- [ ] Denial → user gets email with reason
- [ ] Payment → admin notified, user gets confirmation
- [ ] Emails render correctly across clients
- [ ] Git commit: "Add email notification system"

---

## Phase 9: User Dashboard + Profile
**⏱ Time: ~4-5 hours**  
**Goal: Users can view their bookings, edit their profile, and track booking progress.**

> [!IMPORTANT]
> **Business policy: No cancellations, no refunds.** Users cannot cancel bookings once submitted. Only admin can deny a booking. This simplifies the system significantly.

### Step 9.1 — "My Reservations" page

Create a page at `/my-reservations/` that shows the logged-in user's booking history.

Uses the `/obsidian-booking/v1/bookings/mine` endpoint to load data.

Each booking card shows:
- Car image + name
- Dates
- Status (color-coded badge with progress indicator)
- Amount paid + balance remaining (if down payment)
- **Action needed** indicator:
  - Status = `awaiting_payment` → **[Complete Payment]** button
  - Status = `confirmed` → "See you on May 10!"
  - Status = `pending_review` → "Under review..."
- No cancel button — policy is no cancellations

### Step 9.2 — Profile page

Create a page at `/profile/` for logged-in users:

- Display name, email
- Phone number (editable)
- Nationality (editable)
- Driver's license (editable)
- [Save Changes] button
- Total bookings count
- Member since date

### Step 9.3 — WP-Cron scheduled tasks

➕ **Three automated jobs** that run on a schedule:

**Job 1: Expire stale `pending_review` bookings (every hour)**
- Finds bookings with status `pending_review` older than 48 hours
- Auto-denies them (status → `denied`, note: "Auto-expired — documents not reviewed in time")
- Releases inventory
- Notifies user: "Your reservation request has expired. Please submit a new one."

**Job 2: Expire stale `awaiting_payment` bookings (every hour)**
- Finds bookings with status `awaiting_payment` older than 72 hours
- Auto-denies them (status → `denied`, note: "Auto-expired — payment not completed in time")
- Releases inventory
- Notifies user: "Your payment window has expired."

**Job 3: Auto-transition `confirmed → active` (twice daily)**
- Finds bookings with status `confirmed` where `start_date = today`
- Transitions to `active` (car pickup day)
- No email needed — user already knows their pickup date

```php
// Register cron events on plugin activation
if ( ! wp_next_scheduled( 'obsidian_cleanup_expired_bookings' ) ) {
    wp_schedule_event( time(), 'hourly', 'obsidian_cleanup_expired_bookings' );
}
if ( ! wp_next_scheduled( 'obsidian_activate_confirmed_bookings' ) ) {
    wp_schedule_event( time(), 'twicedaily', 'obsidian_activate_confirmed_bookings' );
}

add_action( 'obsidian_cleanup_expired_bookings', function() {
    // Query: pending_review > 48h ago → deny
    // Query: awaiting_payment > 72h ago → deny
});

add_action( 'obsidian_activate_confirmed_bookings', function() {
    // Query: confirmed + start_date <= today → active
});
```

### Step 9.4 — Final edge cases

| Edge Case | Solution |
|---|---|
| User tries to book dates that were just taken | Server re-checks at submission time. Returns 409 error. |
| Staff approves, then car breaks down | Admin can manually change status to "denied" + add notes |
| Two staff approve conflicting bookings | Availability check runs on approval too, not just creation |
| User uploads wrong file type | Validate file type server-side: only allow `.jpg`, `.png`, `.pdf`, `.webp` |
| Booking starts tomorrow, still "pending_review" | Dashboard widget highlights urgent bookings |
| User wants to cancel | Per business policy: no cancellations. User must contact admin directly. |
| User pays but PayMongo webhook fails | Admin can manually mark as "paid" from meta box |
| Deposit refund | Admin manually releases deposit hold via PayMongo dashboard (or future automation) |

### ✅ Phase 9 Done When:
- [ ] Users can see their booking history at /my-reservations/
- [ ] Users can edit their profile at /profile/
- [ ] Expired pending bookings auto-deny via cron
- [ ] Expired awaiting_payment bookings auto-deny via cron
- [ ] Edge cases tested and handled
- [ ] Git commit: "Add user dashboard, profile, and cleanup cron"

---

## Phase 10: Polish + Production Prep
**⏱ Time: ~3-4 hours**  
**Goal: Everything is tested, styled, and ready for launch.**

### Step 10.1 — Switch PayMongo to live mode
- Replace test API keys with live keys in `wp-config.php`
- Update webhook URL in PayMongo dashboard to production URL
- Test one real payment

### Step 10.2 — Style login/register pages
- Match your dark theme with gold accents
- Add your logo
- Mobile responsive

### Step 10.3 — Full booking flow test
Walk through the entire flow as a new user:
1. Register → Login → Browse fleet → Open modal → Pick dates → Proceed
2. Fill requirements → Submit → Check admin gets email
3. Admin approves docs → Check user gets email
4. User pays → Check payment webhook fires → Check confirmation email
5. Check My Reservations shows the booking
6. Admin marks active → marks completed

### Step 10.4 — Security audit
- [ ] All REST endpoints validate nonces
- [ ] File uploads restricted to safe types
- [ ] Booking ownership verified server-side
- [ ] No raw card data touches your server
- [ ] Admin-only actions check `current_user_can()`
- [ ] SQL injection protected (use `$wpdb->prepare()` if any raw queries)

### ✅ Phase 10 Done When:
- [ ] Full booking flow works end-to-end
- [ ] PayMongo live payments work
- [ ] Mobile responsive throughout
- [ ] All emails send correctly
- [ ] Security checklist passed
- [ ] Git commit: "Production ready"

---

## Code Conflicts to Resolve

> [!NOTE]
> **All Phase 1-4 conflicts have been resolved.** Status list, payment fields, blocking statuses, and status transitions are all up to date. The color variant system has been integrated. Remaining Phase 6 work (PayMongo endpoints) is new code, not a conflict.

| File | Status |
|---|---|
| `includes/meta-fields.php` | ✅ Resolved — payment fields added, `_car_color_variants` registered |
| `includes/availability.php` | ✅ Resolved — blocking statuses correct, color-aware functions added |
| `includes/booking-handler.php` | ✅ Resolved — transition map updated |
| `includes/rest-api.php` | ✅ Resolved — `pending_review` status, color validation, color_variants in response. Still needs `/bookings/{id}/payment` and `/webhook/paymongo` in Phase 6 |

---

## Build Timeline

| Week | What You Build | Key Deliverable |
|---|---|---|
| **Week 1** | Phase 1 + 2 ✅ | Cars appear in admin with all fields, test data exists |
| **Week 2** | Phase 3 + 4 ✅ | REST API works, availability engine tested, users can register |
| **Week 3** | Phase 5 | Car grid block, fleet page, booking modal |
| **Week 4** | Phase 6 | Multi-step booking page + PayMongo payment |
| **Week 5** | Phase 7 | Admin approval workflow + dashboard |
| **Week 6** | Phase 8 + 9 | Email notifications + user dashboard/profile |
| **Week 7** | Phase 10 | Polish, security audit, go-live |

> [!TIP]
> **Phases 1-4 are done.** Start Phase 5 next — build the car grid block and fleet page. This gives you something visible and tangible on the frontend.
