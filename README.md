<div align="center">
  <img src="docs/assets/saba-logo.png" alt="Hotel Saba Restaurant Management System" width="180">
</div>

<h1 align="center">Hotel Saba Restaurant Management System</h1>

<p align="center">
  A professional restaurant management and POS system for hotel food and beverage operations.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.x-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.x">
  <img src="https://img.shields.io/badge/Database-MySQL%20%7C%20MariaDB-4479A1?style=flat-square&logo=mysql&logoColor=white" alt="MySQL">
  <img src="https://img.shields.io/badge/Frontend-Vanilla%20JS%20%7C%20CSS3-F7DF1E?style=flat-square&logo=javascript&logoColor=black" alt="JavaScript">
  <img src="https://img.shields.io/badge/RealTime-Server--Sent%20Events%20(SSE)-FF6C37?style=flat-square" alt="SSE">
  <img src="https://img.shields.io/badge/Printing-ESC%2FPOS%20%7C%20TCP%20%7C%20Spooler-2C3E50?style=flat-square" alt="ESC/POS Printing">
  <img src="https://img.shields.io/badge/Status-Production%20Ready-2ECC71?style=flat-square" alt="Production Ready">
</p>

---

## Table of Contents

- [Overview](#overview)
- [Key Features](#key-features)
  - [POS & Cashier](#pos--cashier)
  - [Waiter Operations](#waiter-operations)
  - [Kitchen & Stations](#kitchen--stations)
  - [Inventory](#inventory)
  - [Orders & Tables](#orders--tables)
  - [Printing](#printing)
  - [Reporting](#reporting)
  - [Administration](#administration)
- [System Architecture](#system-architecture)
- [Technology Stack](#technology-stack)
- [Project Structure](#project-structure)
- [Roles & Permissions](#roles--permissions)
- [Database](#database)
- [API](#api)
- [Installation](#installation)
- [Environment Variables](#environment-variables)
- [Development](#development)
- [Screenshots / Demo](#screenshots--demo)
- [Documentation](#documentation)
- [Security](#security)
- [Roadmap](#roadmap)
- [License](#license)
- [Credits / Footer](#credits--footer)

---

## Overview

The **Hotel Saba Restaurant Management System** is a purpose-built, on-premise and cloud-ready hospitality platform engineered specifically for Hotel Saba's restaurant, kitchen, beverage, and dining facilities.

The system addresses critical operational bottlenecks in fast-paced dining environments:
- **Eliminating Lost Tickets**: Real-time dispatching between floor servers and food preparation stations.
- **Multi-Department Routing**: Splitting items automatically to specialized thermal printers (hot kitchen, cold appetizers, beverage bar).
- **Consolidating Billing**: Handling cash, credit cards, room charges for hotel guests, and customer digital wallets in a unified cashier drawer.
- **Preventing Leakage**: Shift-based locking mechanisms, role-based restrictions, and detailed audit trails for sensitive operations.
- **Streamlining Inventory**: Tracking ingredient consumption and handling internal stock requisition workflows.

---

## Key Features

### POS & Cashier
- **High-Speed Checkout Terminal**: Built for rapid order lookup, table selection, and direct walk-in billing.
- **Flexible Settlement Channels**: Supports **Cash**, **Credit Card**, **Hotel Room Charge** (integrated with room view), and **Customer Digital Wallets**.
- **Discounts & Surcharges**: Configurable line-item discounts, promotional coupons (`offers`), tax rates, and optional service charges.
- **Refunds & Adjustments**: Safe partial or full refund tracking with transparent balance calculation and reprint indicators.
- **Cashier Drawer & Shift Management**: Daily shift cutoff enforcement with end-of-shift reconciliation and daily settlement reporting.

### Waiter Operations
- **Floor-Optimized Mobile View**: Touch-friendly interface tailored for handheld tablets and smartphones.
- **Table Status Monitoring**: Visual breakdown of dining tables, active covers, and occupied stations.
- **Add Items to Active Orders**: Real-time addition of extra items to already open orders with distinct visual badges for new vs. previously submitted items.
- **Live Ticket Status**: Instant status tracking informing servers when dishes are in preparation or ready for pickup.
- **Mobile Printing**: Wireless thermal receipt printing directly from the server's mobile device via Bluetooth bridge.

### Kitchen & Stations
- **Station-Specific Dispatch Screens**: Dedicated monitors for Kitchen Chefs (`chef`), Juice & Beverage Bars (`juice_bar`), and Waiter Stations.
- **Category Permissions**: Each station user can be restricted to view only their assigned item categories (`user_category_permissions`).
- **Granular Line-Item Status**: Stations can mark items as `in_progress`, `ready`, or `rejected` with instant feedback to waitstaff and cashier.
- **Preparation Time Tracking**: Real-time benchmarking of ticket creation to completion time per dish.
- **Audible & Visual Alerts**: Sound notifications and pulsing badges triggered instantly upon new incoming orders.

### Inventory
- **Ingredient & Raw Material Catalog**: Tracking raw goods (`inv_items`), measuring units, and minimum safety stock thresholds.
- **Purchase Order Tracking**: Recording supplier stock replenishment, cost values, purchase dates, and receiving staff.
- **Multi-Tier Requisition Workflow**: Station requests (`inv_requests`) routed through coordinators and warehouse managers for approval and issuance.
- **Stock Log Auditing**: Change history logging (`item_audit_log` and `item_stock_log`) for inventory accountability.

### Orders & Tables
- **Comprehensive Lifecycle**: Progression across `pending` &rarr; `sent_to_cashier` &rarr; `in_progress` &rarr; `ready` &rarr; `delivered` / `paid` / `cancelled`.
- **Closed Shift Security**: Automatically prevents modifications or reprints on orders created during closed shifts unless unlocked by an authorized manager.
- **Special Event Ticket Sales**: Integrated ticketing module (`ticket_types` & `ticket_sales`) for hotel buffet passes and special dinner events.

### Printing
- **Intelligent Department Routing**: Automatic dispatch of items based on Category &rarr; Department &rarr; Designated Printer.
- **Dual Hardware Spooling**:
  - **Network Thermal Printers**: Raw ESC/POS byte streaming via TCP sockets (`port 9100`).
  - **Local USB / Windows Printers**: Direct Windows Print Spooler P/Invoke via background service.
- **Asynchronous Print Queue**: Database-backed queue (`print_queue`) with atomic job claiming (`SELECT FOR UPDATE`), pre-built binary buffers, and idempotency protection (`request_id`).
- **Local Print Worker Service**: Standalone background workers (`local_print_worker.php`, `start_print_service.bat`, `start_print_service.ps1`) for polling cloud servers and printing to local hardware.
- **Mobile Bluetooth Bridge**: Seamless integration via `js/native-bridge.js` supporting Android WebView / Flutter channels and print intent apps (RawBT).

### Reporting
- **Financial Daily Summaries**: Breakdown of sales, payment methods, discounts, and net revenues.
- **Sales Statistics & Analytics**: Top-selling items, category performance, and item prep time distributions with visual Chart.js graphs.
- **Room Sales Audit**: Dedicated hotel room charge reconciliation for front-desk audit.
- **Excel & Print Export**: Instant generation of structured accounting spreadsheets and thermal summaries.

### Administration
- **Centralized Dashboard**: Live revenue metrics, active order counters, and operational statistics.
- **Menu Engineering**: Category and item management with image upload, item description, and pricing controls.
- **Printer & Hardware Setup**: IP printer discovery, Windows spooler name mapping, and department assignments.
- **Granular Permissions**: Role management coupled with fine-grained checkbox permissions.
- **Activity Audit Trail**: Searchable audit log with multi-criteria filtering by action, user, and date.

---

## System Architecture

```text
Browser / POS Interfaces (Waiters, Cashiers, Kitchen, Admin)
        │
        ▼
     PHP Application
        │
        ├── REST / JSON APIs (api/orders.php, api/reports.php, etc.)
        ├── SSE Notifications (api/sse.php -> sse_events table)
        ├── Printing Engine (api/print_engine.php & ESC/POS generator)
        └── Business Modules (admin/, cashier/, waiter/, station/)
                │
                ▼
          MySQL / MariaDB (InnoDB, UTF-8 mb4, Relational Constraints)
                │
                ▼
          Printing Pipeline
                ├── Direct TCP/IP Network Printers (port 9100)
                ├── Print Queue (`print_queue` table)
                │       └── Windows Local Print Worker (`local_print_worker.php`)
                │               └── Windows Print Spooler (`winspool.drv`)
                └── Android Bluetooth Bridge (`js/native-bridge.js`)
```

---

## Technology Stack

| Layer | Technology | Details |
| :--- | :--- | :--- |
| **Backend Language** | PHP 8.x | Native PDO, strict types, session management, JSON responses |
| **Database** | MySQL 5.7+ / MariaDB 10.4+ | InnoDB engine, `utf8mb4_unicode_ci`, foreign key constraints |
| **Real-Time Communication** | Server-Sent Events (SSE) | Event queue via database table `sse_events` |
| **Frontend Core** | HTML5, Vanilla JavaScript (ES6+) | Modern async/await, Fetch API, EventSource listeners |
| **Frontend Styling** | CSS3 Custom Properties | Responsive layout, dark/light theme tokens, Flexbox & CSS Grid |
| **Icons & Typography** | FontAwesome 6, Google Fonts | Cairo & Inter typography |
| **Visual Analytics** | Chart.js | Interactive preparation time charts and sales distributions |
| **Thermal Printing** | ESC/POS Binary Protocol | TCP sockets (`fsockopen`), Windows Spooler API (`winspool.drv`), Bluetooth SPP |
| **Web Server** | Apache (XAMPP / Linux) | Directory security and routing via `.htaccess` |

---

## Project Structure

```text
├── admin/                  # Administrative management panel
│   ├── _layout.php         # Base layout, dynamic navigation, and permission gates
│   ├── activity_log.php    # Audit log with multi-criteria filtering
│   ├── bulk_import.php     # Batch item import utility
│   ├── categories.php      # Category management and department mapping
│   ├── departments.php     # Department printer assignment
│   ├── direct_staff.php    # Direct dining staff assignment
│   ├── financial_revenues.php # Financial revenue tracking and analytics
│   ├── index.php           # Main admin KPI dashboard
│   ├── ingredients.php     # Inventory raw materials catalog
│   ├── inventory.php       # Stock intake and purchase order entry
│   ├── inventory_report.php# Stock level and consumption reports
│   ├── inventory_requests.php # Multi-tier internal requisition manager
│   ├── item_audit_logs.php # Item price and status modification audit
│   ├── item_stock.php      # Finished goods stock tracking
│   ├── item_times.php      # Kitchen preparation duration analytics
│   ├── items.php           # Menu item catalog and pricing
│   ├── offers.php          # Promotional discounts and special combos
│   ├── orders.php          # Comprehensive order management
│   ├── printers.php        # Hardware printer network and spooler setup
│   ├── reports.php         # Sales, daily settlements, and revenue reports
│   ├── room_sales_view.php # Hotel room charge audit view
│   ├── sales_stats.php     # Sales velocity and item popularity stats
│   ├── settings.php        # Restaurant parameters, tax, currency, shift times
│   ├── ticket_sales.php    # Event and buffet ticket sales manager
│   ├── ticket_types.php    # Ticket tier configuration
│   ├── users.php           # Staff account management and permissions
│   ├── wallets.php         # Customer digital wallet accounts
│   └── warehouses.php      # Storage warehouse configuration
├── api/                    # RESTful endpoints & real-time handlers
│   ├── activity.php        # Activity log API with multi-criteria search
│   ├── admin_actions.php   # Administrative action handlers
│   ├── app_diagnostic.php  # Public connectivity diagnostic endpoint
│   ├── auth.php            # Authentication and session API
│   ├── categories.php      # Category CRUD API
│   ├── departments.php     # Department configuration API
│   ├── export_report.php   # Excel spreadsheet export engine
│   ├── inventory.php       # Inventory stock and requisition API
│   ├── items.php           # Menu item catalog API
│   ├── orders.php          # Core transaction and order lifecycle engine
│   ├── print_direct.php    # Direct printing HTTP handler
│   ├── print_direct_lib.php# ESC/POS binary builder & Windows spooler library
│   ├── print_engine.php    # Department routing and socket transmission
│   ├── print_queue.php     # Print queue claim and status endpoints
│   ├── printers.php        # Printer hardware CRUD API
│   ├── reports.php         # Financial analytics API
│   ├── sse.php             # Server-Sent Events event broadcast stream
│   ├── tickets.php         # Ticket sales transaction API
│   └── users.php           # User account and permissions API
├── assets/                 # Static frontend assets
│   ├── css/style.css       # Unified design system & responsive styling
│   └── js/app.js           # Client application utilities, toasts, and modals
├── cashier/                # Dedicated Cashier POS station
│   ├── _layout.php         # Cashier layout and navigation
│   ├── index.php           # Cashier order monitor and checkout console
│   └── reports.php         # Shift settlements and cashier summaries
├── config/                 # Configuration and environment setup
│   ├── db.php              # Database connector & environment loader
│   ├── db.example.php      # Sample configuration template
│   └── db.local.php        # Ignored local credentials override
├── database/               # Database schemas and initialization
│   ├── schema.sql          # Clean, complete database schema with seed data
│   └── restaurant_pos.sql  # Base schema reference
├── docs/                   # Documentation assets
│   └── assets/saba-logo.png# Official Hotel Saba logo
├── images/                 # System branding and static imagery
├── js/                     # Hardware integration scripts
│   └── native-bridge.js    # Android / Bluetooth thermal printing bridge
├── station/                # Kitchen & Bar preparation station
│   ├── _layout.php         # Station layout and audio alerts
│   └── index.php           # Real-time prep ticket monitor
├── uploads/                # User-uploaded item images (.gitkeep protected)
├── waiter/                 # Waiter mobile-optimized POS
│   ├── _layout.php         # Waiter interface layout
│   ├── index.php           # Floor table and order creation view
│   ├── inventory_requests.php # Waiter inventory request form
│   └── orders.php          # Active orders and item add-on view
├── local_print_worker.php  # Windows local polling print worker
├── queue_worker.php        # CLI background worker for print queue
├── print_receipt.php       # Multi-language thermal receipt renderer
├── start_print_service.bat # Windows CMD persistent print service launcher
├── start_print_service.ps1 # Windows PowerShell print service launcher
└── index.php               # Root entry point and authenticated role router
```

---

## Roles & Permissions

### Confirmed System Roles

| Role | Identifier | Portal / Default Route | Primary Responsibilities |
| :--- | :--- | :--- | :--- |
| **System Administrator** | `admin` | `admin/` | Complete operational control, menu management, printer setup, system settings, and user administration. |
| **Waiter / Server** | `waiter` | `waiter/` | Dining floor table service, order entry, item additions, and order delivery confirmation. |
| **Cashier** | `cashier` | `cashier/` | Order verification, invoice settlement, payment collection, receipt printing, and shift reconciliation. |
| **Head Chef / Kitchen** | `chef` / `kitchen` | `station/` | Food preparation queue, preparation time updates, item completion signaling. |
| **Juice Bar Operator** | `juice_bar` | `station/` | Cold beverage and juice preparation queue. |
| **Financial Accountant** | `accountant` | `admin/reports.php` | Auditing sales, ledger reports, and daily cashier settlements. |
| **Warehouse Manager** | `warehouse_manager` | `admin/inventory.php` | Managing raw material stocks, approving requisitions, and receiving purchases. |
| **Inventory Monitor** | `inventory_monitor` | `admin/inventory.php` | Reviewing stock thresholds, usage logs, and inventory reports. |
| **Request Coordinator** | `request_coordinator` | `admin/inventory_requests.php` | Routing internal department stock requisitions. |
| **Hotel Receptionist** | `receptionist` | `admin/room_sales_view.php` | Auditing dining room sales billed to hotel guest room portfolios. |

### Granular Permission Keys
Administrators can assign fine-grained permission flags to any user account:
- **Menu Management**: `categories`, `items`
- **Transactions & Orders**: `orders`, `wallets`, `offers`, `cancel_pending_orders`, `bypass_closed_shift`
- **Financial Management**: `reports`, `financial_revenues`, `finance.daily_reports.view`
- **Inventory Management**: `ingredients`, `warehouses`, `inventory`, `inventory_report`, `sales_stats`, `inventory_requests_manage`, `inventory_requests_create`, `stock_management`, `stock_view`
- **Ticketing & Events**: `ticket_types`, `ticket_sales`
- **System Administration**: `printers`, `departments`, `settings`, `direct_staff`, `users`, `activity_log`, `item_times`

---

## Database

The database consists of 36 relational tables categorized by operational domain:

* **Core POS & Menu**: `categories`, `items`, `orders`, `order_items`, `offers`, `offer_items`, `discounts`
* **Printing Pipeline**: `printers`, `departments`, `print_queue`, `print_logs`
* **Inventory & Warehousing**: `inv_items`, `inv_purchases`, `inv_requests`, `inv_request_items`, `inv_warehouses`, `inv_sub_stock`, `ingredients`, `item_ingredients`, `item_stock`, `item_stock_log`, `item_audit_log`, `inventory_departments`, `inventory_transactions`
* **Operations & Ticketing**: `daily_settlements`, `direct_staff`, `manual_sales`, `ticket_types`, `ticket_sales`, `wallets`
* **User Accounts & Audit**: `users`, `roles`, `user_category_permissions`, `activity_log`, `settings`, `messages`, `sse_events`

### Database Setup
A sanitized, self-contained schema is available in [database/schema.sql](database/schema.sql). It contains the complete schema structure, foreign key relationships, indexes, and safe default seed data (roles, default admin user, and initial system settings).

---

## API

The application exposes structured JSON endpoints consumed by the front-of-house interfaces, background services, and real-time listeners:

| Endpoint | Method | Purpose |
| :--- | :--- | :--- |
| `/api/auth.php?action=login` | `POST` | Authenticate staff and establish session |
| `/api/orders.php?action=create` | `POST` | Create a new table or takeaway order |
| `/api/orders.php?action=append_items` | `POST` | Append additional items to an active order |
| `/api/orders.php?action=pay` | `POST` | Settle an order via Cash, Card, Room, or Wallet |
| `/api/orders.php?action=refund` | `POST` | Process partial or full refund adjustments |
| `/api/sse.php` | `GET` | Open SSE stream for real-time order/item events |
| `/api/print_engine.php` | `POST` | Route and dispatch items to department printers |
| `/api/print_queue.php?action=claim` | `POST` | Atomically claim next pending print job |
| `/api/print_queue.php?action=mark_done` | `POST` | Mark claimed print job as printed |
| `/api/activity.php?action=get_logs` | `GET` | Query audit trail with multi-criteria filters |
| `/api/app_diagnostic.php` | `GET` | Health check and print worker connectivity diagnostic |

---

## Installation

### Prerequisites
- **Web Server**: Apache (XAMPP for Windows or Apache2 on Linux)
- **PHP**: PHP 8.0 or higher with `pdo_mysql`, `curl`, and `mbstring` extensions
- **Database**: MySQL 5.7+ or MariaDB 10.4+

### Step 1: Clone Repository
```bash
git clone https://github.com/adnanalqham/restaurant.git
cd restaurant
```

### Step 2: Initialize Database
1. Create a fresh database:
   ```sql
   CREATE DATABASE `restaurant_pos` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. Import the schema file:
   ```bash
   mysql -u root -p restaurant_pos < database/schema.sql
   ```

### Step 3: Configure Database Connection
By default, the application connects to local XAMPP (`127.0.0.1`, user `root`, no password).

To customize your connection, create `config/db.local.php` (this file is ignored by Git):
```php
<?php
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
define('DB_NAME', 'restaurant_pos');
```
Alternatively, configure server environment variables (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`).

### Step 4: Launch Web Server
Ensure the project is served via Apache (e.g., placed inside `C:\xampp\htdocs\restaurant` or configured via a VirtualHost).

Access the application in your browser:
```text
http://localhost/restaurant/
```

#### Default Credentials:
- **Username**: `admin`
- **Password**: `password`

*(Change the default administrative password immediately after first login via Profile Settings).*

---

## Environment Variables

The application reads optional environment variables when `config/db.local.php` is omitted:

| Variable | Default Value | Description |
| :--- | :--- | :--- |
| `DB_HOST` | `127.0.0.1` | Database server host or IP |
| `DB_USER` | `root` | Database user account |
| `DB_PASS` | `(empty)` | Database user password |
| `DB_NAME` | `restaurant_pos` | Target database name |
| `PRINT_API_URL` | `http://localhost/restaurant/` | Target URL used by `local_print_worker.php` |
| `PRINT_API_TOKEN` | `SHEBA_APP_2026` | Shared authentication token for print queue polling |

---

## Development

### Background Print Service Setup
For stations requiring USB thermal receipt printing via the Windows Print Spooler:
1. Ensure the printer driver is installed in Windows (e.g. named `POS-Kitchen` or `POS-Cashier`).
2. Run the persistent launcher:
   - Double-click `start_print_service.bat` or execute `start_print_service.ps1`.
3. To run the CLI queue worker continuously:
   ```bash
   php queue_worker.php --daemon
   ```

### Testing Diagnostics
Verify printer engine connectivity and auth headers by opening:
```text
http://localhost/restaurant/diagnostics.php
```

---

## Screenshots / Demo

| Module | Description |
| :--- | :--- |
| **Admin Dashboard** | Real-time overview of daily sales, total orders, active staff, and top-selling items. |
| **Cashier Terminal** | Split-screen order processing with quick payment selection, line discounts, and thermal printing. |
| **Waiter Floor View** | Mobile-responsive table grid with instant order creation and extra item add-on workflows. |
| **Kitchen Monitor** | Real-time preparation queue filtered by assigned station categories with audio alerts. |

---

## Documentation

- **Database Reference**: See [database/schema.sql](database/schema.sql) for table definitions, indices, and foreign key constraints.
- **Hardware Printing Guide**: See [local_print_worker.php](local_print_worker.php) and [api/print_engine.php](api/print_engine.php) for department routing rules and ESC/POS byte formats.
- **Mobile Integration**: See [js/native-bridge.js](js/native-bridge.js) for Android WebView bridge implementation.

---

## Security

- **Credential Isolation**: Production database passwords and server details are excluded from Git via `config/db.local.php` and `.gitignore`.
- **Shift Locking**: Completed shift records are locked against retrospective tampering; overrides require manager permissions and generate audit logs.
- **Prepared Statements**: All database operations use PDO prepared statements with parameter binding to prevent SQL injection.
- **Session Protection**: Strict session cookies scoped to application base paths with role-based validation on every route.
- **Audit Logging**: Sensitive operations (order deletions, shift unlocks, price adjustments) are recorded in the `activity_log` table with client user and timestamp.

---

## Roadmap

- [x] Multi-department thermal printing with network and USB spooling support.
- [x] Closed shift locking and audit bypass workflow.
- [x] Search and filtering in the activity audit log.
- [x] Visual preparation time distribution charts.
- [ ] Progressive Web App (PWA) offline service caching for waiter tablets.
- [ ] QR code digital menu and table ordering for hotel guests.
- [ ] Direct PMS (Property Management System) API integration for room charges.

---

## License

Proprietary software developed for **Hotel Saba**. All rights reserved.

---

## Credits / Footer

Developed and maintained for **Hotel Saba** by [adnanalqham](https://github.com/adnanalqham).
