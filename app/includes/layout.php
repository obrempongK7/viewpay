<?php
/**
 * ViewPay v2.0 — Shared Layout
 * Developed by Jcode | Author: Obrempong
 */
defined('VP_BOOT') || die('Direct access not permitted.');

function render_head(string $title = '', bool $adminMode = false): void
{
    $site      = setting('site_name',     APP_NAME);
    $primary   = setting('primary_color', '#6366f1');
    $favicon   = setting('favicon');
    $logo      = setting('logo');
    $ga        = setting('google_analytics_id');
    $metaDesc  = $title ? setting('meta_description','') : setting('meta_description','');
    $metaTitle = $title ? e($title).' — '.e($site) : e(setting('meta_title', $site));
    $canonUrl  = APP_URL . '/' . ltrim(parse_url($_SERVER['REQUEST_URI']??'/', PHP_URL_PATH)?:'/','/');
    $ogImage   = $logo ? url('uploads/'.$logo) : '';
    $splashBg  = setting('pwa_splash_bg',  '#6366f1');
    $splashTxt = setting('pwa_splash_text', $site);
    $adsenseC  = setting('adsense_client','');
    $adsenseOn = setting('adsense_enabled','0')==='1' && $adsenseC;

    echo '<!DOCTYPE html>' . "\n";
    echo '<html lang="en">' . "\n";
    echo '<head>' . "\n";
    echo '<meta charset="UTF-8">' . "\n";
    echo '<meta name="viewport" content="width=device-width,initial-scale=1.0">' . "\n";
    echo '<title>' . $metaTitle . '</title>' . "\n";
    if ($metaDesc) {
        echo '<meta name="description" content="' . e($metaDesc) . '">' . "\n";
    }
    // Open Graph
    echo '<meta property="og:title"       content="' . $metaTitle . '">' . "\n";
    echo '<meta property="og:description" content="' . e($metaDesc) . '">' . "\n";
    echo '<meta property="og:type"        content="website">' . "\n";
    echo '<meta property="og:url"         content="' . e($canonUrl) . '">' . "\n";
    if ($ogImage) { echo '<meta property="og:image" content="' . e($ogImage) . '">' . "\n"; }
    // Twitter card
    echo '<meta name="twitter:card"  content="summary_large_image">' . "\n";
    echo '<meta name="twitter:title" content="' . $metaTitle . '">' . "\n";

    // Canonical
    echo '<link rel="canonical" href="' . e($canonUrl) . '">' . "\n";

    // Favicon
    if ($favicon) {
        echo '<link rel="icon" href="' . url('uploads/'.$favicon) . '">' . "\n";
    }

    // PWA
    if (!$adminMode) {
        echo '<link rel="manifest" href="' . url('manifest.json') . '">' . "\n";
        echo '<meta name="theme-color" content="' . e($primary) . '">' . "\n";
        echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
        echo '<meta name="apple-mobile-web-app-status-bar-style" content="default">' . "\n";
        echo '<meta name="apple-mobile-web-app-title" content="' . e($site) . '">' . "\n";
    }

    // Fonts
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    echo '<link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,300..800;1,14..32,400&display=swap" rel="stylesheet">' . "\n";

    // Bootstrap 5.3 + Icons
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">' . "\n";
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" crossorigin="anonymous">' . "\n";

    // Google Analytics
    if ($ga) {
        echo '<script async src="https://www.googletagmanager.com/gtag/js?id=' . e($ga) . '"></script>' . "\n";
        echo "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','".e($ga)."');</script>\n";
    }

    // AdSense
    if ($adsenseOn) {
        echo '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' . e($adsenseC) . '" crossorigin="anonymous"></script>' . "\n";
    }

    // CSS
    $pri = e($primary);
    echo '<style>
:root{
  --vp-primary:' . $pri . ';
  --vp-primary-dark:color-mix(in srgb,var(--vp-primary) 80%,#000);
  --vp-primary-light:color-mix(in srgb,var(--vp-primary) 15%,#fff);
  --vp-sidebar:#111827;
  --vp-sidebar-text:rgba(255,255,255,.72);
  --vp-radius:.75rem;
}
*,*::before,*::after{box-sizing:border-box}
body{font-family:"Inter",-apple-system,BlinkMacSystemFont,sans-serif;font-size:.9375rem;line-height:1.65;-webkit-font-smoothing:antialiased;background:#f3f4f8;color:#1a1a2e}
/* Typography */
h1,h2,h3,h4,h5,h6{letter-spacing:-.015em}
.fw-600{font-weight:600}.fw-700{font-weight:700}.fw-800{font-weight:800}
/* Buttons */
.btn-primary{background:var(--vp-primary);border-color:var(--vp-primary);font-weight:600}
.btn-primary:hover,.btn-primary:focus{background:var(--vp-primary-dark);border-color:var(--vp-primary-dark)}
.btn-outline-primary{color:var(--vp-primary);border-color:var(--vp-primary)}
.btn-outline-primary:hover{background:var(--vp-primary);border-color:var(--vp-primary);color:#fff}
.text-primary{color:var(--vp-primary)!important}
.bg-primary{background:var(--vp-primary)!important}
.border-primary{border-color:var(--vp-primary)!important}
.nav-pills .nav-link.active{background:var(--vp-primary)}
.form-check-input:checked{background-color:var(--vp-primary);border-color:var(--vp-primary)}
.form-control:focus,.form-select:focus{border-color:var(--vp-primary);box-shadow:0 0 0 .2rem color-mix(in srgb,var(--vp-primary) 25%,transparent)}
/* Cards */
.card{border-radius:var(--vp-radius);border-color:#e5e7eb}
.card-hover{transition:transform .2s,box-shadow .2s}
.card-hover:hover{transform:translateY(-3px);box-shadow:0 10px 30px rgba(0,0,0,.1)!important}
/* Navbar */
.vp-navbar{border-bottom:1px solid #e5e7eb;min-height:62px;background:#fff}
.vp-navbar .navbar-brand{font-weight:800;font-size:1.1rem;letter-spacing:-.02em;gap:.5rem;display:flex;align-items:center}
/* Admin sidebar */
.vp-sidebar{width:250px;min-width:250px;background:var(--vp-sidebar);position:sticky;top:0;height:100vh;overflow-y:auto;overflow-x:hidden;display:flex;flex-direction:column;scrollbar-width:thin;flex-shrink:0}
.vp-sidebar::-webkit-scrollbar{width:3px}.vp-sidebar::-webkit-scrollbar-thumb{background:rgba(255,255,255,.1);border-radius:99px}
.vp-nav-link{display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;color:var(--vp-sidebar-text);font-size:.83rem;font-weight:500;text-decoration:none;transition:background .15s,color .15s;margin-bottom:1px}
.vp-nav-link i{width:20px;text-align:center;flex-shrink:0;font-size:.95rem}
.vp-nav-link:hover{background:rgba(255,255,255,.08);color:#fff}
.vp-nav-link.active{background:var(--vp-primary);color:#fff}
.vp-nav-section{font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:rgba(255,255,255,.3);padding:12px 12px 4px;margin-top:4px}
/* Stat cards */
.stat-card{border-radius:var(--vp-radius);border:1px solid #e5e7eb;background:#fff;padding:1.1rem 1.25rem;transition:box-shadow .2s}
.stat-card:hover{box-shadow:0 4px 20px rgba(0,0,0,.07)}
.stat-icon{width:44px;height:44px;border-radius:10px;display:grid;place-items:center;font-size:1.2rem;flex-shrink:0}
/* Wallet */
.wallet-card{background:linear-gradient(135deg,var(--vp-primary),color-mix(in srgb,var(--vp-primary) 60%,#8b5cf6));border-radius:var(--vp-radius);color:#fff}
/* Ad card */
.ad-slot-card{border:2px dashed #e5e7eb;border-radius:var(--vp-radius);background:#fafafa;transition:border-color .2s,background .2s}
.ad-slot-card.active-period{border-color:var(--vp-primary);background:var(--vp-primary-light)}
/* Task cards */
.task-card{transition:transform .18s,box-shadow .18s}
.task-card:hover{transform:translateY(-2px);box-shadow:0 6px 24px rgba(0,0,0,.09)!important}
/* Package cards */
.pkg-card{transition:transform .2s}
.pkg-card:hover{transform:translateY(-4px)}
/* Badge */
.badge-soft{background:var(--vp-primary-light);color:var(--vp-primary)}
/* Alerts */
.alert{border-radius:.65rem}
/* Footer */
.vp-footer{border-top:1px solid #e5e7eb;background:#fff}
/* Splash */
#vp-splash{position:fixed;inset:0;background:' . e($splashBg) . ';display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:9999;transition:opacity .5s}
#vp-splash.hide{opacity:0;pointer-events:none}
#vp-splash h1{color:#fff;font-weight:800;font-size:1.8rem;margin-top:16px}
#vp-splash p{color:rgba(255,255,255,.75);font-size:.9rem}
/* PWA install banner */
#pwa-banner{position:fixed;bottom:0;left:0;right:0;z-index:1060;transform:translateY(100%);transition:transform .4s cubic-bezier(.16,1,.3,1)}
#pwa-banner.show{transform:translateY(0)}
/* Utils */
.rounded-xl{border-radius:1rem}
.text-truncate-2{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.opacity-60{opacity:.6}
.gap-lg-4{gap:1.5rem!important}
@media(max-width:991.98px){
  .vp-sidebar{display:none!important}
  .vp-sidebar.show{display:flex!important;position:fixed;z-index:1050;top:0;left:0;height:100%;width:260px;box-shadow:8px 0 30px rgba(0,0,0,.3)}
}
@media(prefers-color-scheme:dark){
  body.auto-dark{background:#0f0f1a;color:#e2e8f0}
}
</style>' . "\n";
}

function render_nav(): void
{
    $user    = auth();
    $site    = setting('site_name',    APP_NAME);
    $logo    = setting('logo');
    $primary = setting('primary_color','#6366f1');
    $curr    = get_currency();
    $logoHtml = $logo
        ? '<img src="'.url('uploads/'.$logo).'" style="height:32px;max-width:130px;object-fit:contain" alt="'.e($site).'">'
        : '<div style="width:30px;height:30px;background:'.e($primary).';border-radius:7px;display:grid;place-items:center"><i class="bi bi-play-circle-fill text-white" style="font-size:.9rem"></i></div>'
          . '<span class="d-none d-md-inline">'.e($site).'</span>';

    echo '<nav class="vp-navbar navbar navbar-expand-lg sticky-top shadow-sm">';
    echo '<div class="container-fluid px-3 px-lg-4">';
    echo '<a class="navbar-brand text-decoration-none" href="'.url().'">'. $logoHtml.'</a>';
    echo '<button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#vpNav" aria-controls="vpNav" aria-expanded="false"><span class="navbar-toggler-icon"></span></button>';
    echo '<div class="collapse navbar-collapse" id="vpNav">';
    echo '<ul class="navbar-nav me-auto align-items-lg-center gap-1">';

    // CMS header pages
    try {
        $hpages = db()->query("SELECT title,slug FROM pages WHERE show_in_header=1 AND is_active=1 ORDER BY sort_order LIMIT 5")->fetchAll();
        foreach ($hpages as $hp) {
            echo '<li class="nav-item"><a class="nav-link fw-500" href="'.url('page/'.$hp['slug']).'">'.e($hp['title']).'</a></li>';
        }
    } catch (Exception $e) {}

    echo '<li class="nav-item"><a class="nav-link fw-500" href="'.url('tasks').'"><i class="bi bi-play-circle me-1"></i>Earn</a></li>';
    echo '<li class="nav-item"><a class="nav-link fw-500" href="'.url('packages').'"><i class="bi bi-megaphone me-1"></i>Advertise</a></li>';
    echo '<li class="nav-item"><a class="nav-link fw-500" href="'.url('watch-ads').'"><i class="bi bi-tv me-1"></i>Watch Ads</a></li>';
    echo '</ul>';

    echo '<div class="d-flex align-items-center gap-2 mt-3 mt-lg-0">';
    if ($user) {
        echo '<a href="'.url('wallet').'" class="btn btn-sm btn-outline-success fw-600 d-none d-md-inline-flex align-items-center gap-1"><i class="bi bi-wallet2"></i>'.money((float)$user['wallet_balance']).'</a>';
        // Email verification warning
        if (empty($user['email_verified_at']) && setting('email_verification_required','0')==='1') {
            echo '<span class="badge bg-warning text-dark" title="Email not verified"><i class="bi bi-exclamation-circle me-1"></i>Unverified</span>';
        }
        echo '<div class="dropdown"><button class="btn btn-sm border fw-500 d-flex align-items-center gap-2 py-1" data-bs-toggle="dropdown">';
        echo '<img src="'.e(avatar_url($user['avatar']??null,$user['name'])).'" width="26" height="26" class="rounded-circle" alt="">';
        echo '<span class="d-none d-md-inline small">'.e(explode(' ',$user['name'])[0]).'</span>';
        echo '<i class="bi bi-chevron-down" style="font-size:.55rem"></i></button>';
        echo '<ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-1" style="min-width:210px">';
        echo '<li class="px-3 py-2 border-bottom"><div class="fw-600 small">'.e($user['name']).'</div><div class="text-muted" style="font-size:.72rem">'.e($user['email']).'</div>';
        if (!empty($user['is_admin'])) { echo '<span class="badge bg-danger mt-1" style="font-size:.6rem">Admin</span>'; }
        elseif (($user['role']??'')==='moderator') { echo '<span class="badge bg-warning text-dark mt-1" style="font-size:.6rem">Moderator</span>'; }
        echo '</li>';
        foreach ([['dashboard','speedometer2','Dashboard'],['tasks','play-circle','Earn Money'],['creator','megaphone','My Campaigns'],['watch-ads','tv','Watch Ads'],['wallet','wallet2','Wallet'],['profile','person','Profile']] as [$path,$icon,$lbl]) {
            echo '<li><a class="dropdown-item py-2 small" href="'.url($path).'"><i class="bi bi-'.$icon.' me-2"></i>'.$lbl.'</a></li>';
        }
        if (is_admin()) {
            echo '<li><hr class="dropdown-divider"></li>';
            echo '<li><a class="dropdown-item py-2 small fw-600" style="color:var(--vp-primary)" href="'.url('admin').'"><i class="bi bi-shield-fill me-2"></i>Admin Panel</a></li>';
        } elseif (is_moderator()) {
            echo '<li><hr class="dropdown-divider"></li>';
            echo '<li><a class="dropdown-item py-2 small fw-600" style="color:#f59e0b" href="'.url('admin').'"><i class="bi bi-shield me-2"></i>Moderator Panel</a></li>';
        }
        echo '<li><hr class="dropdown-divider"></li>';
        echo '<li><a class="dropdown-item py-2 small text-danger" href="'.url('logout').'"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>';
        echo '</ul></div>';
    } else {
        echo '<a href="'.url('login').'" class="btn btn-sm btn-outline-secondary fw-600">Log In</a>';
        echo '<a href="'.url('register').'" class="btn btn-sm btn-primary fw-600">Sign Up Free</a>';
    }
    echo '</div></div></div></nav>';
}

function render_flash(): void
{
    $f = get_flash(); if (!$f) return;
    $types = ['success'=>['alert-success','check-circle-fill'],'error'=>['alert-danger','exclamation-triangle-fill'],'warning'=>['alert-warning','exclamation-triangle'],'info'=>['alert-info','info-circle-fill']];
    [$cls,$icon] = $types[$f['type']] ?? ['alert-secondary','info-circle-fill'];
    echo '<div class="container-fluid px-3 px-lg-4 pt-3">';
    echo '<div class="alert '.$cls.' alert-dismissible fade show d-flex align-items-center gap-2 py-2 border-0 shadow-sm">';
    echo '<i class="bi bi-'.$icon.' flex-shrink-0"></i><span>'.e($f['msg']).'</span>';
    echo '<button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>';
    echo '</div></div>';
}

function render_footer(): void
{
    $site  = setting('site_name',    APP_NAME);
    $email = setting('site_email',   '');
    $wa    = setting('support_whatsapp', '');
    try {
        $fpages = db()->query("SELECT title,slug FROM pages WHERE show_in_footer=1 AND is_active=1 ORDER BY sort_order LIMIT 8")->fetchAll();
    } catch(Exception $e) { $fpages=[]; }

    echo '<footer class="vp-footer py-5 mt-auto">';
    echo '<div class="container"><div class="row g-4">';
    echo '<div class="col-md-4"><h5 class="fw-800 mb-2">'.e($site).'</h5>';
    echo '<p class="text-muted small">'.e(setting('site_description','Earn money by watching videos and visiting websites.')).'</p>';
    if ($email) echo '<div class="small text-muted mb-1"><i class="bi bi-envelope me-2"></i>'.e($email).'</div>';
    if ($wa)    echo '<div class="small text-muted"><i class="bi bi-whatsapp text-success me-2"></i>'.e($wa).'</div>';
    echo '</div>';
    echo '<div class="col-md-3"><h6 class="fw-700 mb-3">Quick Links</h6><ul class="list-unstyled small">';
    foreach ([['Earn Money','tasks'],['Buy Views','packages'],['Watch Ads','watch-ads'],['Dashboard','dashboard'],['Wallet','wallet']] as [$l,$p]) {
        echo '<li class="mb-2"><a href="'.url($p).'" class="text-muted text-decoration-none">'.$l.'</a></li>';
    }
    echo '</ul></div>';
    echo '<div class="col-md-3"><h6 class="fw-700 mb-3">Information</h6><ul class="list-unstyled small">';
    foreach ($fpages as $pg) {
        echo '<li class="mb-2"><a href="'.url('page/'.$pg['slug']).'" class="text-muted text-decoration-none">'.e($pg['title']).'</a></li>';
    }
    echo '</ul></div>';
    echo '<div class="col-md-2"><h6 class="fw-700 mb-3">Download App</h6>';
    echo '<button id="pwaInstallBtn" class="btn btn-sm btn-outline-primary w-100 d-none fw-600"><i class="bi bi-download me-1"></i>Install App</button>';
    echo '<div class="mt-2 d-flex flex-wrap gap-2">';
    foreach ([['phone-fill','#10b981','MoMo'],['credit-card-fill','#00C3F7','Paystack'],['bank','#3b82f6','Bank']] as [$ic,$col,$tip]) {
        echo '<div title="'.$tip.'" style="width:34px;height:34px;background:'.$col.'22;color:'.$col.';border-radius:8px;display:grid;place-items:center"><i class="bi bi-'.$ic.'"></i></div>';
    }
    echo '</div></div>';
    echo '</div>';
    echo '<hr class="mt-4 mb-3"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2">';
    echo '<div class="small text-muted">&copy; '.date('Y').' '.e($site).'. All rights reserved.</div>';
    echo '<div class="small text-muted">Developed by <strong>Jcode</strong> &nbsp;|&nbsp; Author: <strong>Obrempong</strong> &nbsp;|&nbsp; v'.VP_VERSION.'</div>';
    echo '</div></div></footer>';

    // Bootstrap JS
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>';

    // PWA service worker + install
    $splashBg  = setting('pwa_splash_bg',  '#6366f1');
    $splashTxt = setting('pwa_splash_text', setting('site_name', APP_NAME));
    $splashLogo = setting('logo');
    echo '<script>
// PWA Install
let deferredPrompt = null;
window.addEventListener("beforeinstallprompt", e => {
    e.preventDefault(); deferredPrompt = e;
    document.getElementById("pwaInstallBtn")?.classList.remove("d-none");
});
document.getElementById("pwaInstallBtn")?.addEventListener("click", async () => {
    if (!deferredPrompt) return;
    deferredPrompt.prompt();
    const {outcome} = await deferredPrompt.userChoice;
    deferredPrompt = null;
    if(outcome==="accepted") document.getElementById("pwaInstallBtn")?.classList.add("d-none");
});

// Service worker
if ("serviceWorker" in navigator) {
    navigator.serviceWorker.register("'.url('sw.js').'").catch(()=>{});
}

// Splash screen
const splash = document.getElementById("vp-splash");
if (splash) { window.addEventListener("load", ()=>{ setTimeout(()=>splash.classList.add("hide"),800); }); }
</script>';
}

function page_start(string $title = ''): void
{
    render_head($title);
    // Splash screen
    $splashOn  = setting('pwa_splash_enabled','1') === '1';
    $splashBg  = setting('pwa_splash_bg', '#6366f1');
    $splashTxt = setting('pwa_splash_text', setting('site_name', APP_NAME));
    $splashLogo = setting('logo');

    echo '</head><body>';

    if ($splashOn) {
        echo '<div id="vp-splash">';
        if ($splashLogo) {
            echo '<img src="'.url('uploads/'.$splashLogo).'" style="height:60px;object-fit:contain;filter:brightness(0) invert(1)" alt="'.e($splashTxt).'">';
        } else {
            echo '<div style="width:64px;height:64px;background:rgba(255,255,255,.15);border-radius:14px;display:grid;place-items:center"><i class="bi bi-play-circle-fill text-white" style="font-size:2rem"></i></div>';
        }
        echo '<h1>'.e($splashTxt).'</h1>';
        echo '<p>Loading…</p>';
        echo '</div>';
    }

    render_nav();
    render_flash();
}

function page_end(): void
{
    render_footer();
    echo '</body></html>';
}
