<?php
/**
 * AntCareers — Master Hardcoded Job Titles / Categories / Subcategories
 * includes/job_titles.php
 *
 * This is the SINGLE SOURCE OF TRUTH for all job categories, subcategories,
 * and position titles used across the entire platform.
 *
 * Usage:
 *   require_once __DIR__ . '/job_titles.php';
 *   $cats = getJobCategories();           // returns full category tree
 *   $flat = getJobTitlesList();           // flat list of all titles
 *   $options = getJobCategoryOptions();   // for <select> dropdowns
 */

declare(strict_types=1);

/**
 * Returns the master job categories tree.
 * Structure: [ categoryName => [ subcategories => [...], icon => '...', titles => [...] ] ]
 */
function getJobCategories(): array
{
    return [
        'Technology & IT' => [
            'icon' => 'fa-laptop-code',
            'subcategories' => [
                'Software Development' => [
                    'Frontend Developer', 'Backend Developer', 'Full Stack Developer',
                    'Mobile Developer', 'Software Engineer', 'Web Developer',
                    'DevOps Engineer', 'QA Engineer', 'Software Architect',
                    'Game Developer', 'Embedded Systems Developer',
                ],
                'Data & Analytics' => [
                    'Data Analyst', 'Data Scientist', 'Data Engineer',
                    'Business Intelligence Analyst', 'Machine Learning Engineer',
                    'AI Engineer', 'Database Administrator',
                ],
                'IT & Infrastructure' => [
                    'Systems Administrator', 'Network Engineer', 'Cloud Engineer',
                    'IT Support Specialist', 'Cybersecurity Analyst', 'Security Engineer',
                    'IT Manager', 'Technical Support Engineer',
                ],
                'Product & Project' => [
                    'Product Manager', 'Technical Product Manager', 'Project Manager',
                    'Scrum Master', 'Agile Coach', 'IT Project Manager',
                ],
            ],
        ],
        'Design & Creative' => [
            'icon' => 'fa-palette',
            'subcategories' => [
                'UI/UX Design' => [
                    'UI Designer', 'UX Designer', 'UX Researcher',
                    'Product Designer', 'Interaction Designer', 'Visual Designer',
                ],
                'Graphic & Media' => [
                    'Graphic Designer', 'Motion Graphics Designer', 'Video Editor',
                    'Animator', 'Illustrator', 'Multimedia Artist',
                    'Creative Director', 'Art Director',
                ],
                'Content Creation' => [
                    'Content Creator', 'Copywriter', 'Technical Writer',
                    'Content Strategist', 'Social Media Content Creator',
                ],
            ],
        ],
        'Finance & Accounting' => [
            'icon' => 'fa-chart-line',
            'subcategories' => [
                'Accounting' => [
                    'Accountant', 'Senior Accountant', 'Staff Accountant',
                    'Tax Accountant', 'Auditor', 'Bookkeeper',
                    'Accounting Manager', 'Controller',
                ],
                'Finance' => [
                    'Financial Analyst', 'Investment Analyst', 'Investment Associate',
                    'Financial Advisor', 'Risk Analyst', 'Treasury Analyst',
                    'Finance Manager', 'CFO',
                ],
                'Banking & Insurance' => [
                    'Bank Teller', 'Loan Officer', 'Credit Analyst',
                    'Insurance Agent', 'Underwriter', 'Claims Adjuster',
                    'Branch Manager', 'Relationship Manager',
                ],
            ],
        ],
        'Healthcare & Medical' => [
            'icon' => 'fa-heartbeat',
            'subcategories' => [
                'Clinical' => [
                    'Registered Nurse', 'Licensed Practical Nurse', 'Nurse Practitioner',
                    'Physician', 'Surgeon', 'Dentist',
                    'Pharmacist', 'Physical Therapist', 'Occupational Therapist',
                    'Medical Technologist', 'Radiologic Technologist',
                ],
                'Healthcare Administration' => [
                    'Healthcare Administrator', 'Medical Records Specialist',
                    'Health Information Manager', 'Clinical Coordinator',
                    'Patient Care Coordinator', 'Healthcare Data Analyst',
                ],
                'Mental Health' => [
                    'Psychologist', 'Psychiatrist', 'Counselor',
                    'Social Worker', 'Behavioral Therapist',
                ],
            ],
        ],
        'Marketing & Communications' => [
            'icon' => 'fa-bullhorn',
            'subcategories' => [
                'Digital Marketing' => [
                    'Digital Marketing Specialist', 'SEO Specialist', 'SEM Specialist',
                    'Social Media Manager', 'Email Marketing Specialist',
                    'Growth Marketing Manager', 'Performance Marketing Manager',
                ],
                'Brand & PR' => [
                    'Brand Manager', 'Marketing Manager', 'Marketing Director',
                    'PR Specialist', 'Communications Manager',
                    'Content Marketing Manager', 'Marketing Lead',
                ],
                'Market Research' => [
                    'Market Research Analyst', 'Consumer Insights Analyst',
                    'Competitive Intelligence Analyst',
                ],
            ],
        ],
        'Sales & Business Development' => [
            'icon' => 'fa-handshake',
            'subcategories' => [
                'Sales' => [
                    'Sales Representative', 'Account Executive', 'Sales Manager',
                    'Sales Director', 'Business Development Representative',
                    'Inside Sales Representative', 'Territory Sales Manager',
                ],
                'Business Development' => [
                    'Business Development Manager', 'Partnership Manager',
                    'Strategic Account Manager', 'Key Account Manager',
                ],
                'Customer Success' => [
                    'Customer Success Manager', 'Account Manager',
                    'Client Relations Manager', 'Customer Experience Specialist',
                ],
            ],
        ],
        'Human Resources' => [
            'icon' => 'fa-users',
            'subcategories' => [
                'Recruitment' => [
                    'Recruiter', 'Talent Acquisition Specialist',
                    'Recruitment Manager', 'Sourcing Specialist',
                    'Headhunter', 'Campus Recruiter',
                ],
                'HR Management' => [
                    'HR Manager', 'HR Director', 'HR Business Partner',
                    'HR Generalist', 'HR Coordinator', 'HR Assistant',
                    'Compensation & Benefits Manager',
                ],
                'Learning & Development' => [
                    'Training Manager', 'Learning & Development Specialist',
                    'Organizational Development Specialist',
                ],
            ],
        ],
        'Engineering & Manufacturing' => [
            'icon' => 'fa-industry',
            'subcategories' => [
                'Engineering' => [
                    'Mechanical Engineer', 'Electrical Engineer', 'Civil Engineer',
                    'Chemical Engineer', 'Industrial Engineer',
                    'Structural Engineer', 'Environmental Engineer',
                    'Biomedical Engineer', 'Aerospace Engineer',
                ],
                'Manufacturing' => [
                    'Production Manager', 'Quality Control Inspector',
                    'Manufacturing Engineer', 'Process Engineer',
                    'Plant Manager', 'Maintenance Technician',
                    'Supply Chain Manager', 'Logistics Coordinator',
                ],
            ],
        ],
        'Education & Training' => [
            'icon' => 'fa-graduation-cap',
            'subcategories' => [
                'Teaching' => [
                    'Teacher', 'Professor', 'Instructor',
                    'Tutor', 'Special Education Teacher',
                    'ESL Teacher', 'Substitute Teacher',
                ],
                'Administration' => [
                    'School Administrator', 'Academic Advisor',
                    'Admissions Officer', 'Registrar',
                    'Education Coordinator', 'Curriculum Developer',
                ],
            ],
        ],
        'Legal' => [
            'icon' => 'fa-gavel',
            'subcategories' => [
                'Legal Practice' => [
                    'Attorney', 'Lawyer', 'Legal Counsel',
                    'Paralegal', 'Legal Assistant', 'Compliance Officer',
                    'Contract Specialist', 'Corporate Lawyer',
                ],
            ],
        ],
        'Operations & Administration' => [
            'icon' => 'fa-cogs',
            'subcategories' => [
                'Operations' => [
                    'Operations Manager', 'Operations Analyst',
                    'Business Analyst', 'Process Improvement Specialist',
                    'COO', 'Office Manager',
                ],
                'Administration' => [
                    'Executive Assistant', 'Administrative Assistant',
                    'Office Administrator', 'Receptionist',
                    'Virtual Assistant', 'Data Entry Specialist',
                ],
            ],
        ],
        'Hospitality & Tourism' => [
            'icon' => 'fa-concierge-bell',
            'subcategories' => [
                'Hotel & Restaurant' => [
                    'Hotel Manager', 'Front Desk Agent', 'Concierge',
                    'Chef', 'Restaurant Manager', 'Bartender',
                    'Food & Beverage Manager', 'Housekeeper',
                ],
                'Travel & Events' => [
                    'Travel Agent', 'Tour Guide', 'Event Planner',
                    'Event Coordinator', 'Catering Manager',
                ],
            ],
        ],
        'Construction & Real Estate' => [
            'icon' => 'fa-hard-hat',
            'subcategories' => [
                'Construction' => [
                    'Construction Manager', 'Site Engineer', 'Foreman',
                    'Estimator', 'Safety Officer', 'Surveyor',
                    'Architect', 'Interior Designer',
                ],
                'Real Estate' => [
                    'Real Estate Agent', 'Property Manager',
                    'Real Estate Broker', 'Leasing Consultant',
                ],
            ],
        ],
        'Media & Entertainment' => [
            'icon' => 'fa-film',
            'subcategories' => [
                'Media' => [
                    'Journalist', 'Reporter', 'Editor',
                    'News Anchor', 'Photographer', 'Photojournalist',
                ],
                'Entertainment' => [
                    'Producer', 'Production Assistant', 'Director',
                    'Sound Engineer', 'Stage Manager',
                ],
            ],
        ],
        'Retail & Customer Service' => [
            'icon' => 'fa-shopping-bag',
            'subcategories' => [
                'Retail' => [
                    'Store Manager', 'Retail Sales Associate', 'Cashier',
                    'Merchandiser', 'Inventory Specialist',
                    'Visual Merchandiser', 'Loss Prevention Specialist',
                ],
                'Customer Service' => [
                    'Customer Service Representative', 'Call Center Agent',
                    'Technical Support Representative', 'Help Desk Analyst',
                    'Customer Service Manager',
                ],
            ],
        ],
        'Transportation & Logistics' => [
            'icon' => 'fa-truck',
            'subcategories' => [
                'Logistics' => [
                    'Logistics Manager', 'Warehouse Manager', 'Dispatcher',
                    'Freight Coordinator', 'Inventory Manager',
                    'Procurement Specialist', 'Purchasing Manager',
                ],
                'Transportation' => [
                    'Driver', 'Delivery Driver', 'Fleet Manager',
                    'Pilot', 'Shipping Coordinator',
                ],
            ],
        ],
    ];
}

