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
