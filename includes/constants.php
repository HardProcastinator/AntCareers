<?php
/**
 * AntCareers — Shared Dropdown Constants
 *
 * SINGLE SOURCE OF TRUTH for all dropdown options.
 * Both profile forms and filter components must use these values.
 * The value saved to the database MUST match the filter query values exactly.
 */

// ─── Experience Levels ────────────────────────────────────────────────────────
// Stored in: jobs.experience_level (ENUM), seeker_profiles.experience_level (varchar)
// Used in: seeker profile, people search filter, browse jobs filter, post job form
const EXPERIENCE_LEVELS = [
    'Entry',
    'Junior',
    'Mid',
    'Senior',
    'Lead',
    'Executive',
];

// ─── Industry List ────────────────────────────────────────────────────────────
// Stored in: jobs.industry (varchar), company_profiles.industry (varchar)
// Used in: post job form, company profile, browse jobs filter
const INDUSTRY_LIST = [
    'Accounting',
    'Administration & Office Support',
    'Advertising, Arts & Media',
    'Banking & Financial Services',
    'Call Centre & Customer Service',
    'CEO & General Management',
    'Community Services & Development',
    'Construction',
    'Consulting & Strategy',
    'Design & Architecture',
    'Education & Training',
    'Engineering',
    'Farming, Animals & Conservation',
    'Government & Defence',
    'Healthcare & Medical',
    'Hospitality & Tourism',
    'Human Resources & Recruitment',
    'Information & Communication Technology',
    'Insurance & Superannuation',
    'Legal',
    'Manufacturing, Transport & Logistics',
    'Marketing & Communications',
    'Mining, Resources & Energy',
    'Real Estate & Property',
    'Retail & Consumer Products',
    'Sales',
    'Science & Technology',
    'Self Employment',
    'Sports & Recreation',
    'Trades & Services',
];

// ─── Company Sizes ────────────────────────────────────────────────────────────
// Stored in: company_profiles.company_size (varchar)
// Used in: employer company profile form, Browse Companies filter
// Key   = value stored in DB (clean, no "employees" suffix)
// Value = human-readable label shown in dropdowns
const COMPANY_SIZES = [
    '1-10'      => '1–10 employees',
    '11-50'     => '11–50 employees',
    '51-200'    => '51–200 employees',
    '201-500'   => '201–500 employees',
    '501-1000'  => '501–1,000 employees',
    '1001-5000' => '1,001–5,000 employees',
    '5000+'     => '5,000+ employees',
];

// ─── Company Size Filter Ranges ───────────────────────────────────────────────
// Maps Browse Companies filter option keys → array of matching DB stored values
// Key must match the value="" attribute of the <option> in the filter dropdown
const COMPANY_SIZE_FILTER_RANGES = [
    'startup'  => ['1-10', '11-50'],
    'small'    => ['51-200'],
    'midsize'  => ['201-500', '501-1000'],
    'large'    => ['1001-5000', '5000+'],
];
