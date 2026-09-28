# ThreadFlow — Team Setup & Architecture Guide

This guide is the shared reference for setting up, understanding, verifying, and contributing to ThreadFlow. It reflects the current application modules and database scripts in the repository.

## 1. System Overview

ThreadFlow is a role-based garment production, quality-control, and traceability system. It connects the operational path from buyer and order management through fabric handling, production, inspection, rework, packing, shipment, and audit reporting.

### Technology constraints

- Core PHP 7.4 or later
- MySQL or MariaDB through MySQLi
- HTML5, CSS3, and Vanilla JavaScript
- XAMPP on Windows
- No PHP or JavaScript framework
- No Composer or Node.js build pipeline
- No CDN or external runtime dependency

## 2. Local Environment

### Required services

Start Apache and MySQL from the XAMPP Control Panel.

### Project location

~~~text
C:\xampp\htdocs\ThreadFlow
~~~

### Local configuration

Copy `config/config.example.php` to `config/config.local.php`.

Default development values:

~~~php
return [
    'db_host' => '127.0.0.1',
    'db_port' => 3306,
    'db_name' => 'threadflow_db',
    'db_user' => 'root',
    'db_pass' => '',
    'base_url' => '/ThreadFlow',
    'app_name' => 'ThreadFlow',
    'app_env' => 'development',
    'session_lifetime' => 0,
];
~~~

Never commit `config/config.local.php`, credentials, local dumps containing sensitive data, or environment-specific secrets.

## 3. Database Installation

Create an empty database named `threadflow_db` with the `utf8mb4` character set.

Import the SQL files in this dependency-safe order:

1. `buyers.sql`
2. `auth.sql`
3. `orders.sql`
4. `order_items.sql`
5. `buyer_change_requests.sql`
6. `suppliers.sql`
7. `fabric_lots.sql`
8. `production_batches.sql`
9. `stage_progress.sql`
10. `bottleneck_monitoring.sql`
11. `defect_types.sql`
12. `quality_inspections.sql`
13. `rework_management.sql`
14. `packing_shipment.sql`
15. `audit_logs.sql`
16. `fabric_inspections.sql`
17. `fabric_allocations.sql`
18. `operational_dashboard.sql`

Use an empty database for a clean installation. `buyers.sql` creates its table without `IF NOT EXISTS`, and some later scripts contain shared dependencies or seed data. Do not blindly re-import the complete sequence into a populated database.

### Expected database objects

The completed database contains 19 base tables:

- `audit_logs`
- `buyers`
- `buyer_change_requests`
- `buyer_orders`
- `defect_types`
- `fabric_allocations`
- `fabric_inspections`
- `fabric_lots`
- `inspection_defects`
- `order_items`
- `packing_shipments`
- `production_batches`
- `production_stages`
- `quality_inspections`
- `rework_jobs`
- `sewing_lines`
- `stage_progress`
- `suppliers`
- `users`

The bottleneck feature also creates `view_bottleneck_monitoring`.

## 4. Account Provisioning

Create the first administrator from the project directory:

~~~text
php bin/create_admin.php
~~~

Create additional role-based test accounts with:

~~~text
php bin/create_user.php
~~~

Supported application roles are:

- `admin`
- `merchandiser`
- `production_manager`
- `qc_officer`
- `management`

Use test-only credentials and never document real passwords in Git.

## 5. Module and Role Map

| Workflow | Directory | Roles |
| --- | --- | --- |
| Buyer management | `modules/buyers/` | Admin, Merchandiser |
| Order management | `modules/orders/` | Admin, Merchandiser |
| Buyer change requests | `modules/buyer_change_requests/` | Admin, Merchandiser; Admin review |
| Buyer risk analyzer | `modules/buyer_risk_analyzer/` | Admin, Merchandiser, Management |
| Supplier management | `modules/suppliers/` | Admin, Production Manager |
| Fabric receiving | `modules/fabric_receiving/` | Admin, Production Manager |
| Fabric inspections | `modules/fabric_inspections/` | Admin, Production Manager, QC Officer |
| Fabric allocations | `modules/fabric_allocations/` | Admin, Production Manager |
| Production batches | `modules/production_batches/` | Admin, Production Manager |
| Stage progress | `modules/stage_progress/` | Admin, Production Manager |
| Bottleneck monitoring | `modules/bottlenecks/` | Admin, Production Manager |
| Operational dashboard | `modules/operational_dashboard/` | Admin, Production Manager, Management |
| Defect types | `modules/defect_types/` | Admin, QC Officer |
| Quality inspections | `modules/quality_inspections/` | Admin, QC Officer |
| Rework management | `modules/rework/` | Admin, QC Officer |
| Packing and shipment | `modules/packing_shipment/` | Admin, QC Officer |
| Audit history | `modules/audit_logs/` | Admin, QC Officer |
| User management | `modules/users/` | Admin |

