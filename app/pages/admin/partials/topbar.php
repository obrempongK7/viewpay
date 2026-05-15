<?php
defined('VP_BOOT') || die('Direct access not permitted.');
$me = auth();
try {
    $nAll = (int)db()->query(
        "SELECT (SELECT COUNT(*) FROM tasks WHERE status='pending')
              + (SELECT COUNT(*) FROM deposits WHERE status='pending')
              + (SELECT COUNT(*) FROM withdrawals WHERE status='pending')"
    )->fetchColumn();
    $nDep  = (int)db()->query("SELECT COUNT(*) FROM deposits    WHERE status='pending'")->fetchColumn();
    $nTask = (int)db()->query("SELECT COUNT(*) FROM tasks       WHERE status='pending'")->fetchColumn();
    $nWd   = (int)db()->query("SELECT COUNT(*) FROM withdrawals WHERE status='pending'")->fetchColumn();
} catch (Exception $e) {
    $nAll = $nDep = $nTask = $nWd = 0;
}
?>
<header class="d-flex align-items-center gap-3 px-3 px-lg-4 py-2 bg-white sticky-top"
        style="border-bottom:1px solid var(--bs-border-color);z-index:100;min-height:60px">

  <!-- Mobile sidebar toggle -->
  <button class="btn btn-sm btn-outline-secondary d-lg-none"
          onclick="document.getElementById('vpSidebar').classList.toggle('show')">
    <i class="bi bi-list fs-5"></i>
  </button>

  <span class="fw-600 small text-muted flex-grow-1 d-none d-md-block">
    <?= e(setting('site_name', APP_NAME)) ?> — Admin
  </span>

  <div class="d-flex align-items-center gap-2">

    <!-- Alert bell -->
    <?php if ($nAll > 0): ?>
    <div class="dropdown">
      <button class="btn btn-sm btn-outline-warning position-relative" data-bs-toggle="dropdown">
        <i class="bi bi-bell-fill"></i>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
              style="font-size:.55rem"><?= $nAll ?></span>
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="min-width:260px">
        <li class="px-3 py-2 border-bottom fw-600 small">Action Required</li>
        <?php if ($nDep): ?>
        <li><a class="dropdown-item py-2 small" href="<?= url('admin/deposits') ?>">
          <i class="bi bi-cash-stack text-success me-2"></i><?= $nDep ?> payment(s) to confirm</a></li>
        <?php endif; ?>
        <?php if ($nTask): ?>
        <li><a class="dropdown-item py-2 small" href="<?= url('admin/tasks') ?>">
          <i class="bi bi-collection-play text-warning me-2"></i><?= $nTask ?> campaign(s) pending</a></li>
        <?php endif; ?>
        <?php if ($nWd): ?>
        <li><a class="dropdown-item py-2 small" href="<?= url('admin/payments') ?>">
          <i class="bi bi-arrow-up-circle text-danger me-2"></i><?= $nWd ?> withdrawal(s) pending</a></li>
        <?php endif; ?>
      </ul>
    </div>
    <?php endif; ?>

    <!-- User dropdown -->
    <div class="dropdown">
      <button class="btn btn-sm border fw-500 d-flex align-items-center gap-2 py-1"
              data-bs-toggle="dropdown">
        <img src="<?= e(avatar_url($me['avatar'] ?? null, $me['name'])) ?>"
             width="26" height="26" class="rounded-circle" alt="">
        <span class="d-none d-md-inline small"><?= e(explode(' ', $me['name'])[0]) ?></span>
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-1">
        <li class="px-3 py-2 border-bottom">
          <div class="fw-600 small"><?= e($me['name']) ?></div>
          <div class="text-muted" style="font-size:.72rem"><?= e($me['email']) ?></div>
        </li>
        <li><a class="dropdown-item small py-2" href="<?= url('profile') ?>"><i class="bi bi-person me-2"></i>Profile</a></li>
        <li><a class="dropdown-item small py-2" href="<?= url() ?>" target="_blank"><i class="bi bi-globe me-2"></i>View Site</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item small py-2 text-danger" href="<?= url('logout') ?>"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
      </ul>
    </div>
  </div>
</header>
