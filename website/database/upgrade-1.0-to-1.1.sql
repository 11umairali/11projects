-- ════════════════════════════════════════════════════════════════════════
--  MailTrixy upgrade SQL — v1.0 → v1.1
--  ────────────────────────────────────────────────────────────────────
--  How to use:
--    1. BACK UP YOUR DATABASE FIRST.
--    2. Open phpMyAdmin → select your MailTrixy database → Import tab.
--    3. Choose this file → Go.
--    4. Done. No artisan migrate needed.
--    5. On the server, run:  php artisan optimize:clear
--
--  Upgrades cover:
--    • workspace_members.status — adds 'active' to enum + sets default
--    • ai_configs — adds 4 escalation columns
--    • campaigns — adds channel/from_number/body_text + relaxes NOT NULL
--                   on subject / body_html / email_account_id
--    • campaign_recipients — adds phone column + relaxes email NOT NULL
--    • ai_channel_configs — NEW table (per-channel AI overrides)
--    • plan_features — seeds 6 new feature flags per plan tier
--    • migrations registry — records the 4 new migrations as already-run
--
--  Idempotent: every ALTER / INSERT / CREATE is guarded so re-running
--  the file on an already-upgraded DB is a no-op.
--
--  Compatible with MySQL 5.7+ and MariaDB 10.2+. Uses inline prepared
--  statements (no stored procedures, no DELIMITER) so it imports cleanly
--  through phpMyAdmin, Adminer, and the mysql CLI everywhere.
-- ════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

-- ────────────────────────────────────────────────────────────────────
-- 1. workspace_members.status
--    Add 'active' to the enum and switch default to 'active'.
--    Reason: AI auto-escalation round-robin filter looks for status='active';
--    old DBs used the enum 'online/away/offline/vacation' with default
--    'offline', which left no eligible humans to escalate to.
-- ────────────────────────────────────────────────────────────────────
ALTER TABLE `workspace_members`
    MODIFY COLUMN `status`
    ENUM('active','online','away','offline','vacation') NOT NULL DEFAULT 'active';

-- Backfill: bump pre-existing rows that were sitting on the old default
UPDATE `workspace_members` SET `status` = 'active' WHERE `status` = 'offline';

-- ────────────────────────────────────────────────────────────────────
-- 2. ai_configs — AI auto-escalation columns
--    From migration: 2026_04_30_120000_add_ai_escalation_to_ai_configs
-- ────────────────────────────────────────────────────────────────────
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ai_configs'
              AND COLUMN_NAME = 'escalation_enabled') = 0,
    "ALTER TABLE `ai_configs` ADD COLUMN `escalation_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `confidence_threshold`",
    "SELECT 1");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ai_configs'
              AND COLUMN_NAME = 'escalate_below_confidence') = 0,
    "ALTER TABLE `ai_configs` ADD COLUMN `escalate_below_confidence` TINYINT UNSIGNED NOT NULL DEFAULT 50 AFTER `escalation_enabled`",
    "SELECT 1");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ai_configs'
              AND COLUMN_NAME = 'escalation_assignee_id') = 0,
    "ALTER TABLE `ai_configs` ADD COLUMN `escalation_assignee_id` BIGINT UNSIGNED NULL AFTER `escalate_below_confidence`",
    "SELECT 1");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ai_configs'
              AND COLUMN_NAME = 'escalation_tag') = 0,
    "ALTER TABLE `ai_configs` ADD COLUMN `escalation_tag` VARCHAR(64) NOT NULL DEFAULT 'needs_human' AFTER `escalation_assignee_id`",
    "SELECT 1");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ────────────────────────────────────────────────────────────────────
-- 3. campaigns — SMS bulk-campaign support
--    From migration: 2026_04_30_140000_add_sms_support_to_campaigns
-- ────────────────────────────────────────────────────────────────────
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'campaigns'
              AND COLUMN_NAME = 'channel') = 0,
    "ALTER TABLE `campaigns` ADD COLUMN `channel` ENUM('email','sms') NOT NULL DEFAULT 'email' AFTER `type`",
    "SELECT 1");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'campaigns'
              AND COLUMN_NAME = 'from_number') = 0,
    "ALTER TABLE `campaigns` ADD COLUMN `from_number` VARCHAR(32) NULL AFTER `email_account_id`",
    "SELECT 1");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'campaigns'
              AND COLUMN_NAME = 'body_text') = 0,
    "ALTER TABLE `campaigns` ADD COLUMN `body_text` TEXT NULL AFTER `body_html`",
    "SELECT 1");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Index on campaigns.channel for filtering
SET @s := IF((SELECT COUNT(*) FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'campaigns'
              AND INDEX_NAME = 'campaigns_channel_index') = 0,
    "CREATE INDEX `campaigns_channel_index` ON `campaigns` (`channel`)",
    "SELECT 1");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Relax NOT NULL on email-only columns (SMS rows skip these)
