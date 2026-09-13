-- ThreadFlow - Common Authentication Schema
-- This script is additive and creates only the shared authentication tables.
-- It does NOT drop or alter the existing `buyers` table.
--
-- For existing databases where `buyers` already exists:
--   Import ONLY this file via phpMyAdmin Import tab or MySQL CLI.
--
-- For completely fresh setups:
--   1. Import sql/buyers.sql
--   2. Import sql/auth.sql

CREATE TABLE IF NOT EXISTS users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'merchandiser', 'production_manager', 'qc_officer', 'management') NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role (role),
    INDEX idx_users_is_active (is_active)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
