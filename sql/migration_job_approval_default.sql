-- Change jobs.approval_status default from 'approved' to 'pending'
-- so newly posted jobs require admin approval before being visible.
-- Existing jobs are NOT changed — only the default for future inserts.
ALTER TABLE `jobs`
  MODIFY COLUMN `approval_status`
    ENUM('pending','approved','rejected')
    NOT NULL DEFAULT 'pending';
