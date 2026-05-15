<?php
/**
 * ViewPay — Packages / Buy Views
 *
 * BUGS FIXED:
 *  1. INSERT INTO deposits: hardcoded 0 for fee in SQL + 0 in execute = 11 ?s / 12 params
 *     → SQLSTATE[HY093] → HTTP 500. Fixed: all 12 columns use ?s with 12 matching params.
 *  2. JS payDetails strings used raw PHP setting() inside single-quoted JS literals.
 *     If settings contain quotes/backslashes → JS SyntaxError → modal silently breaks.
 *     Fixed: all setting values JSON-encoded then assigned to a PHP-side array, output
 *     once as a single JSON object. Zero JS injection risk.
 *  3. After successful INSERT the redirect to /creator was never reached (exception on line
 *     above). Fixed by fixing the INSERT. Redirect now works correctly.
 *  4. Inline form validation replaces blocking alert() calls.
 *
 * Developed by Jcode | Author: Obrempong
 */
defined('VP_BOOT') || die('Direct access not permitted.');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/layout.php';

// ─────────────────────────────────────────────────────────────
//  POST — Handle order submission
// ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!auth()) {
        flash('error', 'Please log in to place an order.');
        redirect('/login');
    }

    verify_csrf();

    if (!rate_limit('order_' . auth()['id'], 5, 600)) {
        flash('error', 'Too many orders. Please wait 10 minutes before trying again.');
        redirect('/packages');
    }

    $pkgId   = (int)($_POST['package_id'] ?? 0);
    $gateway = trim($_POST['gateway'] ?? '');
    $note    = strip_tags(trim($_POST['payment_note'] ?? ''));

    // Validate gateway
    $allowed = ['momo','paystack','bank_transfer','paypal','stripe','crypto','other'];
    if (!$pkgId) {
        flash('error', 'No package selected. Please choose a package.');
        redirect('/packages');
    }
    if (!$gateway || !in_array($gateway, $allowed, true)) {
        flash('error', 'Please select a payment method.');
        redirect('/packages');
    }
    if (!$note) {
        flash('error', 'Please enter your transaction reference or payment proof.');
        redirect('/packages');
    }

    // Load package
    $pkg = db()->prepare("SELECT * FROM packages WHERE id=? AND is_active=1 LIMIT 1");
    $pkg->execute([$pkgId]); $pkg = $pkg->fetch();
    if (!$pkg) {
        flash('error', 'Package not found or no longer available.');
        redirect('/packages');
    }

    // Check for an existing un-confirmed order
    $pending = db()->prepare(
        "SELECT id FROM deposits WHERE user_id=? AND status='pending' LIMIT 1"
    );
    $pending->execute([auth()['id']]);
    if ($pending->fetch()) {
        flash('warning', 'You already have a pending payment awaiting confirmation. '
            . 'Please wait for admin to confirm it before placing a new order.');
        redirect('/creator');
    }

    // ── INSERT deposits ───────────────────────────────────────
    // Columns (12): user_id, package_id, amount, fee, currency, gateway,
    //               gateway_transaction_id, status, purpose, gateway_response,
    //               created_at, updated_at
    // VALUES:       ?,       ?,          ?,      ?,   ?,        ?,
    //               ?,                       ?,      ?,       ?,
    //               ?,          ?
    // Total: 12 ?s = 12 params ✓  (fee=0 is passed as a param, NOT hardcoded in SQL)
    $ref = gen_ref('DEP');
    try {
        db()->prepare(
            "INSERT INTO deposits
             (user_id, package_id, amount, fee, currency, gateway,
              gateway_transaction_id, status, purpose, gateway_response,
              created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            auth()['id'],                              //  1  user_id
            $pkg['id'],                                //  2  package_id
            $pkg['price'],                             //  3  amount
            0,                                         //  4  fee (always 0 at order time)
            setting('currency_code', 'GHS'),           //  5  currency
            $gateway,                                  //  6  gateway
            $ref,                                      //  7  gateway_transaction_id (our ref)
            'pending',                                 //  8  status
            'package',                                 //  9  purpose
            json_encode(['note' => $note, 'ref' => $ref]), // 10 gateway_response
            now_ts(),                                  // 11  created_at
            now_ts(),                                  // 12  updated_at
        ]); // 12 params for 12 ?s ✓
    } catch (Exception $e) {
        error_log('[ViewPay] Deposit INSERT failed: ' . $e->getMessage());
        flash('error', 'An error occurred saving your order. Please try again or contact support.');
        redirect('/packages');
    }

    // ── Success: send user to their campaigns page ─────────────
    flash('success',
        'Order submitted! Your reference is <strong>' . e($ref) . '</strong>. '
        . 'Admin will confirm your payment within 24 hours, then your campaign will go live.'
    );
    redirect('/creator'); // ← User lands here after successful order
}

