-- ThreadFlow - Buyer Change Requests (Member 1 workflow)
-- Additive schema. Import this file after auth.sql, orders.sql, and production_batches.sql.

CREATE TABLE IF NOT EXISTS buyer_change_requests (
    request_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    requester_id INT UNSIGNED NOT NULL,
    change_type ENUM('Quantity', 'Style', 'Delivery Date', 'Color', 'Other') NOT NULL,
    current_value VARCHAR(500) NOT NULL,
    requested_value VARCHAR(500) NOT NULL,
    reason_details TEXT NOT NULL,
    affected_quantity INT UNSIGNED NULL,
    impact_level ENUM('Low', 'Medium', 'High', 'Critical') NOT NULL,
    recommended_action VARCHAR(1000) NOT NULL,
    status ENUM('Submitted', 'Approved', 'Rejected') NOT NULL DEFAULT 'Submitted',
    review_notes TEXT NULL,
    reviewed_by INT UNSIGNED NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_bcr_order (order_id),
    INDEX idx_bcr_requester (requester_id),
    INDEX idx_bcr_status (status),
    INDEX idx_bcr_impact (impact_level),
    INDEX idx_bcr_requested_at (requested_at),
    CONSTRAINT fk_bcr_order FOREIGN KEY (order_id)
        REFERENCES buyer_orders (order_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_bcr_requester FOREIGN KEY (requester_id)
        REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_bcr_reviewer FOREIGN KEY (reviewed_by)
        REFERENCES users (user_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
