# ThreadFlow — Team Setup & Architecture Guide

This document outlines the setup workflow, team boundaries, database management, and shared helper contracts for all four team members collaborating on **ThreadFlow**.

---

## 1. Project Overview & Tech Stack

- **Project**: ThreadFlow — Garment Production, Quality Control and Traceability System.
- **Stack**: Core PHP, MySQL-compatible SQL, HTML, CSS (Custom Properties), Vanilla JavaScript.
- **Runtime**: Local XAMPP stack (Apache + MariaDB/MySQL).
- **Core Principle**: No Laravel, React, Vue, Node build pipeline, Composer, or external CDNs/frameworks. Keep everything lightweight, transparent, and modular.

---

## 2. Environment & PHP Requirements

Ensure your XAMPP installation has the following PHP extensions enabled in `php.ini` (standard in default XAMPP):
- `mysqli` (Database connectivity)
- `session` (Session management)
- `openssl` (Secure token generation)
- `json` (Payload exchange)
- `mbstring` (Multibyte string operations)

---

## 3. Local Configuration Setup

1. Navigate to `config/`.
2. Copy `config.example.php` to `config.local.php`:
   ```bash
   # In Windows Command Prompt or PowerShell:
   copy config\config.example.php config\config.local.php
   ```
3. Update `config/config.local.php` with your local database credentials if they differ from XAMPP defaults:
   - `db_host`: `127.0.0.1`
   - `db_port`: `3306`
   - `db_name`: `threadflow_db`
   - `db_user`: `root`
   - `db_pass`: `""` (empty string by default in XAMPP)
   - `base_url`: `/ThreadFlow`
4. **Note**: `config.local.php` is listed in `.gitignore` and must **never** be committed.

---

## 4. Database Setup & Import Order

> [!IMPORTANT]
> **Git Does NOT Sync Live Databases!**
> Git only tracks SQL migration files under `sql/`. Each teammate runs their own local database in MySQL/phpMyAdmin. When a teammate adds a table, they commit a new SQL script to `sql/`, and teammates must import that script locally.

### Scenario A: Existing Database (Current Host)
If `threadflow_db` and the `buyers` table are **already created** on your machine:
- **DO NOT re-import `sql/buyers.sql`** (doing so may cause duplicate table errors or disrupt existing buyer rows).
- **Import ONLY the additive authentication schema**:
  - **Using phpMyAdmin**:
    1. Open phpMyAdmin (`http://localhost/phpmyadmin`).
    2. Click on database `threadflow_db` in the left navigation.
    3. Click the **Import** tab at the top.
    4. Click **Choose File** and select `sql/auth.sql`.
    5. Click **Import** (or **Go**) at the bottom of the page.
  - **Using MySQL CLI**:
    ```bash
    mysql -u root -p threadflow_db < sql/auth.sql
    ```

### Scenario B: Fresh Database Setup (New Teammate Machine)
If setting up ThreadFlow from scratch on a new laptop or fresh XAMPP installation:
1. Create the database:
   - In phpMyAdmin: Click **New**, enter database name `threadflow_db`, choose collation `utf8mb4_unicode_ci`, click **Create**.
   - Or in MySQL CLI:
     ```sql
     CREATE DATABASE IF NOT EXISTS threadflow_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
     ```
2. Import tables in strict order:
   - **Step 1: Import `sql/buyers.sql`**
     - phpMyAdmin: Open `threadflow_db` &rarr; click **Import** tab &rarr; select `sql/buyers.sql` &rarr; click **Import**.
     - Or MySQL CLI: `mysql -u root -p threadflow_db < sql/buyers.sql`
   - **Step 2: Import `sql/auth.sql`**
     - phpMyAdmin: Open `threadflow_db` &rarr; click **Import** tab &rarr; select `sql/auth.sql` &rarr; click **Import**.
     - Or MySQL CLI: `mysql -u root -p threadflow_db < sql/auth.sql`

---

## 5. Account Provisioning via CLI

To ensure strict security and prevent hardcoded credentials or public registration exploits, user accounts are created using interactive CLI scripts. Passwords are typed interactively from the terminal and are never accepted via CLI flags.

### 1. Initial Administrator Account
Run the provisioning tool from the project root:
```bash
C:\xampp\php\php.exe bin/create_admin.php
```
Follow the interactive prompts:
- Username (default: `admin`)
- Full Name
- Email Address
- Password (minimum 8 characters, typed interactively)
- Password Confirmation

### 2. Testing Non-Admin Roles
To test role-restricted pages without building registration or CRUD:
```bash
C:\xampp\php\php.exe bin/create_user.php
```
Select a role:
1. `admin`
2. `merchandiser` (Member 1 module access)
3. `production_manager` (Member 2 & 3 module access)
4. `qc_officer` (Member 4 module access)
5. `management` (Dashboard-only access)

Enter username, email, full name, and password interactively.

