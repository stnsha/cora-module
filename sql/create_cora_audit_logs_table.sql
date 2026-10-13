-- Table `cora_audit_logs` (CORA module, odb database).
-- Audit trail: every edit, approval, rejection and entitlement adjustment, with the
-- staff member and time. entity: company | employee | claim | import | setting | user.
-- changes JSON = { field: [before, after] }.
-- Drops and recreates the table (development: existing rows are lost).
-- Datetime columns hold Asia/Kuala_Lumpur local time. deleted_at = soft delete (NULL = active).

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `cora_audit_logs`;
SET FOREIGN_KEY_CHECKS = 1;

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
