-- =============================================================================
-- ThreadFlow - Bottleneck Monitoring View & Demonstration Data (Module 3)
-- Assigned to: Member 3 (Production Manager / Admin)
-- Feature 3: Bottleneck Monitoring
--
-- MariaDB / MySQL 5.7+ / 8.x compatible
-- Repeat-safe & non-destructive migration script
--
-- Manual Import Instructions:
--   Option A (phpMyAdmin):
--     1. Open http://localhost/phpmyadmin and select 'threadflow_db'.
--     2. Click 'Import', choose sql/bottleneck_monitoring.sql, and click 'Import'.
--   Option B (MySQL CLI):
--     mysql -u root -p threadflow_db < sql/bottleneck_monitoring.sql
-- =============================================================================

-- =============================================================================
-- 1. Repeat-Safe SQL View: view_bottleneck_monitoring
--
-- Business Rules:
--   1. Active Overdue: stage_progress.status IN ('In Progress', 'On Hold')
--      AND CURRENT_DATE() > production_batches.target_end_date.
--   2. Historical Delay: stage_progress.status = 'Completed'
--      AND end_time IS NOT NULL AND DATE(end_time) > production_batches.target_end_date.
--   3. Due Today: stage_progress.status IN ('In Progress', 'On Hold')
--      AND CURRENT_DATE() = production_batches.target_end_date (NOT a bottleneck).
--   4. On Schedule: completed on/before target date OR active with target date in the future.
--   5. delay_days: DATEDIFF against target_end_date for bottlenecks, strictly 0 for non-bottlenecks.
--
-- Validity & Integrity Filter (Explicit Exclusions):
--   - Excludes invalid or zero dates in batch start_date, target_end_date, or stage start_time.
--   - Excludes illogical batch schedule: pb.start_date > pb.target_end_date.
--   - Excludes chronological paradox: sp.end_time < sp.start_time.
--   - Excludes Completed stages missing an end_time.
--   - Excludes process quantity violations: input_quantity <= 0 OR output_quantity > input_quantity.
--   - Excludes state contradiction: Completed batch containing an In Progress or On Hold stage.
-- =============================================================================

