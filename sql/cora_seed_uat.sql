-- CORA UAT seed: dummy companies, employees, claims and staff roles for testing.
-- LOCAL / UAT ONLY - never run on production.
-- Run after cora_master.sql and cora_seed.sql. Every insert is skipped when the
-- row already exists among active rows (company by code, employee by company +
-- IC, claim by sales order number), so the file is safe to re-run.
--
-- Staff roles: gives a CORA role to a handful of existing odb staff on the
-- local database (ids below are local staff rows; outlet roles use staff with a
-- single outlet in staff.outlet). Only staff with no CORA role are touched.
-- Undo:   UPDATE staff SET cora = 0 WHERE id IN (114977, 114976, 172, 171, 166, 177, 93);
-- Remove the dummy data:
--   DELETE FROM cora_claims    WHERE company_id IN (SELECT id FROM cora_companies WHERE code LIKE 'UAT-%');
--   DELETE FROM cora_employees WHERE company_id IN (SELECT id FROM cora_companies WHERE code LIKE 'UAT-%');
--   DELETE FROM cora_companies WHERE code LIKE 'UAT-%';

SET @now = NOW();
SET @reviewer_id = (SELECT `id` FROM `staff` WHERE `cora` = 1 AND `recycle` != 1 ORDER BY `id` LIMIT 1);
SET @reviewer_name = (SELECT `nama_staff` FROM `staff` WHERE `id` = @reviewer_id);

-- ---------------------------------------------------------------------------
-- Staff roles: 2 = Corporate Admin, 3 = Outlet PIC, 4 = Outlet User
-- ---------------------------------------------------------------------------
UPDATE `staff` SET `cora` = 2 WHERE `id` IN (114977, 114976) AND `cora` = 0;
UPDATE `staff` SET `cora` = 3 WHERE `id` IN (172, 171) AND `cora` = 0;
UPDATE `staff` SET `cora` = 4 WHERE `id` IN (166, 177, 93) AND `cora` = 0;

