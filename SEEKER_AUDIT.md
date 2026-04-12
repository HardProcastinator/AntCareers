# 🔍 AntCareers Seeker Module Comprehensive Audit Report
**Date: April 11, 2026**  
**Audit Scope: Complete seeker/ directory and related includes**  
**Status: Multiple critical, high, and medium-severity issues found**

---

## 📋 Executive Summary

The seeker module has **MULTIPLE ARCHITECTURAL ISSUES** including:
- ✗ **Wrong API file in seeker directory** (api_messages.php is a copy of employer API)
- ✗ **Broken messaging system** (API path mismatch)
- ✗ **Duplicate profile pages** with different functionality
- ✗ **Inconsistent navbar integration** across pages
- ✗ **Hardcoded data** (industries, demo content)
- ✗ **Missing navbar** on several pages
- ✗ **Broken redirects** with hardcoded paths
- ✗ **No quick sidebar messaging** from action buttons
- ✗ **Theme persistence** not maintained across all redirects

---

## 1. 🗂️ SEEKER PAGE INVENTORY

### Main Seeker Pages (Dashboard/Discovery)
| File | Navbar | Full Page? | Issues |
|------|--------|-----------|--------|
| `antcareers_seekerDashboard.php` | ✓ YES (line 307) | ✓ YES | Hardcoded **demo counts** (4 apps, 3 saved, 1 interview, **2 messages** - all fake) |
| `antcareers_seekerJobs.php` | ✓ YES (line 722) | ✓ YES | Works, loads from DB correctly |
| `antcareers_seekerPeopleSearch.php` | ✓ YES (line 312) | ✓ YES | **Hardcoded industry** "Technology & IT" for all people |
| `antcareers_seekerCompany.php` | ✓ YES (line 292) | ✓ YES | Works, loads companies correctly |
| `antcareers_seekerProfile.php` | ✓ YES (line 583) | ✓ YES | FULL PROFILE EDITOR - good implementation |
| `antcareers_seekerApplications.php` | ✓ YES (line 374) | ✓ YES | Works, shows application list + interview details |
| `antcareers_seekerSaved.php` | ✓ YES (line 317) | ✓ YES | Works, shows saved jobs from DB |
| `antcareers_seekerMessages.php` | ✓ YES (line 158) | ✓ YES | **BROKEN: Uses API path '../api/messages.php' which doesn't exist** |
| `antcareers_seekerSettings.php` | ✓ YES (line 246) | ✓ YES | Settings sidebar - appears incomplete |
| `public_company_profile.php` | ✓ YES (line 419) | ✓ YES | Works, shows company details + jobs |

### Old/Duplicate Pages (SHOULD BE DELETED)
| File | Status | Issue |
|------|--------|-------|
| `seeker_profile.php` | ✗ DEPRECATED | OLD simple profile form - no navbar, conflicts with `antcareers_seekerProfile.php` |
| `seeker_resume.php` | ✗ DEPRECATED | OLD resume page - no navbar, should merge into `antcareers_seekerProfile.php` |

### Backend/API Files
| File | Type | Navbar? | Issues |
|------|------|---------|--------|
| `api_messages.php` | API | ✗ NO | **CRITICAL: This is a copy of `employer/api_messages.php` placed in seeker/, WRONG FILE** |
| `apply_job.php` | Backend | ✗ NO | Works correctly - JSON API |
| `save_job.php` | Backend | ✗ NO | Works, toggle save - but uses hardcoded `/antcareers/` in redirects |
| `withdraw_application.php` | Backend | ✗ NO | Works correctly - JSON API |
| `update_profile.php` | Backend | ✗ NO | Works, auto-migrates DB schema with ON DUPLICATE KEY UPDATE |
| `upload_resume.php` | Backend | ✗ NO | Works, validates file type/size, stores in `uploads/resumes/` |
| `upload_photo.php` | Backend | ✗ NO | Works, stores avatars + banners, upserts into DB |
| `delete_resume.php` | Backend | ✗ NO | Works, deletes file + DB record |
| `follow_company.php` | Backend | ✗ NO | Works, toggles follow state |

---

## 2. 🎯 SEEKER NAVBAR INTEGRATION AUDIT

