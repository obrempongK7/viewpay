<?php
defined('VP_BOOT') || die('Direct access not permitted.');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/layout.php';
if (auth()) { redirect('/dashboard'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!rate_limit('register_'.vp_ip(), 3, 300)) {
        $errors[] = 'Too many registrations from this location. Try again in 5 minutes.';
    } else {
        $name  = trim($_POST['name']  ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $pass  = $_POST['password']  ?? '';
        $pass2 = $_POST['password2'] ?? '';
        $ref   = trim($_POST['ref']   ?? '');
        $ip    = vp_ip();
        $fp    = vp_device_fp();

        if (strlen($name) < 2)                          { $errors[] = 'Name must be at least 2 characters.'; }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Enter a valid email address.'; }
        if (strlen($pass) < 8)                          { $errors[] = 'Password must be at least 8 characters.'; }
        if ($pass !== $pass2)                           { $errors[] = 'Passwords do not match.'; }

        if (empty($errors)) {
            // IP limit check
            $maxIp = (int)setting('max_accounts_per_ip', '1');
            $ipCount = db()->prepare("SELECT COUNT(*) FROM users WHERE register_ip=? AND is_admin=0");
            $ipCount->execute([$ip]); 
            if ((int)$ipCount->fetchColumn() >= $maxIp) {
                $errors[] = 'Registration limit reached from this network. Contact support if this is an error.';
            }

            // Device fingerprint limit check
            if (empty($errors)) {
                $maxDev = (int)setting('max_accounts_per_device', '1');
                $devCount = db()->prepare("SELECT COUNT(*) FROM users WHERE device_fingerprint=? AND is_admin=0");
                $devCount->execute([$fp]);
                if ((int)$devCount->fetchColumn() >= $maxDev) {
                    $errors[] = 'Account limit reached on this device. Contact support if this is an error.';
                }
            }

            // Email uniqueness
            if (empty($errors)) {
                $exists = db()->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
                $exists->execute([$email]);
                if ($exists->fetch()) {
                    $errors[] = 'An account with this email already exists.';
                }
            }

            if (empty($errors)) {
                $refCode = strtoupper(bin2hex(random_bytes(4)));
                $refBy   = null;
                if ($ref) {
                    $ru = db()->prepare("SELECT id FROM users WHERE referral_code=? LIMIT 1");
                    $ru->execute([$ref]); $ru = $ru->fetch();
                    if ($ru) { $refBy = $ru['id']; }
                }
                // 13 cols, 13 ?s, 13 params ✓
                db()->prepare(
                    "INSERT INTO users (name,username,email,password,role,is_admin,status,
                     email_verified_at,referral_code,referred_by,register_ip,created_at,updated_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
                )->execute([
                    $name, null, $email, hash_pw($pass),
                    'user', 0, 'active',
                    null, // not verified yet
                    $refCode, $refBy, $ip, now_ts(), now_ts()
                ]);

                // Update device fingerprint
                $newId = (int)db()->lastInsertId();
                db()->prepare("UPDATE users SET device_fingerprint=?,last_ip=? WHERE id=?")
                    ->execute([$fp, $ip, $newId]);

                $newUser = db()->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
                $newUser->execute([$newId]); $newUser = $newUser->fetch();

                // Send verification email if required
                if (setting('email_verification_required', '0') === '1') {
                    send_verification_email($newUser);
                    login_user($newUser);
                    audit('register', 'New user registered: '.$email, $newId, 'user', $newId);
                    flash('info', 'Welcome! Please check your email to verify your account.');
                } else {
                    login_user($newUser);
                    audit('register', 'New user registered: '.$email, $newId, 'user', $newId);
                    flash('success', 'Welcome to '.setting('site_name', APP_NAME).'! Start earning now.');
                }
                redirect('/dashboard');
            }
        }
    }
}
$site = setting('site_name', APP_NAME); $primary = setting('primary_color', '#6366f1');
page_start('Create Account');
?>
<div class="container py-5" style="min-height:70vh">
<div class="row justify-content-center">
<div class="col-sm-10 col-md-7 col-lg-5 col-xl-4">
  <div class="text-center mb-4">
    <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3" style="width:48px;height:48px;background:<?= e($primary) ?>">
      <i class="bi bi-play-circle-fill text-white fs-4"></i>
    </div>
    <h4 class="fw-bold mb-1">Create Free Account</h4>
    <p class="text-muted small">Start earning on <?= e($site) ?> today</p>
  </div>
  <?php foreach($errors as $err): ?>
  <div class="alert alert-danger border-0 shadow-sm py-2 d-flex align-items-center gap-2">
    <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i><?= e($err) ?>
  </div>
  <?php endforeach; ?>
  <div class="card border-0 shadow-sm rounded-xl">
    <div class="card-body p-4">
      <form method="POST" novalidate>
        <?= csrf_field() ?>
        <?php if (!empty($_GET['ref'])): ?>
        <input type="hidden" name="ref" value="<?= e($_GET['ref']) ?>">
        <?php endif; ?>
        <div class="mb-3"><label class="form-label small fw-600">Full Name</label><input type="text" name="name" class="form-control" value="<?= e($_POST['name']??'') ?>" placeholder="Kwame Asante" required autofocus></div>
        <div class="mb-3"><label class="form-label small fw-600">Email Address</label><input type="email" name="email" class="form-control" value="<?= e($_POST['email']??'') ?>" placeholder="you@example.com" required></div>
        <div class="mb-3"><label class="form-label small fw-600">Password</label><input type="password" name="password" id="pwf" class="form-control" placeholder="Min. 8 characters" required minlength="8" oninput="checkPw(this.value)"><div class="progress mt-2" style="height:3px"><div id="pwBar" class="progress-bar" style="width:0%;transition:.3s"></div></div></div>
        <div class="mb-4"><label class="form-label small fw-600">Confirm Password</label><input type="password" name="password2" class="form-control" placeholder="Re-enter password" required minlength="8"></div>
        <button type="submit" class="btn btn-primary w-100 fw-700 py-2"><i class="bi bi-person-plus-fill me-2"></i>Create Account</button>
        <p class="text-center text-muted mt-2 mb-0" style="font-size:.72rem">By signing up you agree to our <a href="<?= url('page/terms') ?>" class="text-decoration-none">Terms</a> &amp; <a href="<?= url('page/privacy') ?>" class="text-decoration-none">Privacy Policy</a></p>
      </form>
    </div>
  </div>
  <p class="text-center text-muted small mt-4">Already have an account? <a href="<?= url('login') ?>" class="fw-600 text-decoration-none" style="color:<?= e($primary) ?>">Sign in</a></p>
</div>
</div>
</div>
<script>
function checkPw(v){const b=document.getElementById('pwBar');let s=0,c='bg-danger';if(v.length>=8)s=33;if(v.length>=8&&/[A-Z]/.test(v)){s=66;c='bg-warning';}if(v.length>=10&&/[A-Z]/.test(v)&&/[0-9]/.test(v)){s=100;c='bg-success';}b.style.width=s+'%';b.className='progress-bar '+c;}
</script>
<?php page_end(); ?>