// ─────────────────────────────────────────────────────────────
//  LOAD DATA
// ─────────────────────────────────────────────────────────────
try {
    $pkgs = db()->query(
        "SELECT * FROM packages WHERE is_active=1 ORDER BY sort_order ASC, price ASC"
    )->fetchAll();
} catch (Exception $e) {
    $pkgs = [];
}

// Active payment gateways
$gwAll = [
    'momo'          => ['Mobile Money (MoMo)',  'bi-phone-fill',       '#10b981'],
    'paystack'      => ['Paystack',              'bi-credit-card-fill', '#00C3F7'],
    'bank_transfer' => ['Bank Transfer',         'bi-bank',             '#3b82f6'],
    'paypal'        => ['PayPal',                'bi-paypal',           '#003087'],
    'stripe'        => ['Stripe / Card',         'bi-credit-card',      '#635bff'],
    'crypto'        => ['Cryptocurrency',        'bi-currency-bitcoin', '#f7931a'],
    'other'         => ['Other / Manual',        'bi-upload',           '#6366f1'],
];
$activeGw = [];
foreach ($gwAll as $k => [$lbl, $icon, $col]) {
    if (setting($k . '_enabled', '1') === '1') {
        $activeGw[$k] = compact('lbl', 'icon', 'col');
    }
}

$curr = get_currency();

// ── Build payment instructions safely ─────────────────────────
// All setting values are JSON-encoded here so they are safe to embed in JS.
// No risk of single-quote/backslash injection in JS string literals.
$payInstructions = [
    'momo' => '📱 Send Mobile Money to: <strong>'
        . e(setting('momo_number', '—')) . '</strong><br>'
        . 'Provider: ' . e(setting('momo_provider', 'MTN MoMo'))
        . ' &nbsp;·&nbsp; Account Name: <strong>'
        . e(setting('momo_name', '—')) . '</strong><br>'
        . 'After sending, enter your MoMo transaction ID below.',

    'paystack' => '💳 <strong>Paystack</strong> — Pay securely with card, bank transfer, USSD, or mobile money.'
        . (setting('paystack_payment_link')
            ? '<br>Payment link: <a href="' . e(setting('paystack_payment_link')) . '" target="_blank" rel="noopener">'
              . e(setting('paystack_payment_link')) . '</a>'
            : '<br>Admin will send you a Paystack payment link — contact support if needed.')
        . '<br>Enter your Paystack payment reference below.',

    'bank_transfer' => '🏦 Transfer to:<br>'
        . '&nbsp; Bank: <strong>' . e(setting('bank_name', '—')) . '</strong><br>'
        . '&nbsp; Account: <strong>' . e(setting('bank_account', '—')) . '</strong><br>'
        . '&nbsp; Name: <strong>' . e(setting('bank_holder', '—')) . '</strong>'
        . (setting('bank_branch') ? '<br>&nbsp; Branch: ' . e(setting('bank_branch')) : '')
        . '<br>Enter your bank transaction reference number below.',

    'paypal' => '💰 Send PayPal payment to: <strong>'
        . e(setting('paypal_email', '—')) . '</strong><br>'
        . 'Enter your PayPal transaction ID below.',

    'stripe' => '💳 <strong>Stripe / Card</strong> — Admin will send you a secure payment link.<br>'
        . 'Enter "awaiting link" for now, or contact support for the link immediately.',

    'crypto' => '₿ Send to wallet address:<br>'
        . '&nbsp; <strong>' . e(setting('crypto_address', '—')) . '</strong><br>'
        . '&nbsp; Network: ' . e(setting('crypto_network', '—')) . '<br>'
        . 'Enter your transaction hash below.',

    'other' => '📞 Contact admin to arrange payment:<br>'
        . (setting('support_whatsapp')
            ? '&nbsp; WhatsApp: <strong>' . e(setting('support_whatsapp')) . '</strong><br>'
            : '')
        . '&nbsp; Email: <strong>' . e(setting('support_email', setting('site_email', '—'))) . '</strong><br>'
        . 'Enter any payment reference below.',
];