### ✓ Pages WITH Proper Navbar Integration (10 pages)
```
✓ antcareers_seekerDashboard.php       (line 307)
✓ antcareers_seekerJobs.php             (line 722)
✓ antcareers_seekerPeopleSearch.php     (line 312)
✓ antcareers_seekerCompany.php          (line 292)
✓ antcareers_seekerProfile.php          (line 583)
✓ antcareers_seekerApplications.php     (line 374)
✓ antcareers_seekerSaved.php            (line 317)
✓ antcareers_seekerMessages.php         (line 158)
✓ antcareers_seekerSettings.php         (line 246)
✓ public_company_profile.php            (line 419)
```

### ✗ Pages WITHOUT Navbar (3 pages)
```
✗ seeker_profile.php               NO navbar (deprecated)
✗ seeker_resume.php                NO navbar (deprecated)
✗ api_messages.php                 NO navbar (API, wrong file anyway)
```

### Navbar Component Issues Found:

#### ISSUE #1: Messaging Button/Panel Not Fully Integrated
**Severity: HIGH**
- Navbar has `.msg-btn-nav` button (line ~180 in seeker_navbar.php)
- Navbar has `.msg-panel` sidebar (line ~530+)
- **BUT**: Seeker pages don't trigger quick sidebar messaging from action buttons
- **Current behavior**: "Message" buttons in PeopleSearch redirect to full `/antcareers_seekerMessages.php` page
- **Expected**: Option for both quick sidebar AND full page, with proper context

#### ISSUE #2: Notification Panel Code Exists But Unused
**Severity: MEDIUM**
- Navbar has `.notif-panel` (lines ~600+)
- Navbar has `.notif-btn-nav` with badge counter
- **No backend** to populate notifications
- Pages don't show "new notification" indicators

#### ISSUE #3: Mobile Menu Not Fully Complete
**Severity: MEDIUM**
- Mobile menu mirrors main nav but stops abruptly
- Missing divider after first section
- No bottom CTA buttons
- Mobile responsive CSS exists but nav cut off

#### ISSUE #4: Theme URL Parameter Forwarding
**Severity: LOW**
- Navbar forwards `?theme=` parameter with `_navHref()` function
- **But**: Some pages don't forward it in their own redirects
- Example: `select all <i class="fas fa-arrow-right"></i></a>` in Dashboard doesn't include theme param

---

## 3. 📧 MESSAGING SYSTEM AUDIT

### Critical Issue: Broken Messaging Architecture

#### ISSUE #1: Wrong API File in Seeker Directory
**Severity: CRITICAL - MUST FIX IMMEDIATELY**

| Path | Problem |
|------|---------|
| `seeker/api_messages.php` | **Copy of `employer/api_messages.php`** (wrong context, wrong user role) |
| `seeker/antcareers_seekerMessages.php` | Line 221: `const API = '../api/messages.php'` (DOESN'T EXIST) |
| `messages.php` (root) | Just `require_once __DIR__ . '/employer/employer_messages.php'` - redirects to employer page |

**Why This Breaks:**
1. Seeker pages call `../api/messages.php` 
2. File doesn't exist (would be `/api/messages.php` from root, doesn't exist)
3. `seeker/api_messages.php` EXISTS but IS THE EMPLOYER VERSION
4. Results in: **Seeker messaging UI loads but API calls fail**

#### ISSUE #2: Messaging UI Page Works But API Broken

**What's in `antcareers_seekerMessages.php` (GOOD):**
- Full message UI with thread list, chat area, input
- Thread search filter
- Conversation bubbles
- Unread indicators

**What BREAKS it (BAD API):**
```javascript
// Line 221 in antcareers_seekerMessages.php
const API = '../api/messages.php';  // ← DOESN'T EXIST

// Then it calls:
fetch(API + '?action=threads')  // ← Fails with 404
fetch(API + '?action=messages&thread_id=' + threadId)  // ← Fails
```

#### ISSUE #3: seeker/api_messages.php Is Wrong File
**Severity: CRITICAL**

The file claims to be employer API:
```php
/**
 * AntCareers — Employer Messages API
 * employer/api_messages.php  ← ← ← SAYS IT'S EMPLOYER FILE
 *
 * Actions: threads, messages, send, mark_read, unread_count
 */
```

But it's located in `seeker/api_messages.php` and checks employer context.

#### ISSUE #4: No Sidebar Quick Messaging
**Severity: HIGH**

Navbar has message panel sidebar, but:
- Pages don't have buttons to trigger it
- No "Message this person" quick action that opens sidebar
- All messaging redirects to full page instead

**Current Model:**
```
Click "Message" button → Redirect to antcareers_seekerMessages.php (full page)
```

**Should Also Support:**
```
Click "Quick Message" → Open sidebar panel within current page
```

