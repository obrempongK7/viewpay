<?php
/**
 * Admin Sidebar — function_exists guard prevents redeclaration HTTP 500
 * Developed by Jcode | Author: Obrempong
 */
defined('VP_BOOT') || die('Direct access not permitted.');

if (!function_exists('vp_nav')) {
    function vp_nav(string $path, string $label, string $icon, string $current, int $badge = 0, string $bc = 'bg-warning text-dark'): string
    {
        $slug   = trim($path, '/');
        $active = ($slug === $current || ($slug && str_starts_with($current, $slug . '/'))) ? ' active' : '';
        $b      = $badge > 0 ? '<span class="badge '.$bc.' ms-auto" style="font-size:.6rem">'.$badge.'</span>' : '';
        return '<a href="'.url($path).'" class="vp-nav-link'.$active.'"><i class="bi bi-'.$icon.'"></i><span>'.$label.'</span>'.$b.'</a>'."\n";
    }
}

$current  = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/', '/');
$site     = setting('site_name', APP_NAME);
$logo     = setting('logo');
$primary  = setting('primary_color', '#6366f1');
$isAdmin  = is_admin();

try {
    $pTasks = (int)db()->query("SELECT COUNT(*) FROM tasks       WHERE status='pending'")->fetchColumn();
    $pDeps  = (int)db()->query("SELECT COUNT(*) FROM deposits    WHERE status='pending'")->fetchColumn();
    $pWd    = (int)db()->query("SELECT COUNT(*) FROM withdrawals WHERE status='pending'")->fetchColumn();
} catch (Exception $e) { $pTasks = $pDeps = $pWd = 0; }
?>
<aside class="vp-sidebar" id="vpSidebar">

  <div class="p-3 d-flex align-items-center gap-2" style="border-bottom:1px solid rgba(255,255,255,.08);min-height:62px">
    <?php if ($logo): ?>
      <img src="<?= url('uploads/'.$logo) ?>" style="height:28px;max-width:110px;object-fit:contain" alt="<?= e($site) ?>">
    <?php else: ?>
      <div style="width:30px;height:30px;background:<?= e($primary) ?>;border-radius:7px;display:grid;place-items:center;flex-shrink:0"><i class="bi bi-play-circle-fill text-white" style="font-size:.85rem"></i></div>
      <span class="text-white fw-700" style="font-size:.88rem"><?= e($site) ?></span>
    <?php endif; ?>
    <?php if ($isAdmin): ?>
      <span class="badge bg-danger ms-auto" style="font-size:.55rem;padding:3px 7px">ADMIN</span>
    <?php else: ?>
      <span class="badge bg-warning text-dark ms-auto" style="font-size:.55rem;padding:3px 7px">MOD</span>
    <?php endif; ?>
  </div>

  <nav class="p-2 flex-grow-1">
    <div class="vp-nav-section">Overview</div>
    <?= vp_nav('admin',       'Dashboard',       'speedometer2',      $current) ?>
    <?= vp_nav('admin/users', 'Users',           'people',            $current) ?>

    <div class="vp-nav-section">Campaigns</div>
    <?= vp_nav('admin/tasks',    'Campaigns',   'collection-play',   $current, $pTasks) ?>
    <?= vp_nav('admin/packages', 'Packages',    'box-seam',          $current) ?>

    <div class="vp-nav-section">Finance</div>
    <?= vp_nav('admin/deposits', 'Payments',    'cash-stack',        $current, $pDeps,  'bg-success text-white') ?>
    <?= vp_nav('admin/payments', 'Withdrawals', 'arrow-up-circle',   $current, $pWd,    'bg-danger text-white') ?>

    <div class="vp-nav-section">Content</div>
    <?= vp_nav('admin/ads',   'Advertisements', 'megaphone',         $current) ?>
    <?= vp_nav('admin/pages', 'CMS Pages',      'file-earmark-text', $current) ?>

    <?php if ($isAdmin): ?>
    <div class="vp-nav-section">System</div>
    <?= vp_nav('admin/settings',   'Settings',   'gear',            $current) ?>
    <?= vp_nav('admin/audit-logs', 'Audit Logs', 'journal-text',    $current) ?>
    <?php endif; ?>

    <div style="border-top:1px solid rgba(255,255,255,.08);margin-top:.5rem;padding-top:.5rem">
      <a href="<?= url() ?>" class="vp-nav-link" target="_blank"><i class="bi bi-globe"></i><span>View Site</span></a>
      <a href="<?= url('logout') ?>" class="vp-nav-link" style="color:#f87171"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
    </div>
  </nav>

  <div class="px-3 pb-3 pt-2" style="border-top:1px solid rgba(255,255,255,.07)">
    <div style="font-size:.63rem;line-height:1.9;color:rgba(255,255,255,.28)">
      <div style="color:rgba(255,255,255,.6);font-weight:600;font-size:.72rem"><?= e($site) ?></div>
      <div>Developed by <span style="color:#818cf8;font-weight:700">Jcode</span></div>
      <div>Author: <span style="color:rgba(255,255,255,.5);font-weight:600">Obrempong</span></div>
      <div style="opacity:.4;font-size:.57rem">v<?= VP_VERSION ?></div>
    </div>
  </div>
</aside>