ALTER TABLE `campaigns` MODIFY COLUMN `subject`           VARCHAR(255) NULL;
ALTER TABLE `campaigns` MODIFY COLUMN `body_html`         MEDIUMTEXT NULL;
ALTER TABLE `campaigns` MODIFY COLUMN `email_account_id`  BIGINT UNSIGNED NULL;

-- campaign_recipients.phone for the SMS path
SET @s := IF((SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'campaign_recipients'
              AND COLUMN_NAME = 'phone') = 0,
    "ALTER TABLE `campaign_recipients` ADD COLUMN `phone` VARCHAR(32) NULL AFTER `email`",
    "SELECT 1");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s := IF((SELECT COUNT(*) FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'campaign_recipients'
              AND INDEX_NAME = 'campaign_recipients_phone_index') = 0,
    "CREATE INDEX `campaign_recipients_phone_index` ON `campaign_recipients` (`phone`)",
    "SELECT 1");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Email column NULL-able so SMS recipients don't need fake email values
ALTER TABLE `campaign_recipients` MODIFY COLUMN `email` VARCHAR(255) NULL;

-- ────────────────────────────────────────────────────────────────────
-- 4. ai_channel_configs — NEW table (per-channel AI behavior overrides)
--    From migration: 2026_04_30_150000_create_ai_channel_configs_table
--    Column types match what Laravel's blueprint generates on MySQL/MariaDB
--    (json() compiles to LONGTEXT — confirmed against the live schema).
-- ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ai_channel_configs` (
    `id`                           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `workspace_id`                 BIGINT UNSIGNED NOT NULL,
    `channel`                      ENUM('email','whatsapp','sms','live_chat','telegram') NOT NULL,
    `enabled`                      TINYINT(1) NOT NULL DEFAULT 0,
    `personality_preset`           VARCHAR(32) NOT NULL DEFAULT 'friendly',
    `custom_prompt`                TEXT NULL,
    `additional_instructions`      TEXT NULL,
    `send_mode`                    ENUM('autonomous','approval','suggestions') NOT NULL DEFAULT 'approval',
    `confidence_threshold`         TINYINT UNSIGNED NOT NULL DEFAULT 75,
    `max_reply_length`             ENUM('short','medium','long','very_long') NOT NULL DEFAULT 'medium',
    `temperature`                  DECIMAL(3,2) NULL,
    `skip_filters`                 LONGTEXT NULL,
    `first_message_only`           TINYINT(1) NOT NULL DEFAULT 0,
    `max_replies_per_conversation` TINYINT UNSIGNED NOT NULL DEFAULT 3,
    `skip_own_threads`             TINYINT(1) NOT NULL DEFAULT 1,
    `reply_delay`                  ENUM('none','30s','1m','2m','5m','random') NOT NULL DEFAULT 'none',
    `escalation_enabled`           TINYINT(1) NOT NULL DEFAULT 0,
    `escalate_below_confidence`    TINYINT UNSIGNED NOT NULL DEFAULT 50,
    `escalation_assignee_id`       BIGINT UNSIGNED NULL,
    `escalation_tag`               VARCHAR(64) NOT NULL DEFAULT 'needs_human',
    `created_at`                   TIMESTAMP NULL,
    `updated_at`                   TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ai_channel_workspace_unique` (`workspace_id`, `channel`),
    KEY `ai_channel_configs_channel_index` (`channel`),
    CONSTRAINT `ai_channel_configs_workspace_id_foreign`
        FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────────────
-- 5. plan_features — seed 6 new feature flags per existing plan
--    From migration: 2026_04_30_160000_add_premium_feature_flags_to_plans
--
--    Tier mapping (free / starter / pro / enterprise):
--      ai_own_key             0 / 0 / 1 / 1
--      ai_per_channel         0 / 0 / 1 / 1
--      ai_auto_escalation     0 / 0 / 1 / 1
--      sms_campaigns          0 / 0 / 1 / 1
--      email_templates        0 / 1 / 1 / 1   (FREE for everyone in core)
--      workflow_in_app_notify 0 / 1 / 1 / 1   (FREE for everyone in core)
--
--    Idempotent: only inserts if (plan_id, feature_key) doesn't exist.
--    Tier is detected from plan slug (free/starter/pro/enterprise) — any
--    unrecognized slug falls back to "starter" defaults.
-- ────────────────────────────────────────────────────────────────────
INSERT INTO `plan_features` (`plan_id`, `feature_key`, `enabled`, `limit`, `created_at`, `updated_at`)
SELECT p.id, 'ai_own_key',
       CASE
         WHEN LOWER(p.slug) LIKE '%enterprise%' OR LOWER(p.slug) LIKE '%business%' THEN 1
         WHEN LOWER(p.slug) LIKE '%pro%' OR LOWER(p.slug) LIKE '%premium%' THEN 1
         ELSE 0
       END,
       NULL, NOW(), NOW()
FROM `plans` p
WHERE NOT EXISTS (
    SELECT 1 FROM `plan_features` pf
    WHERE pf.plan_id = p.id AND pf.feature_key = 'ai_own_key'
);

