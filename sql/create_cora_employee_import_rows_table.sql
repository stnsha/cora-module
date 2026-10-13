-- Table `cora_employee_import_rows` (CORA module, odb database).
-- Staged rows of an upload. result: valid_new, valid_update, duplicate_ic,
-- invalid_date, missing_limit, unknown_company, missing_field, invalid_limit.
-- Drops and recreates the table (development: existing rows are lost).
-- Datetime columns hold Asia/Kuala_Lumpur local time. deleted_at = soft delete (NULL = active).

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `cora_employee_import_rows`;
SET FOREIGN_KEY_CHECKS = 1;

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
