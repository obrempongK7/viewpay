<?php
defined('VP_BOOT') || die('Direct access not permitted.');
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/partials/admin-layout.php';
require_admin();

$search = trim($_GET['q']    ?? '');
$action = trim($_GET['action'] ?? '');
$uid    = (int)($_GET['user_id'] ?? 0);
$from   = trim($_GET['from']  ?? date('Y-m-d', strtotime('-7 days')));
$to     = trim($_GET['to']    ?? date('Y-m-d'));
$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 30; $off = ($page-1)*$per;

$where = 'WHERE 1=1'; $params = [];
if ($search) { $where .= ' AND (al.description LIKE ? OR al.action LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($action) { $where .= ' AND al.action=?';       $params[] = $action; }
if ($uid)    { $where .= ' AND al.user_id=?';       $params[] = $uid; }
if ($from)   { $where .= ' AND DATE(al.created_at)>=?'; $params[] = $from; }
if ($to)     { $where .= ' AND DATE(al.created_at)<=?'; $params[] = $to; }

$total = db()->prepare("SELECT COUNT(*) FROM audit_logs al $where");
$total->execute($params); $total = (int)$total->fetchColumn();
$params[] = $per; $params[] = $off;
$logs = db()->prepare(
    "SELECT al.*, u.name uname, u.email uemail, u.role urole, u.is_admin is_admin_u
     FROM audit_logs al
     LEFT JOIN users u ON al.user_id=u.id
     $where ORDER BY al.created_at DESC LIMIT ? OFFSET ?"
);
$logs->execute($params); $logs = $logs->fetchAll();

// Distinct actions for filter
$actions = db()->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

// Action colour map
$actionColors = [
    'register'          => 'bg-success',
    'login'             => 'bg-primary',
    'logout'            => 'bg-secondary',
    'task_complete'     => 'bg-info',
    'ad_watch'          => 'bg-purple',
    'withdrawal_request'=> 'bg-warning text-dark',
    'deposit_confirmed' => 'bg-success',
    'deposit_rejected'  => 'bg-danger',
    'campaign_approved' => 'bg-success',
    'campaign_rejected' => 'bg-danger',
    'user_blocked'      => 'bg-danger',
    'user_unblocked'    => 'bg-success',
    'user_role_changed' => 'bg-warning text-dark',
    'balance_adjusted'  => 'bg-primary',
    'settings_updated'  => 'bg-secondary',
    'email_verified'    => 'bg-success',
];

admin_page_start('Audit Logs');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-1">Audit Logs</h4>
    <p class="text-muted small mb-0"><?= number_format($total) ?> events · All system actions tracked</p>
  </div>
  <a href="<?= url('admin/audit-logs') ?>" class="btn btn-sm btn-outline-secondary">Reset Filters</a>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm rounded-xl p-3 mb-4">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label small fw-600 mb-1">Search</label><input type="text" name="q" class="form-control form-control-sm" placeholder="Action or description…" value="<?= e($search) ?>"></div>
    <div class="col-md-2"><label class="form-label small fw-600 mb-1">Action</label>
      <select name="action" class="form-select form-select-sm">
        <option value="">All Actions</option>
        <?php foreach($actions as $a): ?>
        <option value="<?= e($a) ?>" <?= $action===$a?'selected':'' ?>><?= e(ucfirst(str_replace('_',' ',$a))) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2"><label class="form-label small fw-600 mb-1">From</label><input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>"></div>
    <div class="col-md-2"><label class="form-label small fw-600 mb-1">To</label><input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>"></div>
    <div class="col-auto"><button class="btn btn-primary btn-sm fw-600">Filter</button></div>
  </form>
</div>

<div class="card border-0 shadow-sm rounded-xl">
<div class="table-responsive">
<table class="table table-hover align-middle mb-0 small">
<thead class="table-light">
  <tr>
    <th class="ps-4">Time</th>
    <th>User</th>
    <th>Action</th>
    <th>Description</th>
    <th>Entity</th>
    <th>IP Address</th>
  </tr>
</thead>
<tbody>
<?php foreach($logs as $log): ?>
<tr>
  <td class="ps-4 text-muted text-nowrap"><?= date('M d H:i:s',strtotime($log['created_at'])) ?></td>
  <td>
    <?php if ($log['uname']): ?>
    <a href="<?= url('admin/users/'.$log['user_id']) ?>" class="text-decoration-none fw-600 small">
      <?= e($log['uname']) ?>
    </a>
    <div class="text-muted" style="font-size:.68rem">
      <?= $log['is_admin_u']?'<span class="badge bg-danger" style="font-size:.55rem">Admin</span>':($log['urole']==='moderator'?'<span class="badge bg-warning text-dark" style="font-size:.55rem">Mod</span>':'') ?>
    </div>
    <?php else: ?>
    <span class="text-muted">System</span>
    <?php endif; ?>
  </td>
  <td>
    <?php $cls = $actionColors[$log['action']] ?? 'bg-secondary'; ?>
    <span class="badge <?= $cls ?>" style="font-size:.68rem">
      <?= e(ucfirst(str_replace('_',' ',$log['action']))) ?>
    </span>
  </td>
  <td class="text-muted" style="max-width:240px">
    <span class="text-truncate d-block" title="<?= e($log['description']) ?>">
      <?= e(mb_substr($log['description'],0,60)) ?>
    </span>
  </td>
  <td class="text-muted" style="font-size:.72rem">
    <?php if ($log['entity_type']): ?>
    <?= e(ucfirst($log['entity_type'])) ?> #<?= (int)$log['entity_id'] ?>
    <?php endif; ?>
  </td>
  <td class="font-monospace text-muted" style="font-size:.7rem"><?= e($log['ip_address']) ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$logs): ?>
<tr><td colspan="6" class="text-center py-5 text-muted">
  <i class="bi bi-journal-text display-4 d-block mb-3 opacity-25"></i>
  <h6>No log entries found</h6>
</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<?php if ($total>$per): ?>
<div class="p-3"><?= paginate($total,$per,$page,url('admin/audit-logs?'.http_build_query(['q'=>$search,'action'=>$action,'user_id'=>$uid,'from'=>$from,'to'=>$to]))) ?></div>
<?php endif; ?>
</div>
<style>.bg-purple{background:#8b5cf6;color:#fff}</style>
<?php admin_page_end(); ?>