-- ---------------------------------------------------------------------------
-- Companies: limit_mode 1 = block, 2 = warn; status 1 = active, 2 = inactive
-- ---------------------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS `uat_companies`;
CREATE TEMPORARY TABLE `uat_companies` (
  `code` varchar(30), `name` varchar(255), `category` varchar(100),
  `default_limit` decimal(12,2), `limit_mode` tinyint unsigned,
  `period_start` date, `period_end` date, `hr_contact_name` varchar(150),
  `hr_contact_email` varchar(255), `status` tinyint unsigned, `general_remark` text
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `uat_companies` VALUES
('UAT-ACME',  'Acme Manufacturing Sdn Bhd', 'Manufacturing',  500.00, 1, '2026-01-01', '2026-12-31', 'Puan Rohana Ali',   'hr@acme.example.com',    1, 'Block mode: a claim over the balance is refused.'),
('UAT-BAYU',  'Bayu Logistics Sdn Bhd',     'Logistics',      800.00, 2, '2026-01-01', '2026-12-31', 'Encik Zulkifli',    'hr@bayu.example.com',    1, 'Warn mode: a claim over the balance goes to review.'),
('UAT-CEND',  'Cendana Healthcare Berhad',  'Healthcare',    1200.00, 1, '2026-04-01', '2027-03-31', 'Ms Grace Tan',      'people@cendana.example.com', 1, 'Financial-year period (April - March).'),
('UAT-DELTA', 'Delta Retail Sdn Bhd',       'Retail',         300.00, 1, '2025-01-01', '2025-12-31', 'Mr Ravi Chandran',  'hr@delta.example.com',   2, 'Inactive company, last year period.');

INSERT INTO `cora_companies`
  (`code`, `name`, `category`, `default_limit`, `limit_mode`, `period_start`, `period_end`,
   `benefit_criteria`, `general_remark`, `hr_contact_name`, `hr_contact_email`, `status`,
   `created_by`, `created_by_name`, `created_at`, `updated_at`)
SELECT u.`code`, u.`name`, u.`category`, u.`default_limit`, u.`limit_mode`, u.`period_start`, u.`period_end`,
       'Outpatient pharmacy purchases only. Prescription items require a valid prescription.',
       u.`general_remark`, u.`hr_contact_name`, u.`hr_contact_email`, u.`status`,
       @reviewer_id, @reviewer_name, @now, @now
FROM `uat_companies` u
WHERE NOT EXISTS (SELECT 1 FROM `cora_companies` c WHERE c.`code` = u.`code` AND c.`deleted_at` IS NULL);

-- ---------------------------------------------------------------------------
-- Employees: status 1 = active, 2 = inactive;
-- restriction_type 0 = none, 1 = limit override, 2 = suspended
-- ---------------------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS `uat_employees`;
CREATE TEMPORARY TABLE `uat_employees` (
  `company_code` varchar(30), `employee_no` varchar(50), `name` varchar(255), `ic_no` varchar(30),
  `phone` varchar(30), `department` varchar(150), `entitlement_limit` decimal(12,2),
  `start_date` date, `end_date` date, `status` tinyint unsigned, `restriction_type` tinyint unsigned,
  `override_limit` decimal(12,2), `override_until` date, `restriction_remark` text, `info_remark` text
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `uat_employees` VALUES
('UAT-ACME',  'A001', 'Ahmad Faizal bin Hassan',   '880512-14-5531', '012-345 6781', 'Production',  500.00, '2026-01-01', '2026-12-31', 1, 0, NULL, NULL, NULL, NULL),
('UAT-ACME',  'A002', 'Lim Mei Ling',              '910305-10-6624', '012-345 6782', 'Quality',     500.00, '2026-01-01', '2026-12-31', 1, 0, NULL, NULL, NULL, 'Also on Bayu Logistics (same IC, two companies).'),
('UAT-ACME',  'A003', 'Rajesh a/l Kumar',          '850921-08-5117', '012-345 6783', 'Engineering', 500.00, '2026-01-01', '2026-12-31', 1, 1, 200.00, '2026-12-31', 'Limit reduced to RM200 by HR request.', NULL),
('UAT-ACME',  'A004', 'Nurul Izzah binti Omar',    '950714-03-5248', '012-345 6784', 'HR',          500.00, '2026-01-01', '2026-12-31', 1, 2, NULL, NULL, 'Suspended pending HR review.', NULL),
('UAT-ACME',  'A005', 'Tan Wei Jie',               '870130-07-5339', '012-345 6785', 'Production',  500.00, '2026-01-01', '2026-12-31', 2, 0, NULL, NULL, NULL, 'Resigned.'),
('UAT-ACME',  'A006', 'Siti Aminah binti Yusof',   '920818-01-6450', '012-345 6786', 'Logistics',   500.00, '2026-01-01', '2026-06-30', 1, 0, NULL, NULL, NULL, 'Contract ended 30 June.'),
('UAT-BAYU',  'B001', 'Muhammad Hafiz bin Ismail', '900402-11-5061', '013-222 1001', 'Operations',  800.00, '2026-01-01', '2026-12-31', 1, 0, NULL, NULL, NULL, NULL),
('UAT-BAYU',  'B002', 'Chong Kar Wai',             '930611-14-5173', '013-222 1002', 'Fleet',       800.00, '2026-01-01', '2026-12-31', 1, 0, NULL, NULL, NULL, NULL),
('UAT-BAYU',  'B003', 'Priya a/p Subramaniam',     '890227-10-5284', '013-222 1003', 'Finance',     800.00, '2026-01-01', '2026-12-31', 1, 0, NULL, NULL, NULL, NULL),
('UAT-BAYU',  'B004', 'John Smith',                'A12345678',      '013-222 1004', 'Management',  800.00, '2026-01-01', '2026-12-31', 1, 0, NULL, NULL, NULL, 'Passport holder.'),
('UAT-BAYU',  'B005', 'Lim Mei Ling',              '910305-10-6624', '013-222 1005', 'Part-time',   800.00, '2026-01-01', '2026-12-31', 1, 0, NULL, NULL, NULL, NULL),
('UAT-CEND',  'C001', 'Dr. Farah binti Rahman',    '860919-14-5395', '017-888 3001', 'Clinical',   1200.00, '2026-04-01', '2027-03-31', 1, 0, NULL, NULL, NULL, NULL),
('UAT-CEND',  'C002', 'Wong Siew Hoon',            '940103-08-5406', '017-888 3002', 'Pharmacy',   1200.00, '2026-04-01', '2027-03-31', 1, 0, NULL, NULL, NULL, NULL),
('UAT-CEND',  'C003', 'Aiman bin Zakaria',         '970525-06-5517', '017-888 3003', 'Admin',      1200.00, '2026-04-01', '2027-03-31', 1, 0, NULL, NULL, NULL, NULL),
('UAT-CEND',  'C004', 'Kavitha a/p Raman',         '880808-05-5628', '017-888 3004', 'Nursing',    1200.00, '2026-04-01', '2027-03-31', 1, 0, NULL, NULL, NULL, NULL),
('UAT-DELTA', 'D001', 'Lee Chee Keong',            '820714-07-5739', '016-777 4001', 'Store',       300.00, '2025-01-01', '2025-12-31', 2, 0, NULL, NULL, NULL, NULL);

INSERT INTO `cora_employees`
  (`company_id`, `employee_no`, `name`, `ic_no`, `ic_norm`, `phone`, `department`, `entitlement_limit`,
   `start_date`, `end_date`, `status`, `info_remark`, `restriction_type`, `override_limit`, `override_until`,
   `restriction_remark`, `created_by`, `created_by_name`, `created_at`, `updated_at`)
SELECT c.`id`, u.`employee_no`, u.`name`, u.`ic_no`, UPPER(REGEXP_REPLACE(u.`ic_no`, '[^A-Za-z0-9]', '')),
       u.`phone`, u.`department`, u.`entitlement_limit`, u.`start_date`, u.`end_date`, u.`status`,
       u.`info_remark`, u.`restriction_type`, u.`override_limit`, u.`override_until`, u.`restriction_remark`,
       @reviewer_id, @reviewer_name, @now, @now
FROM `uat_employees` u
JOIN `cora_companies` c ON c.`code` = u.`company_code` AND c.`deleted_at` IS NULL
WHERE NOT EXISTS (
  SELECT 1 FROM `cora_employees` e
  WHERE e.`company_id` = c.`id`
    AND e.`ic_norm` = UPPER(REGEXP_REPLACE(u.`ic_no`, '[^A-Za-z0-9]', ''))
    AND e.`deleted_at` IS NULL
);

-- ---------------------------------------------------------------------------
-- Claims: status 1 draft, 2 submitted, 3 pending review, 4 approved,
-- 5 rejected, 6 cancelled, 7 amendment required;
-- payment_method 1 corporate, 2 TPA, 3 self-pay, 4 mixed
-- ---------------------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS `uat_claims`;
CREATE TEMPORARY TABLE `uat_claims` (
  `so_no` varchar(50), `company_code` varchar(30), `employee_no` varchar(50), `outlet_id` int,
  `visit_date` date, `total_amount` decimal(12,2), `claim_amount` decimal(12,2), `self_pay_amount` decimal(12,2),
  `payment_method` tinyint unsigned, `claim_type` varchar(50), `status` tinyint unsigned, `flags` json,
  `review_remark` text, `cancel_reason` text
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `uat_claims` VALUES
('SO-22-000101', 'UAT-ACME', 'A001', 22, '2026-09-02',  120.50,  120.50,   0.00, 1, 'Outpatient Pharmacy', 4, NULL, 'OK.', NULL),
('SO-22-000102', 'UAT-ACME', 'A001', 22, '2026-10-05',   85.00,   85.00,   0.00, 1, 'Prescription',        2, NULL, NULL, NULL),
('SO-41-000303', 'UAT-ACME', 'A002', 41, '2026-09-10',   95.00,   95.00,   0.00, 1, 'Supplement',          4, NULL, NULL, NULL),
('SO-24-000201', 'UAT-ACME', 'A006', 24, '2026-08-01',   65.00,   65.00,   0.00, 1, 'Outpatient Pharmacy', 3, '{"outside_period": true}', NULL, NULL),
('SO-24-000202', 'UAT-ACME', 'A003', 24, '2026-10-11',  180.00,  180.00,   0.00, 1, 'Medical Supplies',    2, NULL, NULL, NULL),
('SO-41-000301', 'UAT-BAYU', 'B001', 41, '2026-10-08',  900.00,  900.00,   0.00, 1, 'Prescription',        3, '{"exceeds_limit": true}', NULL, NULL),
('SO-41-000302', 'UAT-BAYU', 'B002', 41, '2026-09-20',   60.00,   60.00,   0.00, 1, 'Outpatient Pharmacy', 5, NULL, 'Receipt is unreadable - please resubmit with a clear copy.', NULL),
('SO-22-000103', 'UAT-BAYU', 'B004', 22, '2026-10-03',  310.00,    0.00, 310.00, 3, 'Supplement',          4, NULL, NULL, NULL),
('SO-24-000203', 'UAT-BAYU', 'B003', 24, '2026-10-09',  450.00,  300.00, 150.00, 4, 'Prescription',        2, NULL, NULL, NULL),
('SO-67-000401', 'UAT-CEND', 'C001', 67, '2026-10-12',   45.00,   45.00,   0.00, 1, 'Outpatient Pharmacy', 1, NULL, NULL, NULL),
('SO-67-000402', 'UAT-CEND', 'C002', 67, '2026-10-01',  230.00,  230.00,   0.00, 2, 'Medical Supplies',    7, NULL, 'Wrong claim type - this is a prescription item.', NULL),
('SO-77-000501', 'UAT-CEND', 'C003', 77, '2026-09-15',   75.00,   75.00,   0.00, 1, 'Supplement',          6, NULL, NULL, 'Keyed by mistake - customer paid cash.'),
('SO-77-000502', 'UAT-CEND', 'C004', 77, '2026-08-22', 1100.00, 1100.00,   0.00, 1, 'Prescription',        4, NULL, 'Approved - long-term medication.', NULL);

INSERT INTO `cora_claims`
  (`employee_id`, `company_id`, `outlet_id`, `outlet_name`, `employee_name`, `employee_ic`, `employee_no`,
   `company_name`, `visit_date`, `so_no`, `so_norm`, `total_amount`, `claim_amount`, `self_pay_amount`,
   `payment_method`, `claim_type`, `status`, `flags`, `limit_snapshot`, `available_snapshot`,
   `period_start`, `period_end`, `created_by`, `created_by_name`,
   `submitted_by`, `submitted_by_name`, `submitted_at`,
   `reviewed_by`, `reviewed_by_name`, `reviewed_at`, `review_remark`,
   `cancelled_by`, `cancelled_by_name`, `cancelled_at`, `cancel_reason`,
   `created_at`, `updated_at`)
SELECT e.`id`, c.`id`, o.`id`, o.`comp_name`, e.`name`, e.`ic_no`, e.`employee_no`,
       c.`name`, u.`visit_date`, u.`so_no`, UPPER(REGEXP_REPLACE(u.`so_no`, '[[:space:]/-]+', '')),
       u.`total_amount`, u.`claim_amount`, u.`self_pay_amount`,
       u.`payment_method`, u.`claim_type`, u.`status`, u.`flags`,
       IF(u.`status` = 1, NULL, COALESCE(e.`override_limit`, e.`entitlement_limit`)),
       IF(u.`status` = 1, NULL, COALESCE(e.`override_limit`, e.`entitlement_limit`)),
       e.`start_date`, e.`end_date`, @reviewer_id, @reviewer_name,
       IF(u.`status` = 1, NULL, @reviewer_id), IF(u.`status` = 1, NULL, @reviewer_name),
       IF(u.`status` = 1, NULL, TIMESTAMP(u.`visit_date`, '15:30:00')),
       IF(u.`status` IN (4, 5, 7), @reviewer_id, NULL), IF(u.`status` IN (4, 5, 7), @reviewer_name, NULL),
       IF(u.`status` IN (4, 5, 7), TIMESTAMP(u.`visit_date` + INTERVAL 1 DAY, '10:00:00'), NULL), u.`review_remark`,
       IF(u.`status` = 6, @reviewer_id, NULL), IF(u.`status` = 6, @reviewer_name, NULL),
       IF(u.`status` = 6, TIMESTAMP(u.`visit_date`, '17:00:00'), NULL), u.`cancel_reason`,
       TIMESTAMP(u.`visit_date`, '15:00:00'), @now
FROM `uat_claims` u
JOIN `cora_companies` c ON c.`code` = u.`company_code` AND c.`deleted_at` IS NULL
JOIN `cora_employees` e ON e.`company_id` = c.`id` AND e.`employee_no` = u.`employee_no` AND e.`deleted_at` IS NULL
JOIN `outlet` o ON o.`id` = u.`outlet_id`
WHERE NOT EXISTS (
  SELECT 1 FROM `cora_claims` x
  WHERE x.`so_norm` = UPPER(REGEXP_REPLACE(u.`so_no`, '[[:space:]/-]+', ''))
    AND x.`deleted_at` IS NULL
);

-- Claim number as the app makes it (CR + yymm of creation + id), for new rows.
UPDATE `cora_claims`
SET `claim_no` = CONCAT('CR', DATE_FORMAT(`created_at`, '%y%m'), '-', LPAD(`id`, 6, '0'))
WHERE `claim_no` IS NULL;

DROP TEMPORARY TABLE IF EXISTS `uat_companies`;
DROP TEMPORARY TABLE IF EXISTS `uat_employees`;
DROP TEMPORARY TABLE IF EXISTS `uat_claims`;
