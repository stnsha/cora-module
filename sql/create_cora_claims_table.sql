-- Table `cora_claims` (CORA module, odb database).
-- Claim transactions. status: 1=draft, 2=submitted, 3=pending review, 4=approved,
-- 5=rejected, 6=cancelled/void, 7=amendment required. Submitted, pending review and
-- amendment required reserve claim_amount from the employee's available balance.
-- payment_method: 1=corporate claim, 2=TPA, 3=self-pay, 4=mixed payment.
-- Employee / company / outlet / entitlement values are snapshots taken at submission
-- (historical preservation: later master edits never change a claim).
-- Drops and recreates the table (development: existing rows are lost).
-- Datetime columns hold Asia/Kuala_Lumpur local time. deleted_at = soft delete (NULL = active).

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `cora_claims`;
SET FOREIGN_KEY_CHECKS = 1;

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
