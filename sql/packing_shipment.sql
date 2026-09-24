-- =============================================================================
-- ThreadFlow - Packing & Shipment Schema (Module 4)
-- Assigned to: Member 4 (QC Officer / Admin)
-- 
-- Additive migration file for Packing and Outbound Shipment Management.
-- Strictly schema only: no seed, demo, or test data.
-- Compatible with MySQL / MariaDB (InnoDB, utf8mb4)
--
-- Manual Import Instructions:
--   Option A (phpMyAdmin):
--     1. Open http://localhost/phpmyadmin and select 'threadflow_db'.
--     2. Click the 'Import' tab, choose this file (sql/packing_shipment.sql), and click 'Import'.
--   Option B (MySQL CLI):
--     mysql -u root -p threadflow_db < sql/packing_shipment.sql
-- =============================================================================

CREATE TABLE IF NOT EXISTS packing_shipments (
    packing_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    packing_number VARCHAR(50) NOT NULL UNIQUE,
    batch_id INT UNSIGNED NOT NULL,
    inspection_id INT UNSIGNED NOT NULL,
    packed_quantity INT UNSIGNED NOT NULL,
    carton_count INT UNSIGNED NOT NULL DEFAULT 1,
    packing_date DATE NOT NULL,
    shipping_date DATE NULL,
    shipping_method ENUM('Sea', 'Air', 'Road', 'Courier') NOT NULL DEFAULT 'Sea',
    carrier_name VARCHAR(100) NULL,
    tracking_number VARCHAR(100) NULL,
    destination VARCHAR(150) NOT NULL,
    status ENUM('Packed', 'In Transit', 'Shipped', 'Delivered', 'Cancelled') NOT NULL DEFAULT 'Packed',
    notes TEXT NULL,
    created_by INT UNSIGNED NOT NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Performance and filtering indexes
    INDEX idx_packing_batch (batch_id),
    INDEX idx_packing_inspection (inspection_id),
    INDEX idx_packing_status (status),
    INDEX idx_packing_date (packing_date),
    INDEX idx_packing_shipping_date (shipping_date),
    INDEX idx_packing_created_by (created_by),
    INDEX idx_packing_updated_by (updated_by),

    -- Foreign Key Constraints
    CONSTRAINT fk_packing_batch FOREIGN KEY (batch_id)
        REFERENCES production_batches (batch_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_packing_inspection FOREIGN KEY (inspection_id)
        REFERENCES quality_inspections (inspection_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_packing_created_by FOREIGN KEY (created_by)
        REFERENCES users (user_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_packing_updated_by FOREIGN KEY (updated_by)
        REFERENCES users (user_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    -- Domain and Business Integrity Constraints
    CONSTRAINT chk_packing_quantity CHECK (packed_quantity > 0),
    CONSTRAINT chk_carton_count CHECK (carton_count > 0),
    CONSTRAINT chk_shipping_date CHECK (shipping_date IS NULL OR shipping_date >= packing_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
