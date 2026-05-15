<?php
defined('VP_BOOT') || die('Direct access not permitted.');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/layout.php';
require_auth();

$user   = auth();
$taskId = (int)($_GET['task_id'] ?? 0);

if (!$taskId) { redirect('/tasks'); }

// Load task
$t = db()->prepare(
    "SELECT t.*, u.name as creator FROM tasks t
     JOIN users u ON t.user_id=u.id
     WHERE t.id=? AND t.status='active' AND t.is_visible=1 AND t.views_remaining>0
     LIMIT 1"
);
$t->execute([$taskId]); $t = $t->fetch();

if (!$t) { flash('error', 'This task is no longer available.'); redirect('/tasks'); }
if ($t['user_id'] === $user['id']) { flash('error', 'You cannot complete your own campaign.'); redirect('/tasks'); }

// Already completed?
$done = db()->prepare("SELECT id FROM task_views WHERE task_id=? AND user_id=? AND status='valid' LIMIT 1");
$done->execute([$taskId, $user['id']]);
if ($done->fetch()) { flash('info', 'You have already completed this task.'); redirect('/tasks'); }

// Daily limit
$maxDaily = (int)setting('max_views_per_day', '50');
$todaySt  = db()->prepare("SELECT COUNT(*) FROM task_views WHERE user_id=? AND DATE(created_at)=CURDATE()");
$todaySt->execute([$user['id']]); $todayCount = (int)$todaySt->fetchColumn();
if ($todayCount >= $maxDaily) { flash('warning', 'Daily task limit reached. Come back tomorrow!'); redirect('/tasks'); }

// Handle claim
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if ($_POST['action'] === 'claim') {
        $watched = (int)($_POST['seconds_watched'] ?? 0);
        $minReq  = (int)$t['min_watch_seconds'];
        $fp      = trim(strip_tags($_POST['fp'] ?? ''));

        if ($watched < $minReq) {
            flash('error', 'You must watch for at least ' . $minReq . ' seconds to claim the reward.');
            redirect('/tasks/' . $taskId);
        }

        // Fraud: same IP + task_id in last 10 minutes
        $fraudCheck = db()->prepare(
            "SELECT COUNT(*) FROM task_views
             WHERE task_id=? AND ip_address=? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)"
        );
        $fraudCheck->execute([$taskId, vp_ip()]);
        $isLegit = ((int)$fraudCheck->fetchColumn()) === 0;
        $status  = $isLegit ? 'valid' : 'fraud';

        $reward = $isLegit ? (float)$t['reward_per_view'] : 0.0;

        // Create view record
        db()->prepare(
            "INSERT IGNORE INTO task_views
             (task_id,user_id,status,seconds_watched,reward_earned,ip_address,
              device_fingerprint,started_at,completed_at,created_at,updated_at)
             VALUES(?,?,?,?,?,?,?,?,?,?,?)"
        )->execute([
            $taskId, $user['id'], $status, $watched, $reward,
            vp_ip(), $fp ?: null, now_ts(), now_ts(), now_ts(), now_ts()
        ]);

        if ($isLegit) {
            // Credit wallet
            db()->prepare(
                "UPDATE users SET wallet_balance=wallet_balance+?,
                 total_earned=total_earned+?,total_tasks_completed=total_tasks_completed+1,
                 last_task_at=?,updated_at=? WHERE id=?"
            )->execute([$reward, $reward, now_ts(), now_ts(), $user['id']]);

            // Reduce remaining views on task
            db()->prepare(
                "UPDATE tasks SET views_completed=views_completed+1,
                 views_remaining=GREATEST(0,views_remaining-1),updated_at=?
                 WHERE id=?"
            )->execute([now_ts(), $taskId]);

            // Auto-complete task
            db()->prepare(
                "UPDATE tasks SET status='completed',updated_at=?
                 WHERE id=? AND views_remaining=0"
            )->execute([now_ts(), $taskId]);

            flash('success', 'Reward of ' . money($reward, 4) . ' credited to your wallet!');
        } else {
            flash('error', 'Suspicious activity detected. This view was not rewarded.');
        }
        redirect('/tasks');
    }
}

// Detect embed URL for YouTube
$embedUrl = $t['url'];
if ($t['type'] === 'video') {
    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\s]+)/', $t['url'], $m)) {
        $embedUrl = 'https://www.youtube.com/embed/' . $m[1] . '?autoplay=1&rel=0';
    }
}

