-- ============================================================
-- AntCareers — Job Notifications Migration
-- Run once to add is_expired and ensure user_preferences columns
-- ============================================================

-- 1. Add is_expired to notifications table (idempotent)
ALTER TABLE `notifications`
  ADD COLUMN IF NOT EXISTS `is_expired` TINYINT(1) NOT NULL DEFAULT 0
  AFTER `is_read`;

-- 2. Add index for fast expired-aware lookups
ALTER TABLE `notifications`
  ADD INDEX IF NOT EXISTS `idx_notif_expired` (`is_expired`);

-- 3. Ensure user_preferences table has notif_relevant_jobs column
ALTER TABLE `user_preferences`
  ADD COLUMN IF NOT EXISTS `notif_relevant_jobs` TINYINT(1) NOT NULL DEFAULT 1;

-- 4. Expire any existing relevant_job notifications whose jobs are
--    now closed, deleted, or rejected (safe to run repeatedly)
UPDATE `notifications` n
  JOIN `jobs` j
    ON j.id = n.reference_id
   AND n.reference_type = 'job'
   AND n.type = 'relevant_job'
SET n.is_expired = 1
WHERE n.is_read = 0
  AND (
    j.status = 'Closed'
    OR j.approval_status = 'rejected'
    OR j.deleted_at IS NOT NULL
    OR (j.deadline IS NOT NULL AND j.deadline < CURDATE())
  );
