-- =============================================================================
-- ThreadFlow - Operational Dashboard Demonstration Data (Module 3)
-- Assigned to: Member 3 (Production Manager / Admin / Management)
-- Feature 4: Operational Dashboard
--
-- MariaDB / MySQL 5.7+ / 8.x compatible
-- Additive, repeat-safe integration demonstration script.
--
-- Manual Import Instructions:
--   Option A (phpMyAdmin):
--     1. Open http://localhost/phpmyadmin and select 'threadflow_db'.
--     2. Click 'Import', choose sql/operational_dashboard.sql, and click 'Import'.
--   Option B (MySQL CLI):
--     mysql -u root -p threadflow_db < sql/operational_dashboard.sql
-- =============================================================================

-- =============================================================================
-- 1. Prerequisite Seed Data: Defect Types (Idempotent)
-- Ensures standard defect taxonomy exists so inspection defects can cleanly reference defect_id.
-- =============================================================================

INSERT INTO defect_types (defect_code, defect_name, category, severity, description, is_active)
SELECT 'DEF-STITCH-001', 'Broken Stitch', 'Sewing', 'major', 'Broken thread along primary load-bearing seam.', 1
WHERE NOT EXISTS (SELECT 1 FROM defect_types WHERE defect_code = 'DEF-STITCH-001');

INSERT INTO defect_types (defect_code, defect_name, category, severity, description, is_active)
SELECT 'DEF-SEAM-002', 'Open Seam / Puckering', 'Sewing', 'critical', 'Incomplete fabric joining creating visible structural gap.', 1
WHERE NOT EXISTS (SELECT 1 FROM defect_types WHERE defect_code = 'DEF-SEAM-002');

INSERT INTO defect_types (defect_code, defect_name, category, severity, description, is_active)
SELECT 'DEF-STAIN-003', 'Machine Oil Stain', 'Finishing', 'minor', 'Surface lubricant residue removable via solvent wash.', 1
WHERE NOT EXISTS (SELECT 1 FROM defect_types WHERE defect_code = 'DEF-STAIN-003');

INSERT INTO defect_types (defect_code, defect_name, category, severity, description, is_active)
SELECT 'DEF-MEAS-004', 'Out of Spec Dimension', 'Cutting', 'major', 'Garment measurements exceed standard tolerance chart.', 1
WHERE NOT EXISTS (SELECT 1 FROM defect_types WHERE defect_code = 'DEF-MEAS-004');

-- =============================================================================
-- 2. Demonstration Journey: Quality Inspections
--
-- Demonstrates:
--   - QC-2026-001: Passed Final Pre-Shipment inspection on BATCH-2026-001 (ORD-2026-001) -> Ready for Packing.
--   - QC-2026-002: Failed End of Line inspection on BATCH-2026-010 (ORD-2026-001) with 25 defects -> Leads to Rework.
--   - QC-2026-003: Pending Review Inline inspection on BATCH-2026-011 (ORD-2026-002) with 15 defects -> Blocks Order 2.
--   - QC-2026-004: Passed Final Pre-Shipment inspection on BATCH-2026-013 (ORD-2026-001).
-- =============================================================================

-- QC-2026-001: Passed Final Pre-Shipment Inspection (BATCH-2026-001)
INSERT INTO quality_inspections (
    inspection_number, batch_id, inspector_id, inspection_date, inspection_stage,
    inspected_quantity, passed_quantity, defective_quantity, overall_result, remarks
)
SELECT 
    'QC-2026-001',
    pb.batch_id,
    (SELECT user_id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY user_id ASC LIMIT 1),
    '2026-09-21',
    'Final Pre-Shipment',
    500,
    500,
    0,
    'Passed',
    'AQL 1.5 audit passed with zero critical or major defects. Lot approved for export carton packing.'
FROM production_batches pb
WHERE pb.batch_number = 'BATCH-2026-001'
  AND NOT EXISTS (SELECT 1 FROM quality_inspections WHERE inspection_number = 'QC-2026-001');

-- QC-2026-002: Failed End of Line Inspection (BATCH-2026-010)
INSERT INTO quality_inspections (
    inspection_number, batch_id, inspector_id, inspection_date, inspection_stage,
    inspected_quantity, passed_quantity, defective_quantity, overall_result, remarks
)
SELECT 
    'QC-2026-002',
    pb.batch_id,
    (SELECT user_id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY user_id ASC LIMIT 1),
    '2026-09-18',
    'End of Line',
    400,
    375,
    25,
    'Failed',
    'Excessive seam slippage and thread breaks detected at final sewing output.'
FROM production_batches pb
WHERE pb.batch_number = 'BATCH-2026-010'
  AND NOT EXISTS (SELECT 1 FROM quality_inspections WHERE inspection_number = 'QC-2026-002');

