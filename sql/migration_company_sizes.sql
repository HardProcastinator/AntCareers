-- =============================================================================
-- Migration: Normalize company_size values in company_profiles
-- Converts old long-form labels (e.g. "51–200 employees") to clean stored keys
-- (e.g. "51-200") that match COMPANY_SIZES in includes/constants.php.
--
-- Run once on both local (localhost:3307) and production databases.
-- Safe to re-run — UPDATE only affects rows that still have old values.
-- =============================================================================

-- Values that used en-dash (–, U+2013) between numbers
UPDATE company_profiles SET company_size = '1-10'      WHERE company_size IN ('1–10 employees',   '1-10 employees');
UPDATE company_profiles SET company_size = '11-50'     WHERE company_size IN ('11–50 employees',  '11-50 employees');
UPDATE company_profiles SET company_size = '51-200'    WHERE company_size IN ('51–200 employees', '51-200 employees');
UPDATE company_profiles SET company_size = '201-500'   WHERE company_size IN ('201–500 employees','201-500 employees');
UPDATE company_profiles SET company_size = '501-1000'  WHERE company_size IN ('501–1,000 employees','501-1,000 employees','501-1000 employees');
UPDATE company_profiles SET company_size = '1001-5000' WHERE company_size IN ('1,001–5,000 employees','1,001-5,000 employees','1001-5000 employees');
UPDATE company_profiles SET company_size = '5000+'     WHERE company_size IN ('5,000+ employees', '5000+ employees', '5,000+');

-- Verify remaining non-null values after migration (should all be canonical keys)
-- SELECT DISTINCT company_size FROM company_profiles WHERE company_size IS NOT NULL;
