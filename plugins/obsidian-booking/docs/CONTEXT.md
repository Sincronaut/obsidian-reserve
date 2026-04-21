# Obsidian Reserve — Project Context & Handoff

> **Purpose:** This document captures all project decisions, architecture, and current status so any AI assistant or developer can continue seamlessly from where we left off.
>
> **Last updated:** April 8, 2026

---

## What Is This Project?

A **luxury car rental booking system** built as a custom WordPress plugin (`obsidian-booking`) paired with a child theme (`child-obsidian-reserve`). Users browse a fleet of exotic/luxury cars, select dates, submit required documents for admin review, pay via PayMongo, and receive confirmation.

---

## Architecture Overview

### Plugin: `wp-content/plugins/obsidian-booking/`

```
obsidian-booking/
├── obsidian-booking.php           ← Plugin bootstrap (constants + require_once)
├── includes/
│   ├── post-types.php             ← Car + Booking CPT registration
│   ├── meta-fields.php            ← Booking + Car meta fields (register_post_meta)
│   ├── taxonomies.php             ← Car Class taxonomy (Exotic, SUV, etc.)
│   ├── availability.php           ← Date-overlap engine + color variant helpers
│   ├── rest-api.php               ← All REST API endpoints
│   ├── booking-handler.php        ← Status transitions + booking CRUD
│   ├── user-fields.php            ← Custom user profile fields
│   ├── payment.php                ← [FUTURE] PayMongo integration
│   └── notifications.php          ← [FUTURE] Email notification system
├── admin/
│   ├── car-meta-box.php           ← Color Variants meta box (per-color units + images)
│   ├── booking-meta-box.php       ← [FUTURE] Booking detail panel (approve/deny)
│   ├── booking-columns.php        ← [FUTURE] Custom admin list columns
│   └── dashboard-widget.php       ← [FUTURE] Dashboard widget
├── assets/
│   ├── css/                       ← [FUTURE] Modal + booking page styles
│   └── js/                        ← [FUTURE] Modal + booking page scripts
├── templates/
│   └── emails/                    ← [FUTURE] HTML email templates
├── vendor/
│   └── flatpickr/                 ← Flatpickr date picker library
│       ├── flatpickr.min.css
│       └── flatpickr.min.js
└── docs/
    ├── MASTERPLAN.md              ← Full 10-phase implementation roadmap
    └── CONTEXT.md                 ← This file
```

### Theme: `wp-content/themes/child-obsidian-reserve/`

A WordPress block theme (FSE). Uses `templates/`, `parts/`, and custom `blocks/` directories. The booking system's frontend blocks (car-grid, etc.) will be added here.

### Data Model

**Cars** — Custom Post Type, public, uses ACF + custom meta:
- **All ACF fields are now code-defined in `includes/car-fields.php`** (no longer manually configured via Custom Fields → Field Groups). Field group key: `group_obsidian_car_details`.
- **Shared across every branch** (vehicle-level): `car_make`, `car_model`, `car_year`, `car_daily_rate`, `car_specs`, `car_colors` (master color list), `car_status` (vehicle-wide listing status: available / maintenance / retired)
- **Per-branch** (managed in custom meta boxes, NOT ACF):
  - `_car_inventory` (JSON, **v3 shape**) — per-branch operational status PLUS the per-branch subset of stocked colors with units. Example:
    ```json
    {
      "12": { "status": "available",   "colors": { "blue": {"units": 2}, "black": {"units": 1} } },
      "15": { "status": "maintenance", "colors": { "black": {"units": 3} } }
    }
    ```
    Each branch independently chooses (a) its `status` (`available` / `maintenance` / `retired`) and (b) which subset of the master `car_colors` it actually stocks. Removing a color from one branch does NOT touch any other branch. Admin UI: `admin/car-meta-box.php` "Inventory — by Branch" tabs.
  - Legacy v2 shape `{"12":{"blue":{"units":2}}}` is auto-upgraded to v3 on read by `obsidian_normalize_branch_entry()`, and persistently rewritten by the v3 migration in `includes/migrations.php` (gated by `OBSIDIAN_MIGRATION_V3_OPTION`).
  - `_car_galleries` (JSON) — shared per-color image galleries (6 images per color), e.g. `{"orange":[101,102,...]}` → admin UI: `admin/car-meta-box.php` "Color Galleries" box (galleries are identical at every branch).
