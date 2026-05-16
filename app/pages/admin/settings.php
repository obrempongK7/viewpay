<?php
/**
 * ViewPay Admin — Settings (v2)
 * Tabs: General | Appearance/PWA | Currency | Payments | Payouts |
 *       Ad Watching | Email | Rewards | Security | SEO/Analytics
 */
defined('VP_BOOT') || die('Direct access not permitted.');
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/partials/admin-layout.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // Logo upload
    if (!empty($_FILES['logo']['tmp_name'])) {
        $f = handle_upload('logo', ['jpg','jpeg','png','gif','webp']);
        if ($f) { $old=setting('logo'); if($old) @unlink(__DIR__.'/../../uploads/'.$old); setting_set('logo',$f,'appearance'); }
        else { flash('error','Logo upload failed. Use PNG/JPG under 5 MB.'); redirect('/admin/settings?tab='.($_POST['_tab']??'general')); }
    }
    if (!empty($_FILES['favicon']['tmp_name'])) {
        $f = handle_upload('favicon',['ico','png']); if($f){ $old=setting('favicon'); if($old) @unlink(__DIR__.'/../../uploads/'.$old); setting_set('favicon',$f,'appearance'); }
    }
    if (!empty($_FILES['pwa_icon']['tmp_name'])) {
        $f = handle_upload('pwa_icon',['png']); if($f){ setting_set('pwa_icon',$f,'pwa'); }
    }
    if (!empty($_FILES['splash_logo']['tmp_name'])) {
        $f = handle_upload('splash_logo',['png','jpg','jpeg']); if($f){ setting_set('splash_logo',$f,'pwa'); }
    }

    // All text/select fields
    $textFields = [
        // General
        'site_name','site_description','site_email','support_email','support_whatsapp','timezone',
        // Appearance
        'primary_color',
        // PWA
        'pwa_app_name','pwa_short_name','pwa_splash_bg','pwa_splash_text','pwa_theme_color','pwa_bg_color',
        // Currency
        'currency_code','currency_symbol','currency_name','usd_exchange_rate','currency_position',
        // Payments
        'momo_number','momo_name','momo_provider',
        'paystack_public_key','paystack_secret_key','paystack_payment_link',
        'bank_name','bank_account','bank_holder','bank_branch',
        'paypal_email','stripe_public_key','stripe_secret_key',
        'crypto_address','crypto_network',
        // Ad watching
        'ad_network','ad_watch_code','ad_morning_limit','ad_afternoon_limit','ad_night_limit',
        'ad_morning_reward','ad_afternoon_reward','ad_night_reward','ad_min_watch_seconds',
        // Email
        'mail_host','mail_port','mail_username','mail_password','mail_encryption','mail_from','mail_from_name',
        // Rewards
        'reward_per_video_view','reward_per_website_visit','min_watch_seconds',
        'min_withdrawal','max_withdrawal','withdrawal_fee_percent','deposit_fee_percent','referral_commission_percent',
        // Security
        'max_accounts_per_ip','max_accounts_per_device','max_views_per_day',
        // SEO
        'meta_title','meta_description','google_analytics_id','robots_meta',
        'adsense_client','adsense_slot',
    ];

    $boolFields = [
        'momo_enabled','paystack_enabled','bank_transfer_enabled','paypal_enabled','stripe_enabled','other_enabled',
        'payout_paypal_enabled','payout_stripe_enabled','payout_skrill_enabled','payout_cashapp_enabled','payout_zelle_enabled','payout_bank_enabled',
        'google_login_enabled','facebook_login_enabled',
        'adsense_enabled',
        'ad_watching_enabled',
        'email_verification_required',
        'pwa_splash_enabled',
    ];

    foreach ($textFields as $fk) { setting_set($fk, trim($_POST[$fk] ?? '')); }
    foreach ($boolFields as $fk) { setting_set($fk, isset($_POST[$fk]) ? '1' : '0'); }

    audit('settings_updated', 'Admin updated settings tab: '.($_POST['_tab']??'general'));
    flash('success', 'Settings saved successfully.');
    redirect('/admin/settings?tab='.($_POST['_tab']??'general'));
}

