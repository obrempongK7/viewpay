<?php
/**
 * Admin Layout Wrapper
 * CRITICAL: Uses require_once for partials to prevent HTTP 500 redeclaration errors.
 */
defined('VP_BOOT') || die('Direct access not permitted.');

function admin_page_start(string $title = ''): void
{
    $site = setting('site_name', APP_NAME);
    render_head($title . ' — Admin · ' . $site, true);
    echo '</head><body class="bg-body-secondary">';
    echo '<div class="d-flex min-vh-100">';
    require_once __DIR__ . '/sidebar.php';   // require_once prevents redeclaration
    echo '<div class="flex-grow-1 d-flex flex-column" style="min-width:0;overflow-x:hidden">';
    require_once __DIR__ . '/topbar.php';
    echo '<main class="flex-grow-1 p-3 p-lg-4">';
    $f = get_flash();
    if ($f) {
        $m = ['success'=>['alert-success','check-circle-fill'],'error'=>['alert-danger','exclamation-triangle-fill'],'warning'=>['alert-warning','exclamation-triangle'],'info'=>['alert-info','info-circle-fill']];
        [$cls,$icon] = $m[$f['type']] ?? ['alert-secondary','info-circle-fill'];
        echo '<div class="alert '.$cls.' alert-dismissible fade show d-flex align-items-center gap-2 py-2 mb-4 border-0 shadow-sm">';
        echo '<i class="bi bi-'.$icon.' flex-shrink-0"></i><span>'.e($f['msg']).'</span>';
        echo '<button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button></div>';
    }
}

function admin_page_end(): void
{
    $site = setting('site_name', APP_NAME);
    echo '</main>';
    echo '<footer class="px-4 py-3 d-flex flex-wrap justify-content-between align-items-center gap-2" style="border-top:1px solid var(--bs-border-color);font-size:.72rem;color:var(--bs-secondary-color)">';
    echo '<span>'.e($site).' &copy; '.date('Y').'</span>';
    echo '<span>Developed by <strong style="color:var(--vp-primary)">Jcode</strong> &nbsp;|&nbsp; Author: <strong>Obrempong</strong> &nbsp;|&nbsp; v'.VP_VERSION.'</span>';
    echo '</footer></div></div>';
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>';
    echo '</body></html>';
}
