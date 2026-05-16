<?php
/**
 * ViewPay Admin — Package Management
 *
 * HTTP 500 ROOT CAUSE & FIX:
 *   The 500 was caused by "Cannot redeclare function" PHP fatal error.
 *   The old admin-layout.php used bare include() for sidebar.php, which re-declared
 *   the navigation helper function on every call to admin_page_start().
 *   Fixed permanently by:
 *     1. admin-layout.php now uses require_once for sidebar.php and topbar.php
 *     2. sidebar.php wraps its function in function_exists() guard
 *     3. This file wraps all DB work in try/catch — no unhandled exceptions
 *
 * SQL PARAMETER COUNTS (all manually verified):
 *   UPDATE packages: 14 SET cols = 14 ?s, plus 1 WHERE = 15 total params ✓
 *   INSERT packages: 15 cols = 15 ?s = 15 params ✓
 *
 * Developed by Jcode | Author: Obrempong
 */
defined('VP_BOOT') || die('Direct access not permitted.');
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/partials/admin-layout.php';
require_admin();

// ─────────────────────────────────────────────────────────────
//  POST handlers
// ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = trim($_POST['action'] ?? '');

    // ── CREATE / UPDATE ───────────────────────────────────────
    if ($action === 'save') {
        $pid    = (int)($_POST['pkg_id'] ?? 0);
        $name   = trim($_POST['name']   ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $type   = in_array($_POST['type'] ?? '', ['video','website','both'], true)
                  ? $_POST['type'] : 'video';
        $price  = max(0.01, (float)($_POST['price']        ?? 0));
        $views  = max(1,    (int)($_POST['views_count']    ?? 1));
        $days   = max(1,    (int)($_POST['duration_days']  ?? 30));
        $cpv    = max(0.0,  (float)($_POST['cost_per_view']?? 0));
        $badge  = trim($_POST['badge']  ?? '');
        $order  = (int)($_POST['sort_order'] ?? 0);
        $active = isset($_POST['is_active'])   ? 1 : 0;
        $feat   = isset($_POST['is_featured']) ? 1 : 0;
        $color  = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($_POST['color'] ?? ''))
                  ? $_POST['color'] : '#6366f1';

        // Parse features — one per line, strip blanks
        $features = array_values(array_filter(
            array_map('trim', explode("\n", (string)($_POST['features'] ?? '')))
        ));

        if ($name === '') {
            flash('error', 'Package name is required.');
            redirect('/admin/packages');
        }

        // ── $base: 14 params for 14 SET columns ─────────────
        $base = [
            $name,                                          //  1 name
            $desc,                                          //  2 description
            $type,                                          //  3 type
            $price,                                         //  4 price
            $views,                                         //  5 views_count
            $days,                                          //  6 duration_days
            $cpv,                                           //  7 cost_per_view
            json_encode($features, JSON_UNESCAPED_UNICODE), //  8 features
            $badge ?: null,                                 //  9 badge (null if blank)
            $color,                                         // 10 color
            $order,                                         // 11 sort_order
            $active,                                        // 12 is_active
            $feat,                                          // 13 is_featured
            now_ts(),                                       // 14 updated_at
        ]; // 14 elements

        try {
            if ($pid > 0) {
                // UPDATE: 14 SET ?s + 1 WHERE ? = 15 total params ✓
                db()->prepare(
                    "UPDATE packages
                     SET name=?,description=?,type=?,price=?,views_count=?,duration_days=?,
                         cost_per_view=?,features=?,badge=?,color=?,sort_order=?,
                         is_active=?,is_featured=?,updated_at=?
                     WHERE id=?"
                )->execute([...$base, $pid]); // 15 params ✓
                flash('success', 'Package "' . $name . '" updated successfully.');
            } else {
                // INSERT: 15 cols, 15 ?s, 15 params ✓
                db()->prepare(
                    "INSERT INTO packages
                     (name,description,type,price,views_count,duration_days,cost_per_view,
                      features,badge,color,sort_order,is_active,is_featured,updated_at,created_at)
                     VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
                )->execute([...$base, now_ts()]); // 15 params ✓
                flash('success', 'Package "' . $name . '" created successfully.');
            }
        } catch (Exception $e) {
            error_log('[ViewPay] Package save error: ' . $e->getMessage());
            flash('error', 'Failed to save package. Please try again.');
        }
        redirect('/admin/packages');
    }

    // ── DELETE ────────────────────────────────────────────────
    if ($action === 'delete') {
        $pid = (int)($_POST['pkg_id'] ?? 0);
        if ($pid < 1) {
            flash('error', 'Invalid package.');
            redirect('/admin/packages');
        }
        try {
            $inUse = db()->prepare(
                "SELECT COUNT(*) FROM tasks
                 WHERE package_id=? AND status IN ('active','pending')"
            );
            $inUse->execute([$pid]);
            if ((int)$inUse->fetchColumn() > 0) {
                flash('error', 'Cannot delete — this package has active or pending campaigns attached.');
            } else {
                db()->prepare("DELETE FROM packages WHERE id=?")->execute([$pid]);
                flash('success', 'Package deleted.');
            }
        } catch (Exception $e) {
            flash('error', 'Delete failed. Please try again.');
        }
        redirect('/admin/packages');
    }

    // ── TOGGLE active/inactive ────────────────────────────────
    if ($action === 'toggle') {
        $pid = (int)($_POST['pkg_id'] ?? 0);
        if ($pid > 0) {
            try {
                db()->prepare(
                    "UPDATE packages SET is_active = 1 - is_active, updated_at=? WHERE id=?"
                )->execute([now_ts(), $pid]);
            } catch (Exception $e) { /* silent */ }
        }
        redirect('/admin/packages');
    }
}

