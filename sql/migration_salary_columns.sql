-- Fix salary column overflow (was DECIMAL(10,2), max ~99M)
-- Widen to DECIMAL(13,2) to support values up to 9,999,999,999.99
ALTER TABLE `jobs`
  MODIFY COLUMN `salary_min` DECIMAL(13,2) DEFAULT NULL,
  MODIFY COLUMN `salary_max` DECIMAL(13,2) DEFAULT NULL;
