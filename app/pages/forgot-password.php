<?php
defined('VP_BOOT') || die('Direct access not permitted.');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/layout.php';

if (auth()) { redirect('/dashboard'); }

$msg = ''; $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!rate_limit('forgot_' . vp_ip(), 3, 300)) {
        $error = 'Too many requests. Please wait 5 minutes.';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $u = db()->prepare("SELECT id FROM users WHERE email=? AND status='active' LIMIT 1");
            $u->execute([$email]); $u = $u->fetch();
            if ($u) {
                $token = bin2hex(random_bytes(32));
                db()->prepare("DELETE FROM password_reset_tokens WHERE email=?")->execute([$email]);
                db()->prepare("INSERT INTO password_reset_tokens(email,token,created_at) VALUES(?,?,?)")
                    ->execute([$email, hash('sha256', $token), now_ts()]);
                // TODO: send email with reset link: url('reset-password?token='.$token.'&email='.urlencode($email))
            }
            // Always show same message (anti-enumeration)
            $msg = 'If that email is registered, a reset link has been sent. Check your inbox.';
        }
    }
}
$site = setting('site_name', APP_NAME); $primary = setting('primary_color', '#6366f1');
page_start('Reset Password');
?>
<div class="container py-5" style="min-height:70vh">
<div class="row justify-content-center">
<div class="col-sm-10 col-md-6 col-lg-4">
  <div class="text-center mb-4">
    <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3"
         style="width:48px;height:48px;background:<?= e($primary) ?>18">
      <i class="bi bi-key-fill fs-4" style="color:<?= e($primary) ?>"></i>
    </div>
    <h4 class="fw-bold mb-1">Reset Password</h4>
    <p class="text-muted small">Enter your email to receive a reset link</p>
  </div>
  <?php if ($error): ?><div class="alert alert-danger border-0 py-2"><?= e($error) ?></div><?php endif; ?>
  <?php if ($msg):   ?><div class="alert alert-success border-0 py-2"><?= e($msg) ?></div><?php endif; ?>
  <div class="card border-0 shadow-sm rounded-xl">
    <div class="card-body p-4">
      <form method="POST">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label small fw-600">Email Address</label>
          <input type="email" name="email" class="form-control" placeholder="you@example.com" required autofocus>
        </div>
        <button type="submit" class="btn btn-primary w-100 fw-600 py-2">
          <i class="bi bi-envelope-fill me-2"></i>Send Reset Link
        </button>
      </form>
    </div>
  </div>
  <p class="text-center small text-muted mt-4">
    <a href="<?= url('login') ?>" class="text-decoration-none fw-600" style="color:<?= e($primary) ?>">
      <i class="bi bi-arrow-left me-1"></i>Back to Login
    </a>
  </p>
</div>
</div>
</div>
<?php page_end(); ?>
