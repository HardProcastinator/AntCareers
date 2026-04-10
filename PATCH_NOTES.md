# AntCareers — Settings Feature Patch
# Sidebar fix: replace the "Coming Soon" Settings button in 3 pages
# -----------------------------------------------------------------------
# In seeker/antcareers_seekerDashboard.php, seeker/antcareers_seekerApplications.php,
# and seeker/antcareers_seekerSaved.php, find this exact line:
#
#   <button class="sb-nav-item" onclick="showToast('Settings coming soon','fa-cog')"><i class="fas fa-cog"></i> Settings</button>
#
# Replace it with:
#
#   <a class="sb-nav-item" href="seeker/antcareers_seekerSettings.php"><i class="fas fa-cog"></i> Settings</a>
#
# That's the only change needed in those 3 files.
# The navbar (seeker_navbar.php) and the new settings page handle everything else.
