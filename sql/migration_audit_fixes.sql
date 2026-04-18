-- =============================================================================
-- AntCareers — Audit Fix Migration
-- Run once against the antcareers database.
-- Safe to re-run: all statements use IF NOT EXISTS / IF EXISTS guards.
-- =============================================================================

-- Soft-delete support for jobs
ALTER TABLE jobs ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL;

-- Performance indexes
CREATE INDEX IF NOT EXISTS idx_jobs_deleted_at          ON jobs(deleted_at);
CREATE INDEX IF NOT EXISTS idx_jobs_status              ON jobs(status);
CREATE INDEX IF NOT EXISTS idx_jobs_approval            ON jobs(approval_status);
CREATE INDEX IF NOT EXISTS idx_notifications_is_read    ON notifications(is_read);
CREATE INDEX IF NOT EXISTS idx_applications_status      ON applications(status);
CREATE INDEX IF NOT EXISTS idx_conversations_parts      ON conversations(participant_a_id, participant_b_id);
