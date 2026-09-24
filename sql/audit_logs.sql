-- =============================================================================
-- ThreadFlow - Audit History Schema (Module 4)
-- Assigned to: Member 4 (QC Officer / Admin)
-- 
-- Additive migration file for system-wide immutable audit trail logging.
-- Strictly schema only: no seed, demo, or test data.
-- Compatible with MySQL / MariaDB (InnoDB, utf8mb4)
--
-- Manual Import Instructions:
--   Option A (phpMyAdmin):
--     1. Open http://localhost/phpmyadmin and select 'threadflow_db'.
--     2. Click the 'Import' tab, choose this file (sql/audit_logs.sql), and click 'Import'.
--   Option B (MySQL CLI):
--     mysql -u root -p threadflow_db < sql/audit_logs.sql
-- =============================================================================

CREATE TABLE IF NOT EXISTS audit_logs (
    log_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    user_identity VARCHAR(100) NOT NULL,
    user_role VARCHAR(50) NULL,
    action VARCHAR(50) NOT NULL,
    module VARCHAR(50) NOT NULL,
    table_name VARCHAR(64) NOT NULL,
    record_id INT UNSIGNED NULL,
    record_identifier VARCHAR(100) NULL,
    old_values LONGTEXT NULL,
    new_values LONGTEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Performance and search indexes
    INDEX idx_audit_user (user_id),
    INDEX idx_audit_module (module),
    INDEX idx_audit_action (action),
    INDEX idx_audit_table_record (table_name, record_id),
    INDEX idx_audit_created_at (created_at),

    -- Foreign Key Constraint matching exact datatype INT UNSIGNED from users.user_id
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id)
        REFERENCES users (user_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
