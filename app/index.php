<?php
/**
 * ViewPay v2.0 — Front Controller
 * Developed by Jcode | Author: Obrempong
 */
define('VP_BOOT', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/layout.php';

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$appPath = parse_url(APP_URL, PHP_URL_PATH);
if ($appPath && $appPath !== '/') {
    $appPath = rtrim($appPath, '/');
    if (str_starts_with($uri, $appPath)) {
        $uri = substr($uri, strlen($appPath));
    }
} else {
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    if ($scriptDir !== '/' && $scriptDir !== '\\' && str_starts_with($uri, $scriptDir)) {
        $uri = substr($uri, strlen($scriptDir));
    }
}
$uri = trim($uri, '/');
$uri = preg_replace('#[^a-zA-Z0-9/\-_]#', '', $uri);
$uri = preg_replace('#/+#', '/', $uri);

$routes = [
    ''                  => 'pages/home.php',
    'login'             => 'pages/login.php',
    'register'          => 'pages/register.php',
    'logout'            => 'pages/logout.php',
    'forgot-password'   => 'pages/forgot-password.php',
    'reset-password'    => 'pages/reset-password.php',
    'verify-email'      => 'pages/verify-email.php',
    'dashboard'         => 'pages/dashboard.php',
    'tasks'             => 'pages/tasks.php',
    'wallet'            => 'pages/wallet.php',
    'profile'           => 'pages/profile.php',
    'packages'          => 'pages/packages.php',
    'creator'           => 'pages/creator.php',
    'watch-ads'         => 'pages/watch-ads.php',
    // Admin
    'admin'             => 'pages/admin/dashboard.php',
    'admin/users'       => 'pages/admin/users.php',
    'admin/tasks'       => 'pages/admin/tasks.php',
    'admin/packages'    => 'pages/admin/packages.php',
    'admin/deposits'    => 'pages/admin/deposits.php',
    'admin/payments'    => 'pages/admin/payments.php',
    'admin/ads'         => 'pages/admin/ads.php',
    'admin/pages'       => 'pages/admin/pages.php',
    'admin/settings'    => 'pages/admin/settings.php',
    'admin/audit-logs'  => 'pages/admin/audit-logs.php',
];

$file = null;

// Dynamic routes
if      (preg_match('#^tasks/(\d+)$#',          $uri, $m)) { $_GET['task_id']  = (int)$m[1]; $file = 'pages/task-view.php'; }
elseif  (preg_match('#^admin/users/(\d+)$#',    $uri, $m)) { $_GET['user_id']  = (int)$m[1]; $file = 'pages/admin/user-detail.php'; }
elseif  (preg_match('#^admin/tasks/(\d+)$#',    $uri, $m)) { $_GET['task_id']  = (int)$m[1]; $file = 'pages/admin/task-detail.php'; }
elseif  (preg_match('#^admin/pages/(\d+)$#',    $uri, $m)) { $_GET['page_id']  = (int)$m[1]; $file = 'pages/admin/page-edit.php'; }
elseif  (preg_match('#^page/([a-z0-9\-]{1,80})$#', $uri, $m)) { $_GET['slug'] = $m[1]; $file = 'pages/cms-page.php'; }
else    { $file = $routes[$uri] ?? null; }

if ($file !== null && file_exists(__DIR__ . '/' . $file)) {
    require __DIR__ . '/' . $file;
    exit;
}

// 404
http_response_code(404);
page_start('Page Not Found');
?>
<div class="container py-5 text-center">
  <div style="font-size:5rem;opacity:.15">🔍</div>
  <h2 class="fw-bold mt-3">Page Not Found</h2>
  <p class="text-muted">The page <code><?= e($uri) ?></code> doesn't exist.</p>
  <a href="<?= url() ?>" class="btn btn-primary mt-2 fw-600">
    <i class="bi bi-house me-1"></i>Back Home
  </a>
</div>
<?php page_end();
