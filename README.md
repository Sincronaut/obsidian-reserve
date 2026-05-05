# Project Obsidian Reserve 🏎️

Welcome to the **Project Obsidian Reserve** repository. This project is a premium luxury car rental website built as a custom WordPress block theme, featuring a bespoke booking system for exotic and executive vehicle reservations.

---

## 🛠️ Tech Stack

| Layer | Technology |
|---|---|
| **CMS** | WordPress 6.9+ |
| **Parent Theme** | Twenty Twenty-Five |
| **Child Theme** | `child-obsidian-reserve` (Block / FSE) |
| **Booking System** | `obsidian-booking` (Custom Plugin) |
| **Typography** | Montserrat & Outfit (Google Fonts) |
| **Interactive Map** | Leaflet.js + OpenStreetMap |
| **Date Picker** | Flatpickr 4.6.13 (Bundled) |
| **Data Fields** | Advanced Custom Fields (ACF) Free |

---

## 🛠️ Local Setup Instructions

### 1. Prerequisites

- Install [Local WP](https://localwp.com/) (recommended) or any local WordPress dev environment.
- Ensure you have [Git](https://git-scm.com/) installed.
- PHP 8.0+ required.

### 2. Setup Procedure

This repository tracks only the **child theme**, **custom plugin**, and essential config files. Pull it into an existing WordPress installation:

1. **Create a new WordPress site** in Local WP (e.g., name it "Obsidian Reserve").

2. **Open a terminal** in the site's `wp-content` directory:
   ```
   cd app/public/wp-content
   ```

3. **Initialize Git and connect** to the repository:
   ```bash
   git init
   git remote add origin https://github.com/YOUR_USERNAME/project-obsidian-reserve.git
   ```

4. **Pull the project files:**
   ```bash
   git fetch
   git checkout -f main
   ```

   > **Note:** The `-f` flag forces the checkout and merges the tracked theme + plugin into your existing `wp-content` structure without affecting WordPress core files.

### 3. Activation

1. Log in to your **WordPress Dashboard**.
2. Go to **Appearance → Themes** → Activate **child-obsidian-reserve**.
3. Go to **Plugins** → Activate **Obsidian Booking**.

---

## 📦 Required Plugins

Install and activate these **before** importing content to avoid errors:

| Plugin | Purpose | Required? |
|---|---|---|
| **Advanced Custom Fields (ACF)** | Car data fields (specs, units, colors, daily rate) | ✅ Yes |
| **Theme My Login** | Frontend login/registration for booking flow | ✅ Yes |
| **Media Sync** | Syncing large media files to the library | Optional |

---

## 🚀 Syncing Content & Media

### 1. Import Content
1. Go to **Tools → Import → WordPress**.
2. Upload the latest `.xml` export file.
3. Check **"Download and import file attachments."**

### 2. Branch & Inventory Setup
1. Go to **Locations** and create your primary branches (e.g., Makati, Cebu).
2. Assign **Regions** (Luzon, Visayas, Mindanao).
3. Edit each **Car** to set branch-specific inventory (Units and Colors per branch).

---

## 🏗️ Project Structure

### Child Theme (`child-obsidian-reserve`)

```
child-obsidian-reserve/
├── blocks/                  ← Custom server-rendered blocks
│   ├── hero/                   (Dynamic branch selection + car search)
│   ├── fleet-filters/          (Real-time region/class filtering)
│   ├── car-grid/               (Live availability badges)
│   ├── locations-map/          (Leaflet.js interactive branch picker)
│   ├── profile-dashboard/      (User booking history + status)
│   ├── booking-form/           (Step-by-step reservation flow)
│   └── ... (Contact, FAQ, Logo Slider, etc.)
├── templates/
│   ├── front-page.html
│   ├── page-fleet.html         (Fleet catalog)
│   ├── page-profile.html       (User dashboard)
│   └── page-booking.html       (Main reservation page)
├── assets/
│   ├── css/                    (Component-specific styling)
│   └── images/                 (UI icons and luxury accents)
├── theme.json               ← Typography, colors, and block styles
└── functions.php            ← Enqueues, block registration, and WP hooks
```

### Booking Plugin (`obsidian-booking`)

```
obsidian-booking/
├── includes/                ← Core PHP logic
│   ├── post-types.php          (Car, Booking, Location CPTs)
│   ├── taxonomies.php          (Car Class, Regions)
│   ├── availability.php        (Branch-aware range-guard engine)
│   ├── rest-api.php            (Multi-branch REST endpoints)
│   ├── notifications.php       (HTML Email System)
│   └── migrations/             (V2 Inventory Migration tools)
├── assets/
│   ├── js/
│   │   ├── modal.js            (Specifications modal + Range Guard)
│   │   ├── booking-form.js     (Multi-step logic + Exit Guard)
│   │   └── payment-form.js     (sessionStorage payment flow)
│   └── css/
│       ├── modal.css           (Global luxury modal system)
│       └── admin.css           (Custom WP Admin dashboard UI)
└── templates/
    └── emails/                 (Themed booking status notifications)
```

---

## 🔌 REST API Endpoints

| Method | Endpoint | Purpose |
|---|---|---|
| `GET` | `/v1/cars` | List available cars (filterable by branch/region) |
| `GET` | `/v1/availability/{id}` | Unavailable dates per branch/color |
| `GET` | `/v1/regions` | All regions with nested child branches |
| `GET` | `/v1/locations` | Detailed branch data (coords, address, contact) |
| `POST`| `/v1/bookings` | Create booking (includes range-guard re-check) |

---

## 🛡️ Security & UX Guards

*   **Logout Confirmation:** Intercepts all logout links to show a branded Obsidian confirmation modal.
*   **Exit Guard:** Prevents accidental data loss in the booking form by asking for confirmation before the user leaves the page.
*   **Range Guard:** The calendar scans every middle-day of a requested range to ensure no "hidden" overlaps occur in the reservation window.
*   **Text Modals:** Renders legal pages (T&C, Privacy) inside lightweight modals to keep the user within the booking flow.

---

## 📋 Modern Booking Flow

```
Fleet Page → Select Branch → Pick Car → Spec Modal
    ↓
Pick Dates (Range Guard Validated) → Redirect to Booking Page
    ↓
Step 1: Renter Details & ID Upload → Step 2: Delivery Details
    ↓
Admin Review → Approval Email → Secure Payment Link
    ↓
Status: CONFIRMED (Reflected in Profile Dashboard)
```

---

## 🤝 Collaboration Workflow

1. **Feature Branches:** `feature/name`
2. **Version Control:** We follow Semantic Versioning (e.g., `1.0.2`).
3. **Asset Bumping:** Always bump the plugin version to clear CDN/Browser caches for JS/CSS changes.

---

Built with precision by the **Obsidian Reserve** team. 🖤✨
