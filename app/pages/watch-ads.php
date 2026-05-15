<?php
/**
 * ViewPay v2.0 — Watch Ads to Earn
 * Time-slot system: Morning (06–11), Afternoon (12–17), Night (18–05)
 * Admin configures limits and rewards per period.
 */
defined('VP_BOOT') || die('Direct access not permitted.');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/layout.php';
require_auth();

$user   = auth();
$network = setting('ad_network', 'custom');
$enabled = setting('ad_watching_enabled', '1') === '1';

// Email verification gate
if (setting('email_verification_required', '0') === '1' && empty($user['email_verified_at'])) {
    flash('warning', 'Please verify your email address before watching ads.');
    redirect('/dashboard');
}

// Handle claim POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (!$enabled) {
        flash('error', 'Ad watching is currently disabled.');
        redirect('/watch-ads');
    }

    if (!rate_limit('adwatch_' . $user['id'], 1, 4)) {
        flash('error', 'Please wait a moment before claiming the next ad reward.');
        redirect('/watch-ads');
    }

    $adInfo = ad_can_watch((int)$user['id']);

    if (!$adInfo['can']) {
        flash('warning', 'You have reached the ' . ucfirst($adInfo['period']) . ' ad limit (' . $adInfo['limit'] . '). Come back during the next period.');
        redirect('/watch-ads');
    }

    $period  = $adInfo['period'];
    $reward  = $adInfo['reward']; // USD

    // Fraud: same IP watching too fast
    $recentSt = db()->prepare(
        "SELECT COUNT(*) FROM ad_watches WHERE user_id=? AND ip_address=? AND watched_at > DATE_SUB(NOW(), INTERVAL 3 MINUTE)"
    );
    $recentSt->execute([$user['id'], vp_ip()]);
    if ((int)$recentSt->fetchColumn() > 2) {
        flash('error', 'Suspicious activity detected. Please slow down.');
        redirect('/watch-ads');
    }

    // Record the watch
    db()->prepare(
        "INSERT INTO ad_watches (user_id, period, ad_network, reward_earned, ip_address, watched_at)
         VALUES (?, ?, ?, ?, ?, ?)"
    )->execute([$user['id'], $period, $network, $reward, vp_ip(), now_ts()]);

    // Credit wallet
    db()->prepare(
        "UPDATE users SET wallet_balance=wallet_balance+?, total_earned=total_earned+?, updated_at=? WHERE id=?"
    )->execute([$reward, $reward, now_ts(), $user['id']]);

    // Audit
    audit('ad_watch', "Period: {$period}, Reward: {$reward} USD, Network: {$network}", (int)$user['id']);

    flash('success', 'Ad reward of ' . money($reward, 4) . ' credited to your wallet!');
    redirect('/watch-ads');
}

// Build period info
$periods = [
    'morning'   => ['name' => 'Morning',   'time' => '6:00 AM – 12:00 PM', 'icon' => 'sunrise-fill',  'color' => '#f59e0b'],
    'afternoon' => ['name' => 'Afternoon', 'time' => '12:00 PM – 6:00 PM', 'icon' => 'sun-fill',       'color' => '#10b981'],
    'night'     => ['name' => 'Night',     'time' => '6:00 PM – 3:00 AM',  'icon' => 'moon-stars-fill','color' => '#6366f1'],
];

$tz      = setting('timezone', 'UTC');
$nowHour = (int)(new DateTime('now', new DateTimeZone($tz)))->format('G');
$current = ad_current_period();
$adInfo  = ad_can_watch((int)$user['id']);

// Load today stats for all periods
$today = (new DateTime('now', new DateTimeZone($tz)))->format('Y-m-d');
$pStats = [];
foreach ($periods as $pk => $pv) {
    $lim  = ad_period_limit($pk);
    $done = ad_watches_period((int)$user['id'], $pk);
    $pStats[$pk] = ['limit' => $lim, 'done' => $done, 'left' => max(0,$lim-$done), 'reward' => ad_period_reward($pk)];
}

