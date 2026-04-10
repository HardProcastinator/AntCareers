-- =============================================================================
-- AntCareers — Employer Module Migration
-- Run AFTER schema.sql (which creates the `users` table)
-- Tables:
--   1. company_profiles   — Extended employer company info
--   2. jobs               — Job postings by employers
--   3. applications       — Job seeker applications to jobs
--   4. interview_schedules — Interview bookings from employers
--   5. messages           — Internal messaging between users
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1. company_profiles
--    Extended profile data for employer accounts.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS company_profiles (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED     NOT NULL,           -- FK → users.id
    company_name    VARCHAR(150)     NOT NULL,
    industry        VARCHAR(100)     DEFAULT NULL,
    company_size    ENUM('1-10','11-50','51-200','201-500','501-1000','1000+')
                                     DEFAULT NULL,
    website         VARCHAR(300)     DEFAULT NULL,
    location        VARCHAR(200)     DEFAULT NULL,
    about           TEXT             DEFAULT NULL,
    logo_url        VARCHAR(500)     DEFAULT NULL,
    founded_year    YEAR             DEFAULT NULL,
    is_verified     TINYINT(1)       NOT NULL DEFAULT 0,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE  KEY uq_company_user (user_id),
    INDEX        idx_company_name (company_name),

    CONSTRAINT fk_company_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 2. jobs
--    Job postings created by employer accounts.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS jobs (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    employer_id     INT UNSIGNED     NOT NULL,           -- FK → users.id
    title           VARCHAR(200)     NOT NULL,
    description     TEXT             DEFAULT NULL,
    requirements    TEXT             DEFAULT NULL,
    location        VARCHAR(200)     DEFAULT NULL,
    job_type        ENUM('Full-time','Part-time','Contract','Freelance','Internship')
                                     NOT NULL DEFAULT 'Full-time',
    setup           ENUM('On-site','Remote','Hybrid')
                                     NOT NULL DEFAULT 'On-site',
    salary_min      DECIMAL(12,2)    DEFAULT NULL,
    salary_max      DECIMAL(12,2)    DEFAULT NULL,
    salary_currency VARCHAR(10)      DEFAULT 'PHP',
    industry        VARCHAR(100)     DEFAULT NULL,
    experience_level ENUM('Entry','Junior','Mid','Senior','Lead','Executive')
                                     DEFAULT NULL,
    skills_required TEXT             DEFAULT NULL,       -- comma-separated tags
    status          ENUM('Active','Closed','Draft')
                                     NOT NULL DEFAULT 'Active',
    deadline        DATE             DEFAULT NULL,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_jobs_employer (employer_id),
    INDEX idx_jobs_status   (status),
    INDEX idx_jobs_type     (job_type),
    FULLTEXT KEY ft_jobs_search (title, description, skills_required),

    CONSTRAINT fk_jobs_employer
        FOREIGN KEY (employer_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 3. applications
--    Job seeker applications. Status reflects employer review pipeline.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS applications (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    job_id          INT UNSIGNED     NOT NULL,           -- FK → jobs.id
    seeker_id       INT UNSIGNED     NOT NULL,           -- FK → users.id
    cover_letter    TEXT             DEFAULT NULL,
    resume_url      VARCHAR(500)     DEFAULT NULL,
    status          ENUM('Pending','Reviewed','Shortlisted','Rejected','Hired')
                                     NOT NULL DEFAULT 'Pending',
    employer_notes  TEXT             DEFAULT NULL,       -- internal notes (seeker never sees)
    applied_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at     DATETIME         DEFAULT NULL,
    updated_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE  KEY uq_application (job_id, seeker_id),    -- one application per job per seeker
    INDEX idx_app_job     (job_id),
    INDEX idx_app_seeker  (seeker_id),
    INDEX idx_app_status  (status),

    CONSTRAINT fk_app_job
        FOREIGN KEY (job_id) REFERENCES jobs(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_app_seeker
        FOREIGN KEY (seeker_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 4. interview_schedules
--    Employer-initiated interviews linked to an application.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS interview_schedules (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    application_id  INT UNSIGNED     NOT NULL,           -- FK → applications.id
    employer_id     INT UNSIGNED     NOT NULL,
    seeker_id       INT UNSIGNED     NOT NULL,
    scheduled_at    DATETIME         NOT NULL,
    duration_mins   SMALLINT UNSIGNED DEFAULT 60,
    interview_type  ENUM('Online','Phone','On-site') NOT NULL DEFAULT 'Online',
    meeting_link    VARCHAR(500)     DEFAULT NULL,
    location        VARCHAR(300)     DEFAULT NULL,
    notes           TEXT             DEFAULT NULL,
    status          ENUM('Scheduled','Cancelled','Completed','No-show')
                                     NOT NULL DEFAULT 'Scheduled',
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_interview_app      (application_id),
    INDEX idx_interview_employer (employer_id),
    INDEX idx_interview_seeker   (seeker_id),

    CONSTRAINT fk_interview_app
        FOREIGN KEY (application_id) REFERENCES applications(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_interview_employer
        FOREIGN KEY (employer_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_interview_seeker
        FOREIGN KEY (seeker_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 5. messages
--    Simple internal messaging between users (employer ↔ seeker).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS messages (
    id          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    sender_id   INT UNSIGNED     NOT NULL,
    receiver_id INT UNSIGNED     NOT NULL,
    subject     VARCHAR(255)     DEFAULT NULL,
    body        TEXT             NOT NULL,
    is_read     TINYINT(1)       NOT NULL DEFAULT 0,
    created_at  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_msg_sender   (sender_id),
    INDEX idx_msg_receiver (receiver_id),
    INDEX idx_msg_read     (is_read),

    CONSTRAINT fk_msg_sender
        FOREIGN KEY (sender_id)   REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_msg_receiver
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- SAMPLE DATA — remove in production
-- =============================================================================

-- Sample employer (password: Test1234!)
INSERT IGNORE INTO users
  (email, password_hash, full_name, account_type, is_verified, is_active, company_name)
VALUES
  ('employer@antcareers.test',
   '$2y$12$PlaceholderHashXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
   'Maria Santos', 'employer', 1, 1, 'TechNova PH');

-- Sample seeker (password: Test1234!)
INSERT IGNORE INTO users
  (email, password_hash, full_name, account_type, is_verified, is_active)
VALUES
  ('seeker@antcareers.test',
   '$2y$12$PlaceholderHashXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
   'Juan dela Cruz', 'seeker', 1, 1);
