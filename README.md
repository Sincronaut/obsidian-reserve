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
| **Typography** | Montserrat (Google Fonts) |
| **Date Picker** | Flatpickr 4.6.13 (Bundled) |
| **Car Data Fields** | Advanced Custom Fields (ACF) Free |

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
| **Theme My Login** | Frontend login/registration for booking flow | Optional |

> Install via **Plugins → Add New** in the WordPress Dashboard.

---

## 🚀 Syncing Content & Media

Since the database and media metadata are not tracked by Git, follow these steps to see the full site content:

### 1. Import Content

1. Go to **Tools → Import**.
2. Click **Run Importer** under the WordPress section (install it if prompted).
3. Choose the XML export file (if provided in the repo).
4. Upload and import. Assign content to your local admin user.
5. Check the box to **"Download and import file attachments."**

### 2. Sync Media Files

If images exist in `uploads/` but don't appear in the Media Library:

1. Install and activate the **[Media Sync](https://wordpress.org/plugins/jeremygreen-media-sync/)** plugin.
2. Go to **Media → Media Sync**.
3. Scan the `uploads/` directory.
4. Uncheck "Dry Run", select all scanned files, and click **Import Selected**.

### 3. Add Test Car Data

If no XML import is available, manually create cars:

1. Go to **Cars → Add New** in the Dashboard.
2. Add test vehicles with all ACF fields filled in:
   - **Nissan GTR Katsura Orange** — Exotic, $850/day, 3 units
   - **Porsche 911 GTS** — Sport, $950/day, 2 units
   - **Cadillac Escalade** — SUV, $650/day, 4 units
3. Upload featured images and fill in all specification fields.

---

## 📁 Repository Scope

This repository is configured to be lightweight and only tracks the following:

### ✅ Tracked

```
wp-content/
├── themes/child-obsidian-reserve/    ← Custom block theme (all pages, blocks, styles)
├── plugins/                          ← All plugins (including obsidian-booking)
├── .gitignore
└── index.php
```

### ❌ Not Tracked

- **WordPress Core** files (`wp-admin/`, `wp-includes/`, `wp-config.php`)
- **Uploads / Media** (`wp-content/uploads/`) — too large for Git
- **Database** — use XML Import for content sync
- **Cache / Upgrade** directories

---

## 🏗️ Project Structure

### Child Theme (`child-obsidian-reserve`)

```
child-obsidian-reserve/
├── blocks/                  ← Custom server-rendered blocks
│   ├── hero/                   (Hero section with booking inputs)
│   ├── slider/                 (Car showcase + testimonial slider)
│   ├── three-cards/            (Feature cards)
│   ├── text-img-bg/            (CTA with background image)
│   ├── img-text/               (Image + text layout)
│   ├── logo-slider/            (Brand logo carousel)
│   ├── standard/               (Standard content block)
│   └── contact/                (Contact form block)
├── templates/               ← Block template files
│   ├── front-page.html
│   ├── page-about.html
│   └── page-contact.html
├── parts/                   ← Template parts
│   ├── header.html
│   └── footer.html
├── assets/
│   ├── css/                    (Header & footer styles)
│   └── images/                 (Block-specific images)
├── functions.php            ← Enqueues, block registration
├── style.css                ← Theme header + global styles
└── theme.json               ← Design tokens, typography, colors
```

### Booking Plugin (`obsidian-booking`)

```
obsidian-booking/
├── obsidian-booking.php     ← Plugin bootstrap
├── uninstall.php            ← Clean data removal
├── includes/                ← Core PHP logic
│   ├── post-types.php          (Car + Booking CPTs)
│   ├── meta-fields.php         (Booking custom meta)
│   ├── taxonomies.php          (Car Class: Exotic, SUV, etc.)
│   ├── availability.php        (Date-overlap availability engine)
│   ├── booking-handler.php     (Create/update bookings)
│   ├── rest-api.php            (All REST endpoints)
│   ├── notifications.php       (Email system)
│   └── user-fields.php         (Extra user profile fields)
├── admin/                   ← WP Admin customizations
│   ├── booking-meta-box.php
│   ├── booking-columns.php
│   ├── car-meta-box.php
│   └── dashboard-widget.php
├── templates/
│   └── emails/              ← HTML email templates
├── assets/
│   ├── css/                    (Modal + admin styles)
│   └── js/                     (Modal + admin scripts)
└── vendor/
    └── flatpickr/           ← Bundled date picker library
```

---

## 🔌 REST API Endpoints

| Method | Endpoint | Auth | Purpose |
|---|---|---|---|
| `GET` | `/obsidian-booking/v1/cars` | Public | List all available cars |
| `GET` | `/obsidian-booking/v1/cars/{id}` | Public | Single car details + specs |
| `GET` | `/obsidian-booking/v1/availability/{car_id}` | Public | Unavailable dates for Flatpickr |
| `POST` | `/obsidian-booking/v1/bookings` | Logged in | Create a new booking |
| `GET` | `/obsidian-booking/v1/bookings/mine` | Logged in | User's own bookings |
| `POST` | `/obsidian-booking/v1/upload-document` | Logged in | Upload ID/passport document |

---

## 📋 Booking Flow

```
Browse Cars → Click "Book Now" → Login Gate
    ↓
Modal Opens → Select Dates (Flatpickr) → Choose Local/Foreigner
    ↓
Upload Documents → Submit Reservation
    ↓
Status: PENDING → Staff Reviews in WP Admin
    ↓
Staff Approves → CONFIRMED (email sent)
Staff Denies → DENIED (email sent with reason)
```

> **Policy:** No cancellations, no refunds. All bookings are final once submitted.

---

## 🤝 Collaboration Workflow

1. **Always create a new branch** for your features:
   ```bash
   git checkout -b feature/your-feature-name
   ```
2. **Commit with descriptive messages:**
   ```bash
   git commit -m "Add car-grid block with availability badges"
   ```
3. **Push your branch** and create a Pull Request on GitHub.
4. **Never commit directly to `main`.**

---

## 📄 License

This project is licensed under the [GNU General Public License v2.0](http://www.gnu.org/licenses/gpl-2.0.html).

`child-obsidian-reserve` is a child theme of [Twenty Twenty-Five](https://wordpress.org/themes/twentytwentyfive/) © the WordPress team, GPLv2 or later.

---

Built with precision by the **Obsidian Reserve** team. 🖤✨
