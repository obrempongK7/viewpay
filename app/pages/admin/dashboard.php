<?php
defined('VP_BOOT') || die('Direct access not permitted.');
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/partials/admin-layout.php';
require_moderator();

try {
    $s = [
        'users'      => (int)db()->query("SELECT COUNT(*) FROM users WHERE is_admin=0")->fetchColumn(),
        'new_today'  => (int)db()->query("SELECT COUNT(*) FROM users WHERE DATE(created_at)=CURDATE() AND is_admin=0")->fetchColumn(),
        'active_cmp' => (int)db()->query("SELECT COUNT(*) FROM tasks WHERE status='active'")->fetchColumn(),
        'pend_tasks' => (int)db()->query("SELECT COUNT(*) FROM tasks WHERE status='pending'")->fetchColumn(),
        'views_today'=> (int)db()->query("SELECT COUNT(*) FROM task_views WHERE status='valid' AND DATE(created_at)=CURDATE()")->fetchColumn(),
        'ad_today'   => (int)db()->query("SELECT COUNT(*) FROM ad_watches WHERE DATE(watched_at)=CURDATE()")->fetchColumn(),
        'revenue'    => (float)db()->query("SELECT COALESCE(SUM(amount),0) FROM deposits WHERE status='completed'")->fetchColumn(),
        'rev_today'  => (float)db()->query("SELECT COALESCE(SUM(amount),0) FROM deposits WHERE status='completed' AND DATE(created_at)=CURDATE()")->fetchColumn(),
        'pend_dep'   => (int)db()->query("SELECT COUNT(*) FROM deposits WHERE status='pending'")->fetchColumn(),
        'pend_wd'    => (int)db()->query("SELECT COUNT(*) FROM withdrawals WHERE status='pending'")->fetchColumn(),
        'unverified' => (int)db()->query("SELECT COUNT(*) FROM users WHERE email_verified_at IS NULL AND is_admin=0")->fetchColumn(),
    ];
    $pendDeps  = db()->query("SELECT d.*,u.name uname,p.name pname FROM deposits d JOIN users u ON d.user_id=u.id LEFT JOIN packages p ON d.package_id=p.id WHERE d.status='pending' ORDER BY d.created_at DESC LIMIT 5")->fetchAll();
    $pendTasks = db()->query("SELECT t.*,u.name uname FROM tasks t JOIN users u ON t.user_id=u.id WHERE t.status='pending' ORDER BY t.created_at DESC LIMIT 5")->fetchAll();
    $chartL=$chartR=$chartV=[];
    for ($i=6;$i>=0;$i--) {
        $d=date('Y-m-d',strtotime("-{$i} days"));
        $chartL[]=date('M d',strtotime($d));
        $rv=db()->prepare("SELECT COALESCE(SUM(amount),0) FROM deposits WHERE status='completed' AND DATE(created_at)=?"); $rv->execute([$d]); $chartR[]=(float)$rv->fetchColumn();
        $vw=db()->prepare("SELECT COUNT(*) FROM task_views WHERE status='valid' AND DATE(created_at)=?"); $vw->execute([$d]); $chartV[]=(int)$vw->fetchColumn();
    }
} catch (Exception $e) {
    $s=['users'=>0,'new_today'=>0,'active_cmp'=>0,'pend_tasks'=>0,'views_today'=>0,'ad_today'=>0,'revenue'=>0,'rev_today'=>0,'pend_dep'=>0,'pend_wd'=>0,'unverified'=>0];
    $pendDeps=$pendTasks=[];$chartL=$chartR=$chartV=[];
}

