<?php
defined('VP_BOOT') || die('Direct access not permitted.');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/layout.php';
if (auth()) { redirect('/dashboard'); }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!rate_limit('login_'.vp_ip(), 5, 120)) {
        $error = 'Too many attempts. Please wait 2 minutes.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
        if (!$email || !$pass) {
            $error = 'Email and password are required.';
        } else {
            $st = db()->prepare("SELECT * FROM users WHERE email=? AND deleted_at IS NULL LIMIT 1");
            $st->execute([$email]); $user = $st->fetch();
            if ($user && verify_pw($pass, $user['password'])) {
                if ($user['status'] === 'blocked') {
                    $error = 'Your account has been suspended. Contact support.';
                    audit('login_blocked', 'Blocked user attempted login: '.$email);
                } else {
                    login_user($user);
                    audit('login', 'User logged in', (int)$user['id'], 'user', (int)$user['id']);
                    flash('success', 'Welcome back, '.explode(' ',$user['name'])[0].'!');
                    redirect($user['is_admin'] ? '/admin' : '/dashboard');
                }
            } else {
                $error = 'Incorrect email or password.';
                audit('login_failed', 'Failed login attempt for: '.$email);
            }
        }
    }
}
$site = setting('site_name', APP_NAME); $primary = setting('primary_color', '#6366f1');
page_start('Log In');
?>
<div class="container py-5" style="min-height:70vh">
<div class="row justify-content-center">
<div class="col-sm-10 col-md-6 col-lg-5 col-xl-4">
  <div class="text-center mb-4">
    <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3" style="width:48px;height:48px;background:<?= e($primary) ?>"><i class="bi bi-play-circle-fill text-white fs-4"></i></div>
    <h4 class="fw-bold mb-1">Welcome Back</h4>
    <p class="text-muted small">Sign in to <?= e($site) ?></p>
  </div>
  <?php if ($error): ?><div class="alert alert-danger border-0 shadow-sm py-2 d-flex align-items-center gap-2"><i class="bi bi-exclamation-triangle-fill"></i><?= e($error) ?></div><?php endif; ?>
  <div class="card border-0 shadow-sm rounded-xl">
    <div class="card-body p-4">
      <form method="POST" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label small fw-600">Email Address</label><input type="email" name="email" class="form-control" value="<?= e($_POST['email']??'') ?>" placeholder="you@example.com" required autofocus autocomplete="email"></div>
        <div class="mb-4"><div class="d-flex justify-content-between mb-1"><label class="form-label small fw-600 mb-0">Password</label><a href="<?= url('forgot-password') ?>" class="small text-decoration-none" style="color:<?= e($primary) ?>">Forgot?</a></div><input type="password" name="password" class="form-control" placeholder="••••••••" required autocomplete="current-password"></div>
        <button type="submit" class="btn btn-primary w-100 fw-700 py-2"><i class="bi bi-box-arrow-in-right me-2"></i>Sign In</button>
      </form>
    </div>
  </div>
  <p class="text-center text-muted small mt-4">No account? <a href="<?= url('register') ?>" class="fw-600 text-decoration-none" style="color:<?= e($primary) ?>">Create one free</a></p>
</div>
</div>
</div>
<?php page_end(); ?>
