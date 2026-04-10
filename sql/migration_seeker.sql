-- =============================================================================
-- AntCareers — Seeker Module Migration
-- Run AFTER schema.sql and migration_employer.sql
-- Tables:
--   1. seeker_profiles   — Extended personal/contact info for seekers
--   2. seeker_education  — Educational background entries
--   3. seeker_skills     — Skills list per seeker
--   4. seeker_experience — Work history entries
--   5. seeker_resumes    — Resume file tracking
--   6. saved_jobs        — Bookmarked jobs per seeker
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1. seeker_profiles
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS seeker_profiles (
    id                   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id              INT UNSIGNED  NOT NULL,
    phone                VARCHAR(30)   DEFAULT NULL,
    headline             VARCHAR(200)  DEFAULT NULL,
    address_line         VARCHAR(255)  DEFAULT NULL,
    landmark             VARCHAR(255)  DEFAULT NULL,
    country_code         VARCHAR(10)   DEFAULT NULL,
    country_name         VARCHAR(100)  DEFAULT NULL,
    region_code          VARCHAR(20)   DEFAULT NULL,
    region_name          VARCHAR(100)  DEFAULT NULL,
    province_code        VARCHAR(20)   DEFAULT NULL,
    province_name        VARCHAR(100)  DEFAULT NULL,
    city_code            VARCHAR(20)   DEFAULT NULL,
    city_name            VARCHAR(100)  DEFAULT NULL,
    barangay_code        VARCHAR(20)   DEFAULT NULL,
    barangay_name        VARCHAR(100)  DEFAULT NULL,
    desired_position     VARCHAR(200)  DEFAULT NULL,
    professional_summary TEXT          DEFAULT NULL,
    experience_level     ENUM('Entry','Junior','Mid','Senior','Lead') DEFAULT NULL,
    bio                  TEXT          DEFAULT NULL,
    created_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_seeker_user (user_id),
    CONSTRAINT fk_sp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 2. seeker_education
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS seeker_education (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED  NOT NULL,
    education_level ENUM('elementary','junior_high','senior_high','college') NOT NULL DEFAULT 'college',
    school_name     VARCHAR(255)  DEFAULT NULL,
    degree_course   VARCHAR(255)  DEFAULT NULL,
    start_year      YEAR          DEFAULT NULL,
    end_year        YEAR          DEFAULT NULL,
    graduation_date DATE          DEFAULT NULL,
    remarks         VARCHAR(255)  DEFAULT NULL,
    honors          VARCHAR(255)  DEFAULT NULL,
    no_schooling    TINYINT(1)    NOT NULL DEFAULT 0,
    sort_order      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_edu_user (user_id),
    CONSTRAINT fk_edu_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 3. seeker_skills
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS seeker_skills (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED  NOT NULL,
    skill_name  VARCHAR(100)  NOT NULL,
    skill_level ENUM('Beginner','Intermediate','Advanced','Expert') DEFAULT 'Intermediate',
    sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_skill_user (user_id),
    CONSTRAINT fk_skill_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 4. seeker_experience
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS seeker_experience (
    id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED  NOT NULL,
    company_name VARCHAR(255)  NOT NULL,
    job_title    VARCHAR(200)  NOT NULL,
    start_date   DATE          DEFAULT NULL,
    end_date     DATE          DEFAULT NULL,
    is_current   TINYINT(1)    NOT NULL DEFAULT 0,
    description  TEXT          DEFAULT NULL,
    sort_order   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_exp_user (user_id),
    CONSTRAINT fk_exp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 5. seeker_resumes
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS seeker_resumes (
    id                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id           INT UNSIGNED  NOT NULL,
    original_filename VARCHAR(255)  NOT NULL,
    stored_filename   VARCHAR(255)  NOT NULL,
    file_path         VARCHAR(500)  NOT NULL,
    file_size         INT UNSIGNED  NOT NULL DEFAULT 0,
    mime_type         VARCHAR(100)  DEFAULT NULL,
    is_active         TINYINT(1)    NOT NULL DEFAULT 1,
    uploaded_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_resume_user   (user_id),
    INDEX idx_resume_active (is_active),
    CONSTRAINT fk_resume_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 6. saved_jobs
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS saved_jobs (
    id       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id  INT UNSIGNED  NOT NULL,
    job_id   INT UNSIGNED  NOT NULL,
    saved_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_saved (user_id, job_id),
    CONSTRAINT fk_saved_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_saved_job  FOREIGN KEY (job_id)  REFERENCES jobs(id)  ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
