<?php
/**
 * ViewPay Admin — Deposits / Confirm Payments
 * Developed by Jcode | Author: Obrempong
 */
defined('VP_BOOT') || die('Direct access not permitted.');
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/partials/admin-layout.php';
require_admin();

// ── POST handler ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $did    = (int)($_POST['dep_id'] ?? 0);

    if (!$did) { flash('error','Invalid deposit.'); redirect('/admin/deposits'); }

    $dep = db()->prepare(
        "SELECT d.*, p.name pname, p.views_count, p.duration_days, p.cost_per_view AS reward_per_view
         FROM deposits d LEFT JOIN packages p ON d.package_id=p.id
         WHERE d.id=? LIMIT 1"
    );
    $dep->execute([$did]); $dep = $dep->fetch();
    if (!$dep) { flash('error','Deposit not found.'); redirect('/admin/deposits'); }

    if ($action === 'approve' && $dep['status'] === 'pending') {
        $now  = now_ts();
        $note = strip_tags(trim($_POST['admin_note'] ?? ''));

        // 1. Mark deposit completed
        db()->prepare(
            "UPDATE deposits SET status='completed', updated_at=? WHERE id=?"
        )->execute([$now, $did]);

        // 2. Create task (campaign) for the creator — 15 cols, 15 params ✓
        db()->prepare(
            "INSERT INTO tasks
             (user_id,package_id,title,type,url,total_views_purchased,views_remaining,
              views_completed,min_watch_seconds,reward_per_view,amount_paid,status,
              show_ads_before,created_at,updated_at)
             VALUES(?,?,?,?,?,?,?,0,30,?,?,?,1,?,?)"
        )->execute([
            $dep['user_id'],                     //  1 user_id
            $dep['package_id'],                   //  2 package_id
            'Awaiting URL — ' . ($dep['pname'] ?? 'Campaign'), // 3 title
            'video',                              //  4 type (creator changes later)
            '#',                                  //  5 url placeholder
            (int)$dep['views_count'],             //  6 total_views_purchased
            (int)$dep['views_count'],             //  7 views_remaining
            (float)$dep['reward_per_view'],       //  8 reward_per_view
            (float)$dep['amount'],                //  9 amount_paid
            'pending',                            // 10 status
            $now,                                 // 11 created_at
            $now,                                 // 12 updated_at
        ]);

        // 3. Update user totals
        db()->prepare(
            "UPDATE users SET total_deposited=total_deposited+?, updated_at=? WHERE id=?"
        )->execute([$dep['amount'], $now, $dep['user_id']]);

        // 4. Transaction record
        db()->prepare(
            "INSERT INTO transactions
             (user_id,type,amount,fee,net_amount,currency,status,gateway,note,reference,created_at,updated_at)
             VALUES(?,?,?,0,?,?,?,?,?,?,?,?)"
        )->execute([
            $dep['user_id'], 'deposit', $dep['amount'], $dep['amount'],
            setting('currency_code','GHS'), 'completed', $dep['gateway'],
            $note ?: 'Package purchase confirmed',
            gen_ref('TX'), $now, $now
        ]);

        flash('success', 'Payment confirmed! Campaign created — creator must submit their URL.');

    } elseif ($action === 'reject' && $dep['status'] === 'pending') {
        $reason = strip_tags(trim($_POST['reason'] ?? 'Payment could not be verified.'));
        db()->prepare(
            "UPDATE deposits SET status='rejected', updated_at=? WHERE id=?"
        )->execute([now_ts(), $did]);
        flash('warning', 'Deposit rejected. Creator has been notified via their campaign list.');
    }

    redirect('/admin/deposits');
}

// ── Filters ────────────────────────────────────────────────
$status = $_GET['status'] ?? 'pending';
$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 15; $off = ($page-1)*$per;

$where  = $status ? "WHERE d.status=?" : "";
$params = $status ? [$status] : [];

$total = db()->prepare("SELECT COUNT(*) FROM deposits d $where");
$total->execute($params); $total = (int)$total->fetchColumn();

