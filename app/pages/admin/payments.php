<?php
defined('VP_BOOT') || die('Direct access not permitted.');
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/partials/admin-layout.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $wid    = (int)($_POST['wd_id'] ?? 0);
    if (!$wid) { flash('error','Invalid withdrawal.'); redirect('/admin/payments'); }

    $wd = db()->prepare("SELECT * FROM withdrawals WHERE id=? LIMIT 1");
    $wd->execute([$wid]); $wd = $wd->fetch();
    if (!$wd) { flash('error','Withdrawal not found.'); redirect('/admin/payments'); }

    if ($action === 'approve' && $wd['status'] === 'pending') {
        $note = strip_tags(trim($_POST['note'] ?? ''));
        db()->prepare(
            "UPDATE withdrawals SET status='approved',admin_note=?,processed_by=?,processed_at=?,updated_at=? WHERE id=?"
        )->execute([$note, auth()['id'], now_ts(), now_ts(), $wid]);

        // Transaction record
        db()->prepare(
            "INSERT INTO transactions
             (user_id,type,amount,fee,net_amount,currency,status,gateway,note,reference,created_at,updated_at)
             VALUES(?,?,?,?,?,?,?,?,?,?,?,?)"
        )->execute([
            $wd['user_id'], 'withdrawal', $wd['amount'], $wd['fee'], $wd['net_amount'],
            setting('currency_code','GHS'), 'completed', $wd['gateway'],
            $note ?: 'Withdrawal processed',
            gen_ref('WD'), now_ts(), now_ts()
        ]);

        // Update user totals
        db()->prepare(
            "UPDATE users SET total_withdrawn=total_withdrawn+?,updated_at=? WHERE id=?"
        )->execute([$wd['net_amount'], now_ts(), $wd['user_id']]);

        flash('success', 'Withdrawal approved. Remember to physically send ' . money($wd['net_amount']) . ' to the user.');

    } elseif ($action === 'reject' && $wd['status'] === 'pending') {
        $reason = strip_tags(trim($_POST['reason'] ?? 'Rejected by admin.'));
        // Refund wallet
        db()->prepare(
            "UPDATE users SET wallet_balance=wallet_balance+?,updated_at=? WHERE id=?"
        )->execute([$wd['amount'], now_ts(), $wd['user_id']]);
        db()->prepare(
            "UPDATE withdrawals SET status='rejected',admin_note=?,processed_by=?,processed_at=?,updated_at=? WHERE id=?"
        )->execute([$reason, auth()['id'], now_ts(), now_ts(), $wid]);
        flash('warning', 'Withdrawal rejected. Amount refunded to user wallet.');

    } elseif ($action === 'paid' && $wd['status'] === 'approved') {
        db()->prepare(
            "UPDATE withdrawals SET status='paid',updated_at=? WHERE id=?"
        )->execute([now_ts(), $wid]);
        flash('success', 'Withdrawal marked as paid.');
    }
    redirect('/admin/payments');
}

$status = $_GET['status'] ?? 'pending';
$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 15; $off = ($page-1)*$per;

$where  = $status ? "WHERE w.status=?" : "";
$params = $status ? [$status] : [];
$total  = db()->prepare("SELECT COUNT(*) FROM withdrawals w $where");
$total->execute($params); $total = (int)$total->fetchColumn();

$params[] = $per; $params[] = $off;
$wds = db()->prepare(
    "SELECT w.*, u.name uname, u.email uemail FROM withdrawals w
     JOIN users u ON w.user_id=u.id
     $where ORDER BY w.created_at DESC LIMIT ? OFFSET ?"
);
$wds->execute($params); $wds = $wds->fetchAll();

$counts = [];
foreach (['pending','approved','paid','rejected'] as $s) {
    $st = db()->prepare("SELECT COUNT(*) FROM withdrawals WHERE status=?");
    $st->execute([$s]); $counts[$s] = (int)$st->fetchColumn();
}

