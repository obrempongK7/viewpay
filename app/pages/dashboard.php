<?php
defined('VP_BOOT') || die('Direct access not permitted.');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/layout.php';
require_auth();

$user = auth();
$curr = get_currency();

// Stats
$tasksToday = (int)db()->prepare(
    "SELECT COUNT(*) FROM task_views WHERE user_id=? AND DATE(created_at)=CURDATE()"
)->execute([$user['id']]) ? db()->prepare("SELECT COUNT(*) FROM task_views WHERE user_id=? AND DATE(created_at)=CURDATE()")->execute([$user['id']]) ?: 0 : 0;

try {
    $st = db()->prepare("SELECT COUNT(*) FROM task_views WHERE user_id=? AND DATE(created_at)=CURDATE()");
    $st->execute([$user['id']]); $tasksToday = (int)$st->fetchColumn();

    $st2 = db()->prepare("SELECT COUNT(*) FROM task_views WHERE user_id=? AND status='valid'");
    $st2->execute([$user['id']]); $totalTasks = (int)$st2->fetchColumn();

    $st3 = db()->prepare("SELECT COALESCE(SUM(reward_earned),0) FROM task_views WHERE user_id=? AND DATE(created_at)=CURDATE()");
    $st3->execute([$user['id']]); $earnedToday = (float)$st3->fetchColumn();

    // Recent activity
    $recent = db()->prepare(
        "SELECT tv.*, t.title, t.type FROM task_views tv
         JOIN tasks t ON tv.task_id=t.id
         WHERE tv.user_id=? ORDER BY tv.created_at DESC LIMIT 8"
    );
    $recent->execute([$user['id']]); $recent = $recent->fetchAll();

    // Available tasks count
    $availCount = (int)db()->prepare(
        "SELECT COUNT(*) FROM tasks
         WHERE status='active' AND is_visible=1 AND views_remaining>0
         AND id NOT IN (SELECT task_id FROM task_views WHERE user_id=? AND status='valid')
         AND user_id != ?"
    )->execute([$user['id'], $user['id']]) ? 0 : 0;
    $ac = db()->prepare("SELECT COUNT(*) FROM tasks WHERE status='active' AND is_visible=1 AND views_remaining>0 AND id NOT IN (SELECT task_id FROM task_views WHERE user_id=? AND status='valid') AND user_id!=?");
    $ac->execute([$user['id'], $user['id']]); $availCount = (int)$ac->fetchColumn();

    $maxDaily = (int)setting('max_views_per_day', '50');
} catch (Exception $e) {
    $tasksToday = $totalTasks = 0; $earnedToday = 0;
    $recent = []; $availCount = 0; $maxDaily = 50;
}

