-- Table `cora_settings` (CORA module, odb database).
-- Default Alpro rules (key/value), the last level of the rule hierarchy
-- Individual employee rule -> Company rule -> Default Alpro rule.
-- Drops and recreates the table (development: existing rows are lost).
-- Datetime columns hold Asia/Kuala_Lumpur local time. deleted_at = soft delete (NULL = active).

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `cora_settings`;
SET FOREIGN_KEY_CHECKS = 1;

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
