<?php
defined('VP_BOOT') || die('Direct access not permitted.');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/layout.php';
require_auth();
$user = auth();

// Handle URL submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_url') {
    verify_csrf();
    $tid   = (int)($_POST['task_id'] ?? 0);
    $url   = trim($_POST['campaign_url'] ?? '');
    $title = trim($_POST['campaign_title'] ?? '');
    $type  = in_array($_POST['campaign_type'] ?? '', ['video','website'], true) ? $_POST['campaign_type'] : 'video';

    if (!$tid || !$title || !$url) {
        flash('error', 'Title and URL are required.');
    } elseif (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $url)) {
        flash('error', 'Please enter a valid URL starting with https://');
    } else {
        $t = db()->prepare("SELECT id FROM tasks WHERE id=? AND user_id=? LIMIT 1");
        $t->execute([$tid, $user['id']]); $t = $t->fetch();
        if ($t) {
            db()->prepare(
                "UPDATE tasks SET title=?,type=?,url=?,status='pending',updated_at=? WHERE id=?"
            )->execute([$title, $type, $url, now_ts(), $tid]);
            flash('success', 'Campaign URL submitted! Admin will review and activate within 24 hours.');
        } else {
            flash('error', 'Campaign not found.');
        }
    }
    redirect('/creator');
}

// My campaigns
$campaigns = db()->prepare(
    "SELECT t.*, p.name as pkg_name FROM tasks t
     LEFT JOIN packages p ON t.package_id=p.id
     WHERE t.user_id=? ORDER BY t.created_at DESC"
);
$campaigns->execute([$user['id']]); $campaigns = $campaigns->fetchAll();

// Pending deposits
$pendDeposits = db()->prepare(
    "SELECT d.*, p.name as pkg_name FROM deposits d
     LEFT JOIN packages p ON d.package_id=p.id
     WHERE d.user_id=? AND d.status='pending'
     ORDER BY d.created_at DESC"
);
$pendDeposits->execute([$user['id']]); $pendDeposits = $pendDeposits->fetchAll();

// Campaigns needing a URL
$needUrl = array_filter($campaigns, fn($c) => empty($c['url']) || $c['url'] === '#');

