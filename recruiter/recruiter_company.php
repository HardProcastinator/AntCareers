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
    body.light { --soil-dark:#F9F5F4; --soil-card:#FFFFFF; --soil-hover:#FEF0EE; --soil-line:#E0CECA; --text-light:#1A0A09; --text-mid:#4A2828; --text-muted:#7A5555; }

    /* ── Background orbs ── */
    .glow-orb { position:fixed; border-radius:50%; filter:blur(90px); pointer-events:none; z-index:0; }
    .glow-1 { width:600px; height:600px; background:radial-gradient(circle,rgba(209,61,44,0.13) 0%,transparent 70%); top:-100px; left:-150px; animation:orb1 18s ease-in-out infinite alternate; }
    .glow-2 { width:400px; height:400px; background:radial-gradient(circle,rgba(209,61,44,0.06) 0%,transparent 70%); bottom:0; right:-80px; animation:orb2 24s ease-in-out infinite alternate; }
    @keyframes orb1 { to { transform:translate(60px,80px) scale(1.1); } }
    @keyframes orb2 { to { transform:translate(-40px,-50px) scale(1.1); } }
    body.light .glow-orb { display:none; }

    /* ── Page shell ── */
    .page-shell { position:relative; z-index:1; max-width:960px; margin:0 auto; padding:32px 20px 60px; }

    /* ── Banner / Cover ── */
    .company-banner { position:relative; width:100%; height:200px; border-radius:14px; overflow:hidden; background:linear-gradient(135deg,var(--red-deep) 0%,var(--soil-card) 100%); }
    .company-banner img { width:100%; height:100%; object-fit:cover; }
    .company-logo-wrap { position:absolute; left:32px; bottom:-40px; width:80px; height:80px; border-radius:50%; border:4px solid var(--soil-dark); background:var(--soil-card); overflow:hidden; display:flex; align-items:center; justify-content:center; box-shadow:0 4px 16px rgba(0,0,0,0.3); }
    .company-logo-wrap img { width:100%; height:100%; object-fit:cover; }
    .company-logo-placeholder { font-size:28px; font-weight:700; color:var(--red-vivid); font-family:var(--font-display); }
    body.light .company-logo-wrap { border-color:#FFFFFF; background:#FFFFFF; }
    body.light .company-banner { background:linear-gradient(135deg,#e8c4c0 0%,#FFFFFF 100%); }

    /* ── Info card ── */
    .info-card { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:14px; padding:56px 32px 32px; margin-top:-24px; position:relative; }
    .info-card h1 { font-family:var(--font-display); font-size:28px; font-weight:700; color:var(--text-light); margin-bottom:10px; }
    .industry-badge { display:inline-block; padding:4px 14px; border-radius:20px; background:rgba(209,61,44,0.12); color:var(--red-bright); font-size:12px; font-weight:700; margin-bottom:16px; }
    .meta-row { display:flex; flex-wrap:wrap; gap:18px; margin-bottom:18px; }
    .meta-item { display:flex; align-items:center; gap:7px; font-size:13px; color:var(--text-mid); }
    .meta-item i { color:var(--text-muted); width:14px; text-align:center; font-size:12px; }
    .meta-item a { color:var(--blue); text-decoration:none; font-weight:600; }
    .meta-item a:hover { text-decoration:underline; }
    .description-text { font-size:14px; line-height:1.8; color:var(--text-mid); white-space:pre-line; }

    /* ── Section cards ── */
    .section-card { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:14px; padding:24px 28px; margin-top:20px; }
    .section-card h2 { font-family:var(--font-display); font-size:18px; font-weight:700; color:var(--text-light); margin-bottom:14px; display:flex; align-items:center; gap:10px; }
    .section-card h2 i { color:var(--red-vivid); font-size:16px; }
    .section-body { font-size:14px; line-height:1.8; color:var(--text-mid); white-space:pre-line; }

    /* ── Social links ── */
    .social-row { display:flex; flex-wrap:wrap; gap:12px; }
    .social-link { display:inline-flex; align-items:center; gap:8px; padding:8px 18px; border-radius:8px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-mid); font-size:13px; font-weight:600; text-decoration:none; transition:0.2s; }
    .social-link:hover { color:var(--text-light); border-color:var(--red-vivid); background:rgba(209,61,44,0.08); }
    .social-link i { font-size:15px; }
    body.light .social-link { background:#F5EEEC; border-color:#E0CECA; color:#4A2828; }
    body.light .social-link:hover { background:#FEF0EE; border-color:var(--red-vivid); color:#1A0A09; }

    /* ── Read-only notice ── */
    .readonly-notice { display:flex; align-items:center; gap:12px; background:var(--amber-dim); border:1px solid rgba(212,148,58,0.25); border-radius:10px; padding:14px 20px; margin-top:24px; }
    .readonly-notice i { color:var(--amber); font-size:18px; flex-shrink:0; }
    .readonly-notice span { font-size:13px; color:var(--amber); font-weight:500; line-height:1.5; }
    body.light .readonly-notice { background:#FDF6EC; border-color:rgba(212,148,58,0.3); }

    /* ── Empty state ── */
    .empty-state { text-align:center; padding:80px 20px; }
    .empty-state i { font-size:56px; color:var(--soil-line); margin-bottom:18px; }
    .empty-state h2 { font-family:var(--font-display); font-size:22px; color:var(--text-light); margin-bottom:10px; }
    .empty-state p { font-size:14px; color:var(--text-muted); max-width:400px; margin:0 auto; line-height:1.6; }

    /* ── Light theme — cards ── */
    body.light .info-card, body.light .section-card { background:#FFFFFF; border-color:#E0CECA; box-shadow:0 2px 12px rgba(0,0,0,0.05); }
    body.light .info-card h1, body.light .section-card h2 { color:#1A0A09; }
    body.light .meta-item { color:#4A2828; }
    body.light .meta-item i { color:#7A5555; }
    body.light .description-text, body.light .section-body { color:#4A2828; }
    body.light .industry-badge { background:rgba(209,61,44,0.08); }
    body.light .empty-state i { color:#E0CECA; }
    body.light .empty-state h2 { color:#1A0A09; }

    /* ── Toast ── */
    .toast{position:fixed;bottom:30px;right:30px;background:var(--soil-card);border:1px solid var(--soil-line);border-radius:10px;padding:14px 22px;font-size:13px;color:var(--text-light);z-index:9999;opacity:0;transform:translateY(10px);transition:0.3s;pointer-events:none;box-shadow:0 8px 28px rgba(0,0,0,0.4);}
    .toast.show{opacity:1;transform:translateY(0);pointer-events:auto;}

    /* ── Footer ── */
    .footer { text-align:center; padding:36px 20px 28px; font-size:12px; color:var(--text-muted); border-top:1px solid var(--soil-line); margin-top:48px; }
    .footer-logo { font-family:var(--font-display); font-size:18px; font-weight:700; color:var(--red-vivid); margin-bottom:6px; }
    body.light .footer { border-color:#E0CECA; }

    /* ── Responsive ── */
    @media (max-width:768px) {
      .nav-links { display:none; }
      .hamburger { display:flex; }
      .btn-nav-red { display:none; }
      .page-shell { padding:20px 14px 48px; }
      .company-banner { height:140px; border-radius:10px; }
      .company-logo-wrap { width:64px; height:64px; left:20px; bottom:-32px; }
      .info-card { padding:44px 20px 24px; margin-top:-16px; }
      .info-card h1 { font-size:22px; }
      .meta-row { gap:12px; }
      .section-card { padding:20px; }
      .readonly-notice { flex-direction:column; text-align:center; gap:8px; }
    }
    @media (max-width:480px) {
      .social-row { flex-direction:column; }
      .social-link { justify-content:center; }
    }
  </style>
</head>
<body>

<div class="glow-orb glow-1"></div>
<div class="glow-orb glow-2"></div>

<?php require_once dirname(__DIR__) . '/includes/navbar_recruiter.php'; ?>

<div class="page-shell">

<?php if (!$cp): ?>
  <!-- No company linked -->
  <div class="empty-state">
    <i class="fas fa-building"></i>
    <h2>No Company Profile Found</h2>
    <p>Your recruiter account is not currently linked to a company profile. Please contact your employer administrator.</p>
  </div>
<?php else: ?>
  <?php
    $bannerSrc = cpfImg($cp, 'cover_path');
    $logoSrc   = cpfImg($cp, 'logo_path');
    $cpName    = cpf($cp, 'company_name', 'Company');
    $industry  = cpf($cp, 'industry');
    $location  = cpf($cp, 'location');
    $city      = cpf($cp, 'city');
    $province  = cpf($cp, 'province');
    $country   = cpf($cp, 'country');
    $size      = cpf($cp, 'company_size');
    $cpType    = cpf($cp, 'company_type');
    $founded   = cpf($cp, 'founded_year');
    $website   = cpf($cp, 'website');
    $tagline   = cpf($cp, 'tagline');
    $phone     = cpf($cp, 'contact_phone');
    $email     = cpf($cp, 'contact_email');
    $desc      = cpf($cp, 'about');
    $perks     = cpf($cp, 'perks');
    $linkedin  = cpf($cp, 'social_linkedin');
    $facebook  = cpf($cp, 'social_facebook');
    $twitter   = cpf($cp, 'social_twitter');
    $instagram = cpf($cp, 'social_instagram');
    $youtube   = cpf($cp, 'social_youtube');
    $socWeb    = cpf($cp, 'social_website');

    // Build location string
    $locParts = array_filter([$city, $province, $country]);
    $locStr   = $locParts ? implode(', ', $locParts) : $location;
  ?>

  <!-- Banner -->
  <div class="company-banner">
    <?php if ($bannerSrc): ?>
      <img src="<?= $bannerSrc ?>" alt="Company banner">
    <?php endif; ?>
    <div class="company-logo-wrap">
      <?php if ($logoSrc): ?>
        <img src="<?= $logoSrc ?>" alt="<?= $cpName ?> logo">
      <?php else: ?>
        <span class="company-logo-placeholder"><?= mb_substr($cp['company_name'] ?? 'C', 0, 1, 'UTF-8') ?></span>
      <?php endif; ?>
    </div>
  </div>

  <!-- Info card -->
  <div class="info-card">
    <h1><?= $cpName ?></h1>
    <?php if ($tagline): ?>
      <div style="font-size:14px;color:var(--text-muted);margin-bottom:10px;"><?= $tagline ?></div>
    <?php endif; ?>
    <?php if ($industry): ?>
      <span class="industry-badge"><i class="fas fa-industry"></i> <?= $industry ?></span>
    <?php endif; ?>

    <div class="meta-row">
      <?php if ($locStr): ?>
        <span class="meta-item"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($locStr, ENT_QUOTES, 'UTF-8') ?></span>
      <?php endif; ?>
      <?php if ($size): ?>
        <span class="meta-item"><i class="fas fa-users"></i> <?= $size ?></span>
      <?php endif; ?>
      <?php if ($cpType): ?>
        <span class="meta-item"><i class="fas fa-building"></i> <?= $cpType ?></span>
      <?php endif; ?>
      <?php if ($founded): ?>
        <span class="meta-item"><i class="fas fa-calendar-alt"></i> Founded <?= $founded ?></span>
      <?php endif; ?>
      <?php if ($website): ?>
        <span class="meta-item"><i class="fas fa-globe"></i> <a href="<?= $website ?>" target="_blank" rel="noopener noreferrer"><?= $website ?></a></span>
      <?php endif; ?>
    </div>

    <?php if ($phone || $email): ?>
    <div class="meta-row">
      <?php if ($phone): ?>
        <span class="meta-item"><i class="fas fa-phone"></i> <?= $phone ?></span>
      <?php endif; ?>
      <?php if ($email): ?>
        <span class="meta-item"><i class="fas fa-envelope"></i> <?= $email ?></span>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($desc): ?>
      <div class="description-text"><?= $desc ?></div>
    <?php endif; ?>
  </div>

  <!-- Perks -->
  <?php if ($perks): ?>
  <div class="section-card">
    <h2><i class="fas fa-gift"></i> Perks &amp; Benefits</h2>
    <div class="section-body"><?= $perks ?></div>
  </div>
  <?php endif; ?>

  <!-- Social Links -->
  <?php if ($linkedin || $facebook || $twitter || $instagram || $youtube || $socWeb): ?>
  <div class="section-card">
    <h2><i class="fas fa-share-alt"></i> Social Links</h2>
    <div class="social-row">
      <?php if ($socWeb): ?>
        <a class="social-link" href="<?= $socWeb ?>" target="_blank" rel="noopener noreferrer"><i class="fas fa-globe"></i> Website</a>
      <?php endif; ?>
      <?php if ($linkedin): ?>
        <a class="social-link" href="<?= $linkedin ?>" target="_blank" rel="noopener noreferrer"><i class="fab fa-linkedin"></i> LinkedIn</a>
      <?php endif; ?>
      <?php if ($facebook): ?>
        <a class="social-link" href="<?= $facebook ?>" target="_blank" rel="noopener noreferrer"><i class="fab fa-facebook"></i> Facebook</a>
      <?php endif; ?>
      <?php if ($twitter): ?>
        <a class="social-link" href="<?= $twitter ?>" target="_blank" rel="noopener noreferrer"><i class="fab fa-x-twitter"></i> X / Twitter</a>
      <?php endif; ?>
      <?php if ($instagram): ?>
        <a class="social-link" href="<?= $instagram ?>" target="_blank" rel="noopener noreferrer"><i class="fab fa-instagram"></i> Instagram</a>
      <?php endif; ?>
      <?php if ($youtube): ?>
        <a class="social-link" href="<?= $youtube ?>" target="_blank" rel="noopener noreferrer"><i class="fab fa-youtube"></i> YouTube</a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Read-only notice -->
  <div class="readonly-notice">
    <i class="fas fa-info-circle"></i>
    <span>You're viewing your company's profile. Only the Company Admin can make changes.</span>
  </div>

<?php endif; ?>

</div><!-- .page-shell -->

<footer class="footer">
  <div class="footer-logo">AntCareers</div>
  <div>Company Profile — Recruiter Portal</div>
</footer>

<div class="toast" id="toast"></div>

</body>
</html>
