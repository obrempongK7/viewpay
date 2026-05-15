<?php
/**
 * ViewPay Admin — Tasks / Campaigns
 * Developed by Jcode | Author: Obrempong
 */
defined('VP_BOOT') || die('Direct access not permitted.');
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/partials/admin-layout.php';
require_admin();

// ── POST handlers ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $tid    = (int)($_POST['task_id'] ?? 0);

    if ($tid > 0) {
        if ($action === 'approve') {
            db()->prepare(
                "UPDATE tasks SET status='active', updated_at=? WHERE id=?"
            )->execute([now_ts(), $tid]);
            flash('success', 'Campaign approved and activated.');

        } elseif ($action === 'reject') {
            $reason = strip_tags(trim($_POST['reason'] ?? 'Does not meet guidelines.'));
            db()->prepare(
                "UPDATE tasks SET status='rejected', rejection_reason=?, updated_at=? WHERE id=?"
            )->execute([$reason, now_ts(), $tid]);
            flash('warning', 'Campaign rejected.');

        } elseif ($action === 'pause') {
            db()->prepare(
                "UPDATE tasks SET status='paused', updated_at=? WHERE id=?"
            )->execute([now_ts(), $tid]);
            flash('info', 'Campaign paused.');

        } elseif ($action === 'resume') {
            db()->prepare(
                "UPDATE tasks SET status='active', updated_at=? WHERE id=?"
            )->execute([now_ts(), $tid]);
            flash('success', 'Campaign resumed.');

        } elseif ($action === 'delete') {
            db()->prepare("DELETE FROM task_views WHERE task_id=?")->execute([$tid]);
            db()->prepare("DELETE FROM tasks WHERE id=?")->execute([$tid]);
            flash('success', 'Campaign deleted.');
        }
    }
    redirect('/admin/tasks');
}

// ── Filters ────────────────────────────────────────────────
$status  = $_GET['status'] ?? '';
$search  = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$per     = 18; $off = ($page - 1) * $per;

