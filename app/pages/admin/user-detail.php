<?php
defined('VP_BOOT') || die('Direct access not permitted.');
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/partials/admin-layout.php';
require_admin();

$uid = (int)($_GET['user_id'] ?? 0);
$u   = db()->prepare("SELECT * FROM users WHERE id=? LIMIT 1"); $u->execute([$uid]); $u = $u->fetch();
if (!$u) { flash('error','User not found.'); redirect('/admin/users'); }

$txs  = db()->prepare("SELECT * FROM transactions WHERE user_id=? ORDER BY created_at DESC LIMIT 20"); $txs->execute([$uid]); $txs=$txs->fetchAll();
$wds  = db()->prepare("SELECT * FROM withdrawals  WHERE user_id=? ORDER BY created_at DESC LIMIT 10"); $wds->execute([$uid]); $wds=$wds->fetchAll();
$tasks= db()->prepare("SELECT * FROM tasks         WHERE user_id=? ORDER BY created_at DESC LIMIT 10"); $tasks->execute([$uid]); $tasks=$tasks->fetchAll();

admin_page_start('User — '.$u['name']);
?>
<a href="<?= url('admin/users') ?>" class="btn btn-sm btn-outline-secondary mb-4"><i class="bi bi-arrow-left me-1"></i>All Users</a>
<div class="row g-4">
<div class="col-lg-4">
  <div class="card border-0 shadow-sm rounded-xl p-4 text-center mb-3">
    <img src="<?= e(avatar_url($u['avatar']??null,$u['name'])) ?>" class="rounded-circle mx-auto mb-3" width="72" height="72">
    <h5 class="fw-bold mb-1"><?= e($u['name']) ?></h5>
    <div class="text-muted small mb-2"><?= e($u['email']) ?></div>
    <div class="d-flex gap-1 justify-content-center mb-3">
      <span class="badge <?= $u['status']==='active'?'bg-success':'bg-danger' ?>"><?= ucfirst($u['status']) ?></span>
    </div>
    <div class="row g-2 text-center">
      <?php foreach ([['Balance',money((float)$u['wallet_balance']),'primary'],['Earned',money((float)$u['total_earned']),'success'],['Tasks',number_format($u['total_tasks_completed']),'info']] as [$l,$v,$c]): ?>
      <div class="col-4"><div class="fw-bold small text-<?= $c ?>"><?= $v ?></div><div class="text-muted" style="font-size:.7rem"><?= $l ?></div></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card border-0 shadow-sm rounded-xl p-3 small">
    <?php foreach ([['Email',$u['email']],['IP',$u['last_ip']??'—'],['Ref Code',$u['referral_code']??'—'],['Joined',date('M d, Y',strtotime($u['created_at']))]] as [$l,$v]): ?>
    <div class="d-flex justify-content-between py-1 border-bottom small">
      <span class="text-muted"><?= $l ?></span><span class="fw-600"><?= e($v) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<div class="col-lg-8">
  <ul class="nav nav-pills mb-3 gap-1">
    <li><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#txTab">Transactions</button></li>
    <li><button class="nav-link"        data-bs-toggle="pill" data-bs-target="#wdTab">Withdrawals</button></li>
    <li><button class="nav-link"        data-bs-toggle="pill" data-bs-target="#taskTab">Campaigns</button></li>
  </ul>
  <div class="tab-content">
    <div class="tab-pane fade show active" id="txTab">
      <div class="card border-0 shadow-sm rounded-xl"><div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0 small">
        <thead class="table-light"><tr><th class="ps-4">Type</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($txs as $tx): ?>
        <tr><td class="ps-4"><?= ucfirst($tx['type']) ?></td>
          <td class="fw-700 text-success"><?= money((float)$tx['amount'],4) ?></td>
          <td><span class="badge <?= $tx['status']==='completed'?'bg-success':'bg-warning text-dark' ?>"><?= ucfirst($tx['status']) ?></span></td>
          <td class="text-muted"><?= date('M d, Y',strtotime($tx['created_at'])) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$txs): ?><tr><td colspan="4" class="text-center py-3 text-muted">No transactions.</td></tr><?php endif; ?>
        </tbody>
      </table></div></div>
    </div>
    <div class="tab-pane fade" id="wdTab">
      <div class="card border-0 shadow-sm rounded-xl"><div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0 small">
        <thead class="table-light"><tr><th class="ps-4">Amount</th><th>Net</th><th>Method</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($wds as $w): ?>
        <tr><td class="ps-4 fw-700"><?= money((float)$w['amount']) ?></td><td class="text-success fw-700"><?= money((float)$w['net_amount']) ?></td>
          <td><?= ucfirst(str_replace('_',' ',$w['gateway'])) ?></td>
          <td><span class="badge <?= match($w['status']){'paid'=>'bg-success','approved'=>'bg-info','rejected'=>'bg-danger',default=>'bg-warning text-dark'} ?>"><?= ucfirst($w['status']) ?></span></td>
          <td class="text-muted"><?= date('M d, Y',strtotime($w['created_at'])) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$wds): ?><tr><td colspan="5" class="text-center py-3 text-muted">No withdrawals.</td></tr><?php endif; ?>
        </tbody>
      </table></div></div>
    </div>
    <div class="tab-pane fade" id="taskTab">
      <div class="card border-0 shadow-sm rounded-xl"><div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0 small">
        <thead class="table-light"><tr><th class="ps-4">Title</th><th>Views</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($tasks as $t): ?>
        <tr><td class="ps-4 fw-600"><?= e(mb_substr($t['title'],0,40)) ?></td>
          <td><?= number_format($t['views_completed']) ?>/<?= number_format($t['total_views_purchased']) ?></td>
          <td><span class="badge <?= $t['status']==='active'?'bg-success':($t['status']==='pending'?'bg-warning text-dark':'bg-secondary') ?>"><?= ucfirst($t['status']) ?></span></td></tr>
        <?php endforeach; ?>
        <?php if (!$tasks): ?><tr><td colspan="3" class="text-center py-3 text-muted">No campaigns.</td></tr><?php endif; ?>
        </tbody>
      </table></div></div>
    </div>
  </div>
</div>
</div>
<?php admin_page_end(); ?>
