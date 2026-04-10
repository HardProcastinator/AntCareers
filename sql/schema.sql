-- =============================================================================
-- AntCareers — Database Schema
-- Module: Login / Authentication
-- Tables in this file:
--   1. users               — Core user accounts
--   2. remember_tokens     — "Remember me" 30-day persistent sessions
--   3. password_reset_tokens — Forgot-password one-time tokens
--   4. login_attempts      — Brute-force / rate-limiting audit log
--   5. social_accounts     — OAuth provider links (Google, LinkedIn)
--
-- NOTE: Additional tables (jobs, applications, companies, etc.) will be
--       added in future migrations as each section of the site is built.
-- =============================================================================


-- -----------------------------------------------------------------------------
-- 1. users
--    Core account table. All roles (seeker, employer, admin) share this table.
--    account_type determines which dashboard/features are available.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    email         VARCHAR(255)     NOT NULL,
    password_hash VARCHAR(255)     NOT NULL,                  -- bcrypt hash
    full_name     VARCHAR(150)     DEFAULT NULL,
    account_type  ENUM('seeker','employer','admin')
                                   NOT NULL DEFAULT 'seeker',
    is_verified   TINYINT(1)       NOT NULL DEFAULT 0,        -- email verified
    is_active     TINYINT(1)       NOT NULL DEFAULT 1,        -- soft-delete / ban
    contact       VARCHAR(30)      DEFAULT NULL,              -- phone / mobile
    company_name  VARCHAR(150)     DEFAULT NULL,              -- employer accounts
    avatar_url    VARCHAR(500)     DEFAULT NULL,
    last_login_at DATETIME         DEFAULT NULL,
    created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                             ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE  KEY uq_users_email        (email),
    INDEX        idx_users_type       (account_type),
    INDEX        idx_users_active     (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 2. remember_tokens
--    Stores hashed split-tokens for "Remember me for 30 days" functionality.
--    Cookie stores "selector:plaintext_validator".
--    DB stores "selector:sha256(plaintext_validator)" in token_hash.
--    Cascade-deletes when the parent user is removed.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS remember_tokens (
    id          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED     NOT NULL,
    token_hash  VARCHAR(255)     NOT NULL,   -- "selector:sha256hash"
    expires_at  DATETIME         NOT NULL,
    ip_address  VARCHAR(45)      DEFAULT NULL,   -- supports IPv6
    user_agent  VARCHAR(500)     DEFAULT NULL,
    created_at  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE  KEY uq_remember_token    (token_hash),
    INDEX        idx_remember_user   (user_id),
    INDEX        idx_remember_expiry (expires_at),

    CONSTRAINT fk_remember_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 3. password_reset_tokens
--    One-time tokens issued by the "Forgot password" flow.
--    Expires in RESET_TOKEN_EXPIRY_MINUTES (default 30 min).
--    used_at records when the token was consumed so it cannot be reused.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED     NOT NULL,
    token_hash  VARCHAR(64)      NOT NULL,   -- sha256 of the plain token
    expires_at  DATETIME         NOT NULL,
    used_at     DATETIME         DEFAULT NULL,   -- NULL = unused
    ip_address  VARCHAR(45)      DEFAULT NULL,
    created_at  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE  KEY uq_reset_token       (token_hash),
    INDEX        idx_reset_user      (user_id),
    INDEX        idx_reset_expiry    (expires_at),

    CONSTRAINT fk_reset_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 4. login_attempts
--    Immutable audit log of every sign-in attempt (success & failure).
--    Used by rate-limiter: if an email/IP exceeds MAX_LOGIN_ATTEMPTS failed
--    attempts within LOCKOUT_MINUTES, further attempts are blocked.
--    No foreign key on email because attempts may reference non-existent users.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    email        VARCHAR(255)     NOT NULL,
    ip_address   VARCHAR(45)      NOT NULL,
    user_agent   VARCHAR(500)     DEFAULT NULL,
    success      TINYINT(1)       NOT NULL DEFAULT 0,   -- 1=success, 0=fail
    attempted_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_attempts_email  (email),
    INDEX idx_attempts_ip     (ip_address),
    INDEX idx_attempts_time   (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 5. social_accounts
--    Links a users row to an OAuth provider (Google or LinkedIn).
--    One user can have one linked account per provider.
--    provider_user_id is the unique ID returned by the OAuth provider.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS social_accounts (
    id               INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    user_id          INT UNSIGNED     NOT NULL,
    provider         ENUM('google','linkedin') NOT NULL,
    provider_user_id VARCHAR(255)     NOT NULL,
    provider_email   VARCHAR(255)     DEFAULT NULL,
    created_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE  KEY uq_social_provider       (provider, provider_user_id),
    INDEX        idx_social_user         (user_id),

    CONSTRAINT fk_social_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- Housekeeping event: purge expired remember_tokens daily (optional).
-- Uncomment if your MySQL/MariaDB host has the event scheduler enabled.
-- =============================================================================
-- CREATE EVENT IF NOT EXISTS ev_purge_expired_tokens
--   ON SCHEDULE EVERY 1 DAY
--   DO
--     DELETE FROM remember_tokens WHERE expires_at < NOW();
