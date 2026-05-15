<?php
/**
 * ViewPay — Home / Landing Page
 * Developed by Jcode | Author: Obrempong
 */
defined('VP_BOOT') || die('Direct access not permitted.');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/layout.php';

$site    = setting('site_name', APP_NAME);
$primary = setting('primary_color', '#6366f1');

try {
    $stats = [
        'users'   => (int)db()->query("SELECT COUNT(*) FROM users WHERE status='active' AND is_admin=0")->fetchColumn(),
        'views'   => (int)db()->query("SELECT COUNT(*) FROM task_views WHERE status='valid'")->fetchColumn(),
        'paid'    => (float)db()->query("SELECT COALESCE(SUM(reward_earned),0) FROM task_views WHERE status='valid'")->fetchColumn(),
    ];
    $featPkgs = db()->query(
        "SELECT * FROM packages WHERE is_active=1 AND is_featured=1 ORDER BY sort_order LIMIT 4"
    )->fetchAll();
} catch (Exception $e) {
    $stats    = ['users' => 0, 'views' => 0, 'paid' => 0];
    $featPkgs = [];
}

page_start('Earn Money Watching Videos — ' . $site);
?>

<!-- ── Hero ─────────────────────────────────────────────── -->
<section style="background:linear-gradient(135deg,<?= e($primary) ?>18 0%,#8b5cf618 100%);padding:80px 0 70px">
  <div class="container text-center">
    <span class="badge rounded-pill text-white px-3 py-2 mb-3 d-inline-block"
          style="background:<?= e($primary) ?>;font-size:.82rem">
      🇬🇭 Ghana's #1 View Monetization Platform
    </span>
    <h1 class="display-4 fw-bold mb-3 lh-sm">
      Earn Real Money<br>
      <span style="color:<?= e($primary) ?>">Watching Videos &amp; Websites</span>
    </h1>
    <p class="lead text-muted mb-4 mx-auto" style="max-width:540px">
      Watch content, complete tasks, and get paid instantly in cedis.
      Creators get real audiences. Earners get real money.
    </p>
    <div class="d-flex gap-3 justify-content-center flex-wrap mb-5">
      <a href="<?= url('register') ?>" class="btn btn-primary btn-lg px-5 fw-600 shadow">
        <i class="bi bi-rocket-takeoff-fill me-2"></i>Start Earning Free
      </a>
      <a href="<?= url('packages') ?>" class="btn btn-outline-secondary btn-lg px-4 fw-600">
        <i class="bi bi-megaphone me-2"></i>Buy Views
      </a>
    </div>
    <!-- Stats -->
    <div class="row justify-content-center g-3">
      <?php foreach ([
        [number_format($stats['users']),             'Active Earners',    'people-fill',     $primary],
        [number_format($stats['views']),             'Views Delivered',   'eye-fill',        '#10b981'],
        [money($stats['paid'], 0),                   'Total Paid Out',    'currency-dollar', '#f59e0b'],
      ] as [$val, $lbl, $icon, $col]): ?>
      <div class="col-4 col-md-2">
        <div class="card border-0 shadow-sm py-3">
          <i class="bi bi-<?= $icon ?> mb-1 fs-5" style="color:<?= e($col) ?>"></i>
          <div class="fw-bold fs-5"><?= $val ?></div>
          <div class="text-muted" style="font-size:.75rem"><?= $lbl ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── How it works ────────────────────────────────────── -->
<section class="py-5 bg-white">
  <div class="container">
    <div class="text-center mb-5">
      <h2 class="fw-bold">Simple. Fast. Pays.</h2>
      <p class="text-muted">Get started in 3 easy steps</p>
    </div>
    <div class="row g-4 text-center">
      <?php foreach ([
        ['1','person-plus-fill',   'Create Free Account', 'Sign up in 30 seconds — no credit card needed.',                                     $primary],
        ['2','play-circle-fill',   'Complete Tasks',       'Watch videos or visit websites for the required time, then click Claim Reward.',      '#10b981'],
        ['3','wallet2',            'Withdraw & Get Paid',  'Transfer earnings to your MoMo, bank account, or PayPal. Fast processing.',           '#f59e0b'],
      ] as [$n, $icon, $title, $desc, $col]): ?>
      <div class="col-md-4">
        <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-4"
             style="width:72px;height:72px;background:<?= $col ?>18;color:<?= $col ?>">
          <i class="bi bi-<?= $icon ?> fs-3"></i>
        </div>
        <div class="badge rounded-pill mb-2" style="background:<?= $col ?>;font-size:.75rem">Step <?= $n ?></div>
        <h5 class="fw-bold"><?= $title ?></h5>
        <p class="text-muted small"><?= $desc ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── For Creators ───────────────────────────────────── -->
