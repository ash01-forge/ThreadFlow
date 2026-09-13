# AGENTS.md — Agent Guidelines for ThreadFlow

This file defines constraints, architectural conventions, and development practices for AI coding assistants working in the **ThreadFlow** repository.

---

## 1. Environment & Architecture Constraints

- **Stack**: Core PHP (PHP 7.4 - 8.x compatible), MySQL/MariaDB (MySQLi extension), HTML5, CSS3 (Custom Properties), Vanilla JavaScript.
- **Runtime Target**: XAMPP on Windows.
- **Strict Prohibitions**:
  - NO Laravel, Symfony, Slim, or backend PHP frameworks.
  - NO Composer packages or external autoloader dependencies.
  - NO React, Vue, Angular, Svelte, or frontend JS frameworks.
  - NO Node.js, npm, Webpack, Vite, or CSS preprocessor build steps.
  - NO external CDN dependencies (fonts, icons, stylesheets, JS libraries) to ensure local offline operation.

---

## 2. Core Security & Implementation Rules

### 1. Database Operations
- Always use the shared `$conn` MySQLi connection provided by `config/database.php`.
- **Prepared Statements Are Mandatory**: Never concatenate user-supplied input into SQL queries.
  ```php
  $stmt = $conn->prepare("SELECT ... WHERE id = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  ```
- **Error Privacy**: Never display raw MySQL exceptions or credentials in HTTP responses. All database errors must be caught and written to `error_log()`.

### 2. Output Escaping (XSS Prevention)
- All user-controlled output rendered into HTML must be passed through `e()`:
  ```php
  <?= e($data['name']) ?>
  ```
- Attributes must be enclosed in quotes and escaped.

### 3. State-Changing Operations (CSRF Protection)
- Every POST form must include `<?= csrf_field() ?>`.
- Every POST handler must call `require_csrf()` before processing input.
- Logout must always be POST-only. Never implement GET-based logout.

### 4. Authentication & Role Authorization
- Every protected page must include `includes/auth.php`.
- Use `require_login()` for general authentication checks.
- Use `require_role([...])` for role-restricted pages.
- Role checks must always verify the user's active status and role against the live database.

---

## 3. Module Boundaries & Ownership

- **Common Core**: `config/`, `includes/`, `assets/`, `auth/`, `sql/auth.sql`.
  - Maintain stability. Do not introduce breaking changes to existing contracts.
- **Module 1**: `modules/buyers/` & `sql/buyers.sql` (Member 1 — Merchandiser / Admin).
- **Module 2**: `modules/suppliers/` & `sql/suppliers.sql` (Member 2 — Production Manager / Admin).
- **Module 3**: `modules/production_batches/` & `sql/production_batches.sql` (Member 3 — Production Manager / Admin).
- **Module 4**: `modules/defect_types/` & `sql/defect_types.sql` (Member 4 — QC Officer / Admin).

---

## 4. Visual & UI Guidelines

- **Theme Palette**:
  - Primary Maroon: `#78000F`
  - Background: `#FCEBED`
- Reusable CSS variables are defined in `assets/css/style.css`. Always use variables (`var(--color-primary)`, `var(--color-surface)`, etc.).
- Form inputs must include associated `<label for="...">` tags and distinct `:focus-visible` outlines.
- Note: Layouts are provisional; keep HTML semantic, clean, and extensible.
