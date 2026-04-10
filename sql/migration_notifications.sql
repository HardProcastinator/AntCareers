-- =============================================================================
-- AntCareers — Notifications Table Migration
-- Run AFTER schema.sql
-- =============================================================================

CREATE TABLE IF NOT EXISTS notifications (
    id           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED     NOT NULL,
    type         VARCHAR(50)      NOT NULL DEFAULT 'general',
    content      TEXT             NOT NULL,
    reference_id INT UNSIGNED     DEFAULT NULL,
    is_read      TINYINT(1)       NOT NULL DEFAULT 0,
    created_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_notif_user   (user_id),
    INDEX idx_notif_read   (is_read),
    INDEX idx_notif_type   (type),

    CONSTRAINT fk_notif_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
