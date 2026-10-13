-- CORA seed data: the Default Alpro rules (last level of the rule hierarchy).
-- Run after cora_master.sql. Every insert is skipped when the key already exists
-- among active rows, so this file is safe to re-run and never overwrites edits.

INSERT INTO `cora_settings` (`key`, `value`, `created_at`, `updated_at`)
SELECT 'default_limit', '500.00', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `cora_settings` WHERE `key` = 'default_limit' AND `deleted_at` IS NULL);

INSERT INTO `cora_settings` (`key`, `value`, `created_at`, `updated_at`)
SELECT 'default_limit_mode', '1', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `cora_settings` WHERE `key` = 'default_limit_mode' AND `deleted_at` IS NULL);

INSERT INTO `cora_settings` (`key`, `value`, `created_at`, `updated_at`)
SELECT 'default_benefit_criteria', 'Outpatient pharmacy purchases only. Prescription items require a valid prescription.', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `cora_settings` WHERE `key` = 'default_benefit_criteria' AND `deleted_at` IS NULL);

INSERT INTO `cora_settings` (`key`, `value`, `created_at`, `updated_at`)
SELECT 'claim_types', '["Outpatient Pharmacy","Prescription","Medical Supplies","Supplement","Other"]', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `cora_settings` WHERE `key` = 'claim_types' AND `deleted_at` IS NULL);
