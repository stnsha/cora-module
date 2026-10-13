-- CORA module - master schema for the odb database.
-- Drops every cora_* table, then recreates them all in dependency order
-- (development: all cora_* rows are lost). Per-table copies live next to this file.
-- staff.cora (role tier) is a separate one-off change: add_staff_cora_column.sql.
-- Datetime columns hold Asia/Kuala_Lumpur local time. Every table has
-- created_at / updated_at / deleted_at; deleted_at is a soft delete (NULL = active)
-- and business-key uniqueness is enforced by the app against active rows.

SET NAMES utf8mb4;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `cora_audit_logs`;
DROP TABLE IF EXISTS `cora_employee_import_rows`;
DROP TABLE IF EXISTS `cora_employee_imports`;
DROP TABLE IF EXISTS `cora_claims`;
DROP TABLE IF EXISTS `cora_employees`;
DROP TABLE IF EXISTS `cora_companies`;
DROP TABLE IF EXISTS `cora_settings`;
SET FOREIGN_KEY_CHECKS = 1;

-- cora_settings: Default Alpro rules (key/value), the last level of the rule hierarchy
-- Individual employee rule -> Company rule -> Default Alpro rule.
CREATE TABLE `cora_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `updated_by_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NULL DEFAULT NULL,
  `updated_at` datetime NULL DEFAULT NULL,
  `deleted_at` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cora_settings_key_index` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- cora_companies: Company Master. client_id links the existing Corporate Client List
-- (micare_client_list.id); benefit_criteria / general_remark NULL = inherit from it.
-- status: 1=active, 2=inactive. limit_mode: 1=block over-limit claims, 2=warn only.
CREATE TABLE `cora_companies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int DEFAULT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_limit` decimal(12,2) DEFAULT NULL,
  `limit_mode` tinyint unsigned NOT NULL DEFAULT '1',
  `period_start` date DEFAULT NULL,
  `period_end` date DEFAULT NULL,
  `benefit_criteria` text COLLATE utf8mb4_unicode_ci NULL,
  `general_remark` text COLLATE utf8mb4_unicode_ci NULL,
  `hr_contact_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hr_contact_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint unsigned NOT NULL DEFAULT '1',
  `created_by` int unsigned DEFAULT NULL,
  `created_by_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NULL DEFAULT NULL,
  `updated_at` datetime NULL DEFAULT NULL,
  `deleted_at` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cora_companies_client_id_index` (`client_id`),
  KEY `cora_companies_name_index` (`name`),
  KEY `cora_companies_code_index` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- cora_employees: Employee Master: eligibility per employee. ic_norm = IC / passport upper-cased,
-- letters and digits only (search + uniqueness within a company, enforced by the app).
-- status: 1=active, 2=inactive. restriction_type: 0=none, 1=limit override, 2=suspended.
CREATE TABLE `cora_employees` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `employee_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ic_no` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ic_norm` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci NULL,
  `department` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entitlement_limit` decimal(12,2) NOT NULL DEFAULT '0.00',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` tinyint unsigned NOT NULL DEFAULT '1',
  `info_remark` text COLLATE utf8mb4_unicode_ci NULL,
  `restriction_type` tinyint unsigned NOT NULL DEFAULT '0',
  `override_limit` decimal(12,2) DEFAULT NULL,
  `override_until` date DEFAULT NULL,
  `restriction_remark` text COLLATE utf8mb4_unicode_ci NULL,
  `import_id` bigint unsigned DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_by_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NULL DEFAULT NULL,
  `updated_at` datetime NULL DEFAULT NULL,
  `deleted_at` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cora_employees_company_id_index` (`company_id`),
  KEY `cora_employees_ic_norm_index` (`ic_norm`),
  KEY `cora_employees_name_index` (`name`),
  KEY `cora_employees_phone_index` (`phone`),
  KEY `cora_employees_employee_no_index` (`employee_no`),
  CONSTRAINT `cora_employees_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `cora_companies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- cora_claims: Claim transactions. status: 1=draft, 2=submitted, 3=pending review, 4=approved,
