<?php
defined('VP_BOOT') || die('Direct access not permitted.');
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/partials/admin-layout.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $aid      = (int)($_POST['ad_id'] ?? 0);
        $title    = trim($_POST['title']    ?? '');
        $type     = in_array($_POST['type'] ?? '', ['custom_html','image','video_url'], true) ? $_POST['type'] : 'custom_html';
        $position = in_array($_POST['position'] ?? '', ['before_task','during_task','after_task'], true) ? $_POST['position'] : 'before_task';
        $content  = $_POST['content']  ?? '';
        $dur      = max(1,  (int)($_POST['duration_seconds'] ?? 5));
        $skip     = isset($_POST['is_skippable']) ? 1 : 0;
        $active   = isset($_POST['is_active'])    ? 1 : 0;
        $tUrl     = trim($_POST['target_url'] ?? '');

        if (!$title) { flash('error','Title required.'); redirect('/admin/ads'); }

        $params = [$title,$type,$position,$content,$dur,$skip,$active,$tUrl?:null,now_ts()];
        if ($aid > 0) {
            // 9 SET + WHERE = 10 params ✓
            db()->prepare(
                "UPDATE advertisements
                 SET title=?,type=?,position=?,content=?,duration_seconds=?,
                     is_skippable=?,is_active=?,target_url=?,updated_at=?
                 WHERE id=?"
            )->execute([...$params, $aid]);
            flash('success','Ad updated.');
        } else {
            // 10 cols, 10 ?s, 10 params ✓
            db()->prepare(
                "INSERT INTO advertisements
                 (title,type,position,content,duration_seconds,is_skippable,
                  is_active,target_url,updated_at,created_at)
                 VALUES(?,?,?,?,?,?,?,?,?,?)"
            )->execute([...$params, now_ts()]);
            flash('success','Ad created.');
        }
    } elseif ($action === 'delete') {
        $aid = (int)($_POST['ad_id'] ?? 0);
        db()->prepare("DELETE FROM advertisements WHERE id=?")->execute([$aid]);
        flash('success','Ad deleted.');
    } elseif ($action === 'toggle') {
        $aid = (int)($_POST['ad_id'] ?? 0);
        db()->prepare("UPDATE advertisements SET is_active=1-is_active,updated_at=? WHERE id=?")->execute([now_ts(),$aid]);
    }
    redirect('/admin/ads');
}

$ads    = db()->query("SELECT * FROM advertisements ORDER BY is_active DESC, created_at DESC")->fetchAll();
$editId = (int)($_GET['edit'] ?? 0);
$editAd = null;
foreach ($ads as $a) { if ((int)$a['id'] === $editId) { $editAd = $a; break; } }

$adStore = [];
foreach ($ads as $a) { $adStore[(int)$a['id']] = $a; }

admin_page_start('Advertisements');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="fw-bold mb-1">Advertisements</h4><p class="text-muted small mb-0"><?= count($ads) ?> ads</p></div>
  <button class="btn btn-primary fw-600" onclick="openNewAd()">
    <i class="bi bi-plus-circle me-1"></i>New Ad
  </button>
</div>

<?php if (empty($ads)): ?>
<div class="text-center py-5 text-muted">
  <i class="bi bi-megaphone display-3 d-block mb-3 opacity-25"></i>
  <h5>No advertisements yet</h5>
  <p>Create your first ad to show before tasks.</p>
</div>
<?php else: ?>
<div class="row g-3">
<?php foreach ($ads as $ad): ?>
<div class="col-md-6 col-xl-4">
<div class="card border-0 shadow-sm rounded-xl h-100 <?= !$ad['is_active'] ? 'opacity-75' : '' ?>">
  <div class="card-body p-4">
    <div class="d-flex justify-content-between align-items-start mb-2">
      <div>
        <div class="fw-bold small mb-1"><?= e($ad['title']) ?></div>
        <div class="d-flex gap-1">
          <span class="badge bg-secondary-subtle text-secondary" style="font-size:.65rem">
            <?= ucfirst(str_replace('_',' ',$ad['type'])) ?>
          </span>
          <span class="badge bg-primary-subtle text-primary" style="font-size:.65rem">
            <?= ucfirst(str_replace('_',' ',$ad['position'])) ?>
          </span>
        </div>
      </div>
      <span class="badge <?= $ad['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
        <?= $ad['is_active'] ? 'Active' : 'Off' ?>
      </span>
    </div>
    <div class="text-muted small mb-2">
      <i class="bi bi-clock me-1"></i><?= $ad['duration_seconds'] ?>s
      &nbsp;·&nbsp;
      <i class="bi bi-skip-forward me-1"></i><?= $ad['is_skippable'] ? 'Skippable' : 'Non-skip' ?>
    </div>
    <div class="text-muted small mb-0">
      <i class="bi bi-bar-chart me-1"></i>
      <?= number_format($ad['impressions']) ?> impressions · <?= number_format($ad['clicks']) ?> clicks
    </div>
  </div>
  <div class="card-footer border-0 bg-transparent d-flex gap-1 px-4 pb-3">
    <button class="btn btn-sm btn-outline-primary fw-600 flex-grow-1"
            onclick="openEditAd(<?= (int)$ad['id'] ?>)">
      <i class="bi bi-pencil me-1"></i>Edit
    </button>
    <form method="POST" class="d-inline">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="toggle">
      <input type="hidden" name="ad_id"  value="<?= $ad['id'] ?>">
      <button class="btn btn-sm <?= $ad['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>">
        <i class="bi bi-<?= $ad['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
      </button>
    </form>
    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this ad?')">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="ad_id"  value="<?= $ad['id'] ?>">
      <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
    </form>
  </div>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Ad Modal -->
