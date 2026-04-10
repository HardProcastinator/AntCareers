-- =============================================================================
-- AntCareers Messaging Migration
-- =============================================================================

CREATE TABLE IF NOT EXISTS conversations (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    conversation_key  VARCHAR(80) NOT NULL,
    participant_a_id  INT UNSIGNED NOT NULL,
    participant_b_id  INT UNSIGNED NOT NULL,
    latest_message_id  INT UNSIGNED DEFAULT NULL,
    latest_message_at  DATETIME DEFAULT NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_conversation_key (conversation_key),
    INDEX idx_conversation_a (participant_a_id),
    INDEX idx_conversation_b (participant_b_id),
    INDEX idx_conversation_latest (latest_message_at),
    CONSTRAINT fk_conversation_a FOREIGN KEY (participant_a_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_conversation_b FOREIGN KEY (participant_b_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE messages
    ADD COLUMN IF NOT EXISTS conversation_id INT UNSIGNED DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS message_type VARCHAR(20) NOT NULL DEFAULT 'text',
    ADD COLUMN IF NOT EXISTS seen_at DATETIME DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE messages
    ADD INDEX idx_msg_conversation (conversation_id),
    ADD INDEX idx_msg_created (created_at);

CREATE TABLE IF NOT EXISTS notifications (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED NOT NULL,
    type         VARCHAR(50)  NOT NULL DEFAULT 'general',
    content      TEXT         NOT NULL,
    reference_id INT UNSIGNED DEFAULT NULL,
    is_read      TINYINT(1)   NOT NULL DEFAULT 0,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_notif_user (user_id),
    INDEX idx_notif_read (is_read),
    INDEX idx_notif_type (type),
    CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;