$params[] = $per; $params[] = $off;
$deps = db()->prepare(
    "SELECT d.*, u.name uname, u.email uemail, p.name pname
     FROM deposits d
     JOIN users u ON d.user_id=u.id
     LEFT JOIN packages p ON d.package_id=p.id
     $where
     ORDER BY d.created_at DESC LIMIT ? OFFSET ?"
);
$deps->execute($params); $deps = $deps->fetchAll();

// Counts per status
$pCount = (int)db()->query("SELECT COUNT(*) FROM deposits WHERE status='pending'")->fetchColumn();
$cCount = (int)db()->query("SELECT COUNT(*) FROM deposits WHERE status='completed'")->fetchColumn();
$rCount = (int)db()->query("SELECT COUNT(*) FROM deposits WHERE status='rejected'")->fetchColumn();

admin_page_start('Confirm Payments');
?>

<div class="d-flex justify-content-between align-items-start mb-4">
  <div>
    <h4 class="fw-bold mb-1">Confirm Payments</h4>
    <p class="text-muted small mb-0">Review and confirm deposits from creators</p>
  </div>
  <?php if ($pCount > 0): ?>
  <span class="badge bg-warning text-dark fs-6 px-3 py-2">
    <i class="bi bi-hourglass-split me-1"></i><?= $pCount ?> Awaiting
  </span>
  <?php endif; ?>
</div>

<!-- Status tabs -->
<div class="d-flex gap-2 mb-4">
  <?php foreach ([
    ['pending',   "Pending ($pCount)",    'bg-warning text-dark'],
    ['completed', "Confirmed ($cCount)",  'bg-success'],
    ['rejected',  "Rejected ($rCount)",   'bg-danger'],
    ['',          'All',                  'bg-secondary'],
  ] as [$sv, $sl, $sc]): ?>
  <a href="<?= url('admin/deposits?status=' . $sv) ?>"
     class="btn btn-sm fw-600 <?= $status === $sv ? $sc . ' text-white' : 'btn-outline-secondary' ?>">
    <?= $sl ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- Deposits table -->