// ─────────────────────────────────────────────────────────────
//  Load data
// ─────────────────────────────────────────────────────────────
$pkgs = [];
$dbError = null;
try {
    $pkgs = db()->query(
        "SELECT p.*,
                (SELECT COUNT(*) FROM tasks t WHERE t.package_id = p.id) AS task_count
         FROM packages p
         ORDER BY sort_order ASC, id ASC"
    )->fetchAll();
} catch (Exception $e) {
    $dbError = APP_DEBUG ? $e->getMessage() : 'Could not load packages from the database.';
}

$curr = get_currency();

// Build safe JS data store — JSON_HEX_* prevents any XSS injection
$pkgStore = [];
foreach ($pkgs as $row) {
    $feats = json_decode((string)($row['features'] ?? '[]'), true);
    $pkgStore[(int)$row['id']] = [
        'id'            => (int)$row['id'],
        'name'          => (string)$row['name'],
        'description'   => (string)($row['description'] ?? ''),
        'type'          => (string)$row['type'],
        'price'         => (float)$row['price'],
        'views_count'   => (int)$row['views_count'],
        'duration_days' => (int)$row['duration_days'],
        'cost_per_view' => (float)$row['cost_per_view'],
        'badge'         => (string)($row['badge'] ?? ''),
        'color'         => (string)($row['color'] ?: '#6366f1'),
        'sort_order'    => (int)$row['sort_order'],
        'is_active'     => (bool)$row['is_active'],
        'is_featured'   => (bool)$row['is_featured'],
        'features'      => is_array($feats) ? $feats : [],
    ];
}

admin_page_start('Packages');
?>

<!-- ── Page header ───────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0">Packages</h4>
    <p class="text-muted small mb-0">
      <?= count($pkgs) ?> package(s) &mdash; creators buy these to receive views
    </p>
  </div>
  <button class="btn btn-primary fw-600" onclick="pkgOpenNew()">
    <i class="bi bi-plus-circle me-1"></i>New Package
  </button>
</div>

