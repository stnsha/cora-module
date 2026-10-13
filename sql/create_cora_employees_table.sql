-- Table `cora_employees` (CORA module, odb database).
-- Employee Master: eligibility per employee. ic_norm = IC / passport upper-cased,
-- letters and digits only (search + uniqueness within a company, enforced by the app).
-- status: 1=active, 2=inactive. restriction_type: 0=none, 1=limit override, 2=suspended.
-- Drops and recreates the table (development: existing rows are lost).
-- Datetime columns hold Asia/Kuala_Lumpur local time. deleted_at = soft delete (NULL = active).

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `cora_employees`;
SET FOREIGN_KEY_CHECKS = 1;

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