// JSON-encode the entire instructions map for safe JS injection
$payInstrJs = json_encode($payInstructions,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
);

page_start('Buy Views — Grow Your Audience');
?>

<!-- ── Page header ─────────────────────────────────────────── -->
<div class="py-4 bg-white border-bottom">
  <div class="container text-center">
    <h2 class="fw-bold mb-1">Grow Your Audience</h2>
    <p class="text-muted mb-3">
      Real views from real registered users. Admin confirms every payment.
    </p>
    <div class="btn-group btn-group-sm" id="filterBtns">
      <button class="btn btn-primary active" onclick="filterPkg('all',this)">All</button>
      <button class="btn btn-outline-secondary" onclick="filterPkg('video',this)">
        <i class="bi bi-play-circle me-1"></i>Video
      </button>
      <button class="btn btn-outline-secondary" onclick="filterPkg('website',this)">
        <i class="bi bi-globe me-1"></i>Website
      </button>
      <button class="btn btn-outline-secondary" onclick="filterPkg('both',this)">
        <i class="bi bi-collection me-1"></i>Bundles
      </button>
    </div>
  </div>
</div>

<div class="container py-5">

  <!-- ── How it works steps ─────────────────────────────── -->
  <div class="row g-3 mb-5">
    <?php foreach ([
      ['1', 'box-seam',         'Choose Package',       'Pick views that fit your goal',           '#6366f1'],
      ['2', 'credit-card-fill', 'Pay (MoMo/Paystack)',  'Select method, send payment, add ref',    '#10b981'],
      ['3', 'shield-check',     'Admin Confirms',        'Verified within 24 hours',                '#f59e0b'],
      ['4', 'people-fill',      'Campaign Goes Live',    'Real users watch your content',           '#3b82f6'],
    ] as [$n, $icon, $title, $desc, $col]): ?>
    <div class="col-6 col-md-3 text-center">
      <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-2"
           style="width:44px;height:44px;background:<?= $col ?>18;color:<?= $col ?>">
        <i class="bi bi-<?= $icon ?> fs-5"></i>
      </div>
      <div class="badge d-block mb-1" style="background:<?= $col ?>;font-size:.68rem">
        Step <?= $n ?>
      </div>
      <div class="fw-600 small"><?= $title ?></div>
      <div class="text-muted" style="font-size:.72rem"><?= $desc ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- ── Package grid ───────────────────────────────────── -->
  <div class="row g-4" id="pkgGrid">

    <?php if (empty($pkgs)): ?>
    <div class="col-12 text-center py-5 text-muted">
      <i class="bi bi-box-seam display-3 d-block mb-3 opacity-25"></i>
      <h5>No packages available yet</h5>
      <p>Check back soon — admin is setting them up.</p>
    </div>
    <?php else: ?>

    <?php foreach ($pkgs as $p):
      $feats  = json_decode((string)($p['features'] ?? '[]'), true) ?: [];
      $color  = $p['color'] ?: '#6366f1';
      $cpv    = $p['views_count'] > 0 ? (float)$p['price'] / (int)$p['views_count'] : 0;
      $icons  = ['video'=>'play-circle-fill','website'=>'globe2','both'=>'collection'];
      $tIcon  = $icons[$p['type']] ?? 'box-seam';
      $isFeat = (bool)$p['is_featured'];
    ?>
    <div class="col-xl-3 col-md-4 col-sm-6 pkg-card-wrapper" data-type="<?= e($p['type']) ?>">
      <div class="card border-0 shadow-sm h-100 rounded-xl pkg-card position-relative"
           style="<?= $isFeat ? 'border:2px solid ' . $color . '!important' : '' ?>">

        <?php if ($p['badge']): ?>
        <div class="position-absolute top-0 start-50 translate-middle" style="z-index:2">
          <span class="badge rounded-pill fw-600 px-3 py-1"
                style="background:<?= e($color) ?>;font-size:.75rem">
            <?= e($p['badge']) ?>
          </span>
        </div>
        <?php endif; ?>

        <div class="card-body p-4 d-flex flex-column <?= $p['badge'] ? 'pt-5' : '' ?>">
          <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3"
               style="width:50px;height:50px;background:<?= e($color) ?>18;color:<?= e($color) ?>">
            <i class="bi bi-<?= $tIcon ?> fs-4"></i>
          </div>
          <h5 class="fw-bold mb-1"><?= e($p['name']) ?></h5>
          <p class="text-muted small mb-3"><?= e($p['description'] ?? '') ?></p>

          <div class="fw-bold mb-1" style="font-size:1.9rem;color:<?= e($color) ?>">
            <?= money((float)$p['price']) ?>
          </div>
          <div class="text-muted small mb-3">
            <?= number_format((int)$p['views_count']) ?> views &bull; <?= (int)$p['duration_days'] ?> days
          </div>

          <ul class="list-unstyled small mb-3 flex-grow-1">
            <?php foreach ($feats as $f): ?>
            <li class="mb-2 d-flex align-items-start gap-2">
              <i class="bi bi-check-circle-fill mt-1 flex-shrink-0"
                 style="color:<?= e($color) ?>;font-size:.7rem"></i>
              <span><?= e($f) ?></span>
            </li>
            <?php endforeach; ?>
          </ul>

          <div class="small text-muted mb-3 pt-2 border-top">
            Creator CPV: <strong><?= money($cpv, 4) ?></strong>
            &nbsp;·&nbsp;
            Earner reward: <strong><?= money((float)$p['cost_per_view'], 4) ?></strong>
          </div>

          <?php if (auth()): ?>
          <button class="btn fw-700 text-white mt-auto"
                  style="background:<?= e($color) ?>"
                  data-bs-toggle="modal"
                  data-bs-target="#orderModal"
                  data-pkg-id="<?= (int)$p['id'] ?>"
                  data-pkg-name="<?= e($p['name']) ?>"
                  data-pkg-price="<?= e(money((float)$p['price'])) ?>"
                  data-pkg-views="<?= e(number_format((int)$p['views_count'])) ?>"
                  data-pkg-color="<?= e($color) ?>">
            <i class="bi bi-cart-fill me-2"></i>Order Now
          </button>
          <?php else: ?>
          <a href="<?= url('register') ?>" class="btn fw-700 text-white mt-auto"
             style="background:<?= e($color) ?>">
            <i class="bi bi-person-plus me-2"></i>Sign Up to Order
          </a>
          <?php endif; ?>
        </div><!-- /card-body -->
      </div><!-- /card -->
    </div><!-- /col -->
    <?php endforeach; ?>
    <?php endif; ?>

  </div><!-- /pkgGrid -->
