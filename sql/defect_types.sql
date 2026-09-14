-- ThreadFlow - Defect Types Schema
-- Module 4: Assigned to Member 4 (QC Officer / Admin)
-- Additive creation of defect_types master table

CREATE TABLE IF NOT EXISTS defect_types (
    defect_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    defect_code VARCHAR(50) NOT NULL UNIQUE,
    defect_name VARCHAR(150) NOT NULL,
    category VARCHAR(50) NOT NULL,
    severity ENUM('minor', 'major', 'critical') NOT NULL DEFAULT 'major',
    description TEXT,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_defect_category (category),
    INDEX idx_defect_severity (severity),
    INDEX idx_defect_is_active (is_active)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