<section class="py-5" style="background:linear-gradient(135deg,<?= e($primary) ?>08,#8b5cf608)">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-md-6">
        <span class="badge mb-3" style="background:<?= e($primary) ?>22;color:<?= e($primary) ?>">For Creators</span>
        <h2 class="fw-bold mb-3">Grow Your Audience with Real Views</h2>
        <p class="text-muted mb-4">Buy a views package and hundreds of real registered users will watch your video or visit your website. Admin confirms every payment. No bots — ever.</p>
        <ul class="list-unstyled">
          <?php foreach (['100% real registered users','Admin confirms every payment','Campaign activates within 24 hours','Detailed view analytics'] as $f): ?>
          <li class="mb-2 d-flex align-items-center gap-2">
            <i class="bi bi-check-circle-fill" style="color:<?= e($primary) ?>"></i>
            <span class="small"><?= $f ?></span>
          </li>
          <?php endforeach; ?>
        </ul>
        <a href="<?= url('packages') ?>" class="btn btn-primary mt-2 fw-600 px-4">
          View Packages <i class="bi bi-arrow-right ms-1"></i>
        </a>
      </div>
      <div class="col-md-6">
        <div class="card border-0 shadow-lg rounded-xl overflow-hidden">
          <div class="p-4" style="background:<?= e($primary) ?>">
            <div class="text-white fw-bold mb-1">Campaign Dashboard</div>
            <div class="text-white-50 small">Track your campaign performance</div>
          </div>
          <div class="p-4">
            <?php foreach ([['Video Views','2,450 / 5,000','49%','#10b981'],['Website Visits','980 / 1,000','98%','#6366f1'],['Total Spent',money(15.00),'','#f59e0b']] as [$l,$v,$pct,$c]): ?>
            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
              <span class="small text-muted"><?= $l ?></span>
              <span class="fw-600 small" style="color:<?= $c ?>"><?= $v ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── Featured Packages ──────────────────────────────── -->
<?php if ($featPkgs): ?>
<section class="py-5 bg-white">
  <div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2 class="fw-bold mb-1">Popular Packages</h2>
        <p class="text-muted small mb-0">Choose a package that fits your goals</p>
      </div>
      <a href="<?= url('packages') ?>" class="btn btn-outline-primary btn-sm fw-600">
        All Packages <i class="bi bi-arrow-right ms-1"></i>
      </a>
    </div>
    <div class="row g-3">
      <?php foreach ($featPkgs as $pkg):
        $feats   = json_decode((string)($pkg['features'] ?? '[]'), true) ?: [];
        $color   = $pkg['color'] ?: '#6366f1';
        $cpv     = $pkg['views_count'] > 0 ? $pkg['price'] / $pkg['views_count'] : 0;
      ?>
      <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100 pkg-card position-relative"
             style="<?= $pkg['is_featured'] ? 'border:2px solid ' . $color . '!important' : '' ?>">
          <?php if ($pkg['badge']): ?>
          <div class="position-absolute top-0 end-0 m-2">
            <span class="badge px-2" style="background:<?= e($color) ?>"><?= e($pkg['badge']) ?></span>
          </div>
          <?php endif; ?>
          <div class="card-body p-4 text-center">
            <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3"
                 style="width:48px;height:48px;background:<?= e($color) ?>18;color:<?= e($color) ?>">
              <i class="bi bi-<?= $pkg['type']==='video' ? 'play-circle-fill' : ($pkg['type']==='website' ? 'globe2' : 'collection') ?> fs-5"></i>
            </div>
            <h6 class="fw-bold mb-1"><?= e($pkg['name']) ?></h6>
            <div class="fw-bold fs-4 mb-1" style="color:<?= e($color) ?>"><?= money((float)$pkg['price']) ?></div>
            <div class="text-muted small mb-3"><?= number_format((int)$pkg['views_count']) ?> views · <?= $pkg['duration_days'] ?> days</div>
            <ul class="list-unstyled text-start small mb-3">
              <?php foreach (array_slice($feats, 0, 3) as $f): ?>
              <li class="mb-1"><i class="bi bi-check-circle-fill me-1" style="color:<?= e($color) ?>;font-size:.7rem"></i><?= e($f) ?></li>
              <?php endforeach; ?>
            </ul>
            <a href="<?= url('packages') ?>" class="btn btn-sm w-100 text-white fw-600" style="background:<?= e($color) ?>">
              Get Started
            </a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ── Testimonials / Trust ───────────────────────────── -->
<section class="py-5" style="background:#f8fafc">
  <div class="container">
    <div class="text-center mb-5">
      <h2 class="fw-bold">Trusted by Earners &amp; Creators</h2>
    </div>
    <div class="row g-4">
      <?php foreach ([
        ['Kwame A.','Accra','I earned ₵45 in my first week just watching YouTube videos during lunch breaks. Payments are fast!','⭐⭐⭐⭐⭐'],
        ['Ama D.','Kumasi','My video got 2,000 real views in 3 days. Admin confirmed my MoMo payment within hours. Legit!','⭐⭐⭐⭐⭐'],
        ['Kofi M.','Takoradi','Easy to use. Watch a video, click claim. The wallet system is smooth and withdrawals are quick.','⭐⭐⭐⭐⭐'],
      ] as [$name, $city, $text, $stars]): ?>
      <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100 p-4">
          <div class="mb-3"><?= $stars ?></div>
          <p class="text-muted small mb-3">"<?= $text ?>"</p>
          <div class="d-flex align-items-center gap-2 mt-auto">
            <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
                 style="width:36px;height:36px;background:<?= e($primary) ?>;flex-shrink:0">
              <?= strtoupper($name[0]) ?>
            </div>
            <div>
              <div class="fw-600 small"><?= $name ?></div>
              <div class="text-muted" style="font-size:.72rem"><?= $city ?></div>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── CTA ────────────────────────────────────────────── -->
<section class="py-5 text-white" style="background:linear-gradient(135deg,<?= e($primary) ?>,#8b5cf6)">
  <div class="container text-center">
    <h2 class="fw-bold mb-3">Ready to Start Earning Today?</h2>
    <p class="mb-4 opacity-75">Join <?= number_format($stats['users']) ?>+ earners already making money on <?= e($site) ?>.</p>
    <a href="<?= url('register') ?>" class="btn btn-white btn-lg px-5 fw-700 text-dark shadow">
      <i class="bi bi-person-plus-fill me-2"></i>Create Free Account
    </a>
    <div class="mt-3 small opacity-75">No credit card · No fees · Instant setup</div>
  </div>
</section>

<?php page_end(); ?>