<?php if ($dbError): ?>
<div class="alert alert-danger border-0 shadow-sm d-flex gap-2">
  <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
  <div><strong>Database Error:</strong> <?= e($dbError) ?></div>
</div>
<?php endif; ?>

<!-- ── Package cards ─────────────────────────────────────── -->
<div class="row g-3 mb-4">
  <?php if (empty($pkgs) && !$dbError): ?>
  <div class="col-12 text-center py-5 text-muted">
    <i class="bi bi-box-seam display-3 d-block mb-3 opacity-25"></i>
    <h5>No packages yet</h5>
    <p>Click <strong>New Package</strong> above to add your first one.</p>
  </div>
  <?php endif; ?>

  <?php foreach ($pkgs as $row):
    $feats    = json_decode((string)($row['features'] ?? '[]'), true);
    if (!is_array($feats)) { $feats = []; }
    $color    = e($row['color'] ?: '#6366f1');
    $isActive = (bool)$row['is_active'];
    $cpv      = $row['views_count'] > 0
              ? ((float)$row['price'] / (int)$row['views_count'])
              : 0.0;
    $iconMap  = ['video'=>'play-circle-fill','website'=>'globe2','both'=>'collection'];
    $typeIcon = $iconMap[$row['type']] ?? 'box-seam';
  ?>
  <div class="col-xl-3 col-md-4 col-sm-6">
    <div class="card border-0 shadow-sm h-100 rounded-xl position-relative
                <?= !$isActive ? 'opacity-75' : '' ?>">

      <?php if ($row['badge']): ?>
      <div class="position-absolute top-0 end-0 m-2" style="z-index:2">
        <span class="badge rounded-pill"
              style="background:<?= $color ?>;font-size:.68rem">
          <?= e($row['badge']) ?>
        </span>
      </div>
      <?php endif; ?>

      <?php if (!$isActive): ?>
      <div class="position-absolute top-0 start-0 m-2" style="z-index:2">
        <span class="badge bg-secondary" style="font-size:.65rem">Inactive</span>
      </div>
      <?php endif; ?>

      <div class="card-body p-3">
        <!-- Icon + name -->
        <div class="d-flex align-items-center gap-2 mb-3">
          <div style="width:36px;height:36px;background:<?= $color ?>22;color:<?= $color ?>;
                      border-radius:8px;display:grid;place-items:center;flex-shrink:0">
            <i class="bi bi-<?= $typeIcon ?>"></i>
          </div>
          <div>
            <div class="fw-bold small"><?= e($row['name']) ?></div>
            <div class="text-muted" style="font-size:.7rem">
              <?= ucfirst($row['type']) ?> &bull; Order #<?= (int)$row['sort_order'] ?>
            </div>
          </div>
        </div>

        <div class="fw-bold fs-5 mb-1" style="color:<?= $color ?>">
          <?= money((float)$row['price']) ?>
        </div>
        <div class="text-muted small mb-2">
          <?= number_format((int)$row['views_count']) ?> views &bull;
          <?= (int)$row['duration_days'] ?> days
        </div>

        <!-- Feature bullets (max 3) -->
        <ul class="list-unstyled small mb-2">
          <?php foreach (array_slice($feats, 0, 3) as $f): ?>
          <li class="mb-1 d-flex align-items-start gap-1">
            <i class="bi bi-check-circle-fill mt-1 flex-shrink-0"
               style="color:<?= $color ?>;font-size:.68rem"></i>
            <span><?= e($f) ?></span>
          </li>
          <?php endforeach; ?>
          <?php if (count($feats) > 3): ?>
          <li class="text-muted" style="font-size:.72rem">
            +<?= count($feats) - 3 ?> more
          </li>
          <?php endif; ?>
        </ul>

        <div class="d-flex justify-content-between text-muted border-top pt-2 mt-2 small">
          <span>CPV: <?= money($cpv, 4) ?></span>
          <span><?= (int)$row['task_count'] ?> campaign(s)</span>
        </div>
      </div><!-- /card-body -->

      <div class="card-footer border-0 bg-transparent d-flex gap-1 px-3 pb-3">
        <!-- Edit -->
        <button class="btn btn-outline-primary btn-sm flex-grow-1 fw-600"
                onclick="pkgOpenEdit(<?= (int)$row['id'] ?>)">
          <i class="bi bi-pencil me-1"></i>Edit
        </button>
        <!-- Toggle active -->
        <form method="POST" class="d-inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action"  value="toggle">
          <input type="hidden" name="pkg_id"  value="<?= (int)$row['id'] ?>">
          <button type="submit"
                  class="btn btn-sm <?= $isActive ? 'btn-outline-warning' : 'btn-outline-success' ?>"
                  title="<?= $isActive ? 'Deactivate' : 'Activate' ?>">
            <i class="bi bi-<?= $isActive ? 'eye-slash' : 'eye' ?>"></i>
          </button>
        </form>
        <!-- Delete (disabled if campaigns exist) -->
        <form method="POST" class="d-inline"
              onsubmit="return confirm('Delete package \'<?= e(addslashes($row['name'])) ?>\'?\n\nThis cannot be undone.')">
          <?= csrf_field() ?>
          <input type="hidden" name="action"  value="delete">
          <input type="hidden" name="pkg_id"  value="<?= (int)$row['id'] ?>">
          <button type="submit"
                  class="btn btn-outline-danger btn-sm"
                  title="<?= (int)$row['task_count'] > 0 ? 'Has campaigns — cannot delete' : 'Delete' ?>"
                  <?= (int)$row['task_count'] > 0 ? 'disabled' : '' ?>>
            <i class="bi bi-trash"></i>
          </button>
        </form>
      </div><!-- /card-footer -->
    </div><!-- /card -->
  </div><!-- /col -->
  <?php endforeach; ?>