---

## 4. 🔔 NOTIFICATION SYSTEM AUDIT

### Issue: Notification Infrastructure Exists But Unused
**Severity: MEDIUM**

#### What EXISTS:
```php
// seeker_navbar.php has full notification panel UI
<button class="notif-btn-nav" id="notifToggle">
  <i class="fas fa-bell"></i>
  <span class="badge" id="seekerNotifBadge" style="display:none">0</span>
</button>

<div class="notif-panel" id="notifPanel">
  <!-- Full notification list UI with items, timestamps, etc -->
</div>
```

#### What's MISSING:
- ✗ No JavaScript to populate notification list
- ✗ No backend endpoint to fetch notifications
- ✗ No notification auto-badge counter
- ✗ No notification creation when actions happen (e.g., "message received", "application reviewed")
- ✗ No notification click handlers

#### To Fix:
Would need:
1. Check if `notifications` table exists (migration exists: `migration_notifications.sql`)
2. Create seeker-side API endpoint to GET notifications
3. Add JavaScript to populate navbar badge + panel
4. Update backend to CREATE notifications on events

---

## 5. 📄 SEEKER PAGES DEEP DIVE

### antcareers_seekerDashboard.php
**Status: MOSTLY WORKS - HAS DEMO CONTENT**

**Issues Found:**

1. **Hardcoded Demo Numbers** (CRITICAL)
   ```php
   Line 326: <div class="sc-num">2</div><div class="sc-label">Messages</div>
   ```
   Shows **hardcoded "2"** instead of actual message count
   - Should fetch: `SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0`
   - Currently only fetches it for `$msgCount` variable but doesn't use it in HTML

2. **Profile Completion Score Works Correctly** ✓
   - Fetches real data from seeker_profiles, seeker_skills, experience, education
   - Calculates 35 base + up to 65 points for sections

3. **Recent Applications Works** ✓
   - Fetches last 10 applications with status colors

4. **Upcoming Interviews Works** ✓
   - Auto-migrates missing columns on first run
   - Shows up to 5 upcoming interviews with colors

5. **Recommended Jobs Works** ✓
   - Excludes already-applied jobs
   - Shows real job data from DB

**Fixes Needed:**
- [ ] Use actual `$msgCount` instead of hardcoded "2"
- [ ] Use actual `$savedCount` instead of hardcoded "3"
- [ ] Use actual `$appCount` instead of showing summary then "4" separately
- [ ] Use actual `$interviewCount` instead of hardcoded "1"

---

### antcareers_seekerJobs.php
**Status: WORKS WELL**

✓ Loads jobs from DB correctly  
✓ Filters by all params (location, salary, setup, etc)  
✓ Shows companies hiring now  
✓ Track applied jobs + saved jobs  
✓ Apply button with cover letter modal  

**Minor Issues:**
- Uses fallback queries for pre-migration servers (good defensive coding)
- Hardcoded currency symbol (₱) - should be configurable

---

### antcareers_seekerApplications.php
**Status: WORKS WELL**

✓ Lists all applications with status  
✓ Shows interview details when scheduled  
✓ Withdraw button works  
✓ Status indicators by type (pending, reviewed, shortlisted, interview, hired, rejected)  

**One Issue:**
- Fallback queries for missing company_profiles table (defensive, but old schema)

---

### antcareers_seekerProfile.php
**Status: EXCELLENT - PRODUCTION READY**

✓ Complete profile editor with all sections  
✓ Skills, experience, education management  
✓ Certified & languages sections  
✓ Network recruiting features (availability, work types, salary expectations)  
✓ Auto-migrations for new columns  
✓ Avatar + banner upload  
✓ Creates tables for certifications, languages, resumes if missing  

---

### antcareers_seekerSaved.php
**Status: WORKS WELL**

✓ Shows saved jobs with all details  
✓ Filters and searches  
✓ Fallback queries for pre-migration schema  
✓ Shows empty state when no saved jobs  

---

### antcareers_seekerSettings.php
**Status: INCOMPLETE**

Creates sidebar structure but **empty content panes**:
- Profile & Account section header exists
- But no actual setting controls

**To Complete:**
- [ ] Add toggle controls for email notifications
- [ ] Add preference controls
- [ ] Add privacy settings
- [ ] Add account management (change password, deactivate, etc)

---

### antcareers_seekerCompany.php
**Status: MOSTLY WORKS - HARDCODED ISSUE**