The dashboard is available to authenticated users. The sidebar must display only the modules appropriate for the active role.

## 6. Architecture

### Shared core

- `config/config.php`: application constants and environment configuration
- `config/database.php`: shared MySQLi connection
- `includes/session.php`: session initialization
- `includes/auth.php`: login and role authorization
- `includes/helpers.php`: escaping, URLs, CSRF, redirects, and flash helpers
- `includes/header.php`, `sidebar.php`, and `footer.php`: shared layout
- `includes/audit.php`: audit logging support
- `assets/css/style.css`: shared design system
- `assets/js/main.js`: shared client-side interaction

### Module pattern

Each module owns its business pages and handlers. Typical pages include:

- `index.php`: listing, search, and filters
- `view.php` or `details.php`: record detail
- `create.php` and `store.php`: creation form and handler
- `edit.php` and `update.php`: update form and handler
- dedicated POST handlers for status transitions when needed

Keep SQL and business behavior within the relevant module unless the behavior is genuinely shared.

## 7. Security Contracts

All contributors must preserve these requirements:

1. Include `includes/auth.php` on protected pages.
2. Call `require_login()` or `require_role(...)` before output.
3. Use the shared `$conn` object from `config/database.php`.
4. Use prepared statements for every user-controlled SQL value.
5. Escape displayed user-controlled data with `e()`.
6. Include `csrf_field()` in every state-changing form.
7. Call `require_csrf()` in every state-changing handler.
8. Keep logout POST-only.
9. Log internal database errors without displaying credentials or raw exceptions.
10. Validate identifiers and business-state transitions on the server.

## 8. Interface Standards

ThreadFlow uses a shared maroon and soft-pink visual system.

- Primary color: `#78000F`
- Background color: `#FCEBED`
- Use shared CSS variables and reusable classes from `assets/css/style.css`.
- Preserve the shared header and sidebar behavior.
- Every form control requires a connected label.
- Interactive elements need visible keyboard focus.
- Status badges must maintain readable contrast.
- Tables and action controls must remain usable on narrow screens.
- New interfaces should follow existing spacing, typography, card, alert, and button patterns.

## 9. Verification Checklist

### Database

- Apache and MySQL are running.
- `threadflow_db` contains all 19 tables.
- `view_bottleneck_monitoring` exists.
- Foreign-key-dependent pages load without SQL errors.
- `tests/database_test.php` reports a successful connection.

### Authentication and roles

- Login and logout work.
- Inactive accounts cannot access protected functions.
- Each role sees only its authorized modules.
- Direct URL access is also blocked for unauthorized roles.
- Admin can manage users and review buyer change requests.

### Integrated workflow

- Buyers and orders load.
- Fabric receiving, inspection, and allocation records connect correctly.
- Production batches and stage progress load.
- Bottleneck and operational dashboards load.
- Quality inspection, defect, rework, packing, and shipment pages load.
- Audit history records relevant actions.
- Search, filters, view, create, and edit screens render correctly.

When using important data, perform a non-destructive smoke test: open forms but do not submit status changes or delete records.

## 10. Git Collaboration

### Branching

Create a focused branch from the latest `main`:

~~~text
feature/short-description
fix/short-description
docs/short-description
~~~

Do not develop directly on `main`.

### Commit quality

- Keep commits focused and understandable.
- Use imperative summaries such as `Document database setup and role access`.
- Do not mix unrelated UI, SQL, and documentation changes.
- Review the changed-file list before committing.
- Never commit local credentials or editor-generated files.

### Pull requests

A pull request should explain:

- what changed
- why it changed
- affected modules and roles
- database impact
- verification performed
- screenshots for visible interface changes

Resolve conflicts, review the final diff, and verify the integrated result before merge.

## 11. Team Handoff Checklist

Before marking a feature complete:

- business flow works with the correct role
- input validation and error messages are clear
- SQL uses prepared statements
- output is escaped
- CSRF protection is present
- shared navigation is correct
- layout matches the ThreadFlow design system
- related SQL is included
- no credentials or local-only files are tracked
- the feature has been checked after merging with the latest `main`

## 12. Submission Preparation

For the final DBMS submission, keep the repository and report consistent:

- README setup steps match the actual project
- ERD matches the imported database
- relational schema lists current PK and FK relationships
- the report references the public or shared repository URL
- a complete SQL export is available
- screenshots cover each functional area
- team contribution mapping reflects the final implementation
- limitations and future improvements are stated honestly

Return to the repository overview in [README.md](../README.md).