admin_page_start('Withdrawals');
?>
<div class="d-flex justify-content-between align-items-start mb-4">
  <div>
    <h4 class="fw-bold mb-1">Withdrawals</h4>
    <p class="text-muted small mb-0">Process earner payout requests</p>
  </div>
  <?php if ($counts['pending'] > 0): ?>
  <span class="badge bg-danger fs-6 px-3 py-2">
    <?= $counts['pending'] ?> pending payout<?= $counts['pending']>1?'s':'' ?>
  </span>
  <?php endif; ?>
</div>

<div class="d-flex gap-2 mb-4">
  <?php foreach ([
    ['pending',  "Pending ({$counts['pending']})",   'bg-warning text-dark'],
    ['approved', "Approved ({$counts['approved']})", 'bg-info'],
    ['paid',     "Paid ({$counts['paid']})",         'bg-success'],
    ['rejected', "Rejected ({$counts['rejected']})", 'bg-danger'],
    ['',         'All',                              'bg-secondary'],
  ] as [$sv, $sl, $sc]): ?>
  <a href="<?= url('admin/payments?status=' . $sv) ?>"
     class="btn btn-sm fw-600 <?= $status === $sv ? $sc . ' text-white' : 'btn-outline-secondary' ?>">
    <?= $sl ?>
  </a>
  <?php endforeach; ?>
</div>

<div class="card border-0 shadow-sm rounded-xl">
<div class="table-responsive">
<table class="table table-hover align-middle mb-0 small">
  <thead class="table-light">
    <tr>
      <th class="ps-4">User</th>
      <th>Amount</th>
      <th>Fee</th>
      <th>Net</th>
      <th>Method</th>
      <th>Payout Details</th>
      <th>Status</th>
      <th>Date</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($wds as $w):
    $det = json_decode($w['payout_details'] ?? '{}', true) ?: [];
    $detStr = implode(', ', array_map(fn($k,$v)=>ucfirst(str_replace('_',' ',$k)).': '.$v, array_keys($det), $det));
  ?>
  <tr>
    <td class="ps-4">
      <div class="fw-600"><?= e($w['uname']) ?></div>
      <div class="text-muted" style="font-size:.7rem"><?= e($w['uemail']) ?></div>
    </td>
    <td class="fw-bold"><?= money((float)$w['amount']) ?></td>
    <td class="text-danger small"><?= money((float)$w['fee']) ?></td>
    <td class="fw-bold text-success"><?= money((float)$w['net_amount']) ?></td>
    <td>
      <span class="badge bg-secondary-subtle text-secondary">
        <?= e(ucfirst(str_replace('_', ' ', $w['gateway']))) ?>
      </span>
    </td>
    <td class="text-muted" style="max-width:160px">
      <span class="text-truncate d-block small" title="<?= e($detStr) ?>"><?= e(mb_substr($detStr,0,50)) ?></span>
    </td>
    <td>
      <span class="badge <?= match($w['status']){
        'paid'    =>'bg-success','approved'=>'bg-info',
        'rejected'=>'bg-danger', default   =>'bg-warning text-dark',
      } ?>">
        <?= ucfirst($w['status']) ?>
      </span>
    </td>
    <td class="text-muted"><?= date('M d, H:i', strtotime($w['created_at'])) ?></td>
    <td>
      <?php if ($w['status'] === 'pending'): ?>
      <div class="d-flex gap-1">
        <button class="btn btn-sm btn-success fw-600"
                data-bs-toggle="modal" data-bs-target="#approveWdModal"
                data-wid="<?= $w['id'] ?>"
                data-name="<?= e($w['uname']) ?>"
                data-net="<?= e(money((float)$w['net_amount'])) ?>"
                data-method="<?= e(ucfirst(str_replace('_',' ',$w['gateway']))) ?>"
                data-det="<?= e(mb_substr($detStr,0,120)) ?>">
          <i class="bi bi-check-circle me-1"></i>Approve
        </button>
        <button class="btn btn-sm btn-outline-danger"
                data-bs-toggle="modal" data-bs-target="#rejectWdModal"
                data-wid="<?= $w['id'] ?>" data-name="<?= e($w['uname']) ?>">
          <i class="bi bi-x-circle"></i>
        </button>
      </div>
      <?php elseif ($w['status'] === 'approved'): ?>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="paid">
        <input type="hidden" name="wd_id"  value="<?= $w['id'] ?>">
        <button class="btn btn-sm btn-primary fw-600">
          <i class="bi bi-check2-all me-1"></i>Mark Paid
        </button>
      </form>
      <?php else: ?>
      <a href="<?= url('admin/users/'.$w['user_id']) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-person"></i>
      </a>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$wds): ?>
  <tr><td colspan="9" class="text-center py-5 text-muted">
    <i class="bi bi-check-circle-fill d-block fs-3 text-success mb-2"></i>
    No <?= $status ?: '' ?> withdrawals.
  </td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div>