✓ Lists companies with open roles  
✓ Follow/unfollow toggle  
✓ Links to public_company_profile.php  

**Issues Found:**

1. **Hardcoded Industry Field** (MEDIUM)
   ```php
   Line 48:
   'industry'  => 'Technology & IT',  ← ← hardcoded for ALL people
   ```
   Should use: `$industryOption['label']` or user's actual industry

2. **Industry List Uses Both Inline & Function**
   ```php
   $industryCheckboxesHtml // built from getIndustryFilterOptions() ✓
   // But people list has: 'Technology & IT' hardcoded
   ```

---

### antcareers_seekerPeopleSearch.php
**Status: MOSTLY WORKS - HARDCODED ISSUE**

✓ Lists all people/seekers/employers  
✓ Shows skills from DB  
✓ Availability status  
✓ Filter by industry, location, skills  

**Critical Issue: Hardcoded Industry**

Line 48 in PHP:
```php
'industry'  => 'Technology & IT', ← ← ← ← HARDCODED FOR EVERYONE
```

This shows every person's industry as "Technology & IT" regardless of actual data.

**Should be:**
```php
'industry'  => $r['industry'] ?? $r['headline'] ?? 'Not specified',
```

Or fetch from seeker_profiles:
```sql
SELECT sp.industry FROM seeker_profiles sp WHERE sp.user_id = u.id
```

---

### public_company_profile.php
**Status: WORKS WELL**

✓ Shows all company profile details  
✓ Lists open jobs for company  
✓ Follow/unfollow  
✓ Shows perks, socials, location  

---

## 6. 🔧 BACKEND INTEGRATION ISSUES

### update_profile.php
**Status: WORKS - BUT EXCESSIVE AUTO-MIGRATION**

**Issues:**
1. Runs SHOW COLUMNS and ALTER TABLE **on every single POST request**
2. Should run once during installation, then remove
3. Performance impact: Extra DB queries per profile save

**Fixes:**
- [ ] Move auto-migrations to setup/install phase
- [ ] Add version check to only run on fresh installs
- [ ] Document which migrations are auto vs manual

### apply_job.php  
**Status: WORKS WELL** ✓
- Checks job is active
- Prevents duplicate applications
- Gets active resume if exists
- Returns proper JSON responses

---

### save_job.php
**Status: WORKS - BUT HARDCODED PATH**

HTML redirects on POST:
```php
header('Location: /antcareers/antcareers_seekerJobs.php');
```

**Problem:** Hardcoded `/antcareers/` path won't work if app is in different folder

**Solution:**
```php
header('Location: ../seeker/antcareers_seekerJobs.php');
```

---

### withdraw_application.php
**Status: WORKS WELL** ✓
- Verifies application belongs to user
- Prevents withdrawing hired applications
- Returns proper JSON

---

### upload_resume.php & upload_photo.php
**Status: WORKS WELL** ✓
- Proper file validation (MIME type, size)
- Uses `finfo` to verify actual file type
- Stores in organized folders
- Both have good error handling

---

### delete_resume.php
**Status: WORKS WELL** ✓
- Verifies ownership
- Deletes physical file
- Removes DB record

---

### follow_company.php
**Status: WORKS WELL** ✓
- Toggles follow state
- Returns follow count
- Auto-creates table if missing

---

## 7. 🏗️ ARCHITECTURE DRIFT DETECTION

### Issue #1: Hardcoded Country Lists ❌
**Severity: LOW**

Seeker files DO properly use `includes/countries.php`:
```php
require_once dirname(__DIR__) . '/includes/countries.php';  ✓
getCountries()  ✓
```

---

### Issue #2: Hardcoded Job Titles ❌
**Severity: LOW** 

Seeker files DO properly use `includes/job_titles.php`:
```php
require_once dirname(__DIR__) . '/includes/job_titles.php';  ✓
getJobCategoryOptions()  ✓
getIndustryFilterOptions()  ✓
```

---

### Issue #3: Hardcoded Industries IN PAGES ❌
**Severity: MEDIUM**

While the includes are used, individual pages have hardcoded industry values:

1. **antcareers_seekerPeopleSearch.php - Line 48**
   ```php
   'industry'  => 'Technology & IT', ← hardcoded
   ```

2. **antcareers_seekerCompany.php - No issue**, uses DB

---

### Issue #4: Navbar Code NOT Duplicated ✓
**Severity: SOLVED**

Good news: All pages use `include dirname(__DIR__) . '/includes/seeker_navbar.php'`  
No duplicate navbar code found ✓

