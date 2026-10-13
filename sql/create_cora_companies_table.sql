-- Table `cora_companies` (CORA module, odb database).
-- Company Master. client_id links the existing Corporate Client List
-- (micare_client_list.id); benefit_criteria / general_remark NULL = inherit from it.
-- status: 1=active, 2=inactive. limit_mode: 1=block over-limit claims, 2=warn only.
-- Drops and recreates the table (development: existing rows are lost).
-- Datetime columns hold Asia/Kuala_Lumpur local time. deleted_at = soft delete (NULL = active).

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `cora_companies`;
SET FOREIGN_KEY_CHECKS = 1;

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
