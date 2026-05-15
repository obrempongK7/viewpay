<?php
defined('VP_BOOT') || die('Direct access not permitted.');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/layout.php';
require_auth();
$user = auth();
$curr = get_currency();

// Handle withdrawal
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!rate_limit('wd_' . $user['id'], 3, 300)) {
        flash('error', 'Too many requests. Please wait 5 minutes.');
        redirect('/wallet');
    }
    $amount  = (float)($_POST['amount'] ?? 0);
    $gateway = trim($_POST['gateway'] ?? '');
    $details = [];
    foreach (['account_name','account_number','bank_name','paypal_email','momo_number','momo_name','crypto_address','notes'] as $f) {
        if (!empty($_POST[$f])) { $details[$f] = trim(strip_tags($_POST[$f])); }
    }

    $minWd   = (float)setting('min_withdrawal', '5');
    $maxWd   = (float)setting('max_withdrawal', '500');
    $feeRate = (float)setting('withdrawal_fee_percent', '3') / 100;
    $allowed = ['paypal','bank_transfer','momo','skrill','cashapp','zelle','crypto'];

    if ($amount < $minWd)                                { flash('error', 'Minimum withdrawal is $' . $minWd . ' USD.'); redirect('/wallet'); }
    if ($amount > $maxWd)                                { flash('error', 'Maximum withdrawal is $' . $maxWd . ' USD.'); redirect('/wallet'); }
    if ((float)$user['wallet_balance'] < $amount)        { flash('error', 'Insufficient balance.'); redirect('/wallet'); }
    if (!in_array($gateway, $allowed, true))             { flash('error', 'Invalid payout method.'); redirect('/wallet'); }
    if (empty($details))                                 { flash('error', 'Please provide your payout account details.'); redirect('/wallet'); }

    $fee = round($amount * $feeRate, 8);
    $net = $amount - $fee;

    // Deduct from wallet
    db()->prepare(
        "UPDATE users SET wallet_balance=wallet_balance-?,updated_at=? WHERE id=?"
    )->execute([$amount, now_ts(), $user['id']]);

    // Create withdrawal record
    db()->prepare(
        "INSERT INTO withdrawals
         (user_id,amount,fee,net_amount,gateway,payout_details,status,created_at,updated_at)
         VALUES(?,?,?,?,?,?,?,?,?)"
    )->execute([
        $user['id'], $amount, $fee, $net,
        $gateway, json_encode($details), 'pending', now_ts(), now_ts()
    ]);

    flash('success', 'Withdrawal of ' . money($net) . ' (net) submitted! Admin processes within 24–72 hours.');
    redirect('/wallet');
}

$minWd   = (float)setting('min_withdrawal', '5');
$maxWd   = (float)setting('max_withdrawal', '500');
$feeRate = (float)setting('withdrawal_fee_percent', '3');

// Load transactions
$page  = max(1, (int)($_GET['page'] ?? 1));
$per   = 15; $off = ($page-1)*$per;
$total = (int)db()->prepare("SELECT COUNT(*) FROM transactions WHERE user_id=?")->execute([$user['id']]) ? 0 : 0;
$tSt   = db()->prepare("SELECT COUNT(*) FROM transactions WHERE user_id=?");
$tSt->execute([$user['id']]); $total = (int)$tSt->fetchColumn();
$txSt  = db()->prepare("SELECT * FROM transactions WHERE user_id=? ORDER BY created_at DESC LIMIT ? OFFSET ?");
$txSt->execute([$user['id'], $per, $off]); $txs = $txSt->fetchAll();

$wdSt = db()->prepare("SELECT * FROM withdrawals WHERE user_id=? ORDER BY created_at DESC LIMIT 5");
$wdSt->execute([$user['id']]); $wds = $wdSt->fetchAll();

// Payout methods enabled
$payouts = [];
foreach (['paypal'=>'PayPal','bank_transfer'=>'Bank Transfer','momo'=>'Mobile Money','skrill'=>'Skrill','cashapp'=>'CashApp','zelle'=>'Zelle','crypto'=>'Crypto'] as $k => $lbl) {
    if (setting('payout_' . $k . '_enabled', '1') === '1') { $payouts[$k] = $lbl; }
}

