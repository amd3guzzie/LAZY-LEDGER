<?php declare(strict_types=1);
require_once dirname(__DIR__) . '/src/auth.php';
require_once dirname(__DIR__) . '/src/audit.php';
require_once dirname(__DIR__) . '/src/views/layout.php';

send_security_headers();
$token = $_GET['token'] ?? '';
$token = is_string($token) ? $token : '';
$row = preg_match('/^[a-f0-9]{64}$/', $token)
    ? q_one('SELECT user_id FROM email_verifications WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()', [hash('sha256', $token)])
    : null;

if ($row) {
    q('UPDATE users SET email_verified = 1 WHERE id = ?', [$row['user_id']]);
    q('UPDATE email_verifications SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$row['user_id']]);
    audit((int) $row['user_id'], 'auth.email_confirmed', 'user', (int) $row['user_id'], 'email confirmed');
}
render_head('Confirm email');
?>
<body class="landing">
<div class="container-xl py-5">
  <section class="panel p-4 p-md-5 text-center mx-auto" style="max-width:640px">
    <?php if ($row): ?>
      <h1 class="section-title mb-3">EMAIL CONFIRMED</h1>
      <p class="hero-copy">Your account is active. You can log in now.</p>
      <a class="btn btn-ll btn-lg" href="/?login=1">Go to login</a>
    <?php else: ?>
      <h1 class="section-title mb-3">LINK NOT VALID</h1>
      <p class="hero-copy">This link is invalid, expired, or already used. Log in and click "Resend it" to get a new one.</p>
      <a class="btn btn-ll btn-lg" href="/?login=1">Go to login</a>
    <?php endif; ?>
  </section>
</div>
</body>
</html>