<?php if ($total > $per): ?>
<div class="p-3"><?= paginate($total, $per, $page, url('admin/payments?status='.urlencode($status))) ?></div>
<?php endif; ?>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveWdModal" tabindex="-1">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content border-0 shadow rounded-xl">
  <div class="modal-header border-0 bg-success bg-opacity-10">
    <h6 class="fw-bold text-success"><i class="bi bi-check-circle-fill me-2"></i>Approve Withdrawal</h6>
    <button class="btn-close" data-bs-dismiss="modal"></button>
  </div>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="approve">
    <input type="hidden" name="wd_id"  id="awId">
    <div class="modal-body">
      <div class="rounded-xl p-3 bg-body-secondary small mb-3">
        <div class="row g-2">
          <div class="col-6"><div class="text-muted">User</div><div class="fw-700" id="awName"></div></div>
          <div class="col-6"><div class="text-muted">Net Amount</div><div class="fw-700 text-success" id="awNet"></div></div>
          <div class="col-6"><div class="text-muted">Method</div><div class="fw-700" id="awMethod"></div></div>
          <div class="col-12"><div class="text-muted">Account Details</div><div class="fw-600" id="awDet"></div></div>
        </div>
      </div>
      <div class="alert alert-warning small py-2 border-0 shadow-sm">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Approving means you will <strong>physically send the money</strong> to the user's account.
        Click "Mark Paid" after sending.
      </div>
      <div>
        <label class="form-label small fw-600">Admin Note (optional)</label>
        <input type="text" name="note" class="form-control" placeholder="e.g. Sent via MoMo">
      </div>
    </div>
    <div class="modal-footer border-0">
      <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
      <button type="submit" class="btn btn-success fw-700 px-4">
        <i class="bi bi-check-circle-fill me-1"></i>Approve & Start Processing
      </button>
    </div>
  </form>
</div>
</div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectWdModal" tabindex="-1">
<div class="modal-dialog modal-sm modal-dialog-centered">
<div class="modal-content border-0 shadow rounded-xl">
  <div class="modal-header border-0"><h6 class="fw-bold">Reject Withdrawal</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="reject">
    <input type="hidden" name="wd_id"  id="rwId">
    <div class="modal-body">
      <p class="small text-muted">User: <strong id="rwName"></strong></p>
      <p class="small text-success"><i class="bi bi-info-circle me-1"></i>Amount will be refunded to wallet.</p>
      <label class="form-label small fw-600">Reason *</label>
      <textarea name="reason" class="form-control" rows="2" required placeholder="e.g. Invalid account details…"></textarea>
    </div>
    <div class="modal-footer border-0 pt-0">
      <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
      <button type="submit" class="btn btn-sm btn-danger fw-600">Reject & Refund</button>
    </div>
  </form>
</div>
</div>
</div>

<script>
document.getElementById('approveWdModal').addEventListener('show.bs.modal', e => {
    const b = e.relatedTarget;
    document.getElementById('awId').value           = b.dataset.wid;
    document.getElementById('awName').textContent   = b.dataset.name;
    document.getElementById('awNet').textContent    = b.dataset.net;
    document.getElementById('awMethod').textContent = b.dataset.method;
    document.getElementById('awDet').textContent    = b.dataset.det;
});
document.getElementById('rejectWdModal').addEventListener('show.bs.modal', e => {
    const b = e.relatedTarget;
    document.getElementById('rwId').value           = b.dataset.wid;
    document.getElementById('rwName').textContent   = b.dataset.name;
});
</script>
<?php admin_page_end(); ?>