/**
 * Returns a flat array of all job titles across all categories.
 */
function getJobTitlesList(): array
{
    $titles = [];
    foreach (getJobCategories() as $cat) {
        foreach ($cat['subcategories'] as $titles_list) {
            foreach ($titles_list as $title) {
                $titles[] = $title;
            }
        }
    }
    return array_unique($titles);
}

/**
 * Returns flat category names for dropdown <select>.
 */
function getJobCategoryNames(): array
{
    return array_keys(getJobCategories());
}

/**
 * Returns subcategory names for a given category.
 */
function getSubcategoriesFor(string $category): array
{
    $cats = getJobCategories();
    if (!isset($cats[$category])) return [];
    return array_keys($cats[$category]['subcategories']);
}

/**
 * Returns titles for a given category & subcategory.
 */
function getTitlesFor(string $category, string $subcategory): array
{
    $cats = getJobCategories();
    return $cats[$category]['subcategories'][$subcategory] ?? [];
}

/**
 * Returns category list with icons for dropdowns / navigation.
 */
function getJobCategoryOptions(): array
{
    $options = [];
    foreach (getJobCategories() as $name => $cat) {
        $options[] = [
            'name' => $name,
            'icon' => $cat['icon'],
            'subcategories' => array_keys($cat['subcategories']),
        ];
    }
    return $options;
}