-- 5=rejected, 6=cancelled/void, 7=amendment required. Submitted, pending review and
-- amendment required reserve claim_amount from the employee's available balance.
-- payment_method: 1=corporate claim, 2=TPA, 3=self-pay, 4=mixed payment.
-- Employee / company / outlet / entitlement values are snapshots taken at submission
-- (historical preservation: later master edits never change a claim).
CREATE TABLE `cora_claims` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `claim_no` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employee_id` bigint unsigned NOT NULL,
  `company_id` bigint unsigned NOT NULL,
  `outlet_id` int NOT NULL,
  `outlet_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employee_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employee_ic` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employee_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `visit_date` date NOT NULL,
  `so_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `so_norm` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `claim_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `self_pay_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `payment_method` tinyint unsigned NOT NULL DEFAULT '1',
  `claim_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remark` text COLLATE utf8mb4_unicode_ci NULL,
  `status` tinyint unsigned NOT NULL DEFAULT '1',
  `flags` json DEFAULT NULL,
  `limit_snapshot` decimal(12,2) DEFAULT NULL,
  `available_snapshot` decimal(12,2) DEFAULT NULL,
  `period_start` date DEFAULT NULL,
  `period_end` date DEFAULT NULL,
  `duplicate_authorised` tinyint(1) NOT NULL DEFAULT '0',
  `resubmit_count` int unsigned NOT NULL DEFAULT '0',
  `created_by` int unsigned DEFAULT NULL,
  `created_by_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `submitted_by` int unsigned DEFAULT NULL,
  `submitted_by_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `submitted_at` datetime NULL DEFAULT NULL,
  `reviewed_by` int unsigned DEFAULT NULL,
  `reviewed_by_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reviewed_at` datetime NULL DEFAULT NULL,
  `review_remark` text COLLATE utf8mb4_unicode_ci NULL,
  `cancelled_by` int unsigned DEFAULT NULL,
  `cancelled_by_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancelled_at` datetime NULL DEFAULT NULL,
  `cancel_reason` text COLLATE utf8mb4_unicode_ci NULL,
  `created_at` datetime NULL DEFAULT NULL,
  `updated_at` datetime NULL DEFAULT NULL,
  `deleted_at` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cora_claims_claim_no_index` (`claim_no`),
  KEY `cora_claims_employee_status_index` (`employee_id`, `status`),
  KEY `cora_claims_company_id_index` (`company_id`),
  KEY `cora_claims_outlet_id_index` (`outlet_id`),
  KEY `cora_claims_so_norm_index` (`so_norm`),
  KEY `cora_claims_status_index` (`status`),
  KEY `cora_claims_visit_date_index` (`visit_date`),
  CONSTRAINT `cora_claims_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `cora_employees` (`id`),
  CONSTRAINT `cora_claims_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `cora_companies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- cora_employee_imports: Employee bulk upload batches. status: 1=validated (awaiting confirm), 2=confirmed,
-- 3=discarded. summary JSON = counts per validation result.
CREATE TABLE `cora_employee_imports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_rows` int unsigned NOT NULL DEFAULT '0',
  `summary` json DEFAULT NULL,
  `status` tinyint unsigned NOT NULL DEFAULT '1',
  `confirmed_at` datetime NULL DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_by_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NULL DEFAULT NULL,
  `updated_at` datetime NULL DEFAULT NULL,
  `deleted_at` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- cora_employee_import_rows: Staged rows of an upload. result: valid_new, valid_update, duplicate_ic,
-- invalid_date, missing_limit, unknown_company, missing_field, invalid_limit.
CREATE TABLE `cora_employee_import_rows` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `import_id` bigint unsigned NOT NULL,
  `row_no` int unsigned NOT NULL,
  `data` json NOT NULL,
  `result` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT NULL,
  `employee_id` bigint unsigned DEFAULT NULL,
  `created_at` datetime NULL DEFAULT NULL,
  `updated_at` datetime NULL DEFAULT NULL,
  `deleted_at` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cora_employee_import_rows_import_result_index` (`import_id`, `result`),
  CONSTRAINT `cora_employee_import_rows_import_id_foreign` FOREIGN KEY (`import_id`) REFERENCES `cora_employee_imports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- cora_audit_logs: Audit trail: every edit, approval, rejection and entitlement adjustment, with the
-- staff member and time. entity: company | employee | claim | import | setting | user.
-- changes JSON = { field: [before, after] }.
CREATE TABLE `cora_audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entity` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` bigint unsigned DEFAULT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `changes` json DEFAULT NULL,
  `remark` text COLLATE utf8mb4_unicode_ci NULL,
  `staff_id` int unsigned DEFAULT NULL,
  `staff_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NULL DEFAULT NULL,
  `updated_at` datetime NULL DEFAULT NULL,
  `deleted_at` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cora_audit_logs_entity_index` (`entity`, `entity_id`),
  KEY `cora_audit_logs_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
