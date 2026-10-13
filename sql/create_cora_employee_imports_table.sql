-- Table `cora_employee_imports` (CORA module, odb database).
-- Employee bulk upload batches. status: 1=validated (awaiting confirm), 2=confirmed,
-- 3=discarded. summary JSON = counts per validation result.
-- Drops and recreates the table (development: existing rows are lost).
-- Datetime columns hold Asia/Kuala_Lumpur local time. deleted_at = soft delete (NULL = active).

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `cora_employee_imports`;
SET FOREIGN_KEY_CHECKS = 1;

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
