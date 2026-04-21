<?php
declare(strict_types=1);
/**
 * AntCareers — Job Match Email Helper
 *
 * Sends a formatted HTML email to a jobseeker when a relevant job is found.
 * Uses PHP mail() which works on XAMPP/localhost and most shared hosts.
 * For production SMTP, replace the mail() call with PHPMailer/SendGrid.
 */

if (!defined('MAIL_FROM_ADDRESS')) define('MAIL_FROM_ADDRESS', 'noreply@antcareers.site');
if (!defined('MAIL_FROM_NAME'))    define('MAIL_FROM_NAME',    'AntCareers');

/**
 * Send a job-match email notification to a seeker.
 *
 * @param string $toEmail   Recipient email
 * @param string $toName    Recipient full name
 * @param array  $job       Keys: id, title, company, location, experience_level
 * @param string $appUrl    Base URL (APP_URL), no trailing slash
 * @return bool  True if mail() accepted the message
 */
function sendJobMatchEmail(
    string $toEmail,
    string $toName,
    array  $job,
    string $appUrl
): bool {
    if ($toEmail === '') return false;

    $jobId      = (int)($job['id']               ?? 0);
    $jobTitle   = $job['title']                  ?? '';
    $company    = $job['company']                ?? '';
    $location   = $job['location']               ?? '';
    $experience = $job['experience_level']        ?? '';

    $firstName  = trim(explode(' ', $toName)[0]) ?: 'there';

    $jT  = htmlspecialchars($jobTitle,   ENT_QUOTES, 'UTF-8');
    $jC  = htmlspecialchars($company,    ENT_QUOTES, 'UTF-8');
    $jL  = htmlspecialchars($location,   ENT_QUOTES, 'UTF-8');
    $jE  = htmlspecialchars($experience, ENT_QUOTES, 'UTF-8');
    $jFN = htmlspecialchars($firstName,  ENT_QUOTES, 'UTF-8');
    $jS  = htmlspecialchars('New Job Match: ' . $jobTitle . ' at ' . $company, ENT_QUOTES, 'UTF-8');

    $base           = rtrim($appUrl, '/');
    $viewUrl        = $base . '/seeker/antcareers_seekerJobs.php?job_id=' . $jobId;
    $unsubscribeUrl = $base . '/seeker/job_notif_unsubscribe.php?email=' . rawurlencode($toEmail)
                    . '&token=' . jobNotifToken($toEmail);

    $subject = 'New Job Match: ' . $jobTitle . ' at ' . $company;

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$jS}</title></head>
<body style="margin:0;padding:0;background:#0A0909;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0A0909;padding:32px 16px;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#1C1818;border:1px solid #352E2E;border-radius:14px;overflow:hidden;">

      <!-- Header -->
      <tr><td style="background:linear-gradient(135deg,#7A1515,#D13D2C);padding:28px 32px;text-align:center;">
        <div style="font-size:26px;font-weight:800;color:#fff;letter-spacing:-0.02em;">Ant<span style="color:#FFD0C8;">Careers</span></div>
        <div style="color:rgba(255,255,255,0.8);font-size:13px;margin-top:6px;">Your next opportunity has arrived</div>
      </td></tr>

      <!-- Body -->
      <tr><td style="padding:32px;">
        <div style="font-size:22px;font-weight:700;color:var(--text-light);margin-bottom:8px;">Hi {$jFN},</div>
        <div style="font-size:15px;color:#D0BCBA;line-height:1.65;margin-bottom:24px;">
          We found a new job posting that matches your profile on AntCareers. Check it out before it fills up!
        </div>

        <!-- Job card -->
        <table width="100%" cellpadding="0" cellspacing="0"
               style="background:#252020;border:1px solid #352E2E;border-radius:10px;margin-bottom:24px;">
          <tr><td style="padding:20px 24px;">
            <div style="font-size:19px;font-weight:700;color:var(--text-light);margin-bottom:4px;">{$jT}</div>
            <div style="font-size:14px;color:#E85540;font-weight:600;margin-bottom:16px;">{$jC}</div>
            <table cellpadding="0" cellspacing="0">
              <tr>
                <td style="padding-right:24px;vertical-align:top;">
                  <div style="font-size:11px;color:#927C7A;text-transform:uppercase;letter-spacing:0.07em;font-weight:700;">Location</div>
                  <div style="font-size:13px;color:#D0BCBA;margin-top:3px;">{$jL}</div>
                </td>
                <td style="vertical-align:top;">
                  <div style="font-size:11px;color:#927C7A;text-transform:uppercase;letter-spacing:0.07em;font-weight:700;">Experience</div>
                  <div style="font-size:13px;color:#D0BCBA;margin-top:3px;">{$jE}</div>
                </td>
              </tr>
            </table>
          </td></tr>
        </table>

        <!-- CTA button -->
        <table width="100%" cellpadding="0" cellspacing="0">
          <tr><td align="center" style="padding-bottom:28px;">
            <a href="{$viewUrl}"
               style="display:inline-block;padding:14px 36px;background:#D13D2C;color:#fff;
                      text-decoration:none;border-radius:8px;font-size:15px;font-weight:700;
                      letter-spacing:0.02em;">
              View Job
            </a>
          </td></tr>
        </table>

        <!-- Footer -->
        <div style="padding-top:20px;border-top:1px solid #352E2E;font-size:12px;color:#927C7A;text-align:center;line-height:1.8;">
          You're receiving this because you have
          <strong style="color:#D0BCBA;">Relevant job notifications</strong> enabled on AntCareers.<br>
          <a href="{$unsubscribeUrl}" style="color:#E85540;text-decoration:none;">
            Turn off these notifications
          </a>
        </div>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
HTML;

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= 'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM_ADDRESS . ">\r\n";
    $headers .= 'Reply-To: ' . MAIL_FROM_ADDRESS . "\r\n";
    $headers .= "X-Mailer: AntCareers\r\n";

    return @mail($toEmail, $subject, $htmlBody, $headers);
}

/**
 * Generate a deterministic HMAC token used in the unsubscribe link.
 * Based on the seeker's email + app secret so no DB lookup is needed
 * to validate the link.
 */
function jobNotifToken(string $email): string
{
    $secret = defined('APP_SECRET') ? constant('APP_SECRET') : (DB_PASS . DB_NAME);
    return substr(hash_hmac('sha256', strtolower(trim($email)), $secret), 0, 40);
}
