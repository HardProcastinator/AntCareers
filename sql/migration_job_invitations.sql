-- ============================================================
-- AntCareers: Job Invitations Migration
-- Run once to add the job_invitations table
-- ============================================================

CREATE TABLE IF NOT EXISTS `job_invitations` (
  `id`           INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_id`       INT(10) UNSIGNED NOT NULL,
  `recruiter_id` INT(10) UNSIGNED NOT NULL,
  `jobseeker_id` INT(10) UNSIGNED NOT NULL,
  `status`       ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending',
  `custom_note`  TEXT DEFAULT NULL,
  `sent_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `responded_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invite` (`job_id`, `jobseeker_id`),
  KEY `idx_inv_job`      (`job_id`),
  KEY `idx_inv_recruiter`(`recruiter_id`),
  KEY `idx_inv_seeker`   (`jobseeker_id`),
  KEY `idx_inv_status`   (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