<div class="modal fade" id="adModal" tabindex="-1">
<div class="modal-dialog modal-dialog-centered modal-lg">
<div class="modal-content border-0 shadow rounded-xl">
  <div class="modal-header border-0">
    <h5 class="fw-bold" id="adModalTitle">Advertisement</h5>
    <button class="btn-close" data-bs-dismiss="modal"></button>
  </div>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="ad_id"  id="adId" value="0">
    <div class="modal-body">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label small fw-600">Title *</label>
          <input type="text" name="title" id="adTitle" class="form-control" required maxlength="200">
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-600">Ad Type</label>
          <select name="type" id="adType" class="form-select" onchange="toggleAdType(this.value)">
            <option value="custom_html">Custom HTML</option>
            <option value="image">Image URL</option>
            <option value="video_url">Video URL</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-600">Position</label>
          <select name="position" id="adPos" class="form-select">
            <option value="before_task">Before Task</option>
            <option value="during_task">During Task</option>
            <option value="after_task">After Task</option>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label small fw-600" id="adContentLabel">HTML Content</label>
          <textarea name="content" id="adContent" class="form-control font-monospace" rows="6"
                    placeholder="<div style='...'>Your ad HTML here</div>"></textarea>
        </div>
        <div class="col-12">
          <label class="form-label small fw-600">Click Target URL (optional)</label>
          <input type="url" name="target_url" id="adTarget" class="form-control" placeholder="https://example.com">
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-600">Duration (seconds)</label>
          <input type="number" name="duration_seconds" id="adDur" class="form-control" min="1" value="5">
        </div>
        <div class="col-md-5 d-flex align-items-end gap-4 pb-1">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_skippable" id="adSkip" checked>
            <label class="form-check-label small fw-600" for="adSkip">Skippable</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_active" id="adActive" checked>
            <label class="form-check-label small fw-600" for="adActive">Active</label>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-footer border-0">
      <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
      <button type="submit" class="btn btn-primary fw-600"><i class="bi bi-floppy2-fill me-1"></i>Save Ad</button>
    </div>
  </form>
</div>
</div>
</div>

<script>
const VP_ADS = <?= json_encode($adStore, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
let adModal;
function getAdModal() {
    if (!adModal) adModal = new bootstrap.Modal(document.getElementById('adModal'));
    return adModal;
}

function openNewAd() {
    document.getElementById('adModalTitle').textContent = 'New Advertisement';
    document.getElementById('adId').value    = '0';
    document.getElementById('adModal').querySelector('form').reset();
    document.getElementById('adDur').value   = '5';
    document.getElementById('adSkip').checked   = true;
    document.getElementById('adActive').checked = true;
    getAdModal().show();
}
function openEditAd(id) {
    const a = VP_ADS[id]; if (!a) return;
    document.getElementById('adModalTitle').textContent = 'Edit Ad — ' + a.title;
    document.getElementById('adId').value       = a.id;
    document.getElementById('adTitle').value    = a.title;
    document.getElementById('adType').value     = a.type;
    document.getElementById('adPos').value      = a.position;
    document.getElementById('adContent').value  = a.content;
    document.getElementById('adDur').value      = a.duration_seconds;
    document.getElementById('adTarget').value   = a.target_url || '';
    document.getElementById('adSkip').checked   = a.is_skippable == 1;
    document.getElementById('adActive').checked = a.is_active == 1;
    toggleAdType(a.type);
    getAdModal().show();
}
function toggleAdType(t) {
    const lbl = document.getElementById('adContentLabel');
    const ta  = document.getElementById('adContent');
    if (t==='custom_html') { lbl.textContent='HTML Content'; ta.placeholder='<div>Your ad HTML…</div>'; }
    else if (t==='image')  { lbl.textContent='Image URL';   ta.placeholder='https://example.com/ad.jpg'; }
    else                   { lbl.textContent='Video URL';   ta.placeholder='https://example.com/ad.mp4'; }
}
</script>
<?php admin_page_end(); ?>