page_start('My Campaigns');
?>
<div class="container py-4">

  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
      <h4 class="fw-bold mb-1">My Campaigns</h4>
      <p class="text-muted small mb-0">Manage your video and website promotions</p>
    </div>
    <a href="<?= url('packages') ?>" class="btn btn-primary fw-600">
      <i class="bi bi-plus-circle me-1"></i>Buy New Package
    </a>
  </div>

  <!-- URL submission prompts -->
  <?php foreach ($needUrl as $nt): ?>
  <div class="card border-0 shadow-sm rounded-xl mb-3" style="border-left:4px solid #10b981!important">
    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center py-3">
      <div>
        <span class="badge bg-success me-2">Payment Confirmed</span>
        <span class="fw-600 small">Submit your video/website URL to go live</span>
      </div>
      <i class="bi bi-rocket-takeoff-fill text-success"></i>
    </div>
    <div class="card-body pt-0">
      <form method="POST" class="row g-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action"   value="submit_url">
        <input type="hidden" name="task_id"  value="<?= (int)$nt['id'] ?>">
        <div class="col-md-4">
          <label class="form-label small fw-600">Campaign Title *</label>
          <input type="text" name="campaign_title" class="form-control"
                 placeholder="e.g. My New YouTube Video" required>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-600">Content Type</label>
          <select name="campaign_type" class="form-select">
            <option value="video">📹 Video</option>
            <option value="website">🌐 Website</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-600">URL *</label>
          <input type="url" name="campaign_url" class="form-control"
                 placeholder="https://youtube.com/watch?v=..." required>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button type="submit" class="btn btn-success w-100 fw-600">
            <i class="bi bi-send-fill me-1"></i>Submit
          </button>
        </div>
      </form>
    </div>
  </div>
  <?php endforeach; ?>

  <!-- Pending payments -->
  <?php if ($pendDeposits): ?>
  <div class="card border-0 shadow-sm rounded-xl mb-4">
    <div class="card-header bg-transparent border-0 fw-600 py-3">
      <i class="bi bi-hourglass-split text-warning me-2"></i>Awaiting Payment Confirmation
    </div>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0 small">
        <thead class="table-light">
          <tr><th class="ps-4">Package</th><th>Amount</th><th>Method</th><th>Submitted</th><th>Status</th></tr>
        </thead>
        <tbody>
        <?php foreach ($pendDeposits as $dep): ?>
        <tr>
          <td class="ps-4 fw-600"><?= e($dep['pkg_name'] ?? 'Package') ?></td>
          <td class="fw-700"><?= money((float)$dep['amount']) ?></td>
          <td><?= e(ucfirst(str_replace('_',' ',$dep['gateway']))) ?></td>
          <td class="text-muted"><?= date('M d, Y H:i', strtotime($dep['created_at'])) ?></td>
          <td><span class="badge bg-warning text-dark">Awaiting Confirmation</span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="card-footer bg-transparent border-0 small text-muted">
      <i class="bi bi-info-circle me-1"></i>Admin confirms payments within 24 hours. Contact support if delayed.
    </div>
  </div>
  <?php endif; ?>

  <!-- All campaigns -->
  <?php if ($campaigns): ?>
  <div class="card border-0 shadow-sm rounded-xl">
    <div class="card-header bg-transparent border-0 fw-600 py-3">
      All Campaigns (<?= count($campaigns) ?>)
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 small">
        <thead class="table-light">
          <tr><th class="ps-4">Campaign</th><th>Package</th><th style="min-width:120px">Progress</th><th>Status</th><th>Date</th></tr>
        </thead>
        <tbody>
        <?php foreach ($campaigns as $c): ?>
        <tr>
          <td class="ps-4">
            <div class="fw-600"><?= e(mb_substr($c['title'], 0, 42)) ?></div>
            <?php if ($c['url'] && $c['url'] !== '#'): ?>
            <a href="<?= e($c['url']) ?>" target="_blank" rel="noopener"
               class="text-muted text-decoration-none d-block text-truncate"
               style="font-size:.72rem;max-width:220px">
              <i class="bi bi-link-45deg"></i><?= e($c['url']) ?>
            </a>
            <?php else: ?>
            <span class="text-warning" style="font-size:.72rem">
              <i class="bi bi-exclamation-circle me-1"></i>URL not submitted
            </span>
            <?php endif; ?>
          </td>
          <td class="text-muted small"><?= e($c['pkg_name'] ?? '—') ?></td>
          <td>
            <?php $pct = $c['total_views_purchased'] > 0
              ? min(100, round($c['views_completed'] / $c['total_views_purchased'] * 100)) : 0; ?>
            <div class="d-flex justify-content-between mb-1 text-muted" style="font-size:.7rem">
              <span><?= number_format($c['views_completed']) ?>/<?= number_format($c['total_views_purchased']) ?></span>
              <span><?= $pct ?>%</span>
            </div>
            <div class="progress rounded-pill" style="height:5px">
              <div class="progress-bar bg-success rounded-pill" style="width:<?= $pct ?>%"></div>
            </div>
          </td>
          <td>
            <?php $cls = match($c['status']) {
              'active'    => 'bg-success',
              'pending'   => 'bg-warning text-dark',
              'completed' => 'bg-primary',
              'rejected'  => 'bg-danger',
              default     => 'bg-secondary',
            }; ?>
            <span class="badge <?= $cls ?>"><?= ucfirst($c['status']) ?></span>
            <?php if ($c['status']==='rejected' && $c['rejection_reason']): ?>
            <div class="text-danger mt-1" style="font-size:.68rem"><?= e(mb_substr($c['rejection_reason'],0,40)) ?></div>
            <?php endif; ?>
          </td>
          <td class="text-muted"><?= date('M d, Y', strtotime($c['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php else: ?>
  <div class="text-center py-5">
    <i class="bi bi-megaphone display-3 d-block mb-3 text-muted opacity-25"></i>
    <h5 class="fw-bold">No campaigns yet</h5>
    <p class="text-muted">Buy a views package to start promoting your content to real users.</p>
    <a href="<?= url('packages') ?>" class="btn btn-primary mt-2 fw-600">
      <i class="bi bi-box-seam me-2"></i>Browse Packages
    </a>
  </div>
  <?php endif; ?>

</div>
<?php page_end(); ?>