</div><!-- /row -->

<!-- ── Performance table ─────────────────────────────────── -->
<?php if (!empty($pkgs)): ?>
<div class="card border-0 shadow-sm rounded-xl">
  <div class="card-header bg-transparent border-0 fw-600 small py-3 px-4">
    <i class="bi bi-table me-2 text-primary"></i>Performance Summary
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0 small">
      <thead class="table-light">
        <tr>
          <th class="ps-4">Name</th>
          <th>Type</th>
          <th>Price</th>
          <th>Views</th>
          <th>Creator CPV</th>
          <th>Earner CPV</th>
          <th>Campaigns</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($pkgs as $row):
        $cpv = $row['views_count'] > 0
             ? ((float)$row['price'] / (int)$row['views_count'])
             : 0.0;
      ?>
      <tr>
        <td class="ps-4 fw-600"><?= e($row['name']) ?></td>
        <td>
          <span class="badge bg-<?= $row['type']==='video' ? 'primary' : 'success' ?>">
            <?= ucfirst($row['type']) ?>
          </span>
        </td>
        <td class="fw-bold"><?= money((float)$row['price']) ?></td>
        <td><?= number_format((int)$row['views_count']) ?></td>
        <td class="text-danger"><?= money($cpv, 4) ?></td>
        <td class="text-success"><?= money((float)$row['cost_per_view'], 4) ?></td>
        <td><span class="badge bg-primary"><?= (int)$row['task_count'] ?></span></td>
        <td>
          <span class="badge <?= $row['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
            <?= $row['is_active'] ? 'Active' : 'Off' ?>
          </span>
        </td>
        <td>
          <button class="btn btn-sm btn-outline-secondary"
                  onclick="pkgOpenEdit(<?= (int)$row['id'] ?>)">
            <i class="bi bi-pencil"></i>
          </button>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>


<!-- ── Package Modal ─────────────────────────────────────── -->
<div class="modal fade" id="pkgModal" tabindex="-1"
     aria-labelledby="pkgModalTitle" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered modal-lg">