CREATE OR REPLACE VIEW view_bottleneck_monitoring AS
SELECT 
    sp.progress_id,
    sp.batch_id,
    sp.stage_id,
    pb.order_id,
    pb.line_id,
    bo.buyer_id,
    pb.batch_number,
    pb.planned_quantity,
    pb.status AS batch_status,
    pb.start_date AS batch_start_date,
    pb.target_end_date,
    bo.order_number,
    bo.style_number,
    b.buyer_name,
    sl.line_name,
    sl.line_code,
    ps.stage_name,
    ps.stage_code,
    ps.sequence_number,
    sp.input_quantity,
    sp.output_quantity,
    sp.status AS stage_status,
    sp.start_time,
    sp.end_time,
    sp.remarks,
    sp.created_at,
    sp.updated_at,
    CASE 
        WHEN sp.end_time IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, sp.start_time, sp.end_time)
        ELSE TIMESTAMPDIFF(MINUTE, sp.start_time, NOW())
    END AS elapsed_minutes,
    CONVERT((CASE
        WHEN sp.status = 'On Hold' AND CURRENT_DATE() > pb.target_end_date 
            THEN 'On Hold Overdue'
        WHEN sp.status = 'In Progress' AND CURRENT_DATE() > pb.target_end_date 
            THEN 'In Progress Overdue'
        WHEN sp.status = 'Completed' AND sp.end_time IS NOT NULL AND DATE(sp.end_time) > pb.target_end_date 
            THEN 'Historical Delay'
        WHEN sp.status IN ('In Progress', 'On Hold') AND CURRENT_DATE() = pb.target_end_date 
            THEN 'Due Today'
        ELSE 'On Schedule'
    END) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS classification,
    CASE
        WHEN sp.status IN ('In Progress', 'On Hold') AND CURRENT_DATE() > pb.target_end_date 
            THEN DATEDIFF(CURRENT_DATE(), pb.target_end_date)
        WHEN sp.status = 'Completed' AND sp.end_time IS NOT NULL AND DATE(sp.end_time) > pb.target_end_date 
            THEN DATEDIFF(DATE(sp.end_time), pb.target_end_date)
        ELSE 0
    END AS delay_days,
    CASE
        WHEN (sp.status IN ('In Progress', 'On Hold') AND CURRENT_DATE() > pb.target_end_date)
          OR (sp.status = 'Completed' AND sp.end_time IS NOT NULL AND DATE(sp.end_time) > pb.target_end_date)
            THEN 1
        ELSE 0
    END AS is_bottleneck,
    CONVERT((CASE
        WHEN sp.status = 'On Hold' AND CURRENT_DATE() > pb.target_end_date THEN
            CONCAT('Stage is paused on hold and has exceeded the batch target end date by ', DATEDIFF(CURRENT_DATE(), pb.target_end_date), ' day(s).')
        WHEN sp.status = 'In Progress' AND CURRENT_DATE() > pb.target_end_date THEN
            CONCAT('Stage remains in progress past the batch target end date by ', DATEDIFF(CURRENT_DATE(), pb.target_end_date), ' day(s).')
        WHEN sp.status = 'Completed' AND sp.end_time IS NOT NULL AND DATE(sp.end_time) > pb.target_end_date THEN
            CONCAT('Stage completed late; ended ', DATEDIFF(DATE(sp.end_time), pb.target_end_date), ' day(s) after the batch target end date.')
        WHEN sp.status IN ('In Progress', 'On Hold') AND CURRENT_DATE() = pb.target_end_date THEN
            'Stage is actively running on its target end date boundary (due today).'
        ELSE
            'Stage timeline is within scheduled batch target end date.'
    END) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS bottleneck_reason
FROM stage_progress sp
INNER JOIN production_batches pb ON sp.batch_id = pb.batch_id
INNER JOIN production_stages ps ON sp.stage_id = ps.stage_id
LEFT JOIN sewing_lines sl ON pb.line_id = sl.line_id
INNER JOIN buyer_orders bo ON pb.order_id = bo.order_id
INNER JOIN buyers b ON bo.buyer_id = b.buyer_id
WHERE pb.target_end_date IS NOT NULL 
  AND pb.target_end_date <> '0000-00-00'
  AND pb.start_date IS NOT NULL 
  AND pb.start_date <> '0000-00-00'
  AND pb.start_date <= pb.target_end_date
  AND sp.start_time IS NOT NULL 
  AND sp.start_time <> '0000-00-00 00:00:00'
  AND (sp.status <> 'Completed' OR (sp.end_time IS NOT NULL AND sp.end_time >= sp.start_time))
  AND (sp.end_time IS NULL OR sp.end_time >= sp.start_time)
  AND sp.input_quantity > 0
  AND sp.output_quantity <= sp.input_quantity
  AND NOT (pb.status = 'Completed' AND sp.status IN ('In Progress', 'On Hold'));

-- =============================================================================
-- 2. Idempotent Demonstration Data
--
-- Covers all required test scenarios respecting stage sequences:
--   Scenario 1: Overdue In Progress Stage (BATCH-2026-010)
--   Scenario 2: Overdue On Hold Stage (BATCH-2026-011)
--   Scenario 3: Historical Delay Completed Late (BATCH-2026-012)
--   Scenario 4: Due Today Active Boundary - NOT a bottleneck (BATCH-2026-013)
--   Scenario 5: Completed On Time Target Date Boundary - NOT a bottleneck (BATCH-2026-014)
-- =============================================================================

-- Ensure foundational dependency sewing lines exist
INSERT IGNORE INTO sewing_lines (line_name, line_code, daily_capacity, is_active) VALUES
('Line A - Tops & Shirts', 'LINE-A', 1200, 1),
('Line B - Bottoms & Trousers', 'LINE-B', 800, 1),
('Line C - Outerwear & Jackets', 'LINE-C', 500, 1);

-- Ensure foundational dependency buyer exists
INSERT INTO buyers (buyer_name, contact_person, email, phone, country, address, is_active)
SELECT 'Aurora Apparel Global', 'Eleanor Vance', 'orders@auroraapparel.com', '+1-555-0182', 'United Kingdom', '100 Fashion Way, London', 1
WHERE NOT EXISTS (SELECT 1 FROM buyers WHERE email = 'orders@auroraapparel.com');

-- Ensure foundational dependency orders exist
INSERT IGNORE INTO buyer_orders (buyer_id, order_number, style_number, total_quantity, order_date, delivery_date, status)
SELECT b.buyer_id, 'ORD-2026-001', 'STY-DENIM-JACKET', 2500, '2026-09-01', '2026-10-15', 'In Production'
FROM buyers b WHERE b.email = 'orders@auroraapparel.com' LIMIT 1;

INSERT IGNORE INTO buyer_orders (buyer_id, order_number, style_number, total_quantity, order_date, delivery_date, status)
SELECT b.buyer_id, 'ORD-2026-002', 'STY-OXFORD-SHIRT', 5000, '2026-09-05', '2026-10-30', 'Confirmed'
FROM buyers b WHERE b.email = 'orders@auroraapparel.com' LIMIT 1;

INSERT IGNORE INTO buyer_orders (buyer_id, order_number, style_number, total_quantity, order_date, delivery_date, status)
SELECT b.buyer_id, 'ORD-2026-003', 'STY-CHINO-PANTS', 3200, '2026-09-10', '2026-11-15', 'Confirmed'
FROM buyers b WHERE b.email = 'orders@auroraapparel.com' LIMIT 1;

-- -----------------------------------------------------------------------------
-- Demo Batch 1: BATCH-2026-010 (Scenario 1: Overdue In Progress Stage)
-- Target end date is in the past; Cutting completed, Sewing is In Progress past target.
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO production_batches (batch_number, order_id, line_id, planned_quantity, status, start_date, target_end_date, notes)
SELECT 
    'BATCH-2026-010',
    bo.order_id,
    sl.line_id,
    1000,
    'In Progress',
    '2026-09-01',
    '2026-09-15',
    'Demo batch for overdue In Progress bottleneck monitoring.'
FROM buyer_orders bo
LEFT JOIN sewing_lines sl ON sl.line_code = 'LINE-C'
WHERE bo.order_number = 'ORD-2026-001'
LIMIT 1;

-- Prerequisite Stage 1: Cutting (Completed on time)
INSERT IGNORE INTO stage_progress (batch_id, stage_id, input_quantity, output_quantity, start_time, end_time, status, remarks)
SELECT 
    pb.batch_id,
    ps.stage_id,
    1000,
    980,
    '2026-09-02 08:00:00',
    '2026-09-04 17:00:00',
    'Completed',
    'Cutting completed within schedule.'
FROM production_batches pb
JOIN production_stages ps ON ps.stage_code = 'CUTTING'
WHERE pb.batch_number = 'BATCH-2026-010'
LIMIT 1;

-- Active Stage 2: Sewing (In Progress Overdue past target 2026-09-15)
INSERT IGNORE INTO stage_progress (batch_id, stage_id, input_quantity, output_quantity, start_time, end_time, status, remarks)
SELECT 
    pb.batch_id,
    ps.stage_id,
    980,
    520,
    '2026-09-05 08:30:00',
    NULL,
    'In Progress',
    'Front panel stitching delayed by component shortage; operating past target end date.'
FROM production_batches pb
JOIN production_stages ps ON ps.stage_code = 'SEWING'
WHERE pb.batch_number = 'BATCH-2026-010'
LIMIT 1;

-- -----------------------------------------------------------------------------
-- Demo Batch 2: BATCH-2026-011 (Scenario 2: Overdue On Hold Stage)
-- Target end date is in the past; Cutting completed, Sewing is On Hold past target.
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO production_batches (batch_number, order_id, line_id, planned_quantity, status, start_date, target_end_date, notes)
SELECT 
    'BATCH-2026-011',
    bo.order_id,
    sl.line_id,
    1500,
    'On Hold',
    '2026-09-05',
    '2026-09-18',
    'Demo batch for overdue On Hold bottleneck monitoring.'
FROM buyer_orders bo
LEFT JOIN sewing_lines sl ON sl.line_code = 'LINE-B'
WHERE bo.order_number = 'ORD-2026-002'
LIMIT 1;

-- Prerequisite Stage 1: Cutting (Completed on time)
INSERT IGNORE INTO stage_progress (batch_id, stage_id, input_quantity, output_quantity, start_time, end_time, status, remarks)
SELECT 
    pb.batch_id,
    ps.stage_id,
    1500,
    1470,
    '2026-09-06 08:00:00',
    '2026-09-08 17:00:00',
    'Completed',
    'Precision pattern cut completed.'
FROM production_batches pb
JOIN production_stages ps ON ps.stage_code = 'CUTTING'
WHERE pb.batch_number = 'BATCH-2026-011'
LIMIT 1;

-- Active Stage 2: Sewing (On Hold Overdue past target 2026-09-18)
INSERT IGNORE INTO stage_progress (batch_id, stage_id, input_quantity, output_quantity, start_time, end_time, status, remarks)
SELECT 
    pb.batch_id,
    ps.stage_id,
    1470,
    310,
    '2026-09-09 08:00:00',
    NULL,
    'On Hold',
    'Machine feed breakdown on automated hemmer; stage paused pending technician parts.'
FROM production_batches pb
JOIN production_stages ps ON ps.stage_code = 'SEWING'
WHERE pb.batch_number = 'BATCH-2026-011'
LIMIT 1;

-- -----------------------------------------------------------------------------
-- Demo Batch 3: BATCH-2026-012 (Scenario 3: Historical Delay / Completed Late)
-- Target end date was 2026-09-16; Cutting stage finished on 2026-09-19 (3 days late).
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO production_batches (batch_number, order_id, line_id, planned_quantity, status, start_date, target_end_date, notes)
SELECT 
    'BATCH-2026-012',
    bo.order_id,
    sl.line_id,
    2000,
    'In Progress',
    '2026-09-01',
    '2026-09-16',
    'Demo batch for historical late completion bottleneck monitoring.'
FROM buyer_orders bo
LEFT JOIN sewing_lines sl ON sl.line_code = 'LINE-A'
WHERE bo.order_number = 'ORD-2026-003'
LIMIT 1;

-- Stage 1: Cutting (Completed on 2026-09-19, strictly AFTER target_end_date 2026-09-16)
INSERT IGNORE INTO stage_progress (batch_id, stage_id, input_quantity, output_quantity, start_time, end_time, status, remarks)
SELECT 
    pb.batch_id,
    ps.stage_id,
    2000,
    1950,
    '2026-09-02 08:00:00',
    '2026-09-19 16:30:00',
    'Completed',
    'Fabric roll tension defects required manual recutting, pushing completion 3 days late.'
FROM production_batches pb
JOIN production_stages ps ON ps.stage_code = 'CUTTING'
WHERE pb.batch_number = 'BATCH-2026-012'
LIMIT 1;

-- -----------------------------------------------------------------------------
-- Demo Batch 4: BATCH-2026-013 (Scenario 4: Due Today Active Boundary)
-- Target end date is CURRENT_DATE(); active In Progress stage must NOT be marked overdue.
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO production_batches (batch_number, order_id, line_id, planned_quantity, status, start_date, target_end_date, notes)
SELECT 
    'BATCH-2026-013',
    bo.order_id,
    NULL,
    800,
    'In Progress',
    DATE_SUB(CURRENT_DATE(), INTERVAL 4 DAY),
    CURRENT_DATE(),
    'Demo batch testing exact target date boundary (unassigned sewing line, due today).'
FROM buyer_orders bo
WHERE bo.order_number = 'ORD-2026-001'
LIMIT 1;

-- Prerequisite Stage 1: Cutting (Completed)
INSERT IGNORE INTO stage_progress (batch_id, stage_id, input_quantity, output_quantity, start_time, end_time, status, remarks)
SELECT 
    pb.batch_id,
    ps.stage_id,
    800,
    780,
    DATE_SUB(NOW(), INTERVAL 72 HOUR),
    DATE_SUB(NOW(), INTERVAL 36 HOUR),
    'Completed',
    'Cutting completed smoothly.'
FROM production_batches pb
JOIN production_stages ps ON ps.stage_code = 'CUTTING'
WHERE pb.batch_number = 'BATCH-2026-013'
LIMIT 1;

-- Active Stage 2: Sewing (Running on exact due date boundary; must classify as Due Today, NOT overdue)
INSERT IGNORE INTO stage_progress (batch_id, stage_id, input_quantity, output_quantity, start_time, end_time, status, remarks)
SELECT 
    pb.batch_id,
    ps.stage_id,
    780,
    410,
    DATE_SUB(NOW(), INTERVAL 12 HOUR),
    NULL,
    'In Progress',
    'Sewing run proceeding as scheduled on the deadline day.'
FROM production_batches pb
JOIN production_stages ps ON ps.stage_code = 'SEWING'
WHERE pb.batch_number = 'BATCH-2026-013'
LIMIT 1;

-- -----------------------------------------------------------------------------
-- Demo Batch 5: BATCH-2026-014 (Scenario 5: Completed On Time on Target Date Boundary)
-- Stage completed on target date boundary (DATE(end_time) = target_end_date).
-- Must classify as On Schedule with delay_days = 0, NOT a bottleneck.
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO production_batches (batch_number, order_id, line_id, planned_quantity, status, start_date, target_end_date, notes)
SELECT 
    'BATCH-2026-014',
    bo.order_id,
    sl.line_id,
    600,
    'In Progress',
    DATE_SUB(CURRENT_DATE(), INTERVAL 5 DAY),
    DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY),
    'Demo batch testing on-time completion on boundary day.'
FROM buyer_orders bo
LEFT JOIN sewing_lines sl ON sl.line_code = 'LINE-A'
WHERE bo.order_number = 'ORD-2026-002'
LIMIT 1;

INSERT IGNORE INTO stage_progress (batch_id, stage_id, input_quantity, output_quantity, start_time, end_time, status, remarks)
SELECT 
    pb.batch_id,
    ps.stage_id,
    600,
    590,
    TIMESTAMP(DATE_SUB(CURRENT_DATE(), INTERVAL 3 DAY), '08:00:00'),
    TIMESTAMP(DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY), '21:45:00'),
    'Completed',
    'Final QA wrap-up completed late evening on deadline date; verified on-time.'
