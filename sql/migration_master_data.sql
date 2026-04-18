-- Master Data table: admin-managed lookup values for dropdowns
-- Categories: 'industry', 'job_type', etc.
CREATE TABLE IF NOT EXISTS `master_data` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category`   VARCHAR(50)  NOT NULL,
  `value`      VARCHAR(200) NOT NULL,
  `sort_order` INT          NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_category_value` (`category`, `value`),
  KEY `idx_category_active` (`category`, `is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed industries (source: JOB_ROLES keys in antcareers_seekerJobs.php)
INSERT IGNORE INTO `master_data` (`category`, `value`, `sort_order`) VALUES
('industry', 'Accounting', 1),
('industry', 'Administration & Office Support', 2),
('industry', 'Advertising, Arts & Media', 3),
('industry', 'Banking & Financial Services', 4),
('industry', 'Call Centre & Customer Service', 5),
('industry', 'CEO & General Management', 6),
('industry', 'Community Services & Development', 7),
('industry', 'Construction', 8),
('industry', 'Consulting & Strategy', 9),
('industry', 'Design & Architecture', 10),
('industry', 'Education & Training', 11),
('industry', 'Engineering', 12),
('industry', 'Farming, Animals & Conservation', 13),
('industry', 'Government & Defence', 14),
('industry', 'Healthcare & Medical', 15),
('industry', 'Hospitality & Tourism', 16),
('industry', 'Human Resources & Recruitment', 17),
('industry', 'Information & Communication Technology', 18),
('industry', 'Insurance & Superannuation', 19),
('industry', 'Legal', 20),
('industry', 'Manufacturing, Transport & Logistics', 21),
('industry', 'Marketing & Communications', 22),
('industry', 'Mining, Resources & Energy', 23),
('industry', 'Real Estate & Property', 24),
('industry', 'Retail & Consumer Products', 25),
('industry', 'Sales', 26),
('industry', 'Science & Technology', 27),
('industry', 'Self Employment', 28),
('industry', 'Sports & Recreation', 29),
('industry', 'Trades & Services', 30);

-- Seed job types (matching jobs.job_type ENUM values)
INSERT IGNORE INTO `master_data` (`category`, `value`, `sort_order`) VALUES
('job_type', 'Full-time', 1),
('job_type', 'Part-time', 2),
('job_type', 'Contract', 3),
('job_type', 'Freelance', 4),
('job_type', 'Internship', 5),
('job_type', 'Remote', 6);