</div><!-- /container -->


<!-- ── Order Modal (logged-in users only) ──────────────────── -->
<?php if (auth()): ?>
<div class="modal fade" id="orderModal" tabindex="-1" aria-labelledby="orderModalTitle" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content border-0 shadow rounded-xl">

  <div class="modal-header border-0 pb-0">
    <div>
      <h5 class="fw-bold mb-0" id="orderModalTitle">Order Package</h5>
      <p class="text-muted small mb-0" id="mSubtitle"></p>
    </div>
    <button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
  </div>

  <form method="POST" id="orderForm" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="package_id" id="mPkgId" value="">

    <div class="modal-body pt-3">

      <!-- Package summary card -->
      <div class="rounded-xl p-3 mb-4 text-center border" id="mSummary">
        <div class="text-muted small mb-1" id="mName"></div>
        <div class="fw-bold" style="font-size:2rem" id="mPrice"></div>
        <div class="text-muted small" id="mViews"></div>
      </div>

      <!-- Payment method selection -->
      <div class="mb-3">
        <label class="form-label fw-600 small">Select Payment Method *</label>
        <?php if (empty($activeGw)): ?>
        <div class="alert alert-warning border-0 shadow-sm small">
          <i class="bi bi-exclamation-triangle me-2"></i>
          No payment methods are configured. Please contact admin.
        </div>
        <?php else: ?>
        <div class="row g-2" id="gwGrid">
          <?php foreach ($activeGw as $k => ['lbl'=>$lbl,'icon'=>$icon,'col'=>$col]): ?>
          <div class="col-6">
            <input type="radio" name="gateway" value="<?= e($k) ?>"
                   class="btn-check" id="gw_<?= e($k) ?>">
            <label class="w-100 border rounded-3 p-2 text-center small fw-600 gw-label"
                   for="gw_<?= e($k) ?>" data-gw="<?= e($k) ?>"
                   style="cursor:pointer;transition:all .15s">
              <i class="bi <?= e($icon) ?> d-block fs-4 mb-1"
                 style="color:<?= e($col) ?>"></i>
              <?= e($lbl) ?>
            </label>
          </div>
          <?php endforeach; ?>
        </div>
        <!-- Inline error for no gateway selected -->
        <div id="gwError" class="text-danger small mt-1 d-none">
          <i class="bi bi-exclamation-circle me-1"></i>Please select a payment method.
        </div>
        <?php endif; ?>
      </div>

      <!-- Payment instructions (appear after gateway selection) -->
      <div id="payInstr" class="d-none">
        <div class="alert alert-info border-0 shadow-sm small py-2 mb-3"
             id="payInstrText">
        </div>
        <div class="mb-3">
          <label class="form-label fw-600 small" for="payNote">
            Transaction Reference / Payment Proof *
          </label>
          <input type="text"
                 name="payment_note"
                 id="payNote"
                 class="form-control"
                 maxlength="300"
                 placeholder="e.g. MoMo transaction ID, bank reference, PayPal ID…"
                 autocomplete="off">
          <div class="form-text">
            Enter your transaction ID so admin can verify and confirm your payment.
          </div>
          <!-- Inline error for missing reference -->
          <div id="noteError" class="text-danger small mt-1 d-none">
            <i class="bi bi-exclamation-circle me-1"></i>Please enter your payment reference.
          </div>
        </div>
      </div>

      <!-- Info footer -->
      <div class="alert alert-secondary border-0 shadow-sm small py-2 d-flex gap-2 mb-0">
        <i class="bi bi-clock flex-shrink-0 mt-1"></i>
        <span>
          Campaign activates within <strong>24 hours</strong> of payment confirmation.
          You will need to submit your video/website URL after activation.
        </span>
      </div>

    </div><!-- /modal-body -->

    <div class="modal-footer border-0 pt-0">
      <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
        Cancel
      </button>
      <button type="submit" class="btn btn-primary fw-700 px-4" id="submitOrderBtn">
        <i class="bi bi-send-fill me-1"></i>Submit Order
      </button>
    </div>

  </form><!-- /orderForm -->
