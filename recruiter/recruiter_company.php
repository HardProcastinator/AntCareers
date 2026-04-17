<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
requireLogin('recruiter');
$user        = getUser();
$fullName    = $user['fullName'];
$firstName   = $user['firstName'];
$initials    = $user['initials'];
$avatarUrl   = $user['avatarUrl'];
$companyName = $user['companyName'] ?: 'Your Company';
$navActive   = 'company';

$db  = getDB();
$uid = (int)$_SESSION['user_id'];

/* ── Recruiter → Company lookup ── */
$companyId  = 0;
$employerId = 0;
try {
    $s = $db->prepare("SELECT r.company_id, r.employer_id FROM recruiters r WHERE r.user_id = ? AND r.is_active = 1 LIMIT 1");
    $s->execute([$uid]);
    $rec = $s->fetch(PDO::FETCH_ASSOC);
    if ($rec) {
        $companyId  = (int)($rec['company_id'] ?? 0);
        $employerId = (int)($rec['employer_id'] ?? 0);
    }
} catch (PDOException $e) { error_log('[AntCareers] recruiter company lookup: ' . $e->getMessage()); }

/* ── Fetch company profile ── */
$cp = null;
if ($companyId > 0) {
    try {
        $s = $db->prepare("
            SELECT cp.*, u.full_name AS admin_name, u.email AS admin_email
            FROM company_profiles cp
            JOIN users u ON u.id = cp.user_id
            WHERE cp.id = ?
        ");
        $s->execute([$companyId]);
        $cp = $s->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) { error_log('[AntCareers] company profile fetch: ' . $e->getMessage()); }
}

/* ── Helpers ── */
function cpf(?array $p, string $key, string $default = ''): string {
    return htmlspecialchars((string)($p[$key] ?? $default), ENT_QUOTES, 'UTF-8');
}
function cpfImg(?array $p, string $key): string {
    $v = (string)($p[$key] ?? '');
    if ($v && !str_starts_with($v, '../') && !str_starts_with($v, 'http')) $v = '../' . $v;
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>AntCareers — Company Profile</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600;1,700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    :root {
      --red-deep:#7A1515; --red-mid:#B83525; --red-vivid:#D13D2C; --red-bright:#E85540; --red-pale:#F07060;
      --soil-dark:#0A0909; --soil-med:#131010; --soil-card:#1C1818; --soil-hover:#252020; --soil-line:#352E2E;
      --text-light:#F5F0EE; --text-mid:#D0BCBA; --text-muted:#927C7A;
      --amber:#D4943A; --amber-dim:#251C0E;
      --green:#4CAF70; --blue:#4A90D9;
      --font-display:'Playfair Display',Georgia,serif;
      --font-body:'Plus Jakarta Sans',system-ui,sans-serif;
    }
    html { overflow-x:hidden; }
    body { font-family:var(--font-body); background:var(--soil-dark); color:var(--text-light); overflow-x:hidden; min-height:100vh; -webkit-font-smoothing:antialiased; }

    .tunnel-bg { position:fixed; inset:0; pointer-events:none; z-index:0; overflow:hidden; }
    .tunnel-bg svg { width:100%; height:100%; opacity:0.05; }
    .glow-orb { position:fixed; border-radius:50%; filter:blur(90px); pointer-events:none; z-index:0; }
    .glow-1 { width:600px; height:600px; background:radial-gradient(circle,rgba(209,61,44,0.13) 0%,transparent 70%); top:-100px; left:-150px; animation:orb1 18s ease-in-out infinite alternate; }
    .glow-2 { width:400px; height:400px; background:radial-gradient(circle,rgba(209,61,44,0.06) 0%,transparent 70%); bottom:0; right:-80px; animation:orb2 24s ease-in-out infinite alternate; }
    @keyframes orb1 { to { transform:translate(60px,80px) scale(1.1); } }
    @keyframes orb2 { to { transform:translate(-40px,-50px) scale(1.1); } }

    /* PAGE */
    .page-shell { position:relative; z-index:1; max-width:960px; margin:0 auto; padding:36px 24px 80px; }
    .page-title { font-family:var(--font-display); font-size:26px; font-weight:700; color:#F5F0EE; margin-bottom:4px; }
    .page-sub { font-size:13px; color:var(--text-muted); margin-bottom:32px; }

    /* Cover + logo hero */
    .cover-section { position:relative; border-radius:14px; overflow:hidden; margin-bottom:28px; background:var(--soil-card); border:1px solid var(--soil-line); }
    .cover-img { height:180px; background:linear-gradient(135deg, #3A0F0F 0%, #1C0808 40%, #2A0A1A 100%); position:relative; overflow:hidden; }
    .cover-img::after { content:''; position:absolute; inset:0; background:repeating-linear-gradient(45deg, transparent, transparent 20px, rgba(209,61,44,0.04) 20px, rgba(209,61,44,0.04) 21px); }
    .cover-bottom { display:flex; align-items:flex-end; justify-content:space-between; padding:0 24px 20px; gap:16px; flex-wrap:wrap; }
    .company-logo-wrap { margin-top:-40px; position:relative; }
    .company-logo { width:88px; height:88px; border-radius:14px; background:var(--soil-hover); border:3px solid var(--soil-dark); display:flex; align-items:center; justify-content:center; font-size:32px; font-weight:800; color:var(--red-bright); font-family:var(--font-display); position:relative; overflow:hidden; box-shadow:0 4px 20px rgba(0,0,0,0.4); }
    .cover-company-name { font-size:20px; font-weight:700; color:#F5F0EE; font-family:var(--font-display); }
    .cover-company-sub { font-size:12px; color:var(--amber); font-weight:600; margin-top:2px; }

    /* Form cards (view-only) */
    .form-card { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:14px; padding:28px; margin-bottom:20px; }
    .fc-title { font-size:15px; font-weight:700; color:#F5F0EE; margin-bottom:16px; display:flex; align-items:center; gap:8px; }
    .fc-title i { color:var(--red-bright); font-size:14px; }
    .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
    .form-group { display:flex; flex-direction:column; gap:6px; }
    .form-group.full { grid-column:1/-1; }
    .form-label { font-size:12px; font-weight:600; color:var(--text-muted); letter-spacing:0.03em; text-transform:uppercase; }
    .form-value { font-size:14px; color:var(--text-mid); line-height:1.6; min-height:20px; white-space:pre-line; }
    .form-value a { color:var(--blue); text-decoration:none; font-weight:600; }
    .form-value a:hover { text-decoration:underline; }
    .form-value-empty { color:var(--text-muted); font-style:italic; }

    /* Social row (view-only) */
    .social-row { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; }
    .social-item { display:flex; align-items:center; gap:10px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:8px; padding:10px 12px; }
    .social-icon { width:30px; height:30px; border-radius:6px; display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0; }
    .si-linkedin { background:rgba(10,102,194,0.15); color:#0A66C2; }
    .si-fb { background:rgba(24,119,242,0.15); color:#1877F2; }
    .si-twitter { background:rgba(29,161,242,0.15); color:#1DA1F2; }
    .si-web { background:rgba(209,61,44,0.15); color:var(--red-bright); }
    .si-ig { background:rgba(193,53,132,0.15); color:#C13584; }
    .si-yt { background:rgba(255,0,0,0.15); color:#FF0000; }
    .social-text { font-size:13px; color:var(--text-mid); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .social-text a { color:var(--text-mid); text-decoration:none; }
    .social-text a:hover { color:var(--red-bright); text-decoration:underline; }
    .social-empty { font-size:13px; color:var(--text-muted); font-style:italic; }

    /* Perks (view-only) */
    .perks-grid { display:flex; flex-wrap:wrap; gap:8px; }
    .perk-chip { display:flex; align-items:center; gap:6px; background:rgba(209,61,44,0.12); border:1px solid var(--red-mid); border-radius:20px; padding:6px 12px; font-size:12px; color:var(--red-pale); font-weight:500; }
    .perk-chip i { font-size:10px; }

    /* Read-only notice */
    .readonly-notice { display:flex; align-items:center; gap:12px; background:var(--amber-dim); border:1px solid rgba(212,148,58,0.25); border-radius:10px; padding:14px 20px; margin-top:4px; margin-bottom:20px; }
    .readonly-notice i { color:var(--amber); font-size:18px; flex-shrink:0; }
    .readonly-notice span { font-size:13px; color:var(--amber); font-weight:500; line-height:1.5; }

    /* Empty state */
    .empty-state { text-align:center; padding:80px 20px; }
    .empty-state i { font-size:56px; color:var(--soil-line); margin-bottom:18px; }
    .empty-state h2 { font-family:var(--font-display); font-size:22px; color:var(--text-light); margin-bottom:10px; }
    .empty-state p { font-size:14px; color:var(--text-muted); max-width:400px; margin:0 auto; line-height:1.6; }

    /* Footer */
    .footer { position:relative; z-index:2; border-top:1px solid var(--soil-line); padding:20px 24px; display:flex; align-items:center; justify-content:space-between; font-size:12px; color:var(--text-muted); flex-wrap:wrap; gap:10px; }
    .footer-logo { font-family:var(--font-display); font-weight:700; font-size:15px; color:var(--red-bright); }

    /* Light mode */
    body.light {
      --soil-dark:#F9F5F4; --soil-card:#FFFFFF; --soil-hover:#FEF0EE; --soil-line:#E0CECA;
      --text-light:#1A0A09; --text-mid:#4A2828; --text-muted:#7A5555; --amber-dim:#FDF6EC; --amber:#B8620A;
    }
    body.light .glow-orb { display:none; }
    body.light .navbar { background:rgba(255,253,252,0.98); border-bottom-color:#D4B0AB; }
    body.light .logo-text { color:#1A0A09; }
    body.light .logo-text span { color:var(--red-vivid); }
    body.light .nav-link { color:#5A4040; }
    body.light .nav-link:hover, body.light .nav-link.active { color:#1A0A09; background:#FEF0EE; }
    body.light .theme-btn, body.light .notif-btn-nav, body.light .profile-btn, body.light .hamburger { background:#F5EDEB; border-color:#D4B0AB; }
    body.light .profile-name { color:#1A0A09; }
    body.light .profile-dropdown { background:#FFFFFF; border-color:#E0CECA; }
    body.light .pd-item { color:#4A2828; }
    body.light .pd-item:hover { background:#FEF0EE; color:#1A0A09; }
    body.light .pdh-name { color:#1A0A09; }
    body.light .mobile-menu { background:rgba(255,253,252,0.97); border-color:#E0CECA; }
    body.light .mobile-link { color:#4A2828; }
    body.light .page-title { color:#1A0A09; }
    body.light .page-sub { color:#7A5555; }
    body.light .cover-section { background:#FFFFFF; border-color:#E0CECA; }
    body.light .cover-company-name { color:#1A0A09; }
    body.light .cover-company-sub { color:var(--amber); }
    body.light .company-logo { border-color:#FFFFFF; background:#F5EEEC; }
    body.light .form-card { background:#FFFFFF; border-color:#E0CECA; }
    body.light .fc-title { color:#1A0A09; }
    body.light .form-value { color:#4A2828; }
    body.light .form-label { color:#7A5555; }
    body.light .social-item { background:#F5EEEC; border-color:#E0CECA; }
    body.light .social-text { color:#4A2828; }
    body.light .readonly-notice { background:#FDF6EC; border-color:rgba(212,148,58,0.3); }
    body.light .empty-state i { color:#E0CECA; }
    body.light .empty-state h2 { color:#1A0A09; }

    /* Responsive */
    @media (max-width:768px) {
      .page-shell { padding:20px 14px 48px; }
      .cover-img { height:140px; }
      .company-logo { width:64px; height:64px; }
      .form-grid { grid-template-columns:1fr; }
      .social-row { grid-template-columns:1fr; }
      .readonly-notice { flex-direction:column; text-align:center; gap:8px; }
    }
    @keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
    .anim{animation:fadeUp 0.4s ease both;}.anim-d1{animation-delay:0.05s;}.anim-d2{animation-delay:0.1s;}
  </style>
</head>
<body>

<div class="glow-orb glow-1"></div>
<div class="glow-orb glow-2"></div>

<?php require_once dirname(__DIR__) . '/includes/navbar_recruiter.php'; ?>

<div class="page-shell anim">

<?php if (!$cp): ?>
  <div class="empty-state">
    <i class="fas fa-building"></i>
    <h2>No Company Profile Found</h2>
    <p>Your recruiter account is not currently linked to a company profile. Please contact your employer administrator.</p>
  </div>
<?php else: ?>
  <?php
    $cpName   = cpf($cp, 'company_name', 'Company');
    $industry = cpf($cp, 'industry');
    $city     = cpf($cp, 'city');
    $province = cpf($cp, 'province');
  ?>

  <!-- Read-only notice -->
  <div class="readonly-notice">
    <i class="fas fa-lock"></i>
    <span>This profile is managed by the Company Admin. As a recruiter, you can view but not edit company information.</span>
  </div>

  <!-- Cover + Logo -->
  <div class="cover-section">
    <div class="cover-img"<?php if (!empty($cp['cover_path'])): ?> style="background-image:url('<?php echo cpfImg($cp,'cover_path'); ?>');background-size:cover;background-position:center;background-repeat:no-repeat;"<?php endif; ?>></div>
    <div class="cover-bottom">
      <div style="display:flex;align-items:flex-end;gap:16px;flex-wrap:wrap;">
        <div class="company-logo-wrap">
          <div class="company-logo"><?php
            if (!empty($cp['logo_path'])) {
                echo '<img src="' . cpfImg($cp,'logo_path') . '" style="width:100%;height:100%;object-fit:cover;border-radius:11px;" alt="Logo">';
            } else {
                echo htmlspecialchars(strtoupper(substr($cpName,0,1)), ENT_QUOTES, 'UTF-8');
            }
          ?></div>
        </div>
        <div>
          <div class="cover-company-name"><?php echo $cpName; ?></div>
          <div class="cover-company-sub"><i class="fas fa-map-marker-alt"></i> <?php echo $city ?: 'City'; ?>, <?php echo $province ?: 'Province'; ?> &nbsp;·&nbsp; <i class="fas fa-briefcase"></i> <?php echo $industry ?: 'Industry'; ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Basic Info -->
  <div class="form-card">
    <div class="fc-title"><i class="fas fa-info-circle"></i> Basic Information</div>
    <div class="form-grid">
      <div class="form-group">
        <div class="form-label">Company Name</div>
        <div class="form-value"><?php echo $cpName; ?></div>
      </div>
      <div class="form-group">
        <div class="form-label">Industry</div>
        <div class="form-value"><?php $v = cpf($cp,'industry'); echo $v ?: '<span class="form-value-empty">Not set</span>'; ?></div>
      </div>
      <div class="form-group">
        <div class="form-label">Company Size</div>
        <div class="form-value"><?php $v = cpf($cp,'company_size'); echo $v ?: '<span class="form-value-empty">Not set</span>'; ?></div>
      </div>
      <div class="form-group">
        <div class="form-label">Founded Year</div>
        <div class="form-value"><?php $v = cpf($cp,'founded_year'); echo $v ?: '<span class="form-value-empty">Not set</span>'; ?></div>
      </div>
      <div class="form-group">
        <div class="form-label">Company Type</div>
        <div class="form-value"><?php $v = cpf($cp,'company_type'); echo $v ?: '<span class="form-value-empty">Not set</span>'; ?></div>
      </div>
      <div class="form-group">
        <div class="form-label">Website</div>
        <div class="form-value"><?php
          $w = cpf($cp,'website');
          if ($w) { echo '<a href="' . $w . '" target="_blank" rel="noopener noreferrer">' . $w . '</a>'; }
          else { echo '<span class="form-value-empty">Not set</span>'; }
        ?></div>
      </div>
      <div class="form-group full">
        <div class="form-label">Company Tagline</div>
        <div class="form-value"><?php $v = cpf($cp,'tagline'); echo $v ?: '<span class="form-value-empty">Not set</span>'; ?></div>
      </div>
      <div class="form-group full">
        <div class="form-label">Company Description</div>
        <div class="form-value"><?php $v = cpf($cp,'about'); echo $v ?: '<span class="form-value-empty">Not set</span>'; ?></div>
      </div>
    </div>
  </div>

  <!-- Location -->
  <div class="form-card">
    <div class="fc-title"><i class="fas fa-map-marker-alt"></i> Location</div>
    <div class="form-grid">
      <div class="form-group">
        <div class="form-label">Country</div>
        <div class="form-value"><?php $v = cpf($cp,'country'); echo $v ?: '<span class="form-value-empty">Not set</span>'; ?></div>
      </div>
      <div class="form-group">
        <div class="form-label">City / Municipality</div>
        <div class="form-value"><?php echo $city ?: '<span class="form-value-empty">Not set</span>'; ?></div>
      </div>
      <div class="form-group">
        <div class="form-label">Province / State</div>
        <div class="form-value"><?php echo $province ?: '<span class="form-value-empty">Not set</span>'; ?></div>
      </div>
      <div class="form-group">
        <div class="form-label">ZIP Code</div>
        <div class="form-value"><?php $v = cpf($cp,'zip_code'); echo $v ?: '<span class="form-value-empty">Not set</span>'; ?></div>
      </div>
      <div class="form-group full">
        <div class="form-label">Full Address</div>
        <div class="form-value"><?php $v = cpf($cp,'address_line'); echo $v ?: '<span class="form-value-empty">Not set</span>'; ?></div>
      </div>
    </div>
  </div>

  <!-- Social Links -->
  <div class="form-card">
    <div class="fc-title"><i class="fas fa-share-alt"></i> Social &amp; Online Presence</div>
    <div class="social-row">
      <?php
      $socials = [
        ['key'=>'social_website',  'icon'=>'fas fa-globe',        'cls'=>'si-web',     'label'=>'Website'],
        ['key'=>'social_linkedin', 'icon'=>'fab fa-linkedin-in',  'cls'=>'si-linkedin', 'label'=>'LinkedIn'],
        ['key'=>'social_facebook', 'icon'=>'fab fa-facebook-f',   'cls'=>'si-fb',      'label'=>'Facebook'],
        ['key'=>'social_twitter',  'icon'=>'fab fa-twitter',      'cls'=>'si-twitter', 'label'=>'Twitter'],
        ['key'=>'social_instagram','icon'=>'fab fa-instagram',    'cls'=>'si-ig',      'label'=>'Instagram'],
        ['key'=>'social_youtube',  'icon'=>'fab fa-youtube',      'cls'=>'si-yt',      'label'=>'YouTube'],
      ];
      foreach ($socials as $soc):
        $val = cpf($cp, $soc['key']);
      ?>
      <div class="social-item">
        <div class="social-icon <?php echo $soc['cls']; ?>"><i class="<?php echo $soc['icon']; ?>"></i></div>
        <?php if ($val): ?>
          <span class="social-text"><a href="<?php echo $val; ?>" target="_blank" rel="noopener noreferrer"><?php echo $val; ?></a></span>
        <?php else: ?>
          <span class="social-empty">No <?php echo $soc['label']; ?> linked</span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Perks & Benefits -->
  <?php
  $savedPerks = [];
  if (!empty($cp['perks'])) {
      $decoded = json_decode($cp['perks'], true);
      if (is_array($decoded)) $savedPerks = $decoded;
  }
  $allPerks = [
      ['icon'=>'fa-laptop-house','label'=>'Remote Work'],
      ['icon'=>'fa-heartbeat','label'=>'HMO / Health Insurance'],
      ['icon'=>'fa-graduation-cap','label'=>'Learning & Development'],
      ['icon'=>'fa-umbrella-beach','label'=>'Paid Time Off'],
      ['icon'=>'fa-dumbbell','label'=>'Gym / Wellness'],
      ['icon'=>'fa-baby','label'=>'Parental Leave'],
      ['icon'=>'fa-coffee','label'=>'Free Snacks / Meals'],
      ['icon'=>'fa-car','label'=>'Transportation Allowance'],
      ['icon'=>'fa-chart-line','label'=>'Stock Options / Equity'],
      ['icon'=>'fa-handshake','label'=>'Competitive Salary'],
      ['icon'=>'fa-gamepad','label'=>'Game Room / Recreation'],
      ['icon'=>'fa-globe-asia','label'=>'International Exposure'],
  ];
  $activePerks = array_filter($allPerks, fn($p) => in_array($p['label'], $savedPerks));
  ?>
  <div class="form-card">
    <div class="fc-title"><i class="fas fa-heart"></i> Perks &amp; Benefits</div>
    <?php if ($activePerks): ?>
    <div class="perks-grid">
      <?php foreach ($activePerks as $perk): ?>
        <div class="perk-chip"><i class="fas <?php echo $perk['icon']; ?>"></i> <?php echo htmlspecialchars($perk['label'], ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="font-size:13px;color:var(--text-muted);font-style:italic;">No perks or benefits listed yet.</div>
    <?php endif; ?>
  </div>

<?php endif; ?>
</div>

<footer class="footer">
  <div class="footer-logo">AntCareers</div>
  <div>&copy; <?php echo date('Y'); ?> AntCareers</div>
</footer>

</body>
</html>
