-- ============================================================================
-- DahiMail  STEP 1  -  database changes   (MariaDB 10.11 / phpMyAdmin)
-- Safe to run more than once. Take a backup first (phpMyAdmin -> Export).
-- Select the database `dahi_mail`, open the SQL tab, paste this whole file, Go.
-- ============================================================================

-- 1) UNFRIEND KEEPS EVERYTHING -------------------------------------------------
-- Before: unfriend DELETED the friend_requests row, so the chat could no longer be opened.
-- Now: the row stays with status 'unfriended' and the dates are kept.
ALTER TABLE `friend_requests`
  MODIFY `status` ENUM('pending','accepted','declined','blocked','unfriended') NOT NULL DEFAULT 'pending',
  ADD COLUMN IF NOT EXISTS `unfriended_at` TIMESTAMP NULL DEFAULT NULL AFTER `responded_at`,
  ADD COLUMN IF NOT EXISTS `unfriended_by` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `unfriended_at`;

-- History of the relationship between two people (shown on the profile / history screen)
CREATE TABLE IF NOT EXISTS `friendship_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_low_id` BIGINT UNSIGNED NOT NULL,
  `user_high_id` BIGINT UNSIGNED NOT NULL,
  `actor_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `event` ENUM('requested','became_friends','unfriended','declined','blocked') NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `friendship_events_pair` (`user_low_id`,`user_high_id`,`id`),
  CONSTRAINT `fe_low_fk`  FOREIGN KEY (`user_low_id`)  REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fe_high_fk` FOREIGN KEY (`user_high_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Back-fill the history for friendships that already exist
INSERT INTO `friendship_events` (`user_low_id`,`user_high_id`,`actor_id`,`event`,`created_at`)
SELECT LEAST(r.requester_id,r.addressee_id), GREATEST(r.requester_id,r.addressee_id), r.requester_id, 'requested', r.created_at
FROM `friend_requests` r
WHERE NOT EXISTS (SELECT 1 FROM `friendship_events` e
  WHERE e.user_low_id = LEAST(r.requester_id,r.addressee_id) AND e.user_high_id = GREATEST(r.requester_id,r.addressee_id) AND e.event = 'requested');

INSERT INTO `friendship_events` (`user_low_id`,`user_high_id`,`actor_id`,`event`,`created_at`)
SELECT LEAST(r.requester_id,r.addressee_id), GREATEST(r.requester_id,r.addressee_id), r.addressee_id, 'became_friends', COALESCE(r.responded_at, r.created_at)
FROM `friend_requests` r
WHERE r.status = 'accepted'
  AND NOT EXISTS (SELECT 1 FROM `friendship_events` e
  WHERE e.user_low_id = LEAST(r.requester_id,r.addressee_id) AND e.user_high_id = GREATEST(r.requester_id,r.addressee_id) AND e.event = 'became_friends');

-- Chats of people who were unfriended BEFORE this update (their messages were never deleted, only hidden):
-- bring them back as 'unfriended'. The exact old dates are unknown, so first / last message dates are used.
INSERT INTO `friend_requests` (`requester_id`,`addressee_id`,`status`,`responded_at`,`unfriended_at`,`created_at`,`updated_at`)
SELECT p.a, p.b, 'unfriended', p.first_at, p.last_at, p.first_at, p.last_at
FROM (SELECT LEAST(sender_id,recipient_id) a, GREATEST(sender_id,recipient_id) b, MIN(created_at) first_at, MAX(created_at) last_at
      FROM `friend_messages` GROUP BY LEAST(sender_id,recipient_id), GREATEST(sender_id,recipient_id)) p
WHERE NOT EXISTS (SELECT 1 FROM `friend_requests` r WHERE (r.requester_id=p.a AND r.addressee_id=p.b) OR (r.requester_id=p.b AND r.addressee_id=p.a))
  AND NOT EXISTS (SELECT 1 FROM `workspace_members` x JOIN `workspace_members` y ON x.workspace_id=y.workspace_id WHERE x.user_id=p.a AND y.user_id=p.b);

-- 2) SYSTEM LINES IN THE CHAT ("Became friends", "Unfriended") + link call line -> call ----
ALTER TABLE `friend_messages`
  MODIFY `kind` ENUM('text','file','voice','call','system') NOT NULL DEFAULT 'text',
  ADD COLUMN IF NOT EXISTS `call_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `forwarded`,
  ADD KEY IF NOT EXISTS `friend_messages_call_id` (`call_id`);

-- 3) CALL RECORDINGS ----------------------------------------------------------
-- The recording is attached to the call's chat line (friend_messages.file_*), so it behaves exactly like a voice message /
-- file in the chat. This table keeps it safe until that line exists (the line is written when the call ends) and allows
-- only ONE recording per call.
CREATE TABLE IF NOT EXISTS `call_recordings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `call_id` BIGINT UNSIGNED NOT NULL,
  `uploaded_by` BIGINT UNSIGNED NOT NULL,
  `message_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `has_video` TINYINT(1) NOT NULL DEFAULT 0,
  `duration` INT UNSIGNED NULL DEFAULT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `file_name` VARCHAR(255) NULL DEFAULT NULL,
  `file_mime` VARCHAR(120) NULL DEFAULT NULL,
  `file_size` BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `call_recordings_call_unique` (`call_id`),
  CONSTRAINT `cr_call_fk` FOREIGN KEY (`call_id`) REFERENCES `friend_calls` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) PUSH: what each device needs ------------------------------------------------
-- voip_token = iOS PushKit token (makes an iPhone ring like a real phone call, even when the app is closed)
ALTER TABLE `device_tokens`
  MODIFY `platform` VARCHAR(10) NOT NULL,                       -- android | ios | web
  ADD COLUMN IF NOT EXISTS `voip_token` VARCHAR(255) NULL DEFAULT NULL AFTER `platform`,
  ADD COLUMN IF NOT EXISTS `app_version` VARCHAR(20) NULL DEFAULT NULL AFTER `voip_token`,
  ADD COLUMN IF NOT EXISTS `apns_sandbox` TINYINT(1) NOT NULL DEFAULT 0 AFTER `app_version`;

-- 5) ADMIN SWITCH for call recording (default OFF until you decide - see INSTALL_STEP1.md, legal note)
INSERT INTO `system_settings` (`key`,`value`,`group`,`created_at`,`updated_at`)
SELECT 'friends_call_recording','0','friends',NOW(),NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `system_settings` WHERE `key`='friends_call_recording');
