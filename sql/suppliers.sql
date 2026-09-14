-- ThreadFlow - Supplier Management Schema (Module 2)
-- Assigned to Member 2 (Production Manager / Admin)
-- Additive migration: safe for existing and fresh installations.

CREATE TABLE IF NOT EXISTS suppliers (
    supplier_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(150) NOT NULL,
    supplier_type VARCHAR(50) NOT NULL DEFAULT 'Fabric',
    contact_person VARCHAR(100) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    country VARCHAR(100) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    lead_time_days INT UNSIGNED DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_suppliers_is_active (is_active),
    INDEX idx_suppliers_supplier_type (supplier_type)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