- Legacy: `_car_color_variants` (deprecated, kept only so the migration in `includes/migrations.php` can read it). `car_total_units` was removed in Phase 11 — total is derived live from `_car_inventory`.
- Taxonomy: `car_class` (Exotic, Executive, SUV, Sport)
- A duplicate "Car Details" group created via the ACF UI before the code migration is auto-suppressed by `obsidian_suppress_legacy_car_field_groups()` so the admin only sees one copy.

**Bookings** — Custom Post Type, NOT public, uses `register_post_meta()`:
- Core: `_booking_car_id`, `_booking_user_id`, `_booking_start_date`, `_booking_end_date`
- Details: `_booking_pickup_location`, `_booking_customer_type` (local/foreigner), `_booking_color`
- Status: `_booking_status`, `_booking_admin_notes`, `_booking_denial_reason`
- Payment: `_booking_total_price`, `_booking_payment_type` (down_payment/full_prepayment), `_booking_payment_amount`, `_booking_deposit_amount`, `_booking_balance_due`, `_booking_payment_id`, `_booking_payment_status` (unpaid/paid/deposit_released)
- Documents: `_booking_documents` (JSON array of attachment IDs)

---

## Booking Status Lifecycle

```
pending_review ──► awaiting_payment ──► paid ──► confirmed ──► active ──► completed
      │                    │                        │
      └──► denied          └──► denied              └──► denied (emergency)
```

| Status | Set By | Blocks Inventory? |
|---|---|---|
| `pending_review` | System (user submits docs) | ✅ Yes |
| `awaiting_payment` | Admin (approves docs) | ✅ Yes |
| `paid` | PayMongo webhook (auto → confirmed) | ✅ Yes |
| `confirmed` | System (auto after payment) | ✅ Yes |
| `active` | Cron (auto on start date) or Admin | ✅ Yes |
| `completed` | Admin (car returned) | ❌ No |
| `denied` | Admin (at any stage, with reason) | ❌ No |

### Key Rules
- `paid → confirmed` is **automatic** (no admin step needed)
- `confirmed → active` is **automatic** via cron on the start date
- `confirmed → denied` is an **emergency override** only (admin must refund manually)
- **No cancellations, no refunds** — business policy
- Stale `pending_review` bookings auto-expire after **48 hours**
- Stale `awaiting_payment` bookings auto-expire after **72 hours**

---

## Payment Structure

| Item | Amount |
|---|---|
| **50% Down Payment** (user choice) | 50% of total rental |
| **Full Prepayment** (user choice) | 100% of total rental |
| **Security Deposit** | ₱10,000 or 40% of total — whichever is higher (refundable) |
| **Balance (if 50% down)** | Remaining 50% — collected at vehicle pickup (cash/card on-site) |

**Payment Gateway:** PayMongo (Visa, Mastercard, BPI, BDO, MetroBank)
- Card data NEVER touches our server (PCI compliance)
- API keys stored in `wp-config.php` as `PAYMONGO_SECRET_KEY` and `PAYMONGO_PUBLIC_KEY`

---

## User-Facing Flow

1. **Fleet Page** → Browse cars
2. **Modal** (on fleet page) → View car details, pick dates, see price, choose color → "Proceed to Booking"
3. **Booking Page Step 1** → Upload documents (ID, license), select local/foreigner → Submit → `pending_review`
4. ⏸️ **Admin reviews documents** in WP Admin
5. **Booking Page Step 2** (after approval email) → Choose down/full payment, pay via PayMongo → `paid` → auto `confirmed`
6. **Booking Page Step 3** → Confirmation page with receipt

---

## REST API Endpoints