---

## 6. Team Member Boundaries & Ownership

| Component | Owner | Scope & Responsibilities |
| :--- | :--- | :--- |
| **Common Foundation** | All Members | `config/`, `includes/`, `assets/`, `auth/`, `sql/auth.sql`. Shared conventions. No member should modify these without notifying the team. |
| **Module 1: Buyer Management** | **Member 1** | Directory: `modules/buyers/`. Database: `sql/buyers.sql`. Buyer directory, add/edit profiles, store handlers. |
| **Module 2: Supplier Management** | **Member 2** | Directory: `modules/suppliers/`. Database: `sql/suppliers.sql`. Vendor records, material catalog, lead time evaluations. |
| **Module 3: Production Batches** | **Member 3** | Directory: `modules/production_batches/`. Database: `sql/production_batches.sql`. Work orders, cutting, sewing lines, batch tracking. |
| **Module 4: Defect Types** | **Member 4** | Directory: `modules/defect_types/`. Database: `sql/defect_types.sql`. Defect taxonomy, severity thresholds, QC inspection fault codes. |

---

## 7. Role Access Authorization Mapping

| Role | Accessible Modules |
| :--- | :--- |
| `admin` | Dashboard, Buyer Management, Supplier Management, Production Batches, Defect Types, Database Diagnostics |
| `merchandiser` | Dashboard, Buyer Management |
| `production_manager` | Dashboard, Supplier Management, Production Batches |
| `qc_officer` | Dashboard, Defect Types |
| `management` | Dashboard shell (read-only high-level views) |

> [!NOTE]
> Active status and user roles are verified directly against the live database on every protected request. If an admin modifies a user's role or sets `is_active = 0` in the database, the change applies immediately.

---

## 8. Shared Helper Contracts & Developer API

All pages should include `includes/auth.php` (which automatically loads `config.php`, `database.php`, `session.php`, and `helpers.php`).

### 1. HTML Escaping
```php
echo e($userInput); // Uses htmlspecialchars(..., ENT_QUOTES, 'UTF-8')
```

### 2. URL Generation
```php
<a href="<?= e(url('modules/buyers/create.php')) ?>">Add Buyer</a>
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
```

### 3. Role Authorization Enforcers
```php
// Protect page for specific roles:
require_role(['admin', 'merchandiser']);

// Check role conditionally:
if (has_role('admin')) {
    // Show admin controls
}
```

### 4. CSRF Protection for Forms
In your view form:
```html
<form action="store.php" method="post">
    <?= csrf_field() ?>
    <!-- inputs -->
    <button type="submit">Submit</button>
</form>
```
In your action handler (`store.php`):
```php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['admin', 'merchandiser']);
require_csrf(); // Validates POST token; halts with 403 if invalid
```

### 5. Flash Messages & Redirects
```php
flash_set('success', 'Buyer added successfully.');
redirect('modules/buyers/index.php');
```

### 6. Standard Database Access Pattern
Always use prepared statements with `$conn`:
```php
$stmt = $conn->prepare("SELECT buyer_id, buyer_name, email FROM buyers WHERE is_active = ?");
$isActive = 1;
$stmt->bind_param("i", $isActive);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    echo e($row['buyer_name']);
}
$stmt->close();
```

---

## 9. Git Branching & Collaboration Workflow

1. **Do Not Commit to `main` Directly**:
   - `setup/common-foundation` is the baseline branch.
   - Work in dedicated feature branches:
     - Member 1: `feature/buyer-management`
     - Member 2: `feature/supplier-management`
     - Member 3: `feature/production-batches`
     - Member 4: `feature/defect-types`
2. **Branching Command**:
   ```bash
   git checkout -b feature/your-feature-name
   ```
3. **Database Scripts**:
   - Never write `DROP TABLE` or destructive modifications.
   - Use additive statements (`CREATE TABLE IF NOT EXISTS ...`).
   - Prefix foreign keys and tables cleanly.
4. **Secrets & Cache**:
   - Never commit `config.local.php` or `.env`.
   - Before submitting a pull request, run a PHP syntax lint:
     ```bash
     C:\xampp\php\php.exe -l your_modified_file.php
     ```

---

## 10. Reusable UI Classes — `design/modern-ui` Reference

> Added by the `design/modern-ui` branch. All classes are defined in [`assets/css/style.css`](../assets/css/style.css). Use these in your module views to maintain a consistent look without writing new CSS.

### Layout

| Class | Purpose |
| :--- | :--- |
| `.page-header` | Flex row between page title and action buttons |
| `.page-title-group` | Wraps `<h1>` + `.page-subtitle` |
| `.page-subtitle` | Muted description line under the page title |
| `.page-actions` | Right-aligned action button group |
| `.content-container` | Centred max-width wrapper (1200 px) inside `.app-main` |

### Cards

