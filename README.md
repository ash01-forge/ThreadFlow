# ThreadFlow

![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4)
![Database](https://img.shields.io/badge/Database-MySQL%20%7C%20MariaDB-orange)
![Frontend](https://img.shields.io/badge/Frontend-HTML%20%7C%20CSS%20%7C%20JavaScript-E34F26)
![Team](https://img.shields.io/badge/Team-4%20Members-purple)
![Contribution](https://img.shields.io/badge/Contribution-25%25%20Each-success)

ThreadFlow is a role-based garment production management system that connects buyer operations, fabric sourcing, production tracking, quality control, rework, packing, shipment, and auditability within one centralized workflow.

It provides dedicated workspaces for different operational roles while maintaining traceability from the initial buyer request through production and final shipment.

## Team Contributions

ThreadFlow was collaboratively designed and developed by a four-member team. Each member made an equal overall contribution of **25%** while taking primary responsibility for a specific group of features.

| Team member | GitHub profile | Primary contributions | Share |
|---|---|---|---:|
| Adeeb Sharar | [@ash01-forge](https://github.com/ash01-forge) | Buyer management, order and order-item management, buyer change requests, buyer commitment risk analyzer, authentication, and shared interface modernization | 25% |
| MD. Tashin Alam Tehami | [@TaSHin0302](https://github.com/TaSHin0302) | Supplier management, fabric receiving, inspection, allocation, and traceability | 25% |
| Farhan Labib | [@Farhan-Labib2003](https://github.com/Farhan-Labib2003) | Production batches, stage-progress history, bottleneck monitoring, and operational dashboard | 25% |
| Tanvir Rifat | [@tanrif21](https://github.com/tanrif21) | Quality inspection, defect analysis, rework, packing, partial shipment, and audit history | 25% |

All four members also participated in project planning, integration, testing, debugging, documentation, and final delivery.

> **Project attribution:** This repository is hosted under the `ash01-forge` GitHub account for project administration. Repository ownership and Git activity do not represent individual contribution share; overall project credit is shared equally among all four contributors.

## Highlights

- End-to-end traceability from buyer request to final shipment
- Role-based authentication and access control
- Admin-controlled user, role, and account management
- Buyer, order, and order-item management workflows
- Buyer change-request review and impact analysis
- Buyer commitment risk analysis based on operational data
- Supplier and fabric-lot management
- Fabric receiving, inspection, allocation, and traceability
- Production-batch and stage-progress tracking
- Production bottleneck monitoring
- Quality inspection and defect analysis
- Rework, packing, and partial-shipment management
- Operational dashboard and audit history
- Responsive interface with a consistent ThreadFlow design system
- Prepared SQL statements, CSRF protection, output escaping, and session-based authentication

## Functional Areas

| Area | Main capabilities | Authorized roles |
|---|---|---|
| Buyer operations | Buyers, orders, order items, change requests, impact review, and commitment risk analysis | Admin, Merchandiser, Management |
| Fabric operations | Suppliers, receiving, inspections, allocations, and traceability | Admin, Production Manager, QC Officer |
| Production control | Production batches, stage progress, and bottleneck monitoring | Admin, Production Manager |
| Quality control | Defect types, inspections, rework, packing, and shipment | Admin, QC Officer |
| Administration | Users, account status, and role assignment | Admin |
| Management | Operational dashboard, monitoring views, and selected risk information | Admin, Production Manager, Management |

## Technology Stack

| Layer | Technology |
|---|---|
| Backend | Core PHP 7.4+ |
| Database | MySQL or MariaDB |
| Frontend | HTML5, CSS3, Vanilla JavaScript |
| Database access | MySQLi with prepared statements |
| Local runtime | XAMPP on Windows |

ThreadFlow does not require Composer packages, Node.js dependencies, frontend frameworks, or external CDNs.

## Project Structure

```text
ThreadFlow/
├── assets/                 Shared CSS and JavaScript
├── auth/                   Login and logout
├── bin/                    Account-provisioning scripts
├── config/                 Application and database configuration
├── docs/                   Setup and architecture documentation
├── includes/               Shared authentication, layout, helpers, alerts, and audit utilities
├── modules/                Feature modules grouped by business workflow
├── sql/                    Database schemas, views, and seed data
├── tests/                  Lightweight database verification
├── dashboard.php           Authenticated landing page
├── index.php               Application entry point
└── README.md
```

## Local Setup

### Requirements

- XAMPP with Apache and MySQL
- PHP 7.4 or later with the MySQLi extension
- MySQL or MariaDB
- A modern web browser

### 1. Place the project

Clone or copy the repository into:

```text
C:\xampp\htdocs\ThreadFlow
```

### 2. Configure the application

Copy:

```text
config/config.example.php
```

to:

```text
config/config.local.php
```

Confirm the local configuration values:

```php
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
```

The local configuration file is ignored by Git and must never be committed.

### 3. Create the database

Create an empty MySQL database named:

```text
threadflow_db
```

Use the `utf8mb4` character set where available.

### 4. Import the SQL files

Import the files from the `sql/` directory in the following dependency-safe order:

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

For a clean installation, start with an empty database. Some scripts contain shared dependencies or seed data and should be imported in the documented order.

### 5. Create the initial administrator

From the project directory, run:

```bash
php bin/create_admin.php
```

If PHP is not available in the system path, use:

```text
C:\xampp\php\php.exe bin\create_admin.php
```

Follow the prompts to create the administrator account.

Additional role-based accounts can be created with:

```bash
php bin/create_user.php
```

### 6. Open ThreadFlow

Start Apache and MySQL from XAMPP, then visit:

```text
http://localhost/ThreadFlow/
```

## Security Design

- Protected routes enforce authentication and role-based authorization.
- User-supplied database values are handled with prepared statements.
- HTML output is escaped through shared helper functions.
- State-changing forms require CSRF validation.
- Logout is restricted to POST requests.
- Account roles and active status are checked against the database.
- Sensitive workflow actions are recorded in the audit history.
- Database errors are logged without exposing credentials.
- Local configuration and credentials are excluded from version control.

## Verification

A lightweight database verification script is available at:

```text
tests/database_test.php
```

Before a release, verify:

- Login and logout
- Role-specific navigation and access restrictions
- User and account-status management
- Buyer, order, and order-item workflows
- Buyer change-request review
- Buyer commitment risk analysis
- Fabric receiving, inspection, and allocation
- Production batches and stage progress
- Quality inspection and defect recording
- Rework, packing, and shipment workflows
- Operational dashboard and audit history

Avoid running destructive or status-changing checks against important production data.

## Collaboration

Contributors should use short feature branches and focused pull requests.

When making changes:

- Keep feature logic inside the relevant module.
- Preserve the shared authentication and authorization rules.
- Follow the existing ThreadFlow interface and design system.
- Validate all input on the server.
- Use prepared statements for database operations.
- Escape rendered output.
- Include CSRF protection on state-changing forms.
- Never commit `config/config.local.php`, passwords, tokens, or other credentials.
- Verify the affected workflow before requesting a merge.

Detailed setup, database, role, architecture, and Git guidance is available in [`docs/TEAM_SETUP.md`](docs/TEAM_SETUP.md).

## Repository Documentation

- [Setup and architecture guide](docs/TEAM_SETUP.md)
- [AI contributor constraints](AGENTS.md)

## Academic Use and Licensing

ThreadFlow is maintained as an academic software project. No open-source license has been granted for this repository.

Unless a license is added later, the source code may not be copied, redistributed, or reused outside the project without permission from the project team.
