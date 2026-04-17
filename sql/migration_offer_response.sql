-- ============================================================
-- AntCareers — Migration: Add Accepted/Declined to application status
-- Run AFTER migration_updates.sql
-- ============================================================

-- Expand applications.status ENUM to include 'Accepted' and 'Declined'
ALTER TABLE `applications`
  MODIFY COLUMN `status` ENUM('Pending','Reviewed','Shortlisted','Interviewed','Rejected','Offered','Accepted','Declined')
  NOT NULL DEFAULT 'Pending';

-- Add offer_response notification type support (the notifications table uses VARCHAR, no ENUM change needed)
