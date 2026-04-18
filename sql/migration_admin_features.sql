-- ============================================================
-- AntCareers — Admin Features Migration
-- Run once against the `antcareers` database.
-- Safe to run on existing data (uses IF NOT EXISTS / ALTER IGNORE).
-- ============================================================

-- -------------------------------------------------------
-- 1. Add account_status columns to users
-- -------------------------------------------------------
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `account_status`
    ENUM('active','pending_approval','suspended','banned')
    NOT NULL DEFAULT 'active'
    AFTER `is_active`,
  ADD COLUMN IF NOT EXISTS `status_reason`    VARCHAR(500)  NULL AFTER `account_status`,
  ADD COLUMN IF NOT EXISTS `status_expires_at` DATETIME     NULL AFTER `status_reason`,
  ADD COLUMN IF NOT EXISTS `status_updated_by` INT UNSIGNED  NULL AFTER `status_expires_at`,
  ADD COLUMN IF NOT EXISTS `status_updated_at` DATETIME     NULL AFTER `status_updated_by`;

-- Migrate all existing users to 'active'
UPDATE `users` SET `account_status` = 'active' WHERE `account_status` != 'active';

-- -------------------------------------------------------
-- 2. Add moderation columns to jobs
-- -------------------------------------------------------
ALTER TABLE `jobs`
  ADD COLUMN IF NOT EXISTS `approval_reason` VARCHAR(500) NULL AFTER `approval_status`,
  ADD COLUMN IF NOT EXISTS `approved_by`     INT UNSIGNED NULL AFTER `approval_reason`,
  ADD COLUMN IF NOT EXISTS `approved_at`     DATETIME    NULL AFTER `approved_by`;

-- Migrate all existing jobs: anything pending → approved (no disruption)
UPDATE `jobs` SET `approval_status` = 'approved' WHERE `approval_status` = 'pending';

-- Change the column default so future inserts default to pending
ALTER TABLE `jobs`
  MODIFY COLUMN `approval_status`
    ENUM('pending','approved','rejected')
    NOT NULL DEFAULT 'pending';

-- -------------------------------------------------------
-- 3. Add moderation audit columns to company_profiles
-- -------------------------------------------------------
ALTER TABLE `company_profiles`
  ADD COLUMN IF NOT EXISTS `approval_reason`     VARCHAR(500) NULL AFTER `is_verified`,
  ADD COLUMN IF NOT EXISTS `approval_updated_by` INT UNSIGNED NULL AFTER `approval_reason`,
  ADD COLUMN IF NOT EXISTS `approval_updated_at` DATETIME     NULL AFTER `approval_updated_by`;

-- Existing companies: mark as verified/approved
UPDATE `company_profiles` SET `is_verified` = 1 WHERE `is_verified` = 0;

-- -------------------------------------------------------
-- 4. Create activity_logs table
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id`          INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT(10) UNSIGNED NULL      COMMENT 'Affected user (nullable for anonymous/system)',
  `actor_id`    INT(10) UNSIGNED NULL      COMMENT 'Who performed the action',
  `action_type` VARCHAR(60)      NOT NULL  COMMENT 'e.g. login, user_registered, job_submitted, job_approved, company_approved, user_suspended',
  `entity_type` VARCHAR(40)      NULL      COMMENT 'e.g. user, job, company, application',
  `entity_id`   INT(10) UNSIGNED NULL      COMMENT 'PK of the affected entity',
  `description` TEXT             NULL,
  `ip_address`  VARCHAR(45)      NULL,
  `user_agent`  VARCHAR(500)     NULL,
  `created_at`  DATETIME         NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_al_user`   (`user_id`),
  KEY `idx_al_actor`  (`actor_id`),
  KEY `idx_al_action` (`action_type`),
  KEY `idx_al_entity` (`entity_type`, `entity_id`),
  KEY `idx_al_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
