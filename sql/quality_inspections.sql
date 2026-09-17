-- =============================================================================
-- ThreadFlow - Quality Inspection Management Schema (Module 4)
-- Assigned to: Member 4 (QC Officer / Admin)
-- 
-- Additive migration file for Quality Inspections and Defect Breakdown.
-- Compatible with MySQL / MariaDB (InnoDB, utf8mb4)
--
-- Manual Import Instructions:
--   Option A (phpMyAdmin):
--     1. Open http://localhost/phpmyadmin and select 'threadflow_db'.
--     2. Click the 'Import' tab, choose this file (sql/quality_inspections.sql), and click 'Import'.
--   Option B (MySQL CLI):
--     mysql -u root -p threadflow_db < sql/quality_inspections.sql
-- =============================================================================

-- 1. Main Quality Inspections Table
CREATE TABLE IF NOT EXISTS quality_inspections (
    inspection_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inspection_number VARCHAR(50) NOT NULL UNIQUE,
    batch_id INT UNSIGNED NOT NULL,
    inspector_id INT UNSIGNED NOT NULL,
    inspection_date DATE NOT NULL,
    inspection_stage ENUM('Inline', 'End of Line', 'Final Pre-Shipment') NOT NULL DEFAULT 'Inline',
    inspected_quantity INT UNSIGNED NOT NULL,
    passed_quantity INT UNSIGNED NOT NULL,
    defective_quantity INT UNSIGNED NOT NULL,
    overall_result ENUM('Passed', 'Failed', 'Pending Review') NOT NULL DEFAULT 'Passed',
    remarks TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_inspections_batch (batch_id),
    INDEX idx_inspections_inspector (inspector_id),
    INDEX idx_inspections_date (inspection_date),
    INDEX idx_inspections_result (overall_result),
    CONSTRAINT fk_inspections_batch FOREIGN KEY (batch_id)
        REFERENCES production_batches (batch_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_inspections_inspector FOREIGN KEY (inspector_id)
        REFERENCES users (user_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT chk_inspections_quantity CHECK (passed_quantity + defective_quantity = inspected_quantity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Inspection Defects Breakdown Child Table
CREATE TABLE IF NOT EXISTS inspection_defects (
    inspection_defect_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inspection_id INT UNSIGNED NOT NULL,
    defect_id INT UNSIGNED NOT NULL,
    defect_count INT UNSIGNED NOT NULL DEFAULT 1,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_inspection_defects_inspection (inspection_id),
    INDEX idx_inspection_defects_defect (defect_id),
    UNIQUE KEY uq_inspection_defect (inspection_id, defect_id),
    CONSTRAINT fk_inspection_defects_inspection FOREIGN KEY (inspection_id)
        REFERENCES quality_inspections (inspection_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_inspection_defects_defect FOREIGN KEY (defect_id)
        REFERENCES defect_types (defect_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
