-- ThreadFlow - Fabric Inspection (Member 2, Feature 3)
-- Additive schema only. Import manually after sql/fabric_lots.sql and sql/auth.sql.

CREATE TABLE IF NOT EXISTS fabric_inspections (
    fabric_inspection_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fabric_lot_id INT UNSIGNED NOT NULL,
    inspector_id INT UNSIGNED NOT NULL,
    inspection_date DATE NOT NULL,
    inspected_quantity DECIMAL(10,2) NOT NULL,
    penalty_points INT UNSIGNED NOT NULL DEFAULT 0,
    result ENUM('Passed', 'Failed', 'Conditionally Approved') NOT NULL,
    remarks TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fabric_inspection_lot (fabric_lot_id),
    INDEX idx_fabric_inspection_result (result),
    INDEX idx_fabric_inspection_date (inspection_date),
    CONSTRAINT fk_fabric_inspection_lot FOREIGN KEY (fabric_lot_id)
        REFERENCES fabric_lots (fabric_lot_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_fabric_inspection_user FOREIGN KEY (inspector_id)
        REFERENCES users (user_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_fabric_inspected_quantity CHECK (inspected_quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
