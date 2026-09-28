# ThreadFlow

ThreadFlow is a role-based garment production management system that connects buyer operations, fabric sourcing, production tracking, quality control, rework, packing, shipment, and auditability in one workflow.

The project is built for a university DBMS course and runs locally on XAMPP without external packages or build tools.

## Highlights

- End-to-end traceability from buyer order to shipment
- Role-based workspaces for Admin, Merchandiser, Production Manager, QC Officer, and Management
- Buyer and supplier master data with status controls
- Fabric receiving, inspection, allocation, and traceability
- Production batches, stage progress, and bottleneck monitoring
- Quality inspections, defect tracking, rework, packing, and shipment
- Buyer change-request review and buyer risk analysis
- Operational dashboard and audit history
- Prepared SQL statements, CSRF protection, output escaping, and session-based authentication
- Responsive interface with a consistent ThreadFlow design system

## Technology

| Layer | Technology |
| --- | --- |
| Backend | Core PHP 7.4+ |
| Database | MySQL or MariaDB |
| Frontend | HTML5, CSS3, Vanilla JavaScript |
| Database access | MySQLi with prepared statements |
| Local runtime | XAMPP on Windows |

No Composer, Node.js, frontend framework, or CDN is required.

## Functional Areas

| Area | Main capabilities | Authorized roles |
| --- | --- | --- |
| Buyer operations | Buyers, orders, change requests, risk analysis | Admin, Merchandiser, Management (risk views) |
| Fabric operations | Suppliers, receiving, inspections, allocations, traceability | Admin, Production Manager, QC Officer (inspection) |
| Production control | Batches, stage progress, bottleneck monitoring | Admin, Production Manager |
| Quality control | Defect types, inspections, rework, packing and shipment, audit history | Admin, QC Officer |
| Administration | Users, account status, role assignment | Admin |
| Management | Operational dashboard and selected risk views | Admin, Production Manager, Management |

## Project Structure

~~~text
ThreadFlow/
|-- assets/                 Shared CSS and JavaScript
|-- auth/                   Login and logout
|-- bin/                    CLI account-provisioning scripts
|-- config/                 Local configuration and database connection
|-- docs/                   Team setup and architecture guide
|-- includes/               Shared authentication, layout, helpers, alerts, and audit utilities
|-- modules/                Feature modules grouped by business workflow
|-- sql/                    Database schema, views, and seed data
|-- tests/                  Lightweight database verification
|-- dashboard.php           Authenticated landing page
|-- index.php               Application entry point
`-- README.md
~~~

## Local Setup

### Prerequisites

- XAMPP with Apache and MySQL running
- PHP 7.4 or later with the MySQLi extension
- A modern web browser

### 1. Place the project

Clone or copy the repository to:

~~~text
C:\xampp\htdocs\ThreadFlow
~~~

### 2. Configure the application

Copy `config/config.example.php` to `config/config.local.php`, then confirm the local database values:

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

The local configuration file is ignored by Git and must not be committed.

### 3. Create and import the database

Create a database named `threadflow_db` using `utf8mb4`, then import the SQL files in this dependency-safe order:

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

For a clean installation, start with an empty database. Some scripts include shared dependencies or seed data and are intended to be imported in the order above.

### 4. Create the initial administrator

From the project directory, run:

~~~text
php bin/create_admin.php
~~~

Follow the prompts. Additional role-based accounts can be created with:

~~~text
php bin/create_user.php
~~~

### 5. Open ThreadFlow

Visit:

~~~text
http://localhost/ThreadFlow/
~~~

## Security Design

- All protected routes enforce authentication and role authorization.
- User-supplied values are processed with prepared statements.
- HTML output uses the shared escaping helper.
- State-changing forms require CSRF validation.
- Logout is POST-only.
- Database errors are logged without exposing credentials.
- Account roles and active status are checked against the database.

## Verification

A lightweight database check is available at `tests/database_test.php`. Before a release or submission, verify:

- login and logout
- role-specific navigation and access restrictions
- key list, view, create, and edit screens
- buyer-to-order and fabric-to-production relationships
- production progress, QC, rework, packing, and shipment flows
- audit and dashboard views

Avoid running destructive or status-changing checks against important data.

## Collaboration

Use short feature branches and focused pull requests. Keep module logic inside its module, preserve shared security contracts, and never commit `config/config.local.php` or credentials.

Detailed setup, database, role, architecture, and Git guidance is available in [docs/TEAM_SETUP.md](docs/TEAM_SETUP.md).

## Repository Documents

- [Team setup and architecture guide](docs/TEAM_SETUP.md)
- [AI contributor constraints](AGENTS.md)

## License

This repository is maintained as an academic project. No separate open-source license has been declared.