/**
 * Returns the industry filter values mapped from categories.
 */
function getIndustryFilterOptions(): array
{
    return [
        ['value' => 'Technology & IT',            'label' => 'Technology / IT',          'icon' => 'fa-laptop-code'],
        ['value' => 'Design & Creative',          'label' => 'Design / Creative',        'icon' => 'fa-palette'],
        ['value' => 'Finance & Accounting',       'label' => 'Finance / Accounting',     'icon' => 'fa-chart-line'],
        ['value' => 'Healthcare & Medical',       'label' => 'Healthcare / Medical',     'icon' => 'fa-heartbeat'],
        ['value' => 'Marketing & Communications', 'label' => 'Marketing / Communications','icon' => 'fa-bullhorn'],
        ['value' => 'Sales & Business Development','label' => 'Sales / Business Dev',    'icon' => 'fa-handshake'],
        ['value' => 'Human Resources',            'label' => 'Human Resources',          'icon' => 'fa-users'],
        ['value' => 'Engineering & Manufacturing','label' => 'Engineering / Manufacturing','icon' => 'fa-industry'],
        ['value' => 'Education & Training',       'label' => 'Education / Training',     'icon' => 'fa-graduation-cap'],
        ['value' => 'Legal',                      'label' => 'Legal',                    'icon' => 'fa-gavel'],
        ['value' => 'Operations & Administration','label' => 'Operations / Admin',       'icon' => 'fa-cogs'],
        ['value' => 'Hospitality & Tourism',      'label' => 'Hospitality / Tourism',    'icon' => 'fa-concierge-bell'],
        ['value' => 'Construction & Real Estate', 'label' => 'Construction / Real Estate','icon' => 'fa-hard-hat'],
        ['value' => 'Media & Entertainment',      'label' => 'Media / Entertainment',    'icon' => 'fa-film'],
        ['value' => 'Retail & Customer Service',  'label' => 'Retail / Customer Service','icon' => 'fa-shopping-bag'],
        ['value' => 'Transportation & Logistics', 'label' => 'Transportation / Logistics','icon' => 'fa-truck'],
    ];
}

/**
 * Experience level options used across forms.
 */
function getExperienceLevels(): array
{
    return ['Entry', 'Junior', 'Mid', 'Senior', 'Lead', 'Executive'];
}

/**
 * Job type options used across forms.
 */
function getJobTypes(): array
{
    return ['Full-time', 'Part-time', 'Contract', 'Freelance', 'Internship'];
}

/**
 * Work setup options used across forms.
 */
function getWorkSetups(): array
{
    return ['On-site', 'Remote', 'Hybrid'];
}