page_start('My Wallet');
?>
<div class="container py-4">
  <h4 class="fw-bold mb-4">My Wallet</h4>

  <div class="row g-4">
    <!-- Balance card -->
    <div class="col-lg-5">
      <div class="wallet-card p-4 rounded-xl mb-3">
        <div class="text-white-50 small mb-1">Available Balance</div>
        <div class="text-white fw-bold" style="font-size:2.5rem"><?= money((float)$user['wallet_balance']) ?></div>
        <div class="text-white-50 small mb-4">≈ $<?= number_format((float)$user['wallet_balance'], 6) ?> USD stored</div>
        <div class="d-flex gap-2">
          <a href="<?= url('tasks') ?>" class="btn btn-sm btn-white text-primary fw-600 flex-grow-1">
            <i class="bi bi-plus-circle me-1"></i>Earn More
          </a>
          <button class="btn btn-sm btn-white text-success fw-600 flex-grow-1"
                  data-bs-toggle="modal" data-bs-target="#wdModal">
            <i class="bi bi-arrow-up-circle me-1"></i>Withdraw
          </button>
        </div>
      </div>

      <!-- Stats -->
      <div class="card border-0 shadow-sm rounded-xl p-3 mb-3">
        <?php foreach ([
          ['Total Earned',     money((float)$user['total_earned']),    'currency-dollar', 'text-success'],
          ['Total Withdrawn',  money((float)$user['total_withdrawn']),  'arrow-up-circle', 'text-primary'],
          ['Tasks Completed',  number_format($user['total_tasks_completed']), 'check-circle', 'text-info'],
        ] as [$lbl, $val, $icon, $cls]): ?>
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
          <span class="small text-muted d-flex align-items-center gap-2">
            <i class="bi bi-<?= $icon ?> <?= $cls ?>"></i><?= $lbl ?>
          </span>
          <span class="fw-700 small"><?= $val ?></span>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Pending withdrawals -->
      <?php if ($wds): ?>
      <div class="card border-0 shadow-sm rounded-xl p-3">
        <div class="fw-600 small mb-2">Recent Withdrawals</div>
        <?php foreach ($wds as $w): ?>
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom small">
          <div>
            <span class="fw-600"><?= money((float)$w['net_amount']) ?></span>
            <span class="text-muted ms-1"><?= ucfirst(str_replace('_',' ',$w['gateway'])) ?></span>
          </div>
          <span class="badge <?= match($w['status']) { 'paid'=>'bg-success','approved'=>'bg-info','rejected'=>'bg-danger', default=>'bg-warning text-dark' } ?>">
            <?= ucfirst($w['status']) ?>
          </span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Transaction history -->
    <div class="col-lg-7">
      <div class="card border-0 shadow-sm rounded-xl h-100">
        <div class="card-header bg-transparent border-0 fw-600">Transaction History</div>
        <div class="card-body p-0">
          <?php if ($txs): ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
              <thead class="table-light">
                <tr><th class="ps-4">Type</th><th>Amount</th><th>Status</th><th>Note</th><th>Date</th></tr>
              </thead>
              <tbody>
              <?php foreach ($txs as $tx): ?>
              <tr>
                <td class="ps-4 fw-600">
                  <span class="badge bg-<?= in_array($tx['type'],['earning','deposit','refund']) ? 'success' : 'danger' ?>-subtle text-<?= in_array($tx['type'],['earning','deposit','refund']) ? 'success' : 'danger' ?>">
                    <?= ucfirst($tx['type']) ?>
                  </span>
                </td>
                <td class="fw-700 <?= in_array($tx['type'],['earning','deposit','refund']) ? 'text-success' : 'text-danger' ?>">
                  <?= in_array($tx['type'],['earning','deposit','refund']) ? '+' : '-' ?><?= money((float)$tx['amount'],4) ?>
                </td>
                <td>
                  <span class="badge <?= $tx['status']==='completed'?'bg-success':($tx['status']==='pending'?'bg-warning text-dark':'bg-danger') ?>">
                    <?= ucfirst($tx['status']) ?>
                  </span>
                </td>
                <td class="text-muted text-truncate" style="max-width:140px"><?= e(mb_substr($tx['note']??'',0,30)) ?></td>
                <td class="text-muted"><?= date('M d, Y', strtotime($tx['created_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php if ($total > $per): ?>
          <div class="p-3"><?= paginate($total, $per, $page, url('wallet')) ?></div>
          <?php endif; ?>
          <?php else: ?>
          <div class="text-center py-5 text-muted">
            <i class="bi bi-receipt display-4 d-block mb-3 opacity-25"></i>
            <h6>No transactions yet</h6>
            <p class="small">Complete tasks to see your earnings here.</p>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Withdrawal Modal -->
<div class="modal fade" id="wdModal" tabindex="-1">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content border-0 shadow rounded-xl">
  <div class="modal-header border-0">
    <h5 class="fw-bold">Withdraw Funds</h5>
    <button class="btn-close" data-bs-dismiss="modal"></button>
  </div>
  <form method="POST" id="wdForm">
    <?= csrf_field() ?>
    <div class="modal-body">
      <div class="alert alert-info small py-2 border-0 shadow-sm mb-3">
        Balance: <strong><?= money((float)$user['wallet_balance']) ?></strong>
        &nbsp;·&nbsp; Min: <strong><?= money($minWd) ?></strong>
        &nbsp;·&nbsp; Fee: <strong><?= $feeRate ?>%</strong>
      </div>
      <div class="mb-3">
        <label class="form-label fw-600 small">Amount (USD) *</label>
        <div class="input-group">
          <span class="input-group-text">$</span>
          <input type="number" name="amount" id="wdAmount" class="form-control"
                 step="0.01" min="<?= $minWd ?>" max="<?= min($maxWd, (float)$user['wallet_balance']) ?>"
                 required oninput="calcNet(this.value)">
        </div>
        <div class="small mt-1 text-muted">Net after <?= $feeRate ?>% fee: <strong id="netAmt">—</strong></div>
      </div>
      <div class="mb-3">
        <label class="form-label fw-600 small">Payout Method *</label>
        <select name="gateway" class="form-select" required onchange="showWdFields(this.value)">
          <option value="">— Select method —</option>
          <?php foreach ($payouts as $k => $lbl): ?>
          <option value="<?= e($k) ?>"><?= e($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <!-- Dynamic fields -->
      <div id="wdFields"></div>
    </div>
    <div class="modal-footer border-0">
      <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
      <button type="submit" class="btn btn-success fw-600 px-4">
        <i class="bi bi-arrow-up-circle me-1"></i>Request Withdrawal
      </button>
    </div>
  </form>
</div>
</div>
</div>

<script>
const feeRate = <?= $feeRate / 100 ?>;
function calcNet(v) {
    const n = parseFloat(v) || 0;
    document.getElementById('netAmt').textContent = '$' + (n - n*feeRate).toFixed(4) + ' USD';
}
function showWdFields(gw) {
    const f = document.getElementById('wdFields');
    const fields = {
        momo:          [['momo_number','MoMo Number','e.g. 0244123456'],['momo_name','Account Name','Full name on account']],
        bank_transfer: [['account_name','Account Name','Full name'],['account_number','Account Number',''],['bank_name','Bank Name','e.g. GCB Bank']],
        paypal:        [['paypal_email','PayPal Email','paypal@email.com']],
        skrill:        [['paypal_email','Skrill Email','skrill@email.com']],
        cashapp:       [['account_number','$CashTag','$username']],
        zelle:         [['paypal_email','Zelle Email / Phone','']],
        crypto:        [['crypto_address','Wallet Address','Your crypto wallet address'],['notes','Network','e.g. TRC20']],
    };
    if (!fields[gw]) { f.innerHTML = ''; return; }
    f.innerHTML = fields[gw].map(([name, label, ph]) =>
        `<div class="mb-3">
           <label class="form-label fw-600 small">${label}</label>
           <input type="text" name="${name}" class="form-control" placeholder="${ph}" required>
         </div>`
    ).join('');
}
</script>
<?php page_end(); ?>
