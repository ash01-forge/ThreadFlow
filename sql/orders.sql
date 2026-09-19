-- =============================================================================
-- ThreadFlow - Buyer Orders Schema (Module 1, Week 2)
-- Additive and compatible with sql/production_batches.sql.
-- =============================================================================

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