$where  = ''; $params = [];
$conds  = [];
if ($status) { $conds[] = 't.status=?';  $params[] = $status; }
if ($search) { $conds[] = '(t.title LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
               $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($conds)  { $where = 'WHERE ' . implode(' AND ', $conds); }

$totSt = db()->prepare(
    "SELECT COUNT(*) FROM tasks t JOIN users u ON t.user_id=u.id $where"
);
$totSt->execute($params); $total = (int)$totSt->fetchColumn();

$params[] = $per; $params[] = $off;
$tasks = db()->prepare(
    "SELECT t.*, u.name AS uname, u.email AS uemail, p.name AS pname
     FROM tasks t
     JOIN users u ON t.user_id=u.id
     LEFT JOIN packages p ON t.package_id=p.id
     $where
     ORDER BY FIELD(t.status,'pending','active','paused','completed','rejected'), t.created_at DESC
     LIMIT ? OFFSET ?"
);
$tasks->execute($params); $tasks = $tasks->fetchAll();

// Status counts
$counts = [];
foreach (['pending','active','paused','completed','rejected'] as $s) {
    $st = db()->prepare("SELECT COUNT(*) FROM tasks WHERE status=?");
    $st->execute([$s]); $counts[$s] = (int)$st->fetchColumn();
}

admin_page_start('Tasks / Campaigns');
?>

<div class="d-flex justify-content-between align-items-start mb-4">
  <div>
    <h4 class="fw-bold mb-1">Campaigns</h4>
    <p class="text-muted small mb-0">All creator campaigns — approve, pause, or reject</p>
  </div>
</div>

<!-- Status filter tabs -->
<div class="d-flex flex-wrap gap-1 mb-3">
  <?php
  $statuses = [''         => ['All',       'bg-secondary'],
               'pending'  => ['Pending',   'bg-warning text-dark'],
               'active'   => ['Active',    'bg-success'],
               'paused'   => ['Paused',    'bg-info'],
               'completed'=> ['Completed', 'bg-primary'],
               'rejected' => ['Rejected',  'bg-danger']];
  foreach ($statuses as $sv => [$sl, $sc]): ?>
  <a href="<?= url('admin/tasks?status=' . $sv . ($search ? '&q=' . urlencode($search) : '')) ?>"
     class="btn btn-sm <?= $status === $sv ? $sc . ' text-white' : 'btn-outline-secondary' ?> fw-600">
    <?= $sl ?>
    <?php if ($sv && isset($counts[$sv]) && $counts[$sv] > 0): ?>
    <span class="badge bg-white text-dark ms-1" style="font-size:.6rem"><?= $counts[$sv] ?></span>
    <?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- Search -->
<div class="card border-0 shadow-sm rounded-xl p-3 mb-4">
  <form method="GET" class="d-flex gap-2">
    <input type="hidden" name="status" value="<?= e($status) ?>">
    <input type="text" name="q" class="form-control form-control-sm" placeholder="Search title, user…" value="<?= e($search) ?>">
    <button class="btn btn-sm btn-primary fw-600">Search</button>
    <?php if ($search): ?>
    <a href="<?= url('admin/tasks?status=' . urlencode($status)) ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
    <?php endif; ?>
  </form>
</div>

<!-- Campaign cards -->
<?php if (empty($tasks)): ?>
<div class="text-center py-5 text-muted">
  <i class="bi bi-collection-play display-3 d-block mb-3 opacity-25"></i>
  <h5>No campaigns found</h5>
</div>
<?php else: ?>
<div class="row g-3 mb-4">
<?php foreach ($tasks as $t):
  $pct     = $t['total_views_purchased'] > 0
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
  $typeColor = $t['type'] === 'video' ? '#6366f1' : '#10b981';
?>
<div class="col-xl-4 col-md-6">
<div class="card border-0 shadow-sm rounded-xl h-100">
  <div class="card-body p-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-start mb-2 gap-2">
      <div class="flex-grow-1">
        <div class="fw-bold small mb-1 lh-sm"><?= e(mb_substr($t['title'], 0, 55)) ?></div>
        <div class="text-muted" style="font-size:.72rem">
          <i class="bi bi-person me-1"></i><?= e($t['uname']) ?>
          &nbsp;·&nbsp;<?= ucfirst($t['type']) ?>
        </div>
      </div>
      <span class="badge <?= $statCls ?> flex-shrink-0" style="font-size:.68rem"><?= ucfirst($t['status']) ?></span>
    </div>

    <!-- URL -->
    <?php if ($t['url'] && $t['url'] !== '#'): ?>
    <a href="<?= e($t['url']) ?>" target="_blank" rel="noopener"
       class="text-muted text-decoration-none d-block text-truncate mb-3 small">
      <i class="bi bi-link-45deg me-1"></i><?= e($t['url']) ?>
    </a>
    <?php else: ?>
    <div class="text-warning small mb-3"><i class="bi bi-exclamation-circle me-1"></i>URL not submitted yet</div>
    <?php endif; ?>

    <!-- Progress -->
    <div class="mb-3">
      <div class="d-flex justify-content-between text-muted mb-1" style="font-size:.71rem">
        <span>Views: <?= number_format($t['views_completed']) ?>/<?= number_format($t['total_views_purchased']) ?></span>
        <span><?= $pct ?>%</span>
      </div>
      <div class="progress rounded-pill" style="height:6px">
        <div class="progress-bar rounded-pill"
             style="width:<?= $pct ?>%;background:<?= $typeColor ?>"></div>
      </div>
    </div>

    <!-- Package + reward -->
    <div class="d-flex gap-2 mb-3 flex-wrap">
      <?php if ($t['pname']): ?>
      <span class="badge bg-secondary-subtle text-secondary" style="font-size:.68rem">
        <?= e($t['pname']) ?>
      </span>
      <?php endif; ?>
      <span class="badge bg-success-subtle text-success" style="font-size:.68rem">
        <?= money((float)$t['reward_per_view'], 4) ?>/view
      </span>
      <span class="badge bg-primary-subtle text-primary" style="font-size:.68rem">
        <?= date('M d', strtotime($t['created_at'])) ?>
      </span>
    </div>

    <!-- Rejection reason -->
    <?php if ($t['status'] === 'rejected' && $t['rejection_reason']): ?>
    <div class="alert alert-danger py-1 px-2 small mb-3">
      <i class="bi bi-x-circle me-1"></i><?= e(mb_substr($t['rejection_reason'], 0, 80)) ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Actions -->
  <div class="card-footer bg-transparent border-0 px-4 pb-3 d-flex gap-1 flex-wrap">
    <a href="<?= url('admin/tasks/' . $t['id']) ?>" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-eye"></i>
    </a>

    <?php if ($t['status'] === 'pending'): ?>
    <form method="POST" class="d-inline">
      <?= csrf_field() ?>
      <input type="hidden" name="action"  value="approve">
      <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
      <button class="btn btn-sm btn-success fw-600">
        <i class="bi bi-check-circle me-1"></i>Approve
      </button>
    </form>
    <button class="btn btn-sm btn-outline-danger fw-600"
            data-bs-toggle="modal" data-bs-target="#rejectModal"
            data-tid="<?= $t['id'] ?>" data-title="<?= e($t['title']) ?>">
      <i class="bi bi-x-circle me-1"></i>Reject
    </button>

    <?php elseif ($t['status'] === 'active'): ?>
    <form method="POST" class="d-inline">
      <?= csrf_field() ?>
      <input type="hidden" name="action"  value="pause">
      <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
      <button class="btn btn-sm btn-warning fw-600 text-dark">
        <i class="bi bi-pause-circle me-1"></i>Pause
      </button>
    </form>

    <?php elseif ($t['status'] === 'paused'): ?>
    <form method="POST" class="d-inline">
      <?= csrf_field() ?>
      <input type="hidden" name="action"  value="resume">
      <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
      <button class="btn btn-sm btn-success fw-600">
        <i class="bi bi-play-circle me-1"></i>Resume
      </button>
    </form>
    <?php endif; ?>

    <form method="POST" class="d-inline ms-auto"
          onsubmit="return confirm('Delete this campaign? All view records will be lost.')">
      <?= csrf_field() ?>
      <input type="hidden" name="action"  value="delete">
      <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
      <button class="btn btn-sm btn-outline-danger">
        <i class="bi bi-trash"></i>
      </button>
    </form>
  </div>
</div>
</div>
<?php endforeach; ?>
</div>

<?php if ($total > $per): ?>
<?= paginate($total, $per, $page, url('admin/tasks?' . http_build_query(['status'=>$status,'q'=>$search]))) ?>
<?php endif; ?>
<?php endif; ?>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
<div class="modal-dialog modal-dialog-centered modal-sm">
<div class="modal-content border-0 shadow rounded-xl">
  <div class="modal-header border-0">
    <h6 class="fw-bold">Reject Campaign</h6>
    <button class="btn-close" data-bs-dismiss="modal"></button>
  </div>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action"  value="reject">
    <input type="hidden" name="task_id" id="rTid">
    <div class="modal-body">
      <p class="small text-muted mb-2" id="rTitle"></p>
      <label class="form-label small fw-600">Rejection Reason</label>
      <textarea name="reason" class="form-control" rows="3"
                placeholder="e.g. URL is invalid, inappropriate content…" required></textarea>
      <div class="form-text">The creator will see this reason.</div>
    </div>
    <div class="modal-footer border-0 pt-0">
      <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
      <button type="submit" class="btn btn-sm btn-danger fw-600">Reject</button>
    </div>
  </form>
</div>
</div>
</div>
<script>
document.getElementById('rejectModal').addEventListener('show.bs.modal', e => {
    const b = e.relatedTarget;
    document.getElementById('rTid').value      = b.dataset.tid;
    document.getElementById('rTitle').textContent = b.dataset.title;
});
</script>
<?php admin_page_end(); ?>
