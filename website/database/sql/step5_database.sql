-- ============================================================================
-- DahiMail STEP 5 (part A) - recording policy engine. MariaDB / phpMyAdmin. Safe to run twice. BACKUP FIRST.
-- ============================================================================

-- A recording "session" = one recording of one call / meeting, under the policy (mode 1-5) that was active when it started.
CREATE TABLE IF NOT EXISTS `recording_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `meeting_id` BIGINT UNSIGNED NOT NULL,
  `mode` TINYINT UNSIGNED NOT NULL,
  `status` ENUM('pending','recording','stopped','declined','expired','failed') NOT NULL DEFAULT 'recording',
  `reason` VARCHAR(40) NULL DEFAULT NULL,                    -- why it ended without a file (declined, no_recorder, ...)
  `private` TINYINT(1) NOT NULL DEFAULT 0,                    -- mode 5: the file goes only to the person who started it
  `started_by_pid` BIGINT UNSIGNED NULL DEFAULT NULL,
  `started_by_user` BIGINT UNSIGNED NULL DEFAULT NULL,
  `recorder_pid` BIGINT UNSIGNED NULL DEFAULT NULL,           -- the device that captures the audio/video (claimed first-come)
  `recorder_user` BIGINT UNSIGNED NULL DEFAULT NULL,
  `requested_at` TIMESTAMP NULL DEFAULT NULL,
  `started_at` TIMESTAMP NULL DEFAULT NULL,
  `stopped_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rs_meeting_status` (`meeting_id`,`status`),
  CONSTRAINT `rs_meeting_fk` FOREIGN KEY (`meeting_id`) REFERENCES `meetings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mode 3: who has to say yes
CREATE TABLE IF NOT EXISTS `recording_consents` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id` BIGINT UNSIGNED NOT NULL,
  `pid` BIGINT UNSIGNED NOT NULL,
  `response` ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending',
  `responded_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rc_unique` (`session_id`,`pid`),
  CONSTRAINT `rc_session_fk` FOREIGN KEY (`session_id`) REFERENCES `recording_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The finished recording file (one per session) and who may open it
CREATE TABLE IF NOT EXISTS `recording_files` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id` BIGINT UNSIGNED NOT NULL,
  `meeting_id` BIGINT UNSIGNED NOT NULL,
  `uploaded_by` BIGINT UNSIGNED NULL DEFAULT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `file_name` VARCHAR(255) NULL DEFAULT NULL,
  `file_mime` VARCHAR(120) NULL DEFAULT NULL,
  `file_size` BIGINT UNSIGNED NULL DEFAULT NULL,
  `duration` INT UNSIGNED NULL DEFAULT NULL,
  `has_video` TINYINT(1) NOT NULL DEFAULT 0,
  `audience` TEXT NULL,                                       -- JSON list of user ids that received it
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rf_session_unique` (`session_id`),
  KEY `rf_meeting` (`meeting_id`),
  CONSTRAINT `rf_session_fk` FOREIGN KEY (`session_id`) REFERENCES `recording_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A recording that only ONE person may see (mode 5) is a chat message with visible_to = that person.
ALTER TABLE `friend_messages`
  ADD COLUMN IF NOT EXISTS `visible_to` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `call_id`,
  ADD KEY IF NOT EXISTS `friend_messages_visible_to` (`visible_to`);

-- Settings (the Super Admin panel writes them; these are the safe defaults: recording OFF)
INSERT INTO `system_settings` (`key`,`value`,`group`,`created_at`,`updated_at`)
SELECT 'rec_mode','0','friends',NOW(),NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `system_settings` WHERE `key`='rec_mode');