page_start('My Dashboard');
?>
<div class="container py-4">

  <!-- Welcome -->
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
      <h4 class="fw-bold mb-1">Hello, <?= e(explode(' ', $user['name'])[0]) ?>! 👋</h4>
      <p class="text-muted small mb-0">Here's your earning summary for today</p>
    </div>
    <a href="<?= url('tasks') ?>" class="btn btn-primary fw-600">
      <i class="bi bi-play-circle me-1"></i>Browse Tasks
      <?php if ($availCount > 0): ?>
      <span class="badge bg-white text-primary ms-1"><?= $availCount ?></span>
      <?php endif; ?>
    </a>
  </div>

  <!-- Wallet card -->
  <div class="wallet-card p-4 mb-4 rounded-xl">
    <div class="row g-3 align-items-center">
      <div class="col-md-5">
        <div class="text-white-50 small mb-1">Available Balance</div>
        <div class="text-white fw-bold" style="font-size:2.2rem"><?= money((float)$user['wallet_balance']) ?></div>
        <div class="text-white-50 small">≈ $<?= number_format((float)$user['wallet_balance'], 4) ?> USD</div>
        <a href="<?= url('wallet') ?>" class="btn btn-sm btn-white mt-3 fw-600 text-primary">
          <i class="bi bi-arrow-up-circle me-1"></i>Withdraw
        </a>
      </div>
      <div class="col-md-7">
        <div class="row g-3">
          <?php foreach ([
            ['Total Earned',   money((float)$user['total_earned']),          'currency-dollar', 'rgba(255,255,255,.15)'],
            ['Today\'s Earn',  money($earnedToday, 4),                        'lightning-charge-fill', 'rgba(255,255,255,.15)'],
            ['Tasks Today',    $tasksToday . ' / ' . $maxDaily,              'check-circle',   'rgba(255,255,255,.15)'],
            ['All Tasks Done', number_format($totalTasks),                    'trophy-fill',    'rgba(255,255,255,.15)'],
          ] as [$lbl, $val, $icon, $bg]): ?>
          <div class="col-6">
            <div class="p-2 rounded-3 text-white" style="background:<?= $bg ?>">
              <i class="bi bi-<?= $icon ?> mb-1 d-block small"></i>
              <div class="fw-bold small"><?= $val ?></div>
              <div style="font-size:.7rem;opacity:.75"><?= $lbl ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Daily progress -->
  <?php $pct = $maxDaily > 0 ? min(100, round($tasksToday / $maxDaily * 100)) : 0; ?>
  <div class="card border-0 shadow-sm rounded-xl p-3 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <span class="fw-600 small">Daily Task Progress</span>
      <span class="small text-muted"><?= $tasksToday ?>/<?= $maxDaily ?> tasks</span>
    </div>
    <div class="progress" style="height:8px;border-radius:99px">
      <div class="progress-bar" style="width:<?= $pct ?>%;background:var(--vp-primary);border-radius:99px"></div>
    </div>
    <?php if ($tasksToday >= $maxDaily): ?>
    <div class="small text-warning mt-2"><i class="bi bi-exclamation-triangle me-1"></i>Daily limit reached. Come back tomorrow!</div>
    <?php endif; ?>
  </div>

  <div class="row g-4">
    <!-- Recent activity -->
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm rounded-xl h-100">
        <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center pt-3 pb-0 px-4">
          <h6 class="fw-bold mb-0">Recent Activity</h6>
          <a href="<?= url('tasks') ?>" class="btn btn-sm btn-primary fw-600">Find Tasks</a>
        </div>
        <div class="card-body px-4 py-3">
          <?php if ($recent): ?>
          <div class="list-group list-group-flush">
            <?php foreach ($recent as $r): ?>
            <div class="list-group-item px-0 py-3 border-bottom">
              <div class="d-flex justify-content-between align-items-start gap-2">
                <div class="d-flex align-items-start gap-3">
                  <div class="rounded-2 d-flex align-items-center justify-content-center flex-shrink-0 mt-1"
                       style="width:34px;height:34px;background:<?= $r['type']==='video'?'#6366f118':'#10b98118' ?>;color:<?= $r['type']==='video'?'#6366f1':'#10b981' ?>">
                    <i class="bi bi-<?= $r['type']==='video'?'play-circle-fill':'globe2' ?> small"></i>
                  </div>
                  <div>
                    <div class="fw-600 small"><?= e(mb_substr($r['title'], 0, 45)) ?></div>
                    <div class="text-muted" style="font-size:.72rem">
                      <?= date('M d, H:i', strtotime($r['created_at'])) ?> · <?= $r['seconds_watched'] ?>s watched
                    </div>
                  </div>
                </div>
                <div class="text-end flex-shrink-0">
                  <div class="fw-bold small <?= $r['status']==='valid' ? 'text-success' : 'text-muted' ?>">
                    <?= $r['status']==='valid' ? '+' . money((float)$r['reward_earned'], 4) : 'Pending' ?>
                  </div>
                  <span class="badge <?= $r['status']==='valid'?'bg-success':($r['status']==='fraud'?'bg-danger':'bg-secondary') ?>" style="font-size:.62rem">
                    <?= ucfirst($r['status']) ?>
                  </span>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <div class="text-center py-5 text-muted">
            <i class="bi bi-collection-play display-4 d-block mb-3 opacity-25"></i>
            <h6>No activity yet</h6>
            <p class="small">Start watching tasks to earn money.</p>
            <a href="<?= url('tasks') ?>" class="btn btn-primary btn-sm fw-600">Browse Tasks</a>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Quick links -->
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm rounded-xl mb-3">
        <div class="card-body p-4">
          <h6 class="fw-bold mb-3">Quick Actions</h6>
          <div class="d-grid gap-2">
            <a href="<?= url('tasks') ?>" class="btn btn-outline-primary fw-600 text-start">
              <i class="bi bi-play-circle me-2"></i>Browse Tasks <?php if($availCount): ?><span class="badge bg-primary ms-1"><?=$availCount?></span><?php endif; ?>
            </a>
            <a href="<?= url('wallet') ?>" class="btn btn-outline-success fw-600 text-start">
              <i class="bi bi-wallet2 me-2"></i>My Wallet
            </a>
            <a href="<?= url('creator') ?>" class="btn btn-outline-secondary fw-600 text-start">
              <i class="bi bi-megaphone me-2"></i>My Campaigns
            </a>
            <a href="<?= url('packages') ?>" class="btn btn-outline-secondary fw-600 text-start">
              <i class="bi bi-box-seam me-2"></i>Buy Views Package
            </a>
          </div>
        </div>
      </div>
      <!-- Referral -->
      <div class="card border-0 shadow-sm rounded-xl">
        <div class="card-body p-4">
          <h6 class="fw-bold mb-2">🎁 Referral Code</h6>
          <p class="text-muted small mb-3">Share your code and earn commission when friends join.</p>
          <div class="input-group">
            <input type="text" class="form-control font-monospace fw-600"
                   value="<?= e($user['referral_code'] ?? '') ?>" readonly id="refCode">
            <button class="btn btn-outline-secondary" onclick="copyRef()">
              <i class="bi bi-clipboard"></i>
            </button>
          </div>
          <div id="refCopied" class="text-success small mt-1 d-none">Copied!</div>
        </div>
      </div>
    </div>
  </div>

</div>
<script>
function copyRef() {
    const v = document.getElementById('refCode').value;
    navigator.clipboard.writeText('<?= url('register') ?>?ref=' + v).then(() => {
        document.getElementById('refCopied').classList.remove('d-none');
        setTimeout(() => document.getElementById('refCopied').classList.add('d-none'), 2000);
    });
}
</script>
<?php page_end(); ?>