-- QC-2026-003: Pending Review Inline Inspection (BATCH-2026-011 on ORD-2026-002)
INSERT INTO quality_inspections (
    inspection_number, batch_id, inspector_id, inspection_date, inspection_stage,
    inspected_quantity, passed_quantity, defective_quantity, overall_result, remarks
)
SELECT 
    'QC-2026-003',
    pb.batch_id,
    (SELECT user_id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY user_id ASC LIMIT 1),
    '2026-09-19',
    'Inline',
    300,
    285,
    15,
    'Pending Review',
    'Collar interlining alignment issues flagged during mid-line audit; awaiting management disposition.'
FROM production_batches pb
WHERE pb.batch_number = 'BATCH-2026-011'
  AND NOT EXISTS (SELECT 1 FROM quality_inspections WHERE inspection_number = 'QC-2026-003');

-- QC-2026-004: Passed Final Pre-Shipment Inspection (BATCH-2026-013)
INSERT INTO quality_inspections (
    inspection_number, batch_id, inspector_id, inspection_date, inspection_stage,
    inspected_quantity, passed_quantity, defective_quantity, overall_result, remarks
)
SELECT 
    'QC-2026-004',
    pb.batch_id,
    (SELECT user_id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY user_id ASC LIMIT 1),
    '2026-09-24',
    'Final Pre-Shipment',
    400,
    400,
    0,
    'Passed',
    'Final carton sample inspection verified 100% compliant with buyer packaging specifications.'
FROM production_batches pb
WHERE pb.batch_number = 'BATCH-2026-013'
  AND NOT EXISTS (SELECT 1 FROM quality_inspections WHERE inspection_number = 'QC-2026-004');

-- =============================================================================
-- 3. Inspection Defects Breakdown
-- Links specific defect types to the defective quantities in quality_inspections.
-- =============================================================================

-- Defects for QC-2026-002 (25 total defects = 18 broken stitch + 7 open seam)
INSERT INTO inspection_defects (inspection_id, defect_id, defect_count, notes)
SELECT 
    qi.inspection_id,
    dt.defect_id,
    18,
    'Broken thread along side-seams caused by incorrect needle gauge.'
FROM quality_inspections qi
JOIN defect_types dt ON dt.defect_code = 'DEF-STITCH-001'
WHERE qi.inspection_number = 'QC-2026-002'
  AND NOT EXISTS (
      SELECT 1 FROM inspection_defects id2 
      WHERE id2.inspection_id = qi.inspection_id AND id2.defect_id = dt.defect_id
  );

INSERT INTO inspection_defects (inspection_id, defect_id, defect_count, notes)
SELECT 
    qi.inspection_id,
    dt.defect_id,
    7,
    'Open armhole seam due to operator tension slippage.'
FROM quality_inspections qi
JOIN defect_types dt ON dt.defect_code = 'DEF-SEAM-002'
WHERE qi.inspection_number = 'QC-2026-002'
  AND NOT EXISTS (
      SELECT 1 FROM inspection_defects id2 
      WHERE id2.inspection_id = qi.inspection_id AND id2.defect_id = dt.defect_id
  );

-- Defects for QC-2026-003 (15 total defects = 15 broken stitch)
INSERT INTO inspection_defects (inspection_id, defect_id, defect_count, notes)
SELECT 
    qi.inspection_id,
    dt.defect_id,
    15,
    'Collar top-stitching thread unraveling.'
FROM quality_inspections qi
JOIN defect_types dt ON dt.defect_code = 'DEF-STITCH-001'
WHERE qi.inspection_number = 'QC-2026-003'
  AND NOT EXISTS (
      SELECT 1 FROM inspection_defects id2 
      WHERE id2.inspection_id = qi.inspection_id AND id2.defect_id = dt.defect_id
  );

-- =============================================================================
-- 4. Demonstration Journey: Rework Jobs
--
-- Demonstrates:
--   - RWK-2026-001: Completed rework job (18 allocated, 16 completed, 2 rejected) on BATCH-2026-010.
--   - RWK-2026-002: Open / In Progress rework job (15 allocated, 0 completed) on BATCH-2026-011 -> Blocks Order 2.
-- =============================================================================

-- RWK-2026-001: Completed Rework (from QC-2026-002 Broken Stitch)
INSERT INTO rework_jobs (
    rework_number, inspection_defect_id, line_id, assigned_to, rework_quantity,
    assigned_date, target_completion_date, actual_completion_date,
    completed_quantity, rejected_quantity, status, corrective_action, remarks,
    created_by, updated_by
)
SELECT 
    'RWK-2026-001',
    id.inspection_defect_id,
    sl.line_id,
    'Tariqul Islam (Rework Specialist)',
    18,
    '2026-09-19',
    '2026-09-21',
    '2026-09-21',
    16,
    2,
    'Completed',
    'Needle replaced; thread tension readjusted; 16 pieces successfully resewn, 2 pieces scrapped.',
    'Rework verified and cleared by QC lead.',
    (SELECT user_id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY user_id ASC LIMIT 1),
    (SELECT user_id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY user_id ASC LIMIT 1)
