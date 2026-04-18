-- Add missing columns to jobs table used by employer_manageJobs.php
ALTER TABLE `jobs`
  ADD COLUMN IF NOT EXISTS `country` varchar(100) DEFAULT NULL AFTER `deadline`,
  ADD COLUMN IF NOT EXISTS `recruitment_duration` varchar(100) DEFAULT NULL AFTER `country`;
