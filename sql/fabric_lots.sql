-- =============================================================================
-- ThreadFlow - Fabric Receiving Management Schema (Module 2, Feature 2)
-- Assigned to: Member 2 (Production Manager / Admin)
--
-- Additive migration file for Fabric Receiving Lots.
-- Compatible with MySQL / MariaDB (InnoDB, utf8mb4_unicode_ci).
--
-- Manual Import Instructions:
--   Option A (phpMyAdmin):
--     1. Open http://localhost/phpmyadmin and select 'threadflow_db'.
--     2. Click the 'Import' tab, choose this file (sql/fabric_lots.sql), and click 'Import'.
--   Option B (MySQL CLI):
--     mysql -u root -p threadflow_db < sql/fabric_lots.sql
--
-- Dependencies:
--   - sql/suppliers.sql (creates `suppliers` table)
-- =============================================================================

CREATE TABLE IF NOT EXISTS fabric_lots (
    fabric_lot_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT UNSIGNED NOT NULL,
    lot_number VARCHAR(100) NOT NULL,
    fabric_type VARCHAR(100) NOT NULL,
    color VARCHAR(100) DEFAULT NULL,
    received_quantity DECIMAL(10, 2) NOT NULL,
    unit VARCHAR(20) NOT NULL DEFAULT 'Yards',
    received_date DATE NOT NULL,
    inspection_status ENUM('Pending', 'Passed', 'Failed', 'Conditionally Approved') NOT NULL DEFAULT 'Pending',
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_supplier_lot (supplier_id, lot_number),
    INDEX idx_fabric_lots_supplier (supplier_id),
    INDEX idx_fabric_lots_lot_number (lot_number),
    INDEX idx_fabric_lots_fabric_type (fabric_type),
    INDEX idx_fabric_lots_status (inspection_status),
    INDEX idx_fabric_lots_received_date (received_date),
    CONSTRAINT fk_fabric_lots_supplier FOREIGN KEY (supplier_id)
        REFERENCES suppliers (supplier_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
