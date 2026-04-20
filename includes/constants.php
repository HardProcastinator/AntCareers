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