// Load all settings
$all = db()->query("SELECT `key`,`value` FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
function sv(string $k, string $d=''): string { global $all; return htmlspecialchars($all[$k]??$d,ENT_QUOTES,'UTF-8'); }
function sb(string $k, string $d='0'): bool  { global $all; return ($all[$k]??$d)==='1'; }

$tab = $_GET['tab'] ?? 'general';
$tabs = [
    'general'    => ['General',         'gear-fill'],
    'appearance' => ['Appearance & PWA','palette-fill'],
    'currency'   => ['Currency',        'currency-exchange'],
    'payments'   => ['Payments',        'credit-card-fill'],
    'payouts'    => ['Payouts',         'send-fill'],
    'ads-watch'  => ['Ad Watching',     'tv-fill'],
    'email'      => ['Email / SMTP',    'envelope-fill'],
    'rewards'    => ['Rewards',         'currency-dollar'],
    'security'   => ['Security',        'shield-fill-check'],
    'seo'        => ['SEO & Analytics', 'graph-up-arrow'],
];

admin_page_start('Settings');
?>

<div class="mb-4"><h4 class="fw-bold mb-0">Site Settings</h4><p class="text-muted small">Configure every aspect of your ViewPay platform.</p></div>

<!-- Tab nav -->
<div class="d-flex flex-wrap gap-1 mb-4" id="settingsTabs">
<?php foreach($tabs as $tid=>[$tlabel,$ticon]): ?>
<button type="button" class="btn btn-sm <?= $tab===$tid?'btn-primary':'btn-outline-secondary' ?>" onclick="switchTab('<?= $tid ?>')">
  <i class="bi bi-<?= $ticon ?> me-1"></i><?= $tlabel ?>
</button>
<?php endforeach; ?>
</div>

<form method="POST" enctype="multipart/form-data" id="settingsForm">
<?= csrf_field() ?>
<input type="hidden" name="_tab" id="_tabInput" value="<?= e($tab) ?>">

<!-- GENERAL -->
<div class="setting-tab" id="tab-general" <?= $tab!=='general'?'style="display:none"':'' ?>>
<div class="card border-0 shadow-sm rounded-xl p-4">
  <div class="ctit mb-3 fw-600 small"><i class="bi bi-gear-fill text-primary me-2"></i>General</div>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label small fw-600">Site Name *</label><input type="text" name="site_name" class="form-control" value="<?= sv('site_name') ?>" required></div>
    <div class="col-md-6"><label class="form-label small fw-600">Support Email</label><input type="email" name="site_email" class="form-control" value="<?= sv('site_email') ?>"></div>
    <div class="col-md-6"><label class="form-label small fw-600">Public Support Email</label><input type="email" name="support_email" class="form-control" value="<?= sv('support_email') ?>"></div>
    <div class="col-md-6"><label class="form-label small fw-600">WhatsApp Support</label><input type="text" name="support_whatsapp" class="form-control" value="<?= sv('support_whatsapp') ?>" placeholder="+233xxxxxxxxx"></div>
    <div class="col-md-6"><label class="form-label small fw-600">Timezone</label>
      <select name="timezone" class="form-select">
        <?php foreach(['UTC','Africa/Accra','Africa/Lagos','Africa/Nairobi','Africa/Cairo','Europe/London','America/New_York','America/Los_Angeles','Asia/Kolkata','Asia/Singapore'] as $tz): ?>
        <option value="<?= $tz ?>" <?= sv('timezone','UTC')===$tz?'selected':'' ?>><?= $tz ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12"><label class="form-label small fw-600">Site Description</label><textarea name="site_description" class="form-control" rows="2"><?= sv('site_description') ?></textarea></div>
  </div>
</div>
</div>

<!-- APPEARANCE & PWA -->
<div class="setting-tab" id="tab-appearance" <?= $tab!=='appearance'?'style="display:none"':'' ?>>
<div class="row g-3">
  <!-- Logo & Favicon -->
  <div class="col-md-6">
    <div class="card border-0 shadow-sm rounded-xl p-4 h-100">
      <div class="fw-600 small mb-3"><i class="bi bi-image text-primary me-2"></i>Logo &amp; Favicon</div>
      <?php if(sv('logo')): ?><div class="mb-3"><img src="<?= url('uploads/'.sv('logo')) ?>" style="height:40px;object-fit:contain" alt="Logo"> <span class="text-muted small ms-2">Current logo</span></div><?php endif; ?>
      <div class="mb-3"><label class="form-label small fw-600">Site Logo</label><input type="file" name="logo" class="form-control" accept="image/*"><div class="form-text">PNG with transparent background · 5 MB max</div></div>
      <?php if(sv('favicon')): ?><div class="mb-2"><img src="<?= url('uploads/'.sv('favicon')) ?>" style="width:32px;height:32px;object-fit:contain" alt="Favicon"> <span class="text-muted small ms-2">Current favicon</span></div><?php endif; ?>
      <div class="mb-3"><label class="form-label small fw-600">Favicon</label><input type="file" name="favicon" class="form-control" accept=".ico,.png"></div>
      <div><label class="form-label small fw-600">Brand Colour</label>
        <div class="input-group"><input type="color" name="primary_color" id="colorPicker" class="form-control form-control-color" style="width:52px" value="<?= sv('primary_color','#6366f1') ?>" oninput="syncColor(this.value)">
        <input type="text" id="colorHex" class="form-control font-monospace" value="<?= sv('primary_color','#6366f1') ?>" pattern="#[0-9a-fA-F]{6}" oninput="if(/^#[0-9a-fA-F]{6}$/.test(this.value)){document.getElementById('colorPicker').value=this.value}"></div>
      </div>
    </div>
  </div>
  <!-- PWA / Splash -->
  <div class="col-md-6">
    <div class="card border-0 shadow-sm rounded-xl p-4 h-100">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="fw-600 small"><i class="bi bi-phone-fill text-primary me-2"></i>PWA &amp; Splash Screen</div>
        <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="pwa_splash_enabled" <?= sb('pwa_splash_enabled','1')?'checked':'' ?>><label class="form-check-label small">Enable Splash</label></div>
      </div>
      <div class="row g-2">
        <div class="col-6"><label class="form-label small fw-600">App Name</label><input type="text" name="pwa_app_name" class="form-control" value="<?= sv('pwa_app_name',sv('site_name')) ?>"></div>
        <div class="col-6"><label class="form-label small fw-600">Short Name</label><input type="text" name="pwa_short_name" class="form-control" value="<?= sv('pwa_short_name','ViewPay') ?>" maxlength="12"></div>
        <div class="col-6"><label class="form-label small fw-600">Splash Background</label><input type="color" name="pwa_splash_bg" class="form-control form-control-color w-100" value="<?= sv('pwa_splash_bg','#6366f1') ?>"></div>
        <div class="col-6"><label class="form-label small fw-600">Theme Colour</label><input type="color" name="pwa_theme_color" class="form-control form-control-color w-100" value="<?= sv('pwa_theme_color','#6366f1') ?>"></div>
        <div class="col-12"><label class="form-label small fw-600">Splash Text</label><input type="text" name="pwa_splash_text" class="form-control" value="<?= sv('pwa_splash_text',sv('site_name')) ?>"></div>
        <div class="col-12"><label class="form-label small fw-600">PWA Icon (512×512 PNG)</label><input type="file" name="pwa_icon" class="form-control" accept=".png"></div>
        <div class="col-12"><label class="form-label small fw-600">Splash Logo</label><input type="file" name="splash_logo" class="form-control" accept="image/*"></div>
      </div>
    </div>
  </div>
</div>
</div>

<!-- CURRENCY -->
<div class="setting-tab" id="tab-currency" <?= $tab!=='currency'?'style="display:none"':'' ?>>
<div class="card border-0 shadow-sm rounded-xl p-4">
  <div class="fw-600 small mb-3"><i class="bi bi-currency-exchange text-success me-2"></i>Currency &amp; Exchange Rate</div>
  <div class="alert alert-info small py-2 border-0 shadow-sm mb-3"><i class="bi bi-info-circle me-1"></i>All balances stored in <strong>USD</strong>. Rate is for display only. <a href="https://www.xe.com/currencyconverter/convert/?From=USD&To=GHS" target="_blank">Check live rate ↗</a></div>
  <div class="row g-3">
    <div class="col-md-3"><label class="form-label small fw-600">Currency Code</label>
      <select name="currency_code" class="form-select" onchange="pickCurrency(this)">
        <?php foreach(['GHS'=>['₵','Ghanaian Cedi'],'USD'=>['$','US Dollar'],'EUR'=>['€','Euro'],'GBP'=>['£','British Pound'],'NGN'=>['₦','Nigerian Naira'],'KES'=>['KSh','Kenyan Shilling'],'ZAR'=>['R','South African Rand'],'INR'=>['₹','Indian Rupee'],'PHP'=>['₱','Philippine Peso'],'CAD'=>['CA$','Canadian Dollar'],'AUD'=>['A$','Australian Dollar']] as $cc=>[$cs,$cn]): ?>
        <option value="<?= $cc ?>" data-sym="<?= e($cs) ?>" data-name="<?= e($cn) ?>" <?= sv('currency_code','GHS')===$cc?'selected':'' ?>><?= $cc ?> — <?= $cn ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2"><label class="form-label small fw-600">Symbol</label><input type="text" name="currency_symbol" id="currSym" class="form-control" value="<?= sv('currency_symbol','₵') ?>" maxlength="5" oninput="updateRatePrev()"></div>
    <div class="col-md-3"><label class="form-label small fw-600">Currency Name</label><input type="text" name="currency_name" id="currName" class="form-control" value="<?= sv('currency_name','Ghanaian Cedi') ?>"></div>
    <div class="col-md-2"><label class="form-label small fw-600">Position</label><select name="currency_position" class="form-select" onchange="updateRatePrev()"><option value="before" <?= sv('currency_position','before')==='before'?'selected':'' ?>>Before (₵10)</option><option value="after" <?= sv('currency_position','before')==='after'?'selected':'' ?>>After (10₵)</option></select></div>
    <div class="col-md-4"><label class="form-label small fw-600">Exchange Rate <small class="text-muted">(1 USD = ? <span id="rateCode"><?= sv('currency_code','GHS') ?></span>)</small></label>
      <input type="number" name="usd_exchange_rate" id="exchRate" class="form-control fw-bold" step="0.0001" min="0.0001" value="<?= sv('usd_exchange_rate','14.5') ?>" oninput="updateRatePrev()">
    </div>
    <div class="col-md-4"><label class="form-label small fw-600">Preview</label>
      <div class="border rounded-3 p-3 bg-body-secondary small"><div class="text-muted mb-1">$0.005 USD →</div><div class="fs-4 fw-bold text-success" id="prevReward">—</div></div>
    </div>
  </div>
</div>
</div>

<!-- PAYMENTS -->
<div class="setting-tab" id="tab-payments" <?= $tab!=='payments'?'style="display:none"':'' ?>>
<div class="alert alert-info small py-2 border-0 shadow-sm mb-3 d-flex gap-2"><i class="bi bi-info-circle flex-shrink-0 mt-1"></i><span>Admin confirms all payments manually. Enter account details so users know where to send money.</span></div>
<div class="row g-3">
  <!-- MoMo -->
  <div class="col-md-6"><div class="card border-0 shadow-sm rounded-xl p-4 h-100">
    <div class="d-flex justify-content-between align-items-center mb-3"><span class="fw-600 small"><i class="bi bi-phone-fill text-success me-2"></i>Mobile Money (MoMo)</span><div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="momo_enabled" <?= sb('momo_enabled','1')?'checked':'' ?>></div></div>
    <div class="row g-2">
      <div class="col-12"><label class="form-label small fw-600">Provider</label><select name="momo_provider" class="form-select form-select-sm"><?php foreach(['MTN MoMo','Vodafone Cash','AirtelTigo Money','Other'] as $p): ?><option value="<?= $p ?>" <?= sv('momo_provider','MTN MoMo')===$p?'selected':'' ?>><?= $p ?></option><?php endforeach; ?></select></div>
      <div class="col-6"><label class="form-label small fw-600">Number</label><input type="text" name="momo_number" class="form-control form-control-sm" value="<?= sv('momo_number') ?>" placeholder="+233 XX XXX XXXX"></div>
      <div class="col-6"><label class="form-label small fw-600">Account Name</label><input type="text" name="momo_name" class="form-control form-control-sm" value="<?= sv('momo_name') ?>"></div>
    </div>
  </div></div>
  <!-- Paystack -->
  <div class="col-md-6"><div class="card border-0 shadow-sm rounded-xl p-4 h-100" style="border-left:3px solid #00C3F7!important">
    <div class="d-flex justify-content-between align-items-center mb-3"><span class="fw-600 small"><i class="bi bi-credit-card-fill me-2" style="color:#00C3F7"></i>Paystack</span><div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="paystack_enabled" <?= sb('paystack_enabled','1')?'checked':'' ?>></div></div>
    <div class="alert alert-secondary small py-1 mb-2">Get keys at <a href="https://dashboard.paystack.com/#/settings/developers" target="_blank" rel="noopener">dashboard.paystack.com ↗</a></div>
    <div class="mb-2"><label class="form-label small fw-600">Public Key</label><input type="text" name="paystack_public_key" class="form-control form-control-sm font-monospace" value="<?= sv('paystack_public_key') ?>" placeholder="pk_live_…"></div>
    <div class="mb-2"><label class="form-label small fw-600">Secret Key</label><input type="password" name="paystack_secret_key" class="form-control form-control-sm font-monospace" value="<?= sv('paystack_secret_key') ?>" placeholder="sk_live_…" autocomplete="new-password"></div>
    <div><label class="form-label small fw-600">Payment Link (optional)</label><input type="url" name="paystack_payment_link" class="form-control form-control-sm" value="<?= sv('paystack_payment_link') ?>" placeholder="https://paystack.com/pay/…"></div>
  </div></div>
  <!-- Bank -->
  <div class="col-md-6"><div class="card border-0 shadow-sm rounded-xl p-4">
    <div class="d-flex justify-content-between align-items-center mb-3"><span class="fw-600 small"><i class="bi bi-bank text-primary me-2"></i>Bank Transfer</span><div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="bank_transfer_enabled" <?= sb('bank_transfer_enabled','1')?'checked':'' ?>></div></div>
    <div class="row g-2">
      <div class="col-6"><label class="form-label small fw-600">Bank Name</label><input type="text" name="bank_name" class="form-control form-control-sm" value="<?= sv('bank_name') ?>"></div>
      <div class="col-6"><label class="form-label small fw-600">Account No.</label><input type="text" name="bank_account" class="form-control form-control-sm" value="<?= sv('bank_account') ?>"></div>
      <div class="col-6"><label class="form-label small fw-600">Account Name</label><input type="text" name="bank_holder" class="form-control form-control-sm" value="<?= sv('bank_holder') ?>"></div>
      <div class="col-6"><label class="form-label small fw-600">Branch</label><input type="text" name="bank_branch" class="form-control form-control-sm" value="<?= sv('bank_branch') ?>"></div>
    </div>
  </div></div>
  <!-- Other gateways -->
  <div class="col-md-3"><div class="card border-0 shadow-sm rounded-xl p-3">
    <div class="d-flex justify-content-between align-items-center mb-2"><span class="fw-600 small"><i class="bi bi-paypal text-primary me-1"></i>PayPal</span><div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="paypal_enabled" <?= sb('paypal_enabled')?'checked':'' ?>></div></div>
    <input type="email" name="paypal_email" class="form-control form-control-sm" value="<?= sv('paypal_email') ?>" placeholder="you@paypal.com">
  </div></div>
  <div class="col-md-3"><div class="card border-0 shadow-sm rounded-xl p-3">
    <div class="d-flex justify-content-between align-items-center mb-2"><span class="fw-600 small"><i class="bi bi-credit-card me-1" style="color:#635bff"></i>Stripe</span><div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="stripe_enabled" <?= sb('stripe_enabled')?'checked':'' ?>></div></div>
    <input type="text" name="stripe_public_key" class="form-control form-control-sm font-monospace mb-1" value="<?= sv('stripe_public_key') ?>" placeholder="pk_live_…">
    <input type="password" name="stripe_secret_key" class="form-control form-control-sm font-monospace" value="<?= sv('stripe_secret_key') ?>" placeholder="sk_live_…" autocomplete="new-password">
  </div></div>
  <div class="col-md-6"><div class="card border-0 shadow-sm rounded-xl p-3">
    <div class="d-flex justify-content-between align-items-center mb-2"><span class="fw-600 small"><i class="bi bi-currency-bitcoin text-warning me-1"></i>Crypto / Other</span><div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="other_enabled" <?= sb('other_enabled')?'checked':'' ?>></div></div>
    <div class="row g-2">
      <div class="col-6"><input type="text" name="crypto_network" class="form-control form-control-sm" value="<?= sv('crypto_network') ?>" placeholder="e.g. USDT TRC20"></div>
      <div class="col-6"><input type="text" name="crypto_address" class="form-control form-control-sm" value="<?= sv('crypto_address') ?>" placeholder="Wallet address"></div>
    </div>
  </div></div>
</div>
</div>

<!-- PAYOUTS -->
<div class="setting-tab" id="tab-payouts" <?= $tab!=='payouts'?'style="display:none"':'' ?>>
<div class="card border-0 shadow-sm rounded-xl p-4">
  <div class="fw-600 small mb-3"><i class="bi bi-send-fill text-success me-2"></i>Payout Methods (User Withdrawals)</div>
  <div class="row g-2">
    <?php foreach(['payout_paypal_enabled'=>['PayPal','bi-paypal'],'payout_stripe_enabled'=>['Stripe','bi-credit-card'],'payout_skrill_enabled'=>['Skrill','bi-wallet'],'payout_cashapp_enabled'=>['CashApp','bi-cash-coin'],'payout_zelle_enabled'=>['Zelle','bi-phone'],'payout_bank_enabled'=>['Bank Transfer','bi-bank']] as $k=>[$l,$i]): ?>
    <div class="col-md-4"><div class="d-flex align-items-center justify-content-between border rounded-3 p-3 <?= sb($k,'1')?'border-success bg-success bg-opacity-10':'' ?>">
      <span class="small fw-600"><i class="bi <?= $i ?> me-2 text-muted"></i><?= $l ?></span>
      <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="<?= $k ?>" <?= sb($k,'1')?'checked':'' ?>></div>
    </div></div>
    <?php endforeach; ?>
  </div>
</div>
</div>

<!-- AD WATCHING -->
<div class="setting-tab" id="tab-ads-watch" <?= $tab!=='ads-watch'?'style="display:none"':'' ?>>
<div class="card border-0 shadow-sm rounded-xl p-4 mb-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="fw-600 small"><i class="bi bi-tv-fill text-primary me-2"></i>Ad Watching System</div>
    <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="ad_watching_enabled" <?= sb('ad_watching_enabled','1')?'checked':'' ?>><label class="form-check-label small fw-600">Enable Ad Watching</label></div>
  </div>
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label small fw-600">Ad Network</label>
      <select name="ad_network" class="form-select">
        <?php foreach(['custom'=>'Google AdSense','inhouse'=>'In-House HTML Ads','admob'=>'Google AdMob','unity'=>'Unity Ads','ironsrc'=>'IronSource'] as $k=>$l): ?>
        <option value="<?= $k ?>" <?= sv('ad_network','custom')===$k?'selected':'' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4"><label class="form-label small fw-600">Minimum Watch Time (seconds)</label><input type="number" name="ad_min_watch_seconds" class="form-control" value="<?= sv('ad_min_watch_seconds','10') ?>" min="5" max="60"></div>
  </div>
  <div class="mt-3"><label class="form-label small fw-600">In-House Ad HTML / Ad Code</label><textarea name="ad_watch_code" class="form-control font-monospace" rows="5" placeholder="Paste your ad HTML, AdSense code, or custom ad unit here…"><?= e($all['ad_watch_code']??'') ?></textarea><div class="form-text">This HTML is displayed in the ad player on the Watch Ads page.</div></div>
</div>
<div class="card border-0 shadow-sm rounded-xl p-4">
  <div class="fw-600 small mb-3"><i class="bi bi-clock-fill text-warning me-2"></i>Daily Time Slots &amp; Rewards</div>
  <div class="row g-3">
    <?php foreach([
      ['morning',   'Morning',   '6:00 AM – 12:00 PM', 'sunrise-fill',   '#f59e0b', '10', '0.002'],
      ['afternoon', 'Afternoon', '12:00 PM – 6:00 PM', 'sun-fill',       '#10b981', '10', '0.002'],
      ['night',     'Night',     '6:00 PM – 3:00 AM',  'moon-stars-fill','#6366f1', '15', '0.003'],
    ] as [$pk,$pn,$pt,$pi,$pc,$dl,$dr]): ?>
    <div class="col-md-4"><div class="border rounded-xl p-3" style="border-color:<?= $pc ?>40!important">
      <div class="fw-700 small mb-2"><i class="bi bi-<?= $pi ?> me-1" style="color:<?= $pc ?>"></i><?= $pn ?><small class="text-muted fw-400 ms-2"><?= $pt ?></small></div>
      <div class="row g-2">
        <div class="col-6"><label class="form-label" style="font-size:.7rem;font-weight:600">Daily Limit</label><input type="number" name="ad_<?= $pk ?>_limit" class="form-control form-control-sm" value="<?= sv("ad_{$pk}_limit",$dl) ?>" min="1" max="100"></div>
        <div class="col-6"><label class="form-label" style="font-size:.7rem;font-weight:600">Reward (USD)</label><div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="number" name="ad_<?= $pk ?>_reward" class="form-control font-monospace" value="<?= sv("ad_{$pk}_reward",$dr) ?>" step="0.0001" min="0"></div></div>
      </div>
    </div></div>
    <?php endforeach; ?>
  </div>
  <div class="alert alert-secondary small py-2 mt-3 border-0"><i class="bi bi-info-circle me-1"></i>Total daily ads = morning + afternoon + night limits. Users earn the reward in USD (converted to site currency for display).</div>
</div>
</div>

<!-- EMAIL -->
<div class="setting-tab" id="tab-email" <?= $tab!=='email'?'style="display:none"':'' ?>>
<div class="card border-0 shadow-sm rounded-xl p-4 mb-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="fw-600 small"><i class="bi bi-envelope-fill text-primary me-2"></i>Email Verification</div>
    <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="email_verification_required" <?= sb('email_verification_required')?'checked':'' ?>><label class="form-check-label small fw-600">Require Email Verification</label></div>
  </div>
  <div class="alert alert-warning small py-2 border-0">When enabled, users must verify their email before they can earn or withdraw.</div>
</div>
<div class="card border-0 shadow-sm rounded-xl p-4">
  <div class="fw-600 small mb-3"><i class="bi bi-server text-primary me-2"></i>SMTP Configuration</div>
  <div class="row g-3">
    <div class="col-md-5"><label class="form-label small fw-600">SMTP Host</label><input type="text" name="mail_host" class="form-control" value="<?= sv('mail_host') ?>" placeholder="mail.yourdomain.com"></div>
    <div class="col-md-2"><label class="form-label small fw-600">Port</label><input type="number" name="mail_port" class="form-control" value="<?= sv('mail_port','587') ?>"></div>
    <div class="col-md-3"><label class="form-label small fw-600">Encryption</label><select name="mail_encryption" class="form-select"><option value="tls" <?= sv('mail_encryption','tls')==='tls'?'selected':'' ?>>TLS</option><option value="ssl" <?= sv('mail_encryption','tls')==='ssl'?'selected':'' ?>>SSL</option><option value="" <?= sv('mail_encryption','tls')===''?'selected':'' ?>>None</option></select></div>
    <div class="col-md-5"><label class="form-label small fw-600">Username</label><input type="text" name="mail_username" class="form-control" value="<?= sv('mail_username') ?>" placeholder="noreply@yourdomain.com"></div>
    <div class="col-md-5"><label class="form-label small fw-600">Password</label><input type="password" name="mail_password" class="form-control" value="<?= sv('mail_password') ?>" autocomplete="new-password"></div>
    <div class="col-md-5"><label class="form-label small fw-600">From Email</label><input type="email" name="mail_from" class="form-control" value="<?= sv('mail_from') ?>" placeholder="noreply@yourdomain.com"></div>
    <div class="col-md-5"><label class="form-label small fw-600">From Name</label><input type="text" name="mail_from_name" class="form-control" value="<?= sv('mail_from_name',sv('site_name')) ?>"></div>
  </div>
</div>
</div>

<!-- REWARDS -->
<div class="setting-tab" id="tab-rewards" <?= $tab!=='rewards'?'style="display:none"':'' ?>>
<div class="card border-0 shadow-sm rounded-xl p-4">
  <div class="fw-600 small mb-3"><i class="bi bi-currency-dollar text-success me-2"></i>Task Rewards, Fees &amp; Limits</div>
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label small fw-600">Video Reward (USD)</label><div class="input-group"><span class="input-group-text">$</span><input type="number" name="reward_per_video_view" class="form-control" step="0.000001" value="<?= sv('reward_per_video_view','0.005') ?>"></div></div>
    <div class="col-md-4"><label class="form-label small fw-600">Website Reward (USD)</label><div class="input-group"><span class="input-group-text">$</span><input type="number" name="reward_per_website_visit" class="form-control" step="0.000001" value="<?= sv('reward_per_website_visit','0.003') ?>"></div></div>
    <div class="col-md-4"><label class="form-label small fw-600">Min Watch (seconds)</label><input type="number" name="min_watch_seconds" class="form-control" min="5" value="<?= sv('min_watch_seconds','30') ?>"></div>
    <div class="col-md-3"><label class="form-label small fw-600">Min Withdrawal ($)</label><div class="input-group"><span class="input-group-text">$</span><input type="number" name="min_withdrawal" class="form-control" step="0.01" value="<?= sv('min_withdrawal','5.00') ?>"></div></div>
    <div class="col-md-3"><label class="form-label small fw-600">Max Withdrawal ($)</label><div class="input-group"><span class="input-group-text">$</span><input type="number" name="max_withdrawal" class="form-control" step="0.01" value="<?= sv('max_withdrawal','500.00') ?>"></div></div>
    <div class="col-md-3"><label class="form-label small fw-600">Withdrawal Fee (%)</label><div class="input-group"><input type="number" name="withdrawal_fee_percent" class="form-control" step="0.1" value="<?= sv('withdrawal_fee_percent','3') ?>"><span class="input-group-text">%</span></div></div>
    <div class="col-md-3"><label class="form-label small fw-600">Referral Commission (%)</label><div class="input-group"><input type="number" name="referral_commission_percent" class="form-control" step="0.1" value="<?= sv('referral_commission_percent','5') ?>"><span class="input-group-text">%</span></div></div>
  </div>
</div>
</div>

<!-- SECURITY -->
<div class="setting-tab" id="tab-security" <?= $tab!=='security'?'style="display:none"':'' ?>>
<div class="card border-0 shadow-sm rounded-xl p-4">
  <div class="fw-600 small mb-3"><i class="bi bi-shield-fill-check text-warning me-2"></i>Fraud Prevention &amp; IP/Device Limits</div>
  <div class="alert alert-warning small py-2 border-0 mb-3">These limits prevent multi-accounting. Set to 1 for strictest security.</div>
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label small fw-600">Max Accounts per IP</label><input type="number" name="max_accounts_per_ip" class="form-control" min="1" value="<?= sv('max_accounts_per_ip','1') ?>"><div class="form-text">Recommended: 1–3</div></div>
    <div class="col-md-4"><label class="form-label small fw-600">Max Accounts per Device</label><input type="number" name="max_accounts_per_device" class="form-control" min="1" value="<?= sv('max_accounts_per_device','1') ?>"><div class="form-text">Based on browser fingerprint</div></div>
    <div class="col-md-4"><label class="form-label small fw-600">Max Tasks per Day</label><input type="number" name="max_views_per_day" class="form-control" min="1" value="<?= sv('max_views_per_day','50') ?>"></div>
  </div>
</div>
</div>

<!-- SEO -->
<div class="setting-tab" id="tab-seo" <?= $tab!=='seo'?'style="display:none"':'' ?>>
<div class="row g-3">
  <div class="col-md-6"><div class="card border-0 shadow-sm rounded-xl p-4">
    <div class="fw-600 small mb-3"><i class="bi bi-graph-up-arrow text-primary me-2"></i>SEO Meta Tags</div>
    <div class="mb-3"><label class="form-label small fw-600">Default Meta Title</label><input type="text" name="meta_title" class="form-control" value="<?= sv('meta_title') ?>" maxlength="70"><div class="form-text">Max 60–70 characters</div></div>
    <div class="mb-3"><label class="form-label small fw-600">Default Meta Description</label><textarea name="meta_description" class="form-control" rows="3" maxlength="160"><?= sv('meta_description') ?></textarea><div class="form-text">Max 155–160 characters</div></div>
    <div><label class="form-label small fw-600">Robots Meta</label><select name="robots_meta" class="form-select"><option value="index,follow" <?= sv('robots_meta','index,follow')==='index,follow'?'selected':'' ?>>index, follow (recommended)</option><option value="noindex,nofollow" <?= sv('robots_meta','index,follow')==='noindex,nofollow'?'selected':'' ?>>noindex, nofollow</option></select></div>
  </div></div>
  <div class="col-md-6"><div class="card border-0 shadow-sm rounded-xl p-4">
    <div class="fw-600 small mb-3"><i class="bi bi-bar-chart-fill text-info me-2"></i>Analytics &amp; Ads</div>
    <div class="mb-3"><label class="form-label small fw-600">Google Analytics ID</label><input type="text" name="google_analytics_id" class="form-control" value="<?= sv('google_analytics_id') ?>" placeholder="G-XXXXXXXXXX"></div>
    <div class="mb-3">
      <div class="d-flex justify-content-between align-items-center mb-2"><label class="form-label small fw-600 mb-0">Google AdSense</label><div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="adsense_enabled" <?= sb('adsense_enabled')?'checked':'' ?>></div></div>
      <input type="text" name="adsense_client" class="form-control mb-2" value="<?= sv('adsense_client') ?>" placeholder="ca-pub-XXXXXXXXXXXXXXXX">
      <input type="text" name="adsense_slot" class="form-control" value="<?= sv('adsense_slot') ?>" placeholder="Ad Slot ID">
    </div>
  </div></div>
</div>
</div>

<!-- Save bar -->
<div class="d-flex justify-content-end gap-2 pt-4 mt-3 border-top">
  <button type="button" class="btn btn-outline-secondary" onclick="location.reload()">↺ Discard</button>
  <button type="submit" class="btn btn-primary px-5 fw-600"><i class="bi bi-floppy2-fill me-2"></i>Save Settings</button>
</div>
</form>

<script>
function switchTab(id) {
    document.querySelectorAll('.setting-tab').forEach(t=>t.style.display='none');
    const el=document.getElementById('tab-'+id); if(el) el.style.display='';
    document.getElementById('_tabInput').value=id;
    history.replaceState(null,'','?tab='+id);
    document.querySelectorAll('#settingsTabs button').forEach(btn=>{
        const tid=btn.getAttribute('onclick')?.match(/'([^']+)'/)?.[1];
        btn.className=tid===id?'btn btn-sm btn-primary':'btn btn-sm btn-outline-secondary';
    });
}
function syncColor(c){ document.getElementById('colorHex').value=c; }
function pickCurrency(sel){ const o=sel.selectedOptions[0]; document.getElementById('currSym').value=o.dataset.sym; document.getElementById('currName').value=o.dataset.name; document.getElementById('rateCode').textContent=sel.value; updateRatePrev(); }
function updateRatePrev(){ const r=parseFloat(document.getElementById('exchRate')?.value)||1; const s=document.getElementById('currSym')?.value||'₵'; const el=document.getElementById('prevReward'); if(el) el.textContent=s+(0.005*r).toFixed(4); }
updateRatePrev();
</script>
<?php admin_page_end(); ?>