<div class="modal-content border-0 shadow rounded-xl">

  <div class="modal-header border-0">
    <h5 class="fw-bold" id="pkgModalTitle">Package</h5>
    <button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
  </div>

  <form method="POST" id="pkgForm" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="pkg_id" id="pkgId" value="0">

    <div class="modal-body">
      <div class="row g-3">

        <div class="col-md-5">
          <label class="form-label small fw-600">Package Name *</label>
          <input type="text" name="name" id="pkgName" class="form-control"
                 required maxlength="100" placeholder="e.g. Starter Video">
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-600">Type</label>
          <select name="type" id="pkgType" class="form-select">
            <option value="video">📹 Video</option>
            <option value="website">🌐 Website</option>
            <option value="both">📦 Both</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-600">Brand Colour</label>
          <input type="color" name="color" id="pkgColor"
                 class="form-control form-control-color w-100" value="#6366f1">
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-600">Sort Order</label>
          <input type="number" name="sort_order" id="pkgOrder"
                 class="form-control" value="0" min="0">
        </div>

        <div class="col-12">
          <label class="form-label small fw-600">Short Description</label>
          <input type="text" name="description" id="pkgDesc"
                 class="form-control" maxlength="200"
                 placeholder="e.g. Perfect for new YouTube creators">
        </div>

        <div class="col-md-3">
          <label class="form-label small fw-600">Price *</label>
          <div class="input-group">
            <span class="input-group-text small"><?= e($curr['symbol']) ?></span>
            <input type="number" name="price" id="pkgPrice"
                   class="form-control" step="0.01" min="0.01" required>
          </div>
          <div class="form-text"><?= e($curr['name']) ?></div>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-600">Views Count *</label>
          <input type="number" name="views_count" id="pkgViews"
                 class="form-control" min="1" required>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-600">Duration (days)</label>
          <input type="number" name="duration_days" id="pkgDays"
                 class="form-control" value="30" min="1">
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-600">Earner Reward/View</label>
          <div class="input-group">
            <span class="input-group-text small">$</span>
            <input type="number" name="cost_per_view" id="pkgCpv"
                   class="form-control" step="0.000001" min="0" value="0.005">
          </div>
          <div class="form-text">USD per view (earner pay)</div>
        </div>

        <div class="col-md-5">
          <label class="form-label small fw-600">
            Badge Label <span class="text-muted fw-normal">(optional)</span>
          </label>
          <input type="text" name="badge" id="pkgBadge"
                 class="form-control" maxlength="30" placeholder="e.g. Popular">
        </div>
        <div class="col-md-5 d-flex align-items-end gap-4 pb-1">
          <div class="form-check">
            <input class="form-check-input" type="checkbox"
                   name="is_active" id="pkgActive" checked>
            <label class="form-check-label small fw-600" for="pkgActive">Active</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox"
                   name="is_featured" id="pkgFeatured">
            <label class="form-check-label small fw-600" for="pkgFeatured">Featured</label>
          </div>
        </div>

        <div class="col-12">
          <label class="form-label small fw-600">
            Features <span class="text-muted fw-normal">(one per line)</span>
          </label>
          <textarea name="features" id="pkgFeatures" class="form-control" rows="5"
                    placeholder="500 video views&#10;30-day campaign&#10;Basic analytics&#10;Priority support"></textarea>
          <div class="form-text">Each line = one bullet point on the package card.</div>
        </div>

        <!-- Live price calculator -->
        <div class="col-12">
          <div class="rounded-3 p-3 small bg-body-secondary">
            <span class="fw-600">Live breakdown:</span>
            &nbsp;Creator pays: <strong id="calcTotal">—</strong>
            &nbsp;|&nbsp; Admin CPV: <strong id="calcCpv">—</strong>
            &nbsp;|&nbsp; Earner gets: <strong id="calcEarner">—</strong>
          </div>
        </div>

      </div><!-- /row -->
    </div><!-- /modal-body -->

    <div class="modal-footer border-0">
      <button type="button" class="btn btn-outline-secondary"
              data-bs-dismiss="modal">Cancel</button>
      <button type="submit" class="btn btn-primary fw-600" id="pkgSaveBtn">
        <i class="bi bi-floppy2-fill me-1"></i>Save Package
      </button>
    </div>
  </form>
