-- =============================================================================
-- AntCareers — Recruiter System Migration
-- Run AFTER schema.sql and migration_employer.sql
--
-- Changes:
--   1. Add 'recruiter' to users.account_type ENUM
--   2. Create recruiters table (links recruiter user to employer company)
--   3. Add recruiter_id to jobs table
--   4. Add approval_status to jobs table
--   5. Add 'Interviewed' to applications.status ENUM
--   6. Add must_change_password to users table
--   7. Add hired_credentials table
--   8. Add recruiter_stats table
--   9. Add recruiter_profiles table
-- =============================================================================

-- 1. Expand account_type to include 'recruiter'
ALTER TABLE `users`
  MODIFY COLUMN `account_type` ENUM('seeker','employer','admin','recruiter')
  NOT NULL DEFAULT 'seeker';

-- 2. Add must_change_password flag for first-login forced password change
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `must_change_password` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`;

-- 3. Recruiters table — links a recruiter user to an employer's company
CREATE TABLE IF NOT EXISTS `recruiters` (
    `id`              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `user_id`         INT UNSIGNED     NOT NULL,          -- FK → users.id (recruiter account)
    `company_id`      INT UNSIGNED     NOT NULL,          -- FK → company_profiles.id
    `employer_id`     INT UNSIGNED     NOT NULL,          -- FK → users.id (company admin)
    `role`            ENUM('recruiter','co-admin','viewer') NOT NULL DEFAULT 'recruiter',
    `is_active`       TINYINT(1)       NOT NULL DEFAULT 1,
    `invited_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `accepted_at`     DATETIME         DEFAULT NULL,
    `deactivated_at`  DATETIME         DEFAULT NULL,
    `created_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_recruiter_user` (`user_id`),
    INDEX `idx_recruiter_company` (`company_id`),
    INDEX `idx_recruiter_employer` (`employer_id`),
    INDEX `idx_recruiter_active` (`is_active`),

    CONSTRAINT `fk_recruiter_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_recruiter_company`
        FOREIGN KEY (`company_id`) REFERENCES `company_profiles`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_recruiter_employer`
        FOREIGN KEY (`employer_id`) REFERENCES `users`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Add recruiter_id column to jobs (nullable — NULL means employer-posted)
ALTER TABLE `jobs`
  ADD COLUMN IF NOT EXISTS `recruiter_id` INT UNSIGNED DEFAULT NULL AFTER `employer_id`,
  ADD INDEX `idx_jobs_recruiter` (`recruiter_id`);

-- 5. Add approval_status to jobs for recruiter job approval workflow
ALTER TABLE `jobs`
  ADD COLUMN IF NOT EXISTS `approval_status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved' AFTER `status`,
  ADD INDEX `idx_jobs_approval` (`approval_status`);

-- 6. Expand applications.status to include 'Interviewed'
ALTER TABLE `applications`
  MODIFY COLUMN `status` ENUM('Pending','Reviewed','Shortlisted','Interviewed','Rejected','Hired')
  NOT NULL DEFAULT 'Pending';

-- 7. Hired credentials table — stores auto-generated recruiter credentials for hired seekers
CREATE TABLE IF NOT EXISTS `hired_credentials` (
    `id`              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `application_id`  INT UNSIGNED     NOT NULL,       -- FK → applications.id
    `seeker_id`       INT UNSIGNED     NOT NULL,       -- FK → users.id (hired seeker)
    `recruiter_user_id` INT UNSIGNED   NOT NULL,       -- FK → users.id (new recruiter account created)
    `company_id`      INT UNSIGNED     NOT NULL,       -- FK → company_profiles.id
    `temp_username`   VARCHAR(255)     NOT NULL,
    `temp_password_hash` VARCHAR(255)  NOT NULL,       -- bcrypt of temp password
    `is_claimed`      TINYINT(1)       NOT NULL DEFAULT 0,
    `claimed_at`      DATETIME         DEFAULT NULL,
    `notification_sent` TINYINT(1)     NOT NULL DEFAULT 0,
    `email_sent`      TINYINT(1)       NOT NULL DEFAULT 0,
    `created_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_hired_application` (`application_id`),
    INDEX `idx_hired_seeker` (`seeker_id`),
    INDEX `idx_hired_company` (`company_id`),

    CONSTRAINT `fk_hired_app`
        FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_hired_seeker`
        FOREIGN KEY (`seeker_id`) REFERENCES `users`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_hired_recruiter_user`
        FOREIGN KEY (`recruiter_user_id`) REFERENCES `users`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Recruiter stats table — tracks performance metrics per recruiter
CREATE TABLE IF NOT EXISTS `recruiter_stats` (
    `id`                  INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `recruiter_id`        INT UNSIGNED     NOT NULL,          -- FK → recruiters.id
    `jobs_posted`         INT UNSIGNED     NOT NULL DEFAULT 0,
    `applicants_reviewed` INT UNSIGNED     NOT NULL DEFAULT 0,
    `interviews_scheduled` INT UNSIGNED    NOT NULL DEFAULT 0,
    `hires_made`          INT UNSIGNED     NOT NULL DEFAULT 0,
    `updated_at`          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_stats_recruiter` (`recruiter_id`),

    CONSTRAINT `fk_stats_recruiter`
        FOREIGN KEY (`recruiter_id`) REFERENCES `recruiters`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Recruiter profiles table — personal details editable by the recruiter
CREATE TABLE IF NOT EXISTS `recruiter_profiles` (
    `id`              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `user_id`         INT UNSIGNED     NOT NULL,           -- FK → users.id
    `position`        VARCHAR(200)     DEFAULT NULL,
    `department`      VARCHAR(200)     DEFAULT NULL,
    `phone`           VARCHAR(30)      DEFAULT NULL,
    `bio`             TEXT             DEFAULT NULL,
    `linkedin_url`    VARCHAR(500)     DEFAULT NULL,
    `created_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_rec_profile_user` (`user_id`),

    CONSTRAINT `fk_rec_profile_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
