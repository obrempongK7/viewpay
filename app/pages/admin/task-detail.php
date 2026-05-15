<?php
defined('VP_BOOT') || die('Direct access not permitted.');
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/partials/admin-layout.php';
require_admin();

$tid = (int)($_GET['task_id'] ?? 0);
$t   = db()->prepare(
    "SELECT t.*, u.name uname, u.email uemail, p.name pname
     FROM tasks t JOIN users u ON t.user_id=u.id
     LEFT JOIN packages p ON t.package_id=p.id
     WHERE t.id=? LIMIT 1"
);
$t->execute([$tid]); $t = $t->fetch();
if (!$t) { flash('error', 'Campaign not found.'); redirect('/admin/tasks'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'approve') {
        db()->prepare("UPDATE tasks SET status='active',updated_at=? WHERE id=?")->execute([now_ts(),$tid]);
        flash('success','Campaign approved.');
    } elseif ($action === 'reject') {
        $reason = strip_tags(trim($_POST['reason'] ?? 'Does not meet guidelines.'));
        db()->prepare("UPDATE tasks SET status='rejected',rejection_reason=?,updated_at=? WHERE id=?")->execute([$reason,now_ts(),$tid]);
        flash('warning','Campaign rejected.');
    } elseif ($action === 'pause') {
        db()->prepare("UPDATE tasks SET status='paused',updated_at=? WHERE id=?")->execute([now_ts(),$tid]);
        flash('info','Campaign paused.');
    } elseif ($action === 'resume') {
        db()->prepare("UPDATE tasks SET status='active',updated_at=? WHERE id=?")->execute([now_ts(),$tid]);
        flash('success','Campaign resumed.');
    }
    redirect('/admin/tasks/' . $tid);
}

// Recent views
$views = db()->prepare(
    "SELECT tv.*, u.name uname FROM task_views tv
     JOIN users u ON tv.user_id=u.id
     WHERE tv.task_id=? ORDER BY tv.created_at DESC LIMIT 20"
);
$views->execute([$tid]); $views = $views->fetchAll();

$pct = $t['total_views_purchased'] > 0
     ? min(100, round($t['views_completed'] / $t['total_views_purchased'] * 100))
     : 0;

$statCls = match($t['status']) {
    'active'    => 'bg-success',
    'pending'   => 'bg-warning text-dark',
    'paused'    => 'bg-info',
    'completed' => 'bg-primary',
    'rejected'  => 'bg-danger',
    default     => 'bg-secondary',
};

admin_page_start('Campaign Detail');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <a href="<?= url('admin/tasks') ?>" class="btn btn-sm btn-outline-secondary mb-2">
      <i class="bi bi-arrow-left me-1"></i>All Campaigns
    </a>
    <h4 class="fw-bold mb-0"><?= e(mb_substr($t['title'], 0, 60)) ?></h4>
  </div>
  <span class="badge <?= $statCls ?> px-3 py-2 fs-6"><?= ucfirst($t['status']) ?></span>
</div>

