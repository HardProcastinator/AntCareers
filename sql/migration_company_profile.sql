-- Migration: Add missing columns to company_profiles for full company profile functionality
-- Run this in phpMyAdmin on your b8_41186763_finalfinalproj database

ALTER TABLE `company_profiles`
  ADD COLUMN `tagline` VARCHAR(120) DEFAULT NULL AFTER `about`,
  ADD COLUMN `company_type` VARCHAR(50) DEFAULT NULL AFTER `company_size`,
  ADD COLUMN `country` VARCHAR(100) DEFAULT NULL AFTER `city`,
  ADD COLUMN `zip_code` VARCHAR(20) DEFAULT NULL AFTER `country`,
  ADD COLUMN `social_website` VARCHAR(500) DEFAULT NULL AFTER `contact_phone`,
  ADD COLUMN `social_linkedin` VARCHAR(500) DEFAULT NULL AFTER `social_website`,
  ADD COLUMN `social_facebook` VARCHAR(500) DEFAULT NULL AFTER `social_linkedin`,
  ADD COLUMN `social_twitter` VARCHAR(500) DEFAULT NULL AFTER `social_facebook`,
  ADD COLUMN `social_instagram` VARCHAR(500) DEFAULT NULL AFTER `social_twitter`,
  ADD COLUMN `social_youtube` VARCHAR(500) DEFAULT NULL AFTER `social_instagram`,
  ADD COLUMN `perks` TEXT DEFAULT NULL AFTER `social_youtube`,
  ADD COLUMN `logo_path` VARCHAR(500) DEFAULT NULL AFTER `perks`,
  ADD COLUMN `cover_path` VARCHAR(500) DEFAULT NULL AFTER `logo_path`;