| Endpoint | Method | Auth | Status |
|---|---|---|---|
| `/obsidian-booking/v1/cars` | GET | Public | ✅ Built |
| `/obsidian-booking/v1/cars/{id}` | GET | Public | ✅ Built |
| `/obsidian-booking/v1/availability/{car_id}` | GET | Public | ✅ Built |
| `/obsidian-booking/v1/bookings` | POST | Logged in | ✅ Built |
| `/obsidian-booking/v1/bookings/mine` | GET | Logged in | ✅ Built |
| `/obsidian-booking/v1/upload-document` | POST | Logged in | ✅ Built |
| `/obsidian-booking/v1/bookings/{id}/payment` | POST | Owner only | 🔜 Phase 6 |
| `/obsidian-booking/v1/webhook/paymongo` | POST | PayMongo | 🔜 Phase 6 |

---

## Current Progress

### ✅ Completed

| Phase | What Was Built |
|---|---|
| **Phase 1** | Plugin scaffolding, Flatpickr bundled, Git initialized |
| **Phase 2** | Car + Booking CPTs, ACF fields for Cars, meta fields for Bookings, Car Class taxonomy |
| **Phase 3** | Availability engine (date-overlap logic), REST API (6 endpoints), Booking handler (status machine) |
| **Phase 4** | User registration enabled, custom user profile fields (phone, nationality, license) |

### 🔜 Next Up

| Phase | What To Build | Est. Time |
|---|---|---|
| **Phase 5** | Car grid block, fleet page, booking modal with Flatpickr | 6-8 hrs |
| **Phase 6** | Multi-step booking page + PayMongo payment integration | 10-12 hrs |
| **Phase 7** | Admin approval workflow (meta boxes, columns, dashboard widget) | 4-5 hrs |
| **Phase 8** | Email notification system (7 templates hooked into status changes) | 3-4 hrs |
| **Phase 9** | User dashboard (/my-reservations/), profile page (/profile/), cron jobs | 4-5 hrs |
| **Phase 10** | Polish, security audit, PayMongo live mode, full flow test | 3-4 hrs |

---

## Key Technical Decisions

1. **ACF for Cars, `register_post_meta()` for Bookings** — Cars are admin-managed (humans type), bookings are code-managed (REST API creates them). Exception: `_car_color_variants` is registered via `register_post_meta()` on the Car CPT because it stores structured JSON that ACF Free can't handle (no Repeater field)
2. **Custom car-grid block, NOT Query Loop** — Need full control over `data-car-id` attributes and "Book Now" button logic
3. **Modal is lightweight** — Only shows car info + date selection. Documents, payment, confirmation happen on a full-page `/booking/` wizard
4. **PayMongo for payments** — Card data never touches our server. Uses Payment Intents API
5. **Server-side ownership validation** — Booking page verifies the logged-in user owns the booking before showing Step 2
6. **Race condition protection** — Availability is re-checked at booking creation time AND at admin approval time
7. **No cancellations** — Business policy. Only admin can deny. Simplifies the system significantly

---

## Design Language

- **Background:** #0B0B0B (near black)
- **Accent:** #C5A059 (gold)
- **Font:** Montserrat
- **Theme:** Luxury, dark, premium feel — glassmorphism effects, smooth animations
- **Block theme** with Full Site Editing (FSE)

---

## Important Notes for AI Assistants

1. **IDE lint warnings are false positives** — Functions like `__()`, `get_post_meta()`, `WP_Error`, `register_post_meta()` etc. are WordPress core functions loaded at runtime. The IDE can't resolve them because the WordPress stubs aren't in the project. Ignore all "Call to unknown function" and "Use of unknown class" warnings.

2. **Read `MASTERPLAN.md`** — It contains the complete phase-by-phase implementation guide with code examples, wireframes, and checklists. This CONTEXT.md is the summary; MASTERPLAN.md is the detailed plan.

3. **Test data** — There are 3 test cars in WP Admin (Nissan GTR, Porsche 911 GTS, Cadillac Escalade). The availability API endpoint works: `http://project-obsidian-reserve.local/wp-json/obsidian-booking/v1/availability/{car_id}`

4. **WordPress version:** 6.9+ required
5. **PHP version:** 8.0+ required
6. **Local dev URL:** `http://project-obsidian-reserve.local`