</div>
</div>
</div><!-- /modal -->
<?php endif; ?>


<!-- ── JavaScript ──────────────────────────────────────────── -->
<script>
// ── Filter packages by type ───────────────────────────────────
function filterPkg(type, btn) {
    document.querySelectorAll('.pkg-card-wrapper').forEach(el => {
        el.style.display = (type === 'all' || el.dataset.type === type) ? '' : 'none';
    });
    document.querySelectorAll('#filterBtns button').forEach(b => {
        b.className = (b === btn)
            ? 'btn btn-primary btn-sm'
            : 'btn btn-outline-secondary btn-sm';
    });
}

// ── Payment instructions (safely JSON-encoded from PHP) ───────
// All setting values were HTML-escaped in PHP then JSON-encoded.
// No single-quote / backslash injection risk in JS.
const PAY_INSTR = <?= $payInstrJs ?>;

// ── Order modal ───────────────────────────────────────────────
const orderModal = document.getElementById('orderModal');
if (orderModal) {
    orderModal.addEventListener('show.bs.modal', function(e) {
        const btn = e.relatedTarget;

        // Populate summary
        document.getElementById('mPkgId').value            = btn.dataset.pkgId;
        document.getElementById('mName').textContent        = btn.dataset.pkgName;
        document.getElementById('mPrice').textContent       = btn.dataset.pkgPrice;
        document.getElementById('mViews').textContent       = btn.dataset.pkgViews + ' views';
        document.getElementById('mSubtitle').textContent    = btn.dataset.pkgName;
        document.getElementById('mSummary').style.borderColor = btn.dataset.pkgColor;

        // Reset state
        document.querySelectorAll('.btn-check').forEach(r => r.checked = false);
        document.querySelectorAll('.gw-label').forEach(l => {
            l.style.background  = '';
            l.style.borderColor = '';
            l.style.color       = '';
        });
        document.getElementById('payInstr').classList.add('d-none');
        document.getElementById('payNote').value = '';
        document.getElementById('gwError').classList.add('d-none');
        document.getElementById('noteError').classList.add('d-none');
        document.getElementById('submitOrderBtn').disabled = false;
        document.getElementById('submitOrderBtn').innerHTML =
            '<i class="bi bi-send-fill me-1"></i>Submit Order';
    });
}