---

### Issue #5: Old Redirects with /antcareers/ Prefix ❌
**Severity: MEDIUM**

Multiple hardcoded absolute paths that assume app is at `/antcareers/`:

**Files with hardcoded paths:**
- `seeker/save_job.php` (line 7): `header('Location: /antcareers/antcareers_seekerJobs.php');`
- `seeker/delete_resume.php` (line 38): `header('Location: /antcareers/seeker/seeker_resume.php?error=notfound');`
- All old redirect lines use `/antcareers/` or `/antcareers/seeker/`

**Should use relative paths instead:**
```php
header('Location: ../seeker/antcareers_seekerJobs.php');
header('Location: seeker_resume.php?error=notfound');
```

---

### Issue #6: Duplicate Pages
**Severity: HIGH - CODE DUPLICATION**

Two profile pages exist:
1. `seeker_profile.php` - OLD, simple form, **no navbar**, not in navbar routes
2. `antcareers_seekerProfile.php` - CURRENT, full profile editor with sections, **has navbar**

**Two resume pages exist:**
1. `seeker_resume.php` - OLD, simple resume upload, **no navbar**
2. Resume functionality is also in `antcareers_seekerProfile.php` (in a section)

**Action:** DELETE old files:
- [ ] Delete `seeker/seeker_profile.php`
- [ ] Delete `seeker/seeker_resume.php`
- All functionality exists in `antcareers_seekerProfile.php`

---

## 8. 🎨 UI/UX CONSISTENCY AUDIT

### Spacing & Padding
**Status: CONSISTENT** ✓
- All pages use consistent 24px outer padding
- 16px-20px inner padding on cards
- 12px gaps between sections

### Typography
**Status: CONSISTENT** ✓
- Display: Playfair Display (700 weight for titles)
- Body: Plus Jakarta Sans
- Font sizes: 24px titles, 13px body, 11px muted