| Class | Purpose |
| :--- | :--- |
| `.card` | White rounded card with subtle shadow (12 px radius) |
| `.card-header` | Tinted card header; flex row for title + badge |
| `.card-body` | Card content area with generous padding |
| `.card-footer` | Tinted card footer (links, status info) |
| `.module-card` | Dashboard module tile — hover lifts |
| `.module-card.restricted` | Dimmed dashed tile for locked modules |
| `.module-card-title` | Module name heading inside tile |
| `.module-card-desc` | Short description inside tile |
| `.module-card-footer` | CTA area at the bottom of a module tile |

### Forms

| Class | Purpose |
| :--- | :--- |
| `.form-group` | Vertical spacing wrapper around label + input |
| `.form-label` | Bold field label; add `.required` `<span>` for `*` |
| `.form-control` | Full-width styled input, textarea, or select |
| `.form-control.has-error` | Red border + focus ring when field fails validation |
| `.form-text` | Muted helper text below a field |
| `.form-error` | Red validation message below a field |
| `.filter-bar` | Flex search/filter row (use in index listing views) |
| `.filter-field-grow` | Grows to fill available space (search input column) |
| `.filter-field` | Fixed-width filter column (status dropdowns) |
| `.filter-actions` | Button cluster at the right end of a filter bar |

### Buttons

| Class | Purpose |
| :--- | :--- |
| `.btn` | Base button — always combine with a variant |
| `.btn-primary` | Maroon filled — primary CTA |
| `.btn-secondary` | White/outlined — secondary actions |
| `.btn-danger` | White/red-outlined — destructive actions |
| `.btn-sm` | Compact size for table row actions |
| `.btn-auth-submit` | Full-width button for login/auth forms |

### Badges & Status

| Class | Purpose |
| :--- | :--- |
| `.badge` | Base pill — combine with colour variant |
| `.badge-primary` | Maroon fill |
| `.badge-success` | Green — Active, Ready |
| `.badge-warning` | Amber — Pending, Under Development |
| `.badge-danger` | Red — Inactive, Missing, Error |
| `.badge-info` | Blue — informational |
| `.badge-muted` | Grey — neutral labels (e.g. M1, M2) |
| `.badge-role` | Translucent white — role chip in the header |

### Alerts

| Class | Purpose |
| :--- | :--- |
| `.alert` | Base alert bar — combine with colour variant |
| `.alert-success` | Green confirmation |
| `.alert-danger` / `.alert-error` | Red error |
| `.alert-warning` | Amber caution |
| `.alert-info` | Blue information |
| `.alert-close` | Dismiss button inside an alert |

### Tables

| Class | Purpose |
| :--- | :--- |
| `.table-responsive` | Horizontal-scroll wrapper for wide tables |
| `.table` | Styled data table with hover rows |

### Sidebar Navigation *(shared — do not modify)*

| Class | Purpose |
| :--- | :--- |
| `.nav-link` | Sidebar anchor — add `.active` for current page |
| `.nav-link-main` | Inner flex span (icon + label) |
| `.nav-icon` | 18 × 18 inline SVG icon beside nav label |
| `.nav-badge` | Small pill label at the right of a nav item |

### Utilities

| Class | Purpose |
| :--- | :--- |
| `.text-center / .text-right / .text-left` | Text alignment |
| `.text-muted` | Secondary muted text colour |
| `.text-sm` | 0.8125 rem font size |
| `.text-xs` | 0.72 rem font size |
| `.font-semibold / .font-bold` | Font weight helpers |
| `.d-flex` | `display: flex` |
| `.align-items-center` | Flex vertical centre |
| `.justify-content-between` | Flex space-between |
| `.flex-wrap` | Allow flex children to wrap |
| `.gap-1` – `.gap-4` | Flex/grid gap (0.25 rem – 1 rem) |
| `.mt-1` – `.mt-5` | Margin-top utilities |
| `.mb-1` – `.mb-5` | Margin-bottom utilities |

### Quick Start Example — Module Index View

```php
<div class="page-header">
    <div class="page-title-group">
        <h1>My Records</h1>
        <div class="page-subtitle">Module X &bull; Access: Admin, My Role</div>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('modules/my_module/create.php')) ?>" class="btn btn-primary">Add New</a>
    </div>
</div>

<!-- Filter bar inside a card -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="filter-bar">
            <div class="filter-field-grow">
                <label for="q" class="form-label">Search</label>
                <input type="text" id="q" name="q" class="form-control" value="<?= e($search) ?>">
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Data table inside a card -->
<div class="card">
    <div class="card-header">
        <h2>Records</h2>
        <span class="badge badge-muted"><?= count($rows) ?> results</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>Name</th><th>Status</th><th style="text-align:right;">Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e($row['name']) ?></td>
                        <td><span class="badge badge-success">Active</span></td>
                        <td style="text-align:right;">
                            <a href="..." class="btn btn-sm btn-secondary">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
```
