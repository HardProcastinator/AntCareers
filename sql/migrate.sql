-- AntCareers Migration Script
-- Run this once against the `antcareers` database to add missing columns/tables.
-- phpMyAdmin compatible — no DELIMITER needed.
-- If a column/index already exists the statement will error harmlessly; the next one continues.

-- ── 1. Jobs table: add missing columns ─────────────────────────────────────
ALTER TABLE `jobs` ADD COLUMN `setup` VARCHAR(50) DEFAULT 'On-site' AFTER `job_type`;
ALTER TABLE `jobs` ADD COLUMN `skills_required` TEXT DEFAULT NULL AFTER `setup`;

-- ── 2. Messages table: add missing columns for conversation support ────────
ALTER TABLE `messages` ADD COLUMN `conversation_id` INT UNSIGNED DEFAULT NULL AFTER `receiver_id`;
ALTER TABLE `messages` ADD COLUMN `message_type` VARCHAR(20) DEFAULT 'text' AFTER `body`;
ALTER TABLE `messages` ADD COLUMN `seen_at` DATETIME DEFAULT NULL AFTER `is_read`;
ALTER TABLE `messages` ADD COLUMN `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

ALTER TABLE `messages` ADD INDEX `idx_msg_conversation` (`conversation_id`);
ALTER TABLE `messages` ADD INDEX `idx_msg_sender` (`sender_id`);
ALTER TABLE `messages` ADD INDEX `idx_msg_receiver` (`receiver_id`);

-- ── 3. Conversations table ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `conversations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_a` INT UNSIGNED NOT NULL,
  `user_b` INT UNSIGNED NOT NULL,
  `last_message_id` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pair` (`user_a`, `user_b`),
  INDEX `idx_conv_users` (`user_a`, `user_b`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4. Notifications table ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `type` VARCHAR(50) NOT NULL DEFAULT 'general',
  `content` TEXT NOT NULL,
  `reference_id` INT UNSIGNED DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_notif_user` (`user_id`),
  INDEX `idx_notif_read` (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