$totalDone  = array_sum(array_column($pStats, 'done'));
$totalLimit = array_sum(array_column($pStats, 'limit'));

// Ad network config
$adNetworks = [
    'custom'   => ['Google AdSense',  'Use your AdSense ad unit code'],
    'admob'    => ['AdMob',           'Google AdMob ad unit'],
    'unity'    => ['Unity Ads',       'Unity rewarded ad unit'],
    'ironsrc'  => ['IronSource',      'IronSource rewarded ads'],
    'inhouse'  => ['In-House Ads',    'Admin-defined HTML ads'],
];
$adCode = setting('ad_watch_code', '');

page_start('Watch Ads & Earn');
?>

<div class="container py-4">
  <div class="row justify-content-center">
  <div class="col-xl-10">

  <!-- Header -->
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
      <h4 class="fw-bold mb-1"><i class="bi bi-tv-fill me-2 text-primary"></i>Watch Ads &amp; Earn</h4>
      <p class="text-muted small mb-0">Watch short ads to earn extra rewards. <?= $totalLimit ?> ads available daily.</p>
    </div>
    <a href="<?= url('wallet') ?>" class="btn btn-outline-success fw-600 btn-sm">
      <i class="bi bi-wallet2 me-1"></i><?= money((float)$user['wallet_balance']) ?>
    </a>
  </div>

  <?php if (!$enabled): ?>
  <div class="alert alert-warning border-0 shadow-sm d-flex gap-2">
    <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
    <div><strong>Ad watching is currently disabled.</strong> Admin has turned this feature off temporarily. Check back later.</div>
  </div>
  <?php else: ?>

  <!-- Daily progress overview -->
  <div class="card border-0 shadow-sm rounded-xl p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div class="fw-600">Today's Progress</div>
      <span class="badge bg-primary"><?= $totalDone ?> / <?= $totalLimit ?> ads watched</span>
    </div>
    <div class="progress rounded-pill mb-2" style="height:10px">
      <div class="progress-bar bg-success rounded-pill" style="width:<?= $totalLimit>0?min(100,round($totalDone/$totalLimit*100)):0 ?>%"></div>
    </div>
    <div class="row g-3 mt-1">
      <?php foreach ($periods as $pk => $pv):
        $ps    = $pStats[$pk];
        $isCur = ($pk === $current);
        $pct   = $ps['limit']>0 ? min(100,round($ps['done']/$ps['limit']*100)) : 0;
      ?>
      <div class="col-md-4">
        <div class="ad-slot-card p-3 <?= $isCur?'active-period':'' ?>">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="fw-600 small d-flex align-items-center gap-2">
              <i class="bi bi-<?= $pv['icon'] ?>" style="color:<?= $pv['color'] ?>"></i>
              <?= $pv['name'] ?>
              <?php if ($isCur): ?><span class="badge bg-success" style="font-size:.6rem">NOW</span><?php endif; ?>
            </div>
            <small class="text-muted"><?= $ps['done'] ?>/<?= $ps['limit'] ?></small>
          </div>
          <div class="progress rounded-pill mb-1" style="height:5px">
            <div class="progress-bar rounded-pill" style="width:<?= $pct ?>%;background:<?= $pv['color'] ?>"></div>
          </div>
          <div class="d-flex justify-content-between mt-1" style="font-size:.72rem;color:#6b7280">
            <span><?= $pv['time'] ?></span>
            <span class="fw-600 text-success"><?= money($ps['reward'],4) ?>/ad</span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Current period + Ad player -->
  <?php if ($adInfo['can']): ?>
  <div class="card border-0 shadow-xl rounded-xl overflow-hidden mb-4">
    <div class="p-3 text-white" style="background:<?= $periods[$current]['color'] ?>">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <div class="fw-700 fs-5">
            <i class="bi bi-<?= $periods[$current]['icon'] ?> me-2"></i>
            <?= $periods[$current]['name'] ?> Ads
          </div>
          <div class="small opacity-75"><?= $adInfo['left'] ?> ads remaining this period</div>
        </div>
        <div class="text-end">
          <div class="fw-bold fs-4"><?= money($adInfo['reward'], 4) ?></div>
          <div class="small opacity-75">per ad</div>
        </div>
      </div>
    </div>

    <div class="card-body p-0">
      <!-- Ad Display Area -->
      <div class="bg-dark d-flex align-items-center justify-content-center" style="min-height:280px;position:relative" id="adContainer">

        <?php if ($network === 'inhouse' && $adCode): ?>
          <!-- In-house custom ad HTML -->
          <div class="w-100 h-100 d-flex align-items-center justify-content-center p-3">
            <?= $adCode /* Admin-controlled HTML — trusted */ ?>
          </div>

        <?php elseif ($network === 'custom' && setting('adsense_client') && setting('adsense_slot')): ?>
          <!-- Google AdSense -->
          <div class="w-100 p-3">
            <ins class="adsbygoogle"
                 style="display:block"
                 data-ad-client="<?= e(setting('adsense_client')) ?>"
                 data-ad-slot="<?= e(setting('adsense_slot')) ?>"
                 data-ad-format="auto"
                 data-full-width-responsive="true"></ins>
            <script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
          </div>

        <?php else: ?>
          <!-- Placeholder ad (no network configured) -->
          <div class="text-center text-white p-5">
            <i class="bi bi-tv display-2 d-block mb-3 opacity-25"></i>
            <h5 class="fw-bold">Ad Space</h5>
            <p class="opacity-50 small">Admin has not configured an ad network yet.<br>The timer still counts — you'll earn your reward.</p>
          </div>
        <?php endif; ?>

        <!-- Countdown overlay -->
        <div id="adOverlay" style="position:absolute;inset:0;background:rgba(0,0,0,.7);display:flex;align-items:center;justify-content:center;flex-direction:column;z-index:10">
          <div class="text-white fw-bold" style="font-size:3rem;line-height:1" id="countdown"><?= (int)setting('ad_min_watch_seconds','10') ?></div>
          <div class="text-white-50 small mt-1">seconds remaining</div>
          <div class="mt-3"><button class="btn btn-outline-light btn-sm" onclick="startAd()"><i class="bi bi-play-fill me-1"></i>Start Watching</button></div>
        </div>
      </div>

      <!-- Claim bar -->
      <div class="p-3 border-top d-flex align-items-center gap-3">
        <div class="flex-grow-1">
          <div class="progress rounded-pill" style="height:8px">
            <div id="adProgress" class="progress-bar bg-success rounded-pill" style="width:0%;transition:width 1s linear"></div>
          </div>
          <div class="mt-1 text-muted" style="font-size:.75rem" id="adStatus">Click Start Watching to begin</div>
        </div>

        <form method="POST" id="claimForm">
          <?= csrf_field() ?>
          <button type="submit" id="claimBtn" class="btn btn-success fw-700 px-4" disabled>
            <i class="bi bi-gift-fill me-1"></i>Claim <?= money($adInfo['reward'],4) ?>
          </button>
        </form>
      </div>
    </div>
  </div>

  <?php else: ?>
  <!-- Period exhausted -->
  <div class="card border-0 shadow-sm rounded-xl p-5 text-center mb-4">
    <i class="bi bi-check-circle-fill display-3 text-success d-block mb-3"></i>
    <h5 class="fw-bold"><?= ucfirst($current) ?> ads completed!</h5>
    <p class="text-muted">You've watched all <?= $pStats[$current]['limit'] ?> <?= $current ?> ads. Come back during the next period.</p>
    <?php
      $nextPeriod = match($current) { 'morning'=>'afternoon', 'afternoon'=>'night', default=>'morning' };
      $nextTime   = match($current) { 'morning'=>'12:00 PM', 'afternoon'=>'6:00 PM', default=>'6:00 AM tomorrow' };
    ?>
    <div class="alert alert-info border-0 shadow-sm d-inline-block px-4 py-2 small mt-2">
      <i class="bi bi-clock me-1"></i>Next period: <strong><?= ucfirst($nextPeriod) ?></strong> from <?= $nextTime ?>
    </div>
    <div class="mt-4">
      <a href="<?= url('tasks') ?>" class="btn btn-primary fw-600 me-2"><i class="bi bi-play-circle me-1"></i>Earn via Tasks</a>
      <a href="<?= url('wallet') ?>" class="btn btn-outline-secondary fw-600"><i class="bi bi-wallet2 me-1"></i>My Wallet</a>
    </div>
  </div>
  <?php endif; ?>

  <!-- All-time stats -->
  <?php
  try {
      $myStats = db()->prepare("SELECT COUNT(*) cnt, COALESCE(SUM(reward_earned),0) total_earned, period FROM ad_watches WHERE user_id=? GROUP BY period");
      $myStats->execute([$user['id']]); $myStats=$myStats->fetchAll(PDO::FETCH_ASSOC);
      $statByPeriod = array_column($myStats, null, 'period');
      $allTimeWatched = (int)db()->prepare("SELECT COUNT(*) FROM ad_watches WHERE user_id=?")->execute([$user['id']]) ? 0 : 0;
      $at=db()->prepare("SELECT COUNT(*), COALESCE(SUM(reward_earned),0) FROM ad_watches WHERE user_id=?"); $at->execute([$user['id']]); $atRow=$at->fetch(PDO::FETCH_NUM);
  } catch(Exception $e){ $statByPeriod=[]; $atRow=[0,0]; }
  ?>
  <div class="card border-0 shadow-sm rounded-xl p-4">
    <div class="fw-600 mb-3"><i class="bi bi-bar-chart me-2 text-primary"></i>My Ad Stats</div>
    <div class="row g-3">
      <div class="col-4 text-center">
        <div class="fw-bold fs-4 text-primary"><?= number_format($atRow[0]??0) ?></div>
        <div class="text-muted small">Total Ads Watched</div>
      </div>
      <div class="col-4 text-center">
        <div class="fw-bold fs-4 text-success"><?= money((float)($atRow[1]??0),4) ?></div>
        <div class="text-muted small">Total Ad Earnings</div>
      </div>
      <div class="col-4 text-center">
        <div class="fw-bold fs-4 text-warning"><?= $totalDone ?>/<?= $totalLimit ?></div>
        <div class="text-muted small">Today's Progress</div>
      </div>
    </div>
  </div>

  <?php endif; // $enabled ?>
  </div>
  </div>
</div>

<script>
const minSec = <?= (int)setting('ad_min_watch_seconds','10') ?>;
let timer = null, elapsed = 0, started = false;

function startAd() {
    if (started) return;
    started = true;
    document.getElementById('adOverlay').style.display = 'none';
    document.getElementById('adStatus').textContent = 'Watching ad… do not close this page.';
    timer = setInterval(() => {
        elapsed++;
        const pct = Math.min(100, Math.round(elapsed / minSec * 100));
        document.getElementById('adProgress').style.width = pct + '%';
        const remaining = Math.max(0, minSec - elapsed);
        document.getElementById('countdown').textContent = remaining;
        if (elapsed >= minSec) {
            clearInterval(timer);
            document.getElementById('claimBtn').disabled = false;
            document.getElementById('adStatus').innerHTML = '<span class="text-success fw-600"><i class="bi bi-check-circle-fill me-1"></i>Ad complete! Click Claim to earn your reward.</span>';
        }
    }, 1000);
}

// Prevent double-submit
document.getElementById('claimForm')?.addEventListener('submit', function() {
    document.getElementById('claimBtn').disabled = true;
    document.getElementById('claimBtn').innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Claiming…';
});
</script>

<?php page_end(); ?>
