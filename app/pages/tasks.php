<?php
defined('VP_BOOT') || die('Direct access not permitted.');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/layout.php';
require_auth();

$user    = auth();
$type    = in_array($_GET['type'] ?? '', ['video','website'], true) ? $_GET['type'] : '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset  = ($page - 1) * $perPage;

// Daily limit check
$maxDaily = (int)setting('max_views_per_day', '50');
$st = db()->prepare("SELECT COUNT(*) FROM task_views WHERE user_id=? AND DATE(created_at)=CURDATE()");
$st->execute([$user['id']]); $todayCount = (int)$st->fetchColumn();
$canEarn = $todayCount < $maxDaily;

// Build query
$where  = "WHERE t.status='active' AND t.is_visible=1 AND t.views_remaining>0
           AND t.user_id != ?
           AND t.id NOT IN (SELECT task_id FROM task_views WHERE user_id=? AND status='valid')";
$params = [$user['id'], $user['id']];
if ($type) { $where .= " AND t.type=?"; $params[] = $type; }

$totalSt = db()->prepare("SELECT COUNT(*) FROM tasks t $where");
$totalSt->execute($params); $total = (int)$totalSt->fetchColumn();

$params[] = $perPage; $params[] = $offset;
$tasks = db()->prepare(
    "SELECT t.*, u.name as creator_name FROM tasks t
     JOIN users u ON t.user_id=u.id
     $where ORDER BY t.created_at DESC LIMIT ? OFFSET ?"
);
$tasks->execute($params); $tasks = $tasks->fetchAll();

page_start('Browse Tasks — Earn Money');
?>
<div class="container py-4">

  <!-- Header -->
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
      <h4 class="fw-bold mb-1">Available Tasks</h4>
      <p class="text-muted small mb-0">
        <?= $total ?> task<?= $total != 1 ? 's' : '' ?> available
        &nbsp;·&nbsp; Today: <strong><?= $todayCount ?>/<?= $maxDaily ?></strong>
      </p>
    </div>
    <!-- Filter -->
    <div class="btn-group btn-group-sm">
      <a href="<?= url('tasks') ?>"         class="btn <?= $type===''       ? 'btn-primary' : 'btn-outline-secondary' ?>">All</a>
      <a href="<?= url('tasks?type=video') ?>" class="btn <?= $type==='video'   ? 'btn-primary' : 'btn-outline-secondary' ?>"><i class="bi bi-play-circle me-1"></i>Video</a>
      <a href="<?= url('tasks?type=website') ?>" class="btn <?= $type==='website' ? 'btn-primary' : 'btn-outline-secondary' ?>"><i class="bi bi-globe me-1"></i>Website</a>
    </div>
  </div>

  <!-- Daily limit warning -->
  <?php if (!$canEarn): ?>
  <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center gap-2 mb-4">
    <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
    <div>You've reached the daily limit of <strong><?= $maxDaily ?> tasks</strong>. Come back tomorrow to keep earning!</div>
  </div>
  <?php endif; ?>

  <?php if ($tasks): ?>
  <div class="row g-3">
    <?php foreach ($tasks as $t):
      $typeColor = $t['type'] === 'video' ? '#6366f1' : '#10b981';
      $typeIcon  = $t['type'] === 'video' ? 'play-circle-fill' : 'globe2';
    ?>
    <div class="col-sm-6 col-lg-4">
      <div class="card border-0 shadow-sm task-card h-100 rounded-xl">
        <div class="card-body p-4">
          <!-- Type badge -->
          <div class="d-flex justify-content-between align-items-start mb-3">
            <span class="badge rounded-pill fw-600" style="background:<?= $typeColor ?>18;color:<?= $typeColor ?>">
              <i class="bi bi-<?= $typeIcon ?> me-1"></i><?= ucfirst($t['type']) ?>
            </span>
            <span class="badge bg-success-subtle text-success fw-600">
              +<?= money((float)$t['reward_per_view'], 4) ?>
            </span>
          </div>
          <!-- Title -->
          <h6 class="fw-bold mb-2 lh-sm"><?= e(mb_substr($t['title'], 0, 60)) ?></h6>
          <!-- URL preview -->
          <div class="text-muted small mb-3 text-truncate">
            <i class="bi bi-link-45deg me-1"></i>
            <?= e(parse_url($t['url'], PHP_URL_HOST) ?: $t['url']) ?>
          </div>
          <!-- Meta -->
          <div class="d-flex gap-3 text-muted mb-4" style="font-size:.75rem">
            <span><i class="bi bi-clock me-1"></i><?= $t['min_watch_seconds'] ?>s min</span>
            <span><i class="bi bi-eye me-1"></i><?= number_format($t['views_remaining']) ?> left</span>
          </div>
          <!-- CTA -->
          <?php if ($canEarn): ?>
          <a href="<?= url('tasks/' . $t['id']) ?>"
             class="btn btn-primary w-100 fw-600 stretched-link"
             onclick="window.open('<?= e(addslashes($t['url'])) ?>', '_blank');"
             style="background:<?= $typeColor ?>;border-color:<?= $typeColor ?>">
            <i class="bi bi-<?= $typeIcon ?> me-1"></i>Start Task
          </a>
          <?php else: ?>
          <button class="btn btn-secondary w-100 fw-600" disabled>
            Daily Limit Reached
          </button>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Pagination -->
  <?php if ($total > $perPage): ?>
  <div class="mt-4">
    <?= paginate($total, $perPage, $page, url('tasks' . ($type ? '?type=' . $type : ''))) ?>
  </div>
  <?php endif; ?>

  <?php else: ?>
  <div class="text-center py-5">
    <i class="bi bi-collection-play display-3 d-block mb-3 text-muted opacity-25"></i>
    <h5 class="fw-bold">No tasks available right now</h5>
    <p class="text-muted">You've completed all available tasks, or there are none yet. Check back soon!</p>
    <a href="<?= url('packages') ?>" class="btn btn-primary mt-2 fw-600">
      <i class="bi bi-megaphone me-2"></i>Create a Campaign Instead
    </a>
  </div>
  <?php endif; ?>

</div>
<?php page_end(); ?>
