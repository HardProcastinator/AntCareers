# AntCareers — Setup Instructions

## Step 1: Database (Run in Order)

Go to phpMyAdmin → your database → SQL tab, then run each file:

1. `sql/schema.sql`
2. `sql/migration_employer.sql`
3. `sql/migration_seeker.sql`  ← NEW

All use `CREATE TABLE IF NOT EXISTS` — safe to re-run.

---

## Step 2: Upload Files

Copy the entire `antcareers/` folder to your web server root (e.g. `htdocs/antcareers/`).

Make sure the `uploads/resumes/` folder is writable:
```
chmod 755 uploads/resumes/
```

---

## Step 3: Configure Database

Edit `config.php` and set your DB credentials:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

---

## What Was Fixed / Added

### New Files
- `sql/migration_seeker.sql` — seeker_profiles, seeker_education, seeker_skills, seeker_experience, seeker_resumes, saved_jobs tables
- `seeker/delete_resume.php` — delete resume handler
- `seeker/save_job.php` — save/unsave job toggle

### Updated Files
- `seeker/seeker_profile.php` — added Skills section + Work Experience section (full CRUD in form)
- `seeker/seeker_resume.php` — added View / Download / Delete buttons; improved error messages
- `seeker/update_profile.php` — now saves skills and work experience to DB

### Already Complete (from employer_fix zip)
- `includes/auth.php` — requireLogin() + getUser() shared helpers
- `includes/seeker_navbar.php` — shared navbar with dark/light toggle, mobile menu
- `includes/navbar_employer.php` — employer navbar
- `employer/employer_applicants.php` — full applicant management (status update, interview schedule, resume view)
- `employer/employer_dashboard.php`, `employer/employer_manageJobs.php`, `employer/employer_companyProfile.php` — complete
- `seeker/antcareers_seekerSettings.php` — settings page
- All seeker pages use `includes/auth.php` and `includes/seeker_navbar.php`

---

## Test Checklist

- [ ] Register as seeker → login → complete profile (personal info + education + skills + experience)
- [ ] Upload resume → view → download → delete
- [ ] Browse Jobs page loads with job cards
- [ ] Companies page loads with company cards
- [ ] Dark/light toggle works and persists across pages
- [ ] Login as employer → view applicants → change status → schedule interview → view seeker resume
- [ ] Settings page loads from sidebar link
