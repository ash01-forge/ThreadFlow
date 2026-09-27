-- ThreadFlow - Fabric Allocation and Traceability (Member 2, Feature 4)
-- Additive schema only. Import manually after fabric_lots and production_batches exist.

CREATE TABLE IF NOT EXISTS fabric_allocations (
    fabric_allocation_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fabric_lot_id INT UNSIGNED NOT NULL,
    batch_id INT UNSIGNED NOT NULL,
    allocated_by INT UNSIGNED NOT NULL,
    allocated_quantity DECIMAL(10,2) NOT NULL,
    allocated_date DATE NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fabric_allocation_lot (fabric_lot_id),
    INDEX idx_fabric_allocation_batch (batch_id),
    INDEX idx_fabric_allocation_date (allocated_date),
    CONSTRAINT fk_fabric_allocation_lot FOREIGN KEY (fabric_lot_id)
        REFERENCES fabric_lots (fabric_lot_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_fabric_allocation_batch FOREIGN KEY (batch_id)
        REFERENCES production_batches (batch_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_fabric_allocation_user FOREIGN KEY (allocated_by)
        REFERENCES users (user_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_fabric_allocated_quantity CHECK (allocated_quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