admin_page_start('Dashboard');
$sym = e(get_currency()['symbol']);
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
  <div>
    <h4 class="fw-bold mb-1">Dashboard</h4>
    <p class="text-muted small mb-0">Welcome back, <?= e(explode(' ',auth()['name'])[0]) ?> — <?= date('l, M j Y') ?></p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if ($s['pend_dep']>0): ?>
    <a href="<?= url('admin/deposits') ?>" class="btn btn-success btn-sm fw-600"><i class="bi bi-cash-stack me-1"></i><?= $s['pend_dep'] ?> Payment<?= $s['pend_dep']>1?'s':'' ?></a>
    <?php endif; ?>
    <?php if ($s['pend_tasks']>0): ?>
    <a href="<?= url('admin/tasks?status=pending') ?>" class="btn btn-warning btn-sm fw-600 text-dark"><i class="bi bi-hourglass-split me-1"></i><?= $s['pend_tasks'] ?> Pending</a>
    <?php endif; ?>
  </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
<?php foreach([
  ['Total Users',      number_format($s['users']),       'people-fill',           '#6366f1', '+'.$s['new_today'].' today',                url('admin/users')],
  ['Revenue',          money($s['revenue']),              'currency-exchange',      '#10b981', money($s['rev_today']).' today',             url('admin/deposits')],
  ['Active Campaigns', number_format($s['active_cmp']),  'collection-play-fill',   '#f59e0b', $s['pend_tasks'].' pending',                url('admin/tasks')],
  ['Views Today',      number_format($s['views_today']), 'eye-fill',               '#3b82f6', 'Task completions',                         url('admin/tasks')],
  ['Ad Watches Today', number_format($s['ad_today']),    'tv-fill',                '#8b5cf6', 'Ad reward views',                          url('admin/audit-logs?action=ad_watch')],
  ['Pending Payouts',  number_format($s['pend_wd']),     'arrow-up-circle-fill',   '#ef4444', 'Awaiting processing',                      url('admin/payments')],
] as [$lbl,$val,$icon,$color,$sub,$link]): ?>
<div class="col-xl-2 col-md-4 col-6">
  <a href="<?= $link ?>" class="text-decoration-none">
    <div class="stat-card">
      <div class="d-flex align-items-center gap-2 mb-2">
        <div class="stat-icon" style="background:<?= $color ?>18;color:<?= $color ?>"><i class="bi bi-<?= $icon ?>"></i></div>
        <span class="text-muted small"><?= $lbl ?></span>
      </div>
      <div class="fw-bold fs-5" style="color:<?= $color ?>"><?= $val ?></div>
      <div class="text-muted mt-1" style="font-size:.7rem"><?= $sub ?></div>
    </div>
  </a>
</div>
<?php endforeach; ?>
</div>

<!-- Chart + Actions -->
<div class="row g-4 mb-4">
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm rounded-xl p-3">
      <div class="fw-600 small mb-3"><i class="bi bi-bar-chart-fill text-primary me-2"></i>7-Day Revenue &amp; Views</div>
      <canvas id="mainChart" height="110"></canvas>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm rounded-xl p-3 h-100">
      <div class="fw-600 small mb-3"><i class="bi bi-lightning-fill text-warning me-2"></i>Quick Actions</div>
      <div class="d-grid gap-2">
        <a href="<?= url('admin/deposits') ?>"   class="btn btn-success  btn-sm fw-600 text-start"><i class="bi bi-cash-stack me-2"></i>Confirm Payments (<?= $s['pend_dep'] ?>)</a>
        <a href="<?= url('admin/tasks') ?>"      class="btn btn-warning  btn-sm fw-600 text-dark text-start"><i class="bi bi-collection-play me-2"></i>Review Campaigns (<?= $s['pend_tasks'] ?>)</a>
        <a href="<?= url('admin/payments') ?>"   class="btn btn-danger   btn-sm fw-600 text-start"><i class="bi bi-arrow-up-circle me-2"></i>Withdrawals (<?= $s['pend_wd'] ?>)</a>
        <a href="<?= url('admin/packages') ?>"   class="btn btn-outline-primary btn-sm fw-600 text-start"><i class="bi bi-box-seam me-2"></i>Packages</a>
        <a href="<?= url('admin/audit-logs') ?>" class="btn btn-outline-secondary btn-sm fw-600 text-start"><i class="bi bi-journal-text me-2"></i>Audit Logs</a>
        <?php if (is_admin()): ?>
        <a href="<?= url('admin/settings') ?>"   class="btn btn-outline-secondary btn-sm fw-600 text-start"><i class="bi bi-gear me-2"></i>Settings</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Pending panels -->
