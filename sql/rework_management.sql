-- =============================================================================
-- ThreadFlow - Rework Management Schema (Module 4)
-- Assigned to: Member 4 (QC Officer / Admin)
-- 
-- Additive migration file for Rework Jobs originating from Quality Inspection Defects.
-- Compatible with MySQL / MariaDB (InnoDB, utf8mb4)
--
-- Manual Import Instructions:
--   Option A (phpMyAdmin):
--     1. Open http://localhost/phpmyadmin and select 'threadflow_db'.
--     2. Click the 'Import' tab, choose this file (sql/rework_management.sql), and click 'Import'.
--   Option B (MySQL CLI):
--     mysql -u root -p threadflow_db < sql/rework_management.sql
-- =============================================================================

CREATE TABLE IF NOT EXISTS rework_jobs (
    rework_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rework_number VARCHAR(50) NOT NULL UNIQUE,
    inspection_defect_id INT UNSIGNED NOT NULL,
    line_id INT UNSIGNED NULL,
    assigned_to VARCHAR(150) NOT NULL,
    rework_quantity INT UNSIGNED NOT NULL,
    assigned_date DATE NOT NULL,
    target_completion_date DATE NOT NULL,
    actual_completion_date DATE NULL,
    completed_quantity INT UNSIGNED NOT NULL DEFAULT 0,
    rejected_quantity INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('Pending', 'In Progress', 'Completed', 'Rejected') NOT NULL DEFAULT 'Pending',
    corrective_action TEXT NULL,
    remarks TEXT NULL,
    created_by INT UNSIGNED NOT NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_rework_defect (inspection_defect_id),
    INDEX idx_rework_line (line_id),
    INDEX idx_rework_status (status),
    INDEX idx_rework_assigned_date (assigned_date),
    INDEX idx_rework_target_date (target_completion_date),
    INDEX idx_rework_created_by (created_by),
    INDEX idx_rework_updated_by (updated_by),

    CONSTRAINT fk_rework_defect FOREIGN KEY (inspection_defect_id)
        REFERENCES inspection_defects (inspection_defect_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_rework_line FOREIGN KEY (line_id)
        REFERENCES sewing_lines (line_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT fk_rework_created_by FOREIGN KEY (created_by)
        REFERENCES users (user_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_rework_updated_by FOREIGN KEY (updated_by)
        REFERENCES users (user_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT chk_rework_quantity CHECK (rework_quantity > 0),
    CONSTRAINT chk_rework_progress CHECK (completed_quantity + rejected_quantity <= rework_quantity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