<div class="row g-4">
  <!-- Info card -->
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm rounded-xl p-4 mb-3">
      <h6 class="fw-bold mb-3">Campaign Info</h6>
      <?php foreach ([
        ['Creator',   $t['uname'] . ' (' . $t['uemail'] . ')'],
        ['Package',   $t['pname'] ?? '—'],
        ['Type',      ucfirst($t['type'])],
        ['Amount Paid', money((float)$t['amount_paid'])],
        ['Reward/View', money((float)$t['reward_per_view'], 4)],
        ['Min Watch',  $t['min_watch_seconds'] . 's'],
        ['Created',   date('M d, Y', strtotime($t['created_at']))],
      ] as [$l, $v]): ?>
      <div class="d-flex justify-content-between py-2 border-bottom small">
        <span class="text-muted"><?= $l ?></span>
        <span class="fw-600 text-end"><?= e($v) ?></span>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- URL preview -->
    <?php if ($t['url'] && $t['url'] !== '#'): ?>
    <div class="card border-0 shadow-sm rounded-xl p-3 mb-3">
      <h6 class="fw-bold mb-2">Content URL</h6>
      <a href="<?= e($t['url']) ?>" target="_blank" rel="noopener"
         class="small text-break text-decoration-none text-primary">
        <i class="bi bi-link-45deg me-1"></i><?= e($t['url']) ?>
      </a>
    </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="card border-0 shadow-sm rounded-xl p-3">
      <h6 class="fw-bold mb-3">Actions</h6>
      <div class="d-grid gap-2">
        <?php if ($t['status'] === 'pending'): ?>
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="approve">
          <button class="btn btn-success w-100 fw-600"><i class="bi bi-check-circle me-2"></i>Approve Campaign</button>
        </form>
        <button class="btn btn-outline-danger fw-600" data-bs-toggle="collapse" data-bs-target="#rejectForm">
          <i class="bi bi-x-circle me-2"></i>Reject Campaign
        </button>
        <div class="collapse" id="rejectForm">
          <form method="POST" class="mt-2">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reject">
            <textarea name="reason" class="form-control form-control-sm mb-2" rows="2"
                      placeholder="Reason…" required></textarea>
            <button class="btn btn-danger btn-sm w-100 fw-600">Confirm Reject</button>
          </form>
        </div>
        <?php elseif ($t['status'] === 'active'): ?>
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="pause">
          <button class="btn btn-warning w-100 fw-600 text-dark"><i class="bi bi-pause-circle me-2"></i>Pause Campaign</button>
        </form>
        <?php elseif ($t['status'] === 'paused'): ?>
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="resume">
          <button class="btn btn-success w-100 fw-600"><i class="bi bi-play-circle me-2"></i>Resume Campaign</button>
        </form>
        <?php endif; ?>
        <a href="<?= url('admin/users/' . $t['user_id']) ?>" class="btn btn-outline-secondary fw-600">
          <i class="bi bi-person me-2"></i>View Creator
        </a>
      </div>
    </div>
  </div>

  <!-- Progress + views -->
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm rounded-xl p-4 mb-4">
      <h6 class="fw-bold mb-3">Progress</h6>
      <div class="d-flex justify-content-between mb-2 small">
        <span><?= number_format($t['views_completed']) ?> views delivered</span>
        <span class="text-muted"><?= number_format($t['views_remaining']) ?> remaining of <?= number_format($t['total_views_purchased']) ?></span>
      </div>
      <div class="progress rounded-pill mb-2" style="height:12px">
        <div class="progress-bar bg-success rounded-pill" style="width:<?= $pct ?>%"></div>
      </div>
      <div class="text-muted small"><?= $pct ?>% complete</div>
    </div>

    <!-- Recent view activity -->
    <div class="card border-0 shadow-sm rounded-xl">
      <div class="card-header bg-transparent border-0 fw-600 py-3 px-4">
        Recent Activity (<?= count($views) ?> of <?= number_format($t['views_completed']) ?>)
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
          <thead class="table-light">
            <tr>
              <th class="ps-4">Earner</th>
              <th>Watched</th>
              <th>Reward</th>
              <th>IP</th>
              <th>Status</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($views as $v): ?>
          <tr>
            <td class="ps-4 fw-600 small"><?= e($v['uname']) ?></td>
            <td><?= $v['seconds_watched'] ?>s</td>
            <td class="text-success fw-600"><?= money((float)$v['reward_earned'], 4) ?></td>
            <td class="text-muted font-monospace" style="font-size:.7rem"><?= e($v['ip_address']) ?></td>
            <td>
              <span class="badge <?= $v['status']==='valid'?'bg-success':($v['status']==='fraud'?'bg-danger':'bg-secondary') ?>">
                <?= ucfirst($v['status']) ?>
              </span>
            </td>
            <td class="text-muted"><?= date('M d H:i', strtotime($v['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$views): ?>
          <tr><td colspan="6" class="text-center py-4 text-muted">No views yet.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php admin_page_end(); ?>
