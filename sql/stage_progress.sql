-- Member 3 Feature 2

CREATE TABLE IF NOT EXISTS production_stages (
    stage_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stage_name VARCHAR(100) NOT NULL UNIQUE,
    stage_code VARCHAR(30) NOT NULL UNIQUE,
    sequence_number INT UNSIGNED NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS stage_progress (
    progress_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id INT UNSIGNED NOT NULL,
    stage_id INT UNSIGNED NOT NULL,
    input_quantity INT UNSIGNED NOT NULL,
    output_quantity INT UNSIGNED NOT NULL DEFAULT 0,
    start_time DATETIME NOT NULL,
    end_time DATETIME NULL,
    status ENUM('In Progress', 'On Hold', 'Completed')
        NOT NULL DEFAULT 'In Progress',
    remarks TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_stage_progress_batch_stage (batch_id, stage_id),
    INDEX idx_stage_progress_batch (batch_id),
    INDEX idx_stage_progress_stage (stage_id),
    INDEX idx_stage_progress_status (status),
    INDEX idx_stage_progress_start_time (start_time),

    CONSTRAINT fk_stage_progress_batch
        FOREIGN KEY (batch_id)
        REFERENCES production_batches (batch_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_stage_progress_stage
        FOREIGN KEY (stage_id)
        REFERENCES production_stages (stage_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT chk_stage_progress_input
        CHECK (input_quantity > 0),

    CONSTRAINT chk_stage_progress_output
        CHECK (output_quantity <= input_quantity),

    CONSTRAINT chk_stage_progress_time
        CHECK (end_time IS NULL OR end_time >= start_time),

    CONSTRAINT chk_stage_progress_completed
        CHECK (status <> 'Completed' OR end_time IS NOT NULL)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;




INSERT IGNORE INTO production_stages
    (stage_name, stage_code, sequence_number, is_active)
VALUES
    ('Cutting', 'CUTTING', 1, 1),
    ('Sewing', 'SEWING', 2, 1),
    ('Finishing', 'FINISHING', 3, 1);




INSERT IGNORE INTO stage_progress
    (
        batch_id,
        stage_id,
        input_quantity,
        output_quantity,
        start_time,
        end_time,
        status,
        remarks
    )
SELECT
    pb.batch_id,
    ps.stage_id,
    1200,
    1150,
    '2026-09-10 08:00:00',
    '2026-09-11 17:00:00',
    'Completed',
    'Cutting completed with normal process loss.'
FROM production_batches pb
JOIN production_stages ps
    ON ps.stage_code = 'CUTTING'
WHERE pb.batch_number = 'BATCH-2026-001'
LIMIT 1;


INSERT IGNORE INTO stage_progress
    (
        batch_id,
        stage_id,
        input_quantity,
        output_quantity,
        start_time,
        end_time,
        status,
        remarks
    )
SELECT
    pb.batch_id,
    ps.stage_id,
    1150,
    700,
    '2026-09-12 08:00:00',
    NULL,
    'In Progress',
    'Sewing operation is currently running.'
FROM production_batches pb
JOIN production_stages ps
    ON ps.stage_code = 'SEWING'
WHERE pb.batch_number = 'BATCH-2026-001'
LIMIT 1;