</div>
</div>
</div><!-- /modal -->


<!-- ── JavaScript ──────────────────────────────────────────── -->
<script>
// Package data store — JSON_HEX_* flags prevent XSS injection
const VP_PKGS    = <?= json_encode($pkgStore, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE) ?>;
const currSymbol = <?= json_encode($curr['symbol']) ?>;
const currRate   = <?= json_encode((float)$curr['rate']) ?>;

let pkgModal;
function getPkgModal() {
    if (!pkgModal) pkgModal = new bootstrap.Modal(document.getElementById('pkgModal'));
    return pkgModal;
}

// ── Open modal — NEW package ──────────────────────────────────
function pkgOpenNew() {
    document.getElementById('pkgModalTitle').textContent = 'New Package';
    document.getElementById('pkgForm').reset();
    document.getElementById('pkgId').value        = '0';
    document.getElementById('pkgColor').value     = '#6366f1';
    document.getElementById('pkgDays').value      = '30';
    document.getElementById('pkgOrder').value     = '0';
    document.getElementById('pkgCpv').value       = '0.005';
    document.getElementById('pkgActive').checked  = true;
    document.getElementById('pkgFeatured').checked= false;
    updateCalc();
    getPkgModal().show();
}

// ── Open modal — EDIT existing package ───────────────────────
function pkgOpenEdit(id) {
    var p = VP_PKGS[id];
    if (!p) {
        alert('Package data not found. Please refresh the page and try again.');
        return;
    }
    document.getElementById('pkgModalTitle').textContent = 'Edit Package — ' + p.name;
    document.getElementById('pkgId').value         = p.id;
    document.getElementById('pkgName').value       = p.name;
    document.getElementById('pkgDesc').value       = p.description;
    document.getElementById('pkgType').value       = p.type;
    document.getElementById('pkgColor').value      = p.color;
    document.getElementById('pkgPrice').value      = p.price;
    document.getElementById('pkgViews').value      = p.views_count;
    document.getElementById('pkgDays').value       = p.duration_days;
    document.getElementById('pkgCpv').value        = p.cost_per_view;
    document.getElementById('pkgBadge').value      = p.badge;
    document.getElementById('pkgOrder').value      = p.sort_order;
    document.getElementById('pkgActive').checked   = p.is_active;
    document.getElementById('pkgFeatured').checked = p.is_featured;
    document.getElementById('pkgFeatures').value   = p.features.join('\n');
    updateCalc();
    getPkgModal().show();
}

// ── Live price breakdown calculator ──────────────────────────
function updateCalc() {
    var price = parseFloat(document.getElementById('pkgPrice').value)  || 0;
    var views = parseInt(document.getElementById('pkgViews').value, 10) || 0;
    var cpv   = parseFloat(document.getElementById('pkgCpv').value)     || 0;

    var fmt2  = function(n) { return currSymbol + (n * currRate).toFixed(2); };
    var fmt4  = function(n) { return currSymbol + (n * currRate).toFixed(4); };

    document.getElementById('calcTotal').textContent  = price > 0 ? fmt2(price) : '—';
    document.getElementById('calcCpv').textContent    = (price > 0 && views > 0) ? fmt4(price / views) : '—';
    document.getElementById('calcEarner').textContent = cpv > 0 ? fmt4(cpv) : '—';
}

// Attach calculator listeners
['pkgPrice','pkgViews','pkgCpv'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el) { el.addEventListener('input', updateCalc); }
});

// Prevent double-submit
document.getElementById('pkgForm').addEventListener('submit', function() {
    var btn = document.getElementById('pkgSaveBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';
});
</script>

<?php admin_page_end(); ?>
