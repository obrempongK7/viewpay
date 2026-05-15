<?php
defined('VP_BOOT') || die('Direct access not permitted.');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/layout.php';

$token  = trim($_GET['t']   ?? '');
$uid    = (int)($_GET['uid'] ?? 0);
$status = 'invalid';
$msg    = 'Invalid or expired verification link.';

if ($token && $uid > 0) {
    try {
        $hash = hash('sha256', $token);
        $st   = db()->prepare(
            "SELECT * FROM email_verifications
             WHERE user_id=? AND token=? AND expires_at > NOW() LIMIT 1"
        );
        $st->execute([$uid, $hash]);
        $row = $st->fetch();

        if ($row) {
            db()->prepare("UPDATE users SET email_verified_at=?, updated_at=? WHERE id=?")
                ->execute([now_ts(), now_ts(), $uid]);
            db()->prepare("DELETE FROM email_verifications WHERE user_id=?")
                ->execute([$uid]);

            audit('email_verified', 'Email verified for user ID ' . $uid, $uid, 'user', $uid);

            $status = 'success';
            $msg    = 'Your email has been verified! You can now access all features.';

            // Auto-login if not already logged in
            if (!auth()) {
                $u = db()->prepare("SELECT * FROM users WHERE id=? AND status='active' LIMIT 1");
                $u->execute([$uid]); $u = $u->fetch();
                if ($u) { login_user($u); }
            }
        } else {
            $status = 'expired';
            $msg    = 'This verification link has expired or already been used.';
        }
    } catch (Exception $e) {
        $status = 'error';
        $msg    = 'A database error occurred. Please try again.';
    }
}

// Handle resend
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resend') {
    verify_csrf();
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $u = db()->prepare("SELECT * FROM users WHERE email=? AND email_verified_at IS NULL LIMIT 1");
        $u->execute([$email]); $u = $u->fetch();
        if ($u) {
            send_verification_email($u);
        }
        // Always show success (anti-enumeration)
        flash('success', 'If that email is unverified, a new link has been sent.');
        redirect('/verify-email');
    }
}

$site    = setting('site_name', APP_NAME);
$primary = setting('primary_color', '#6366f1');

page_start('Verify Email');
?>
<div class="container py-5" style="min-height:70vh">
<div class="row justify-content-center">
<div class="col-sm-10 col-md-6 col-lg-4">
  <div class="card border-0 shadow-sm rounded-xl p-4 text-center">

    <?php if ($status === 'success'): ?>
    <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3 mx-auto" style="width:64px;height:64px;background:#ecfdf5">
      <i class="bi bi-check-circle-fill text-success fs-1"></i>
    </div>
    <h4 class="fw-bold mb-2">Email Verified!</h4>
    <p class="text-muted small mb-4"><?= e($msg) ?></p>
    <a href="<?= url('dashboard') ?>" class="btn btn-success fw-700 px-5">
      <i class="bi bi-speedometer2 me-2"></i>Go to Dashboard
    </a>

    <?php elseif ($status === 'expired'): ?>
    <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3 mx-auto" style="width:64px;height:64px;background:#fffbeb">
      <i class="bi bi-clock-history text-warning fs-1"></i>
    </div>
    <h4 class="fw-bold mb-2">Link Expired</h4>
    <p class="text-muted small mb-4"><?= e($msg) ?></p>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="resend">
      <div class="mb-3 text-start">
        <label class="form-label small fw-600">Your Email Address</label>
        <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
      </div>
      <button type="submit" class="btn btn-warning fw-700 w-100 text-dark">
        <i class="bi bi-envelope-fill me-2"></i>Resend Verification
      </button>
    </form>

    <?php else: ?>
    <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3 mx-auto" style="width:64px;height:64px;background:#fef2f2">
      <i class="bi bi-x-circle-fill text-danger fs-1"></i>
    </div>
    <h4 class="fw-bold mb-2">Verification Failed</h4>
    <p class="text-muted small mb-4"><?= e($msg) ?></p>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="resend">
      <div class="mb-3 text-start">
        <label class="form-label small fw-600">Resend to your email</label>
        <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
      </div>
      <button type="submit" class="btn btn-primary fw-700 w-100">
        <i class="bi bi-envelope-fill me-2"></i>Resend Link
      </button>
    </form>
    <?php endif; ?>

    <hr class="my-4">
    <a href="<?= url('login') ?>" class="text-muted small text-decoration-none">
      <i class="bi bi-arrow-left me-1"></i>Back to Login
    </a>
  </div>
</div>
</div>
</div>
<?php page_end(); ?>