// ── Gateway label click → show instructions ───────────────────
document.querySelectorAll('.gw-label').forEach(function(lbl) {
    lbl.addEventListener('click', function() {
        // Highlight selected
        document.querySelectorAll('.gw-label').forEach(function(l) {
            l.style.background  = '';
            l.style.borderColor = '';
        });
        this.style.background  = 'rgba(99,102,241,.07)';
        this.style.borderColor = 'var(--vp-primary)';

        // Show instructions for this gateway
        var gw   = this.dataset.gw;
        var instr = PAY_INSTR[gw] || 'Make payment and enter your reference below.';
        document.getElementById('payInstrText').innerHTML =
            '<i class="bi bi-info-circle me-1"></i>' + instr;
        document.getElementById('payInstr').classList.remove('d-none');
        document.getElementById('gwError').classList.add('d-none');
    });
});

// ── Form validation & submit ──────────────────────────────────
document.getElementById('orderForm')?.addEventListener('submit', function(e) {
    var valid = true;

    // 1. Gateway must be selected
    var gw = document.querySelector('input[name="gateway"]:checked');
    if (!gw) {
        document.getElementById('gwError').classList.remove('d-none');
        document.getElementById('gwGrid').scrollIntoView({behavior:'smooth',block:'center'});
        valid = false;
    } else {
        document.getElementById('gwError').classList.add('d-none');
    }

    // 2. Payment reference must not be empty
    var note = document.getElementById('payNote').value.trim();
    if (!note) {
        document.getElementById('noteError').classList.remove('d-none');
        document.getElementById('payNote').focus();
        valid = false;
    } else {
        document.getElementById('noteError').classList.add('d-none');
    }

    if (!valid) {
        e.preventDefault();
        return;
    }

    // Disable submit button to prevent double-submit
    document.getElementById('submitOrderBtn').disabled = true;
    document.getElementById('submitOrderBtn').innerHTML =
        '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Submitting…';
});
</script>

<?php page_end(); ?>
