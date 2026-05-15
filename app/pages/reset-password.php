<?php
defined('VP_BOOT') || die('Direct access not permitted.');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/layout.php';

if (auth()) { redirect('/dashboard'); }

$token = trim($_GET['token'] ?? '');
$email = strtolower(trim($_GET['email'] ?? ''));
$error = ''; $msg = '';

if (!$token || !$email) {
    $error = 'Invalid password reset link.';
} else {
    // Check if token exists and is valid (1 hour expiry)
    $st = db()->prepare("SELECT * FROM password_reset_tokens WHERE email=? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) LIMIT 1");
    $st->execute([$email]);
    $row = $st->fetch();

    if (!$row || !hash_equals($row['token'], hash('sha256', $token))) {
        $error = 'Invalid or expired password reset link.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    verify_csrf();
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            db()->beginTransaction();

            // Update password
            $st = db()->prepare("UPDATE users SET password=?, updated_at=? WHERE email=? AND status='active'");
            $st->execute([hash_pw($password), now_ts(), $email]);

            // Delete token
            db()->prepare("DELETE FROM password_reset_tokens WHERE email=?")->execute([$email]);

            db()->commit();

            audit('password_reset', 'Password reset for email: ' . $email);
            flash('success', 'Your password has been reset successfully. You can now log in.');
            redirect('/login');
        } catch (Exception $e) {
            db()->rollBack();
            $error = 'An error occurred. Please try again.';
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
      <i class="bi bi-shield-lock-fill fs-4" style="color:<?= e($primary) ?>"></i>
    </div>
    <h4 class="fw-bold mb-1">Set New Password</h4>
    <p class="text-muted small">Enter your new password below</p>
  </div>

  <?php if ($error): ?><div class="alert alert-danger border-0 py-2"><?= e($error) ?></div><?php endif; ?>

  <?php if (!$error || ($_SERVER['REQUEST_METHOD'] === 'POST' && $error)): ?>
  <div class="card border-0 shadow-sm rounded-xl">
    <div class="card-body p-4">
      <form method="POST">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label small fw-600">New Password</label>
          <input type="password" name="password" class="form-control" placeholder="••••••••" required autofocus>
          <div class="form-text small">Minimum 8 characters</div>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-600">Confirm New Password</label>
          <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn btn-primary w-100 fw-600 py-2">
          <i class="bi bi-check-circle-fill me-2"></i>Reset Password
        </button>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <p class="text-center small text-muted mt-4">
    <a href="<?= url('login') ?>" class="text-decoration-none fw-600" style="color:<?= e($primary) ?>">
      <i class="bi bi-arrow-left me-1"></i>Back to Login
    </a>
  </p>
</div>
</div>
</div>
<?php page_end(); ?>