<div class="card border-0 shadow-sm rounded-xl">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0 small">
      <thead class="table-light">
        <tr>
          <th class="ps-4">Creator</th>
          <th>Package</th>
          <th>Amount</th>
          <th>Method</th>
          <th>Reference</th>
          <th>Date</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($deps as $d):
        $meta = json_decode($d['gateway_response'] ?? '{}', true) ?: [];
        $note = $meta['note'] ?? '—';
        $ref  = $d['gateway_transaction_id'] ?? '—';
      ?>
      <tr>
        <td class="ps-4">
          <div class="fw-600"><?= e($d['uname']) ?></div>
          <div class="text-muted" style="font-size:.7rem"><?= e($d['uemail']) ?></div>
        </td>
        <td class="fw-600 small"><?= e($d['pname'] ?? 'Custom') ?></td>
        <td class="fw-bold text-success"><?= money((float)$d['amount']) ?></td>
        <td>
          <span class="badge bg-secondary-subtle text-secondary">
            <?= e(ucfirst(str_replace('_', ' ', $d['gateway']))) ?>
          </span>
        </td>
        <td class="font-monospace small text-muted"><?= e(mb_substr($ref, 0, 20)) ?></td>
        <td class="text-muted"><?= date('M d, Y H:i', strtotime($d['created_at'])) ?></td>
        <td>
          <span class="badge <?= match($d['status']) {
            'completed' => 'bg-success',
            'rejected'  => 'bg-danger',
            'pending'   => 'bg-warning text-dark',
            default     => 'bg-secondary',
          } ?>">
            <?= ucfirst($d['status']) ?>
          </span>
        </td>
        <td>
          <?php if ($d['status'] === 'pending'): ?>
          <div class="d-flex gap-1">
            <button class="btn btn-sm btn-success fw-600"
                    data-bs-toggle="modal" data-bs-target="#approveModal"
                    data-did="<?= $d['id'] ?>"
                    data-name="<?= e($d['uname']) ?>"
                    data-pkg="<?= e($d['pname'] ?? 'Package') ?>"
                    data-amount="<?= e(money((float)$d['amount'])) ?>"
                    data-method="<?= e(ucfirst(str_replace('_',' ',$d['gateway']))) ?>"
                    data-ref="<?= e($note) ?>">
              <i class="bi bi-check-circle me-1"></i>Confirm
            </button>
            <button class="btn btn-sm btn-outline-danger fw-600"
                    data-bs-toggle="modal" data-bs-target="#rejectModal"
                    data-did="<?= $d['id'] ?>" data-name="<?= e($d['uname']) ?>">
              <i class="bi bi-x-circle me-1"></i>Reject
            </button>
          </div>
          <?php else: ?>
          <a href="<?= url('admin/users/' . $d['user_id']) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-person"></i>
          </a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$deps): ?>
      <tr><td colspan="8" class="text-center py-5 text-muted">
        <i class="bi bi-check-circle-fill d-block fs-3 text-success mb-2"></i>
        No <?= $status ?: '' ?> deposits.
      </td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($total > $per): ?>
  <div class="p-3"><?= paginate($total, $per, $page, url('admin/deposits?status=' . urlencode($status))) ?></div>
  <?php endif; ?>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content border-0 shadow rounded-xl">
  <div class="modal-header border-0 bg-success bg-opacity-10">
    <h6 class="fw-bold text-success"><i class="bi bi-check-circle-fill me-2"></i>Confirm Payment</h6>
    <button class="btn-close" data-bs-dismiss="modal"></button>
  </div>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action"  value="approve">
    <input type="hidden" name="dep_id"  id="aDid">
    <div class="modal-body">
      <div class="rounded-xl p-3 bg-body-secondary mb-3 small">
        <div class="row g-2">
          <div class="col-6"><div class="text-muted">Creator</div><div class="fw-700" id="aName"></div></div>
          <div class="col-6"><div class="text-muted">Package</div><div class="fw-700" id="aPkg"></div></div>
          <div class="col-6"><div class="text-muted">Amount</div><div class="fw-700 text-success" id="aAmount"></div></div>
          <div class="col-6"><div class="text-muted">Method</div><div class="fw-700" id="aMethod"></div></div>
          <div class="col-12"><div class="text-muted">User's Reference</div><div class="fw-600 font-monospace" id="aRef"></div></div>
        </div>
      </div>
      <div class="alert alert-success small py-2 border-0">
        <i class="bi bi-info-circle me-1"></i>
        Confirming will create an active campaign and notify the creator to submit their URL.
      </div>
      <div>
        <label class="form-label small fw-600">Admin Note (optional)</label>
        <input type="text" name="admin_note" class="form-control" placeholder="e.g. Verified MTN MoMo transaction">
      </div>
    </div>
    <div class="modal-footer border-0">
      <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
      <button type="submit" class="btn btn-success fw-700 px-4">
        <i class="bi bi-check-circle-fill me-1"></i>Yes, Confirm Payment
      </button>
    </div>
  </form>
</div>
</div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
<div class="modal-dialog modal-dialog-centered modal-sm">
<div class="modal-content border-0 shadow rounded-xl">
  <div class="modal-header border-0">
    <h6 class="fw-bold">Reject Deposit</h6>
    <button class="btn-close" data-bs-dismiss="modal"></button>
  </div>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action"  value="reject">
    <input type="hidden" name="dep_id"  id="rDid">
    <div class="modal-body">
      <p class="small text-muted">Creator: <strong id="rName"></strong></p>
      <label class="form-label small fw-600">Reason *</label>
      <textarea name="reason" class="form-control" rows="3"
                placeholder="e.g. Transaction not found, amount mismatch…" required></textarea>
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
document.getElementById('approveModal').addEventListener('show.bs.modal', e => {
    const b = e.relatedTarget;
    document.getElementById('aDid').value     = b.dataset.did;
    document.getElementById('aName').textContent   = b.dataset.name;
    document.getElementById('aPkg').textContent    = b.dataset.pkg;
    document.getElementById('aAmount').textContent = b.dataset.amount;
    document.getElementById('aMethod').textContent = b.dataset.method;
    document.getElementById('aRef').textContent    = b.dataset.ref;
});
document.getElementById('rejectModal').addEventListener('show.bs.modal', e => {
    const b = e.relatedTarget;
    document.getElementById('rDid').value  = b.dataset.did;
    document.getElementById('rName').textContent = b.dataset.name;
});
</script>
<?php admin_page_end(); ?>