$minSec = (int)$t['min_watch_seconds'];
page_start(e($t['title']));
?>
<div class="container py-4" style="max-width:920px">

  <!-- Back -->
  <a href="<?= url('tasks') ?>" class="btn btn-sm btn-outline-secondary mb-3">
    <i class="bi bi-arrow-left me-1"></i>Back to Tasks
  </a>

  <!-- Task header -->
  <div class="card border-0 shadow-sm rounded-xl overflow-hidden mb-4">
    <div class="p-4 pb-3" style="background:linear-gradient(135deg,var(--vp-primary)18,#8b5cf618)">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
          <span class="badge rounded-pill mb-2" style="background:var(--vp-primary);color:#fff">
            <?= ucfirst($t['type']) ?>
          </span>
          <h4 class="fw-bold mb-1"><?= e($t['title']) ?></h4>
          <p class="text-muted small mb-0">by <?= e($t['creator']) ?></p>
        </div>
        <div class="text-end">
          <div class="fw-bold fs-4 text-success"><?= money((float)$t['reward_per_view'], 4) ?></div>
          <div class="text-muted small">Reward</div>
        </div>
      </div>
    </div>

    <!-- Timer + progress -->
    <div class="px-4 py-3 bg-white border-top">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="fw-600 small">Watch Progress</span>
        <span class="fw-bold small" id="timerDisplay">0 / <?= $minSec ?>s</span>
      </div>
      <div class="progress rounded-pill" style="height:10px">
        <div id="progressBar" class="progress-bar bg-success rounded-pill"
             style="width:0%;transition:width 1s linear"></div>
      </div>
      <div class="mt-2 small text-muted" id="timerMsg">
        <i class="bi bi-info-circle me-1"></i>
        Watch for at least <?= $minSec ?> seconds to unlock the Claim button.
      </div>
    </div>
  </div>

  <!-- Content -->
  <div class="row g-4">
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm rounded-xl overflow-hidden">
        <?php if ($t['type'] === 'video'): ?>
        <div class="ratio ratio-16x9">
          <iframe src="<?= e($embedUrl) ?>" allowfullscreen
                  allow="accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope"
                  style="border:0" id="contentFrame" onload="startTimer()"></iframe>
        </div>
        <?php else: ?>
        <div class="p-3 text-center bg-body-secondary border-bottom">
          <p class="small text-muted mb-2">Visit this website and stay for <?= $minSec ?> seconds:</p>
          <a href="<?= e($t['url']) ?>" target="_blank" class="btn btn-primary fw-600"
             onclick="startTimer()">
            <i class="bi bi-box-arrow-up-right me-2"></i>Open Website
          </a>
        </div>
        <div class="ratio ratio-4x3">
          <iframe src="<?= e($t['url']) ?>" style="border:0" id="contentFrame" onload="startTimer()"
                  sandbox="allow-scripts allow-same-origin allow-forms"></iframe>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Claim panel -->
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm rounded-xl">
        <div class="card-body p-4">
          <h6 class="fw-bold mb-3">Claim Your Reward</h6>
          <div class="mb-3 p-3 rounded-3 text-center" style="background:var(--vp-primary)10">
            <div class="text-muted small mb-1">You'll earn</div>
            <div class="fw-bold fs-3 text-success"><?= money((float)$t['reward_per_view'], 4) ?></div>
          </div>

          <form method="POST" id="claimForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action"          value="claim">
            <input type="hidden" name="seconds_watched" id="secWatched" value="0">
            <input type="hidden" name="fp"              id="fpField"    value="">

            <button type="submit" id="claimBtn"
                    class="btn btn-success w-100 fw-700 py-2 mb-3" disabled>
              <i class="bi bi-lock-fill me-2" id="claimIcon"></i>
              <span id="claimText">Watch <?= $minSec ?>s to unlock</span>
            </button>
          </form>

          <ul class="list-unstyled small text-muted">
            <li class="mb-2"><i class="bi bi-clock me-2 text-primary"></i>Min watch: <?= $minSec ?> seconds</li>
            <li class="mb-2"><i class="bi bi-eye me-2 text-primary"></i><?= number_format((int)$t['views_remaining']) ?> views remaining</li>
            <li class="mb-2"><i class="bi bi-shield-check me-2 text-success"></i>Fraud detection active</li>
          </ul>
        </div>
      </div>
    </div>
  </div>

</div>

<script>
let elapsed  = 0;
let timerId  = null;
const minSec = <?= (int)$t['min_watch_seconds'] ?>;

// Generate simple fingerprint
document.getElementById('fpField').value = btoa(
    navigator.userAgent + screen.width + 'x' + screen.height + window.location.href
).substring(0, 64);

function startTimer() {
    if (timerId) return;
    timerId = setInterval(() => {
        elapsed++;
        document.getElementById('secWatched').value = elapsed;

        const pct  = Math.min(100, Math.round(elapsed / minSec * 100));
        const disp = elapsed + ' / ' + minSec + 's';
        document.getElementById('progressBar').style.width   = pct + '%';
        document.getElementById('timerDisplay').textContent  = disp;

        if (elapsed >= minSec) {
            clearInterval(timerId);
            document.getElementById('claimBtn').disabled = false;
            document.getElementById('claimBtn').classList.remove('btn-success');
            document.getElementById('claimBtn').classList.add('btn-success', 'shadow');
            document.getElementById('claimIcon').className = 'bi bi-check-circle-fill me-2';
            document.getElementById('claimText').textContent = 'Claim Reward Now!';
            document.getElementById('timerMsg').innerHTML =
                '<i class="bi bi-check-circle-fill text-success me-1"></i>' +
                '<strong class="text-success">Minimum time reached! Click Claim Reward.</strong>';
        }
    }, 1000);
}

// Auto-start timer for video (iframe already loaded)
window.addEventListener('load', () => {
    <?php if ($t['type'] === 'video'): ?>
    setTimeout(startTimer, 1500);
    <?php endif; ?>
});

// Prevent form double-submit
document.getElementById('claimForm').addEventListener('submit', function() {
    document.getElementById('claimBtn').disabled = true;
    document.getElementById('claimText').textContent = 'Claiming...';
});
</script>
<?php page_end(); ?>
