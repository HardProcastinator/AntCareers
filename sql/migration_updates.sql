-- ============================================================
-- AntCareers — Migration: Updates batch
-- Run AFTER migration_recruiter.sql, migration_seeker.sql, etc.
-- ============================================================

-- 1. Rename 'Hired' → 'Offered' in application status ENUM
ALTER TABLE applications
  MODIFY COLUMN status ENUM('Pending','Reviewed','Shortlisted','Interviewed','Rejected','Offered')
  NOT NULL DEFAULT 'Pending';

-- Migrate any existing 'Hired' rows (MySQL auto-maps old values during ENUM change)
UPDATE applications SET status = 'Offered' WHERE status = 'Hired';

-- 2. Add country and recruitment_duration columns to jobs table
ALTER TABLE jobs
  ADD COLUMN IF NOT EXISTS country VARCHAR(2) DEFAULT NULL AFTER location,
  ADD COLUMN IF NOT EXISTS recruitment_duration VARCHAR(50) DEFAULT NULL AFTER deadline;

-- 3. Update notification type from hired_credential to offer_credential (if any exist)
UPDATE notifications SET type = 'offer_credential' WHERE type = 'hired_credential';