<div class="row g-4">
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm rounded-xl">
      <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center py-3 px-4">
        <h6 class="fw-bold mb-0"><i class="bi bi-cash-stack text-success me-2"></i>Pending Payments</h6>
        <a href="<?= url('admin/deposits') ?>" class="btn btn-sm btn-outline-secondary">All →</a>
      </div>
      <?php if ($pendDeps): ?>
      <div class="list-group list-group-flush">
        <?php foreach($pendDeps as $dep): ?>
        <div class="list-group-item px-4 py-3"><div class="d-flex justify-content-between align-items-center">
          <div><div class="fw-600 small"><?= e($dep['uname']) ?></div><div class="text-muted" style="font-size:.7rem"><?= e($dep['pname']??'Package') ?> · <?= e(ucfirst(str_replace('_',' ',$dep['gateway']))) ?></div></div>
          <div class="text-end"><div class="fw-bold text-success small"><?= money((float)$dep['amount']) ?></div><a href="<?= url('admin/deposits') ?>" class="badge bg-success text-decoration-none" style="font-size:.65rem">Confirm →</a></div>
        </div></div>
        <?php endforeach; ?>
      </div>
      <?php else: ?><div class="text-center py-4 text-muted small">No pending payments 🎉</div><?php endif; ?>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm rounded-xl">
      <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center py-3 px-4">
        <h6 class="fw-bold mb-0"><i class="bi bi-collection-play text-warning me-2"></i>Pending Campaigns</h6>
        <a href="<?= url('admin/tasks?status=pending') ?>" class="btn btn-sm btn-outline-secondary">All →</a>
      </div>
      <?php if ($pendTasks): ?>
      <div class="list-group list-group-flush">
        <?php foreach($pendTasks as $t): ?>
        <div class="list-group-item px-4 py-3"><div class="d-flex justify-content-between align-items-center">
          <div><div class="fw-600 small"><?= e(mb_substr($t['title'],0,40)) ?></div><div class="text-muted" style="font-size:.7rem"><?= e($t['uname']) ?> · <?= ucfirst($t['type']) ?></div></div>
          <a href="<?= url('admin/tasks/'.$t['id']) ?>" class="badge bg-warning text-dark text-decoration-none" style="font-size:.65rem">Review →</a>
        </div></div>
        <?php endforeach; ?>
      </div>
      <?php else: ?><div class="text-center py-4 text-muted small">No pending campaigns 🎉</div><?php endif; ?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('mainChart'),{
  data:{labels:<?= json_encode($chartL) ?>,datasets:[
    {type:'bar',label:'Revenue (<?= $sym ?>)',data:<?= json_encode($chartR) ?>,backgroundColor:'rgba(16,185,129,.2)',borderColor:'#10b981',borderWidth:2,yAxisID:'y1'},
    {type:'line',label:'Views',data:<?= json_encode($chartV) ?>,borderColor:'#6366f1',backgroundColor:'rgba(99,102,241,.07)',tension:.4,fill:true,pointRadius:4,yAxisID:'y2'}
  ]},
  options:{responsive:true,interaction:{mode:'index',intersect:false},plugins:{legend:{position:'top',labels:{font:{size:11}}}},
    scales:{y1:{type:'linear',position:'left',ticks:{font:{size:10},callback:v=>'<?= $sym ?>'+v}},y2:{type:'linear',position:'right',grid:{drawOnChartArea:false},ticks:{font:{size:10}}}}}
});
</script>
<?php admin_page_end(); ?>
