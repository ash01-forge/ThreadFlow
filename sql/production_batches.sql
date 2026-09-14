-- =============================================================================
-- ThreadFlow - Production Batches Schema & Seed Data (Module 3)
-- Assigned to: Member 3 (Production Manager / Admin)
-- 
-- Additive migration file for Production Batches and supporting relations.
-- Compatible with MySQL / MariaDB (InnoDB, utf8mb4)
--
-- Manual Import Instructions:
--   Option A (phpMyAdmin):
--     1. Open http://localhost/phpmyadmin and select 'threadflow_db'.
--     2. Click the 'Import' tab, choose this file (sql/production_batches.sql), and click 'Import'.
--   Option B (MySQL CLI):
--     mysql -u root -p threadflow_db < sql/production_batches.sql
-- =============================================================================

-- 1. Buyer Orders Table (Minimal dependency table for batch-order relationship)
CREATE TABLE IF NOT EXISTS buyer_orders (
    order_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT UNSIGNED NOT NULL,
    order_number VARCHAR(50) NOT NULL UNIQUE,
    style_number VARCHAR(50) NOT NULL,
    total_quantity INT UNSIGNED NOT NULL DEFAULT 0,
    order_date DATE NULL,
    delivery_date DATE NULL,
    status ENUM('Pending', 'Confirmed', 'In Production', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Confirmed',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_buyer_orders_buyer (buyer_id),
    CONSTRAINT fk_buyer_orders_buyer FOREIGN KEY (buyer_id)
        REFERENCES buyers (buyer_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Sewing Lines Table (Minimal dependency table for optional floor line allocation)
CREATE TABLE IF NOT EXISTS sewing_lines (
    line_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    line_name VARCHAR(100) NOT NULL UNIQUE,
    line_code VARCHAR(30) NOT NULL UNIQUE,
    daily_capacity INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Production Batches Table
CREATE TABLE IF NOT EXISTS production_batches (
    batch_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_number VARCHAR(50) NOT NULL UNIQUE,
    order_id INT UNSIGNED NOT NULL,
    line_id INT UNSIGNED NULL,
    planned_quantity INT UNSIGNED NOT NULL,
    status ENUM('Planned', 'In Progress', 'On Hold', 'Completed') NOT NULL DEFAULT 'Planned',
    start_date DATE NOT NULL,
    target_end_date DATE NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_batches_status (status),
    INDEX idx_batches_order (order_id),
    INDEX idx_batches_line (line_id),
    CONSTRAINT fk_batches_order FOREIGN KEY (order_id)
        REFERENCES buyer_orders (order_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_batches_line FOREIGN KEY (line_id)
        REFERENCES sewing_lines (line_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- Idempotent & Safely Repeatable Seed Data
-- =============================================================================

-- 4. Sewing Lines Seed Data (Idempotent: keyed by UNIQUE line_code, no hardcoded IDs)
INSERT IGNORE INTO sewing_lines (line_name, line_code, daily_capacity, is_active) VALUES
('Line A - Tops & Shirts', 'LINE-A', 1200, 1),
('Line B - Bottoms & Trousers', 'LINE-B', 800, 1),
('Line C - Outerwear & Jackets', 'LINE-C', 500, 1);

-- 5. Dependency Buyer (Guarantees valid buyer existence without breaking existing buyers)
INSERT INTO buyers (buyer_name, contact_person, email, phone, country, address, is_active)
SELECT 'Aurora Apparel Global', 'Eleanor Vance', 'orders@auroraapparel.com', '+1-555-0182', 'United Kingdom', '100 Fashion Way, London', 1
WHERE NOT EXISTS (SELECT 1 FROM buyers WHERE email = 'orders@auroraapparel.com');

-- 6. Dependency Buyer Orders (Idempotent: keyed by UNIQUE order_number)
INSERT IGNORE INTO buyer_orders (buyer_id, order_number, style_number, total_quantity, order_date, delivery_date, status)
SELECT b.buyer_id, 'ORD-2026-001', 'STY-DENIM-JACKET', 2500, '2026-09-01', '2026-10-15', 'In Production'
FROM buyers b WHERE b.email = 'orders@auroraapparel.com' LIMIT 1;

INSERT IGNORE INTO buyer_orders (buyer_id, order_number, style_number, total_quantity, order_date, delivery_date, status)
SELECT b.buyer_id, 'ORD-2026-002', 'STY-OXFORD-SHIRT', 5000, '2026-09-05', '2026-10-30', 'Confirmed'
FROM buyers b WHERE b.email = 'orders@auroraapparel.com' LIMIT 1;

INSERT IGNORE INTO buyer_orders (buyer_id, order_number, style_number, total_quantity, order_date, delivery_date, status)
SELECT b.buyer_id, 'ORD-2026-003', 'STY-CHINO-PANTS', 3200, '2026-09-10', '2026-11-15', 'Confirmed'
FROM buyers b WHERE b.email = 'orders@auroraapparel.com' LIMIT 1;

-- 7. Sample Production Batches (Idempotent: keyed by UNIQUE batch_number, safe relations via JOINs)
-- Batch 1: In Progress with Line C assigned
INSERT IGNORE INTO production_batches (batch_number, order_id, line_id, planned_quantity, status, start_date, target_end_date, notes)
SELECT 
    'BATCH-2026-001',
    bo.order_id,
    sl.line_id,
    1200,
    'In Progress',
    '2026-09-10',
    '2026-09-25',
    'Initial cutting and front pocket assembly running on Line C.'
FROM buyer_orders bo
JOIN sewing_lines sl ON sl.line_code = 'LINE-C'
WHERE bo.order_number = 'ORD-2026-001'
LIMIT 1;

-- Batch 2: Planned with Line A assigned
INSERT IGNORE INTO production_batches (batch_number, order_id, line_id, planned_quantity, status, start_date, target_end_date, notes)
SELECT 
    'BATCH-2026-002',
    bo.order_id,
    sl.line_id,
    2500,
    'Planned',
    '2026-09-20',
    '2026-10-05',
    'Fabric inspection completed; awaiting cutting clearance.'
FROM buyer_orders bo
JOIN sewing_lines sl ON sl.line_code = 'LINE-A'
WHERE bo.order_number = 'ORD-2026-002'
LIMIT 1;

-- Batch 3: On Hold with NULL line allocation (tests optional line relationship)
INSERT IGNORE INTO production_batches (batch_number, order_id, line_id, planned_quantity, status, start_date, target_end_date, notes)
SELECT 
    'BATCH-2026-003',
    bo.order_id,
    NULL,
    1600,
    'On Hold',
    '2026-09-15',
    '2026-09-30',
    'Awaiting accessory trim shipment from supplier; line unassigned.'
FROM buyer_orders bo
WHERE bo.order_number = 'ORD-2026-003'
LIMIT 1;