FROM production_batches pb
JOIN production_stages ps ON ps.stage_code = 'CUTTING'
WHERE pb.batch_number = 'BATCH-2026-014'
LIMIT 1;

-- =============================================================================
-- 3. Documented Test Cases for Excluded Invalid/Inconsistent Records
--
-- The following conditions are strictly rejected by view_bottleneck_monitoring
-- via its WHERE integrity filter and will NEVER appear in monitoring outputs:
--
-- 1. Invalid or zero dates:
--    WHERE pb.target_end_date IS NOT NULL AND pb.target_end_date <> '0000-00-00'
--    AND pb.start_date IS NOT NULL AND pb.start_date <> '0000-00-00'
--    AND sp.start_time IS NOT NULL AND sp.start_time <> '0000-00-00 00:00:00'
--
-- 2. Inverted batch schedule:
--    WHERE pb.start_date <= pb.target_end_date
--    (e.g., pb.start_date = '2026-10-15' AND pb.target_end_date = '2026-10-01' is EXCLUDED)
--
-- 3. Negative stage duration:
--    WHERE (sp.end_time IS NULL OR sp.end_time >= sp.start_time)
--    (e.g., start_time = '2026-09-20 10:00:00' AND end_time = '2026-09-19 10:00:00' is EXCLUDED)
--
-- 4. Completed stage missing end_time:
--    WHERE (sp.status <> 'Completed' OR (sp.end_time IS NOT NULL AND sp.end_time >= sp.start_time))
--    (e.g., status = 'Completed' with end_time IS NULL is EXCLUDED)
--
-- 5. Non-positive input quantity:
--    WHERE sp.input_quantity > 0
--    (e.g., input_quantity = 0 is EXCLUDED)
--
-- 6. Output exceeding input quantity:
--    WHERE sp.output_quantity <= sp.input_quantity
--    (e.g., input_quantity = 500 AND output_quantity = 600 is EXCLUDED)
--
-- 7. Completed batch containing uncompleted stage:
--    WHERE NOT (pb.status = 'Completed' AND sp.status IN ('In Progress', 'On Hold'))
--    (e.g., batch status = 'Completed' but child stage = 'In Progress' is EXCLUDED)
-- =============================================================================
