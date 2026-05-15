<?php
defined('VP_BOOT') || die('Direct access not permitted.');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/layout.php';
require_auth();
$user = auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name  = trim($_POST['name']  ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (strlen($name) < 2) {
        flash('error', 'Name must be at least 2 characters.');
    } else {
        db()->prepare("UPDATE users SET name=?,phone=?,updated_at=? WHERE id=?")
            ->execute([$name, $phone, now_ts(), $user['id']]);

        // Password change
        $newPw = $_POST['new_password'] ?? '';
        if ($newPw !== '') {
            if (strlen($newPw) < 8) {
                flash('error', 'New password must be at least 8 characters.');
            } elseif ($newPw !== ($_POST['confirm_password'] ?? '')) {
                flash('error', 'Passwords do not match.');
            } elseif (!verify_pw($_POST['current_password'] ?? '', $user['password'])) {
                flash('error', 'Current password is incorrect.');
            } else {
                db()->prepare("UPDATE users SET password=?,updated_at=? WHERE id=?")
                    ->execute([hash_pw($newPw), now_ts(), $user['id']]);
                flash('success', 'Profile and password updated!');
                redirect('/profile');
            }
        } else {
            flash('success', 'Profile updated!');
            redirect('/profile');
        }
    }
    $user = auth(); // reload
}

page_start('My Profile');
?>
<div class="container py-4" style="max-width:660px">
  <h4 class="fw-bold mb-4">My Profile</h4>

  <div class="card border-0 shadow-sm rounded-xl p-4 mb-4 text-center">
    <img src="<?= e(avatar_url($user['avatar']??null,$user['name'])) ?>"
         class="rounded-circle mx-auto mb-3" width="72" height="72" alt="">
    <h5 class="fw-bold mb-1"><?= e($user['name']) ?></h5>
    <div class="text-muted small mb-3"><?= e($user['email']) ?></div>
    <div class="d-flex gap-2 justify-content-center mb-4">
      <?php if ($user['is_admin']): ?>
      <span class="badge bg-danger">Admin</span>
      <?php endif; ?>
      <span class="badge <?= $user['status']==='active'?'bg-success':'bg-secondary' ?>">
        <?= ucfirst($user['status']) ?>
      </span>
    </div>
    <div class="row g-3">
      <?php foreach ([
        [money((float)$user['wallet_balance']), 'Balance',          'text-primary'],
        [money((float)$user['total_earned']),    'Total Earned',     'text-success'],
        [number_format($user['total_tasks_completed']), 'Tasks Done','text-info'],
      ] as [$val, $lbl, $cls]): ?>
      <div class="col-4">
        <div class="fw-bold <?= $cls ?>"><?= $val ?></div>
        <div class="text-muted" style="font-size:.72rem"><?= $lbl ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <form method="POST">
    <?= csrf_field() ?>
    <div class="card border-0 shadow-sm rounded-xl p-4 mb-4">
      <h6 class="fw-bold mb-3">Personal Information</h6>
      <div class="mb-3">
        <label class="form-label small fw-600">Full Name</label>
        <input type="text" name="name" class="form-control" value="<?= e($user['name']) ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label small fw-600">Email Address</label>
        <input type="email" class="form-control bg-light" value="<?= e($user['email']) ?>" disabled>
        <div class="form-text">Email cannot be changed.</div>
      </div>
      <div class="mb-0">
        <label class="form-label small fw-600">Phone Number <span class="text-muted fw-normal">(optional)</span></label>
        <input type="text" name="phone" class="form-control" value="<?= e($user['phone']??'') ?>" placeholder="+233 XX XXX XXXX">
      </div>
    </div>
    <div class="card border-0 shadow-sm rounded-xl p-4 mb-4">
      <h6 class="fw-bold mb-1">Change Password</h6>
      <p class="text-muted small mb-3">Leave blank to keep your current password.</p>
      <div class="mb-3">
        <label class="form-label small fw-600">Current Password</label>
        <input type="password" name="current_password" class="form-control" autocomplete="current-password">
      </div>
      <div class="row g-2">
        <div class="col">
          <label class="form-label small fw-600">New Password</label>
          <input type="password" name="new_password" class="form-control" minlength="8" autocomplete="new-password">
        </div>
        <div class="col">
          <label class="form-label small fw-600">Confirm New</label>
          <input type="password" name="confirm_password" class="form-control" autocomplete="new-password">
        </div>
      </div>
    </div>
    <button type="submit" class="btn btn-primary w-100 fw-700 py-2">
      <i class="bi bi-floppy2-fill me-2"></i>Save Changes
    </button>
  </form>
</div>
<?php page_end(); ?>