FROM inspection_defects id
JOIN quality_inspections qi ON id.inspection_id = qi.inspection_id
JOIN defect_types dt ON id.defect_id = dt.defect_id
LEFT JOIN sewing_lines sl ON sl.line_code = 'LINE-C'
WHERE qi.inspection_number = 'QC-2026-002' 
  AND dt.defect_code = 'DEF-STITCH-001'
  AND NOT EXISTS (SELECT 1 FROM rework_jobs WHERE rework_number = 'RWK-2026-001');

-- RWK-2026-002: In Progress Rework (from QC-2026-003 Broken Stitch on ORD-2026-002)
INSERT INTO rework_jobs (
    rework_number, inspection_defect_id, line_id, assigned_to, rework_quantity,
    assigned_date, target_completion_date, actual_completion_date,
    completed_quantity, rejected_quantity, status, corrective_action, remarks,
    created_by, updated_by
)
SELECT 
    'RWK-2026-002',
    id.inspection_defect_id,
    sl.line_id,
    'Sultana Razia (Line B Lead)',
    15,
    '2026-09-20',
    '2026-09-25',
    NULL,
    0,
    0,
    'In Progress',
    'Unpicking defective collar stitching and re-aligning with replacement thread.',
    'Under active rework on secondary repair station.',
    (SELECT user_id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY user_id ASC LIMIT 1),
    NULL
FROM inspection_defects id
JOIN quality_inspections qi ON id.inspection_id = qi.inspection_id
JOIN defect_types dt ON id.defect_id = dt.defect_id
LEFT JOIN sewing_lines sl ON sl.line_code = 'LINE-B'
WHERE qi.inspection_number = 'QC-2026-003' 
  AND dt.defect_code = 'DEF-STITCH-001'
  AND NOT EXISTS (SELECT 1 FROM rework_jobs WHERE rework_number = 'RWK-2026-002');

-- =============================================================================
-- 5. Demonstration Journey: Packing & Shipment Outbound
--
-- Demonstrates:
--   - SHIP-2026-001: Packed shipment for BATCH-2026-001 (passed Final Pre-Shipment QC,
--     no open rework on this batch, Packed status) -> Demonstrates Ready to Ship!
-- =============================================================================

-- SHIP-2026-001: Ready to Ship (Status: Packed)
INSERT INTO packing_shipments (
    packing_number, batch_id, inspection_id, packed_quantity, carton_count,
    packing_date, shipping_date, shipping_method, carrier_name, tracking_number,
    destination, status, notes, created_by, updated_by
)
SELECT 
    'SHIP-2026-001',
    pb.batch_id,
    qi.inspection_id,
    500,
    25,
    '2026-09-22',
    NULL,
    'Sea',
    'Maersk Line',
    'MSK-7891234',
    'London Gateway Port, Tilbury, United Kingdom',
    'Packed',
    'Cartons sealed and staged in export warehouse staging bay A-4. All barcode labels verified.',
    (SELECT user_id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY user_id ASC LIMIT 1),
    NULL
FROM production_batches pb
JOIN quality_inspections qi ON qi.inspection_number = 'QC-2026-001'
WHERE pb.batch_number = 'BATCH-2026-001'
  AND NOT EXISTS (SELECT 1 FROM packing_shipments WHERE packing_number = 'SHIP-2026-001');

-- =============================================================================
-- Summary of Demonstration Data Outcomes for Operational Dashboard:
--
-- 1. ORD-2026-001 (STY-DENIM-JACKET, Aurora Apparel):
--    - Batches: BATCH-2026-001, BATCH-2026-010, BATCH-2026-013.
--    - Downstream Data: Passed Final Pre-Shipment QC (QC-2026-001), completed rework
--      (RWK-2026-001), zero open rework, and non-cancelled Packed record (SHIP-2026-001).
--    - Demonstrates: Shipment Readiness = 'Ready to Ship'.
--
-- 2. ORD-2026-002 (STY-OXFORD-SHIRT, Aurora Apparel):
--    - Batches: BATCH-2026-002, BATCH-2026-011, BATCH-2026-014.
--    - Downstream Data: QC-2026-003 'Pending Review', open rework job RWK-2026-002 'In Progress'.
--    - Demonstrates: Shipment Readiness = 'Blocked' (due to open rework and pending inspection).
--
-- 3. ORD-2026-003 (STY-CHINO-PANTS, Aurora Apparel):
--    - Batches: BATCH-2026-003, BATCH-2026-012.
--    - Downstream Data: Zero inspections, zero rework, zero shipments.
--    - Demonstrates: Clean handling of missing downstream data -> Shipment Readiness = 'Awaiting QC/Packing'.
-- =============================================================================