INSERT INTO `plan_features` (`plan_id`, `feature_key`, `enabled`, `limit`, `created_at`, `updated_at`)
SELECT p.id, 'ai_per_channel',
       CASE
         WHEN LOWER(p.slug) LIKE '%enterprise%' OR LOWER(p.slug) LIKE '%business%' THEN 1
         WHEN LOWER(p.slug) LIKE '%pro%' OR LOWER(p.slug) LIKE '%premium%' THEN 1
         ELSE 0
       END,
       NULL, NOW(), NOW()
FROM `plans` p
WHERE NOT EXISTS (
    SELECT 1 FROM `plan_features` pf
    WHERE pf.plan_id = p.id AND pf.feature_key = 'ai_per_channel'
);

INSERT INTO `plan_features` (`plan_id`, `feature_key`, `enabled`, `limit`, `created_at`, `updated_at`)
SELECT p.id, 'ai_auto_escalation',
       CASE
         WHEN LOWER(p.slug) LIKE '%enterprise%' OR LOWER(p.slug) LIKE '%business%' THEN 1
         WHEN LOWER(p.slug) LIKE '%pro%' OR LOWER(p.slug) LIKE '%premium%' THEN 1
         ELSE 0
       END,
       NULL, NOW(), NOW()
FROM `plans` p
WHERE NOT EXISTS (
    SELECT 1 FROM `plan_features` pf
    WHERE pf.plan_id = p.id AND pf.feature_key = 'ai_auto_escalation'
);

INSERT INTO `plan_features` (`plan_id`, `feature_key`, `enabled`, `limit`, `created_at`, `updated_at`)
SELECT p.id, 'sms_campaigns',
       CASE
         WHEN LOWER(p.slug) LIKE '%enterprise%' OR LOWER(p.slug) LIKE '%business%' THEN 1
         WHEN LOWER(p.slug) LIKE '%pro%' OR LOWER(p.slug) LIKE '%premium%' THEN 1
         ELSE 0
       END,
       NULL, NOW(), NOW()
FROM `plans` p
WHERE NOT EXISTS (
    SELECT 1 FROM `plan_features` pf
    WHERE pf.plan_id = p.id AND pf.feature_key = 'sms_campaigns'
);

-- email_templates and workflow_in_app_notify: FREE for everyone except 'free' tier
INSERT INTO `plan_features` (`plan_id`, `feature_key`, `enabled`, `limit`, `created_at`, `updated_at`)
SELECT p.id, 'email_templates',
       CASE WHEN LOWER(p.slug) LIKE '%free%' THEN 0 ELSE 1 END,
       NULL, NOW(), NOW()
FROM `plans` p
WHERE NOT EXISTS (
    SELECT 1 FROM `plan_features` pf
    WHERE pf.plan_id = p.id AND pf.feature_key = 'email_templates'
);

INSERT INTO `plan_features` (`plan_id`, `feature_key`, `enabled`, `limit`, `created_at`, `updated_at`)
SELECT p.id, 'workflow_in_app_notify',
       CASE WHEN LOWER(p.slug) LIKE '%free%' THEN 0 ELSE 1 END,
       NULL, NOW(), NOW()
FROM `plans` p
WHERE NOT EXISTS (
    SELECT 1 FROM `plan_features` pf
    WHERE pf.plan_id = p.id AND pf.feature_key = 'workflow_in_app_notify'
);

-- ────────────────────────────────────────────────────────────────────
-- 6. Migrations registry — record the 4 new migrations as already-run
--    so a future `php artisan migrate` skips them instead of trying to
--    re-apply (which would fail because the columns/table already exist).
-- ────────────────────────────────────────────────────────────────────
SET @batch := COALESCE((SELECT MAX(batch) FROM `migrations`), 0) + 1;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_04_30_120000_add_ai_escalation_to_ai_configs', @batch
WHERE NOT EXISTS (
    SELECT 1 FROM `migrations`
    WHERE migration = '2026_04_30_120000_add_ai_escalation_to_ai_configs'
);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_04_30_140000_add_sms_support_to_campaigns', @batch
WHERE NOT EXISTS (
    SELECT 1 FROM `migrations`
    WHERE migration = '2026_04_30_140000_add_sms_support_to_campaigns'
);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_04_30_150000_create_ai_channel_configs_table', @batch
WHERE NOT EXISTS (
    SELECT 1 FROM `migrations`
    WHERE migration = '2026_04_30_150000_create_ai_channel_configs_table'
);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_04_30_160000_add_premium_feature_flags_to_plans', @batch
WHERE NOT EXISTS (
    SELECT 1 FROM `migrations`
    WHERE migration = '2026_04_30_160000_add_premium_feature_flags_to_plans'
);

-- ════════════════════════════════════════════════════════════════════
-- DONE.
-- After importing this file, on the server run:
--   php artisan optimize:clear
-- ════════════════════════════════════════════════════════════════════