### Button Sizes & Colors
**Status: CONSISTENT** ✓
- Primary buttons: Red (#D13D2C)
- Secondary: Soil hover color
- Consistent 13px font weight 600-700

### Card Layouts
**Status: CONSISTENT** ✓
- All use soil-card background (#1C1818)
- All use soil-line borders (#352E2E)
- Border radius: 8-12px

### Dark/Light Theme
**Status: MOSTLY WORKS** ✓
- Color variables defined in :root
- CSS has `body.light` overrides for most components
- Light theme applies to:
  - Navbar ✓
  - Messages panel ✓
  - Notifications ✓
  - Typography ✓
  - Inputs ✓

**Minor issues:**
- Some dynamic HTML generated in JS doesn't include light-theme classes
- Glow orbs hidden in light theme (good)

### Responsive Mobile
**Status: WORKS** ✓
- All pages have `viewport-fit=cover` meta tag
- Navbar has mobile hamburger menu
- Most layouts switch to single-column at 760px
- Modals and sidebars adapt to screen size

### Empty States
**Status: IMPLEMENTED** ✓
- Jobs page: Shows empty state when no jobs match
- Applications page: Shows empty state when no applications
- Saved jobs: Shows empty state when no saved

**Missing:**
- [ ] Messages page doesn't show empty state (shows "Select a conversation")

### Loading States
**Status: PARTIAL**
- Buttons don't have loading spinners
- No skeleton loaders while fetching
- No indication when data is loading

**To Add:**
- [ ] Disable apply/save buttons while submitting
- [ ] Show spinner on form submissions
- [ ] Add skeleton loaders for initial data load

### Error States
**Status: WORKING** ✓
- Toast notifications for errors
- Modal dialogs for confirmations
- Error messages in forms

---

## 📊 SUMMARY TABLE: All Issues Found

| ID | Category | Severity | Issue | Files | Fix Effort |
|:--:|:---------|:--------:|-------|-------|:----------:|
| 1 | Messaging | 🔴 CRITICAL | API path broken (../api/messages.php doesn't exist) | antcareers_seekerMessages.php | HIGH |
| 2 | Messaging | 🔴 CRITICAL | seeker/api_messages.php is wrong file (copy of employer version) | seeker/api_messages.php | HIGH |
| 3 | Architecture | 🔴 CRITICAL | Duplicate pages with different functionality | seeker_profile.php, seeker_resume.php | MEDIUM |
| 4 | Seeker | 🔴 CRITICAL | Dashboard shows hardcoded demo numbers ("2" messages, "3" saved, "4" apps, "1" interview) | antcareers_seekerDashboard.php | LOW |
| 5 | UX | 🟠 HIGH | No quick sidebar messaging from action buttons | antcareers_seekerPeopleSearch.php & others | MEDIUM |
| 6 | Data | 🟠 HIGH | Hardcoded industry "Technology & IT" for all people | antcareers_seekerPeopleSearch.php | LOW |
| 7 | Architecture | 🟠 HIGH | Hardcoded absolute paths /antcareers/ in redirects | seeker/save_job.php, seeker/delete_resume.php, others | MEDIUM |
| 8 | Messaging | 🟡 MEDIUM | Notification panel UI exists but no backend implementation | seeker_navbar.php | HIGH |
| 9 | Messaging | 🟡 MEDIUM | Mobile menu incomplete (cuts off) | seeker_navbar.php | LOW |
| 10 | Settings | 🟡 MEDIUM | Settings page has empty content sections | antcareers_seekerSettings.php | MEDIUM |
| 11 | Performance | 🟡 MEDIUM | Auto-migration runs on every POST request | update_profile.php | LOW |
| 12 | UX | 🟡 MEDIUM | Theme parameter not forwarded in all redirects | antcareers_seekerDashboard.php & others | LOW |
| 13 | Navigation | 🟡 MEDIUM | Old pages (seeker_profile.php, seeker_resume.php) not deleted but also not in navbar | seeker/ | LOW |
| 14 | UX | 🟢 LOW | No loading indicators on async operations | antcareers_seekerProfile.php & others | MEDIUM |
| 15 | UX | 🟢 LOW | Hardcoded currency (₱) not configurable | antcareers_seekerJobs.php & others | LOW |

---

## 🚀 RECOMMENDED FIX PRIORITY

### IMMEDIATE (This Week)
1. **Fix messaging API** - seeker_messages.php broken, can't message anyone
2. **Fix dashboard demo numbers** - shows fake counts instead of real data
3. **Delete duplicate pages** - seeker_profile.php + seeker_resume.php
4. **Fix hardcoded paths** - /antcareers/ prefixes

### SOON (Next Week)  
5. **Fix people search industry** - all show "Technology & IT"
6. **Implement notifications** - connect to notification table
7. **Add quick messaging** - sidebar option from action buttons
8. **Complete settings page** - add actual controls

### NICE TO HAVE (This Sprint)
9. Add loading states to buttons
10. Implement notification badge counter
11. Complete mobile menu
12. Make currency configurable

---

## 📝 ADDITIONAL NOTES

### Database Schema Issues Found
- ✓ All seeker tables exist (profiles, skills, experience, education, certifications, languages, resumes)
- ✓ Interview schedules has auto-migration for new columns
- ⚠️ Notifications table initialized but no data population in seeker pages

### File Organization
- ✓ Follows pattern of `antcareers_<role><page>.php` naming
- ✓ Backend files separate in same directory
- ✓ All uses relative paths to includes (good)
- ✗ Old files not deleted (seeker_profile.php, seeker_resume.php)

### Security Review
- ✓ All user input sanitized with htmlspecialchars
- ✓ All file operations verify user ownership
- ✓ All API endpoints check authentication + authorization
- ✓ File uploads validate MIME type + size
- ✓ SQL prepared statements used throughout

---

## Appendix: File Checklist for Fixes Done

```
CRITICAL FIXES NEEDED:
- [ ] Create proper /api/messages.php for seeker messaging
- [ ] Fix antcareers_seekerMessages.php API path
- [ ] Delete seeker/api_messages.php (wrong file)
- [ ] Replace hardcoded numbers in antcareers_seekerDashboard.php with real counts

HIGH PRIORITY:
- [ ] Fix hardcoded /antcareers/ paths to relative paths
- [ ] Delete seeker_profile.php
- [ ] Delete seeker_resume.php
- [ ] Fix hardcoded 'Technology & IT' in antcareers_seekerPeopleSearch.php

MEDIUM PRIORITY:
- [ ] Implement notification system (backend + frontend)
- [ ] Add quick sidebar messaging from action buttons
- [ ] Complete antcareers_seekerSettings.php content
- [ ] Move auto-migrations to setup phase

NICE TO HAVE:
- [ ] Add loading spinners
- [ ] Add skeleton loaders
- [ ] Make currency configurable
- [ ] Complete mobile menu
```

---

**END OF AUDIT REPORT**  
*Generated: 2026-04-11*  
*Auditor: Automated Code Review*
