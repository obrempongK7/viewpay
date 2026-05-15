<?php
/**
 * Admin — CMS Page Editor
 * Accessed via /admin/pages/{id} or /admin/pages/new
 */
defined('VP_BOOT') || die('Direct access not permitted.');
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/partials/admin-layout.php';
require_admin();

$pid = (int)($_GET['page_id'] ?? 0);  // 0 = new page
$pg  = null;
if ($pid > 0) {
    $st = db()->prepare("SELECT * FROM pages WHERE id=? LIMIT 1");
    $st->execute([$pid]); $pg = $st->fetch();
    if (!$pg) { flash('error','Page not found.'); redirect('/admin/pages'); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $title       = trim($_POST['title']       ?? '');
    $slug        = trim($_POST['slug']        ?? '');
    $content     = $_POST['content']          ?? '';      // Admin-trusted HTML
    $metaTitle   = trim($_POST['meta_title']  ?? '');
    $metaDesc    = trim($_POST['meta_description'] ?? '');
    $footer      = isset($_POST['show_in_footer']) ? 1 : 0;
    $header      = isset($_POST['show_in_header']) ? 1 : 0;
    $order       = (int)($_POST['sort_order']  ?? 0);
    $active      = isset($_POST['is_active'])  ? 1 : 0;

    // Auto-generate slug from title if blank
    if (!$slug) {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title));
        $slug = trim($slug, '-');
    }
    $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug));

    if (!$title || !$slug) {
        flash('error','Title and slug are required.');
    } else {
        // Slug uniqueness check (exclude current page)
        $check = db()->prepare("SELECT id FROM pages WHERE slug=? AND id!=? LIMIT 1");
        $check->execute([$slug, $pid > 0 ? $pid : 0]);
        if ($check->fetch()) {
            flash('error', "Slug '/{$slug}' is already used by another page.");
        } else {
            if ($pid > 0) {
                // 9 SET + WHERE = 10 params ✓
                db()->prepare(
                    "UPDATE pages
                     SET title=?,slug=?,content=?,meta_title=?,meta_description=?,
                         show_in_footer=?,show_in_header=?,sort_order=?,is_active=?,updated_at=?
                     WHERE id=?"
                )->execute([
                    $title,$slug,$content,$metaTitle,$metaDesc,
                    $footer,$header,$order,$active,now_ts(),$pid
                ]);
                flash('success','Page updated.');
            } else {
                // 11 cols, 11 ?s, 11 params ✓
                db()->prepare(
                    "INSERT INTO pages
                     (title,slug,content,meta_title,meta_description,
                      show_in_footer,show_in_header,sort_order,is_active,created_at,updated_at)
                     VALUES(?,?,?,?,?,?,?,?,?,?,?)"
                )->execute([
                    $title,$slug,$content,$metaTitle,$metaDesc,
                    $footer,$header,$order,$active,now_ts(),now_ts()
                ]);
                flash('success','Page created.');
            }
            redirect('/admin/pages');
        }
    }
}

$isNew = ($pg === null);
admin_page_start($isNew ? 'New Page' : 'Edit: ' . $pg['title']);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <a href="<?= url('admin/pages') ?>" class="btn btn-sm btn-outline-secondary mb-2">
      <i class="bi bi-arrow-left me-1"></i>All Pages
    </a>
    <h4 class="fw-bold mb-0"><?= $isNew ? 'New Page' : 'Edit: ' . e($pg['title']) ?></h4>
  </div>
  <?php if (!$isNew): ?>
  <a href="<?= url('page/' . $pg['slug']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-eye me-1"></i>Preview
  </a>
  <?php endif; ?>
</div>

<form method="POST" id="pageForm">
  <?= csrf_field() ?>
  <div class="row g-4">

    <!-- Main editor -->
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm rounded-xl p-4 mb-4">
        <div class="mb-3">
          <label class="form-label small fw-600">Page Title *</label>
          <input type="text" name="title" id="titleField" class="form-control fw-600"
                 value="<?= e($pg['title'] ?? '') ?>"
                 placeholder="e.g. About Us" required
                 oninput="autoSlug(this.value)">
        </div>
        <div class="mb-0">
          <label class="form-label small fw-600">URL Slug *</label>
          <div class="input-group">
            <span class="input-group-text text-muted small">/page/</span>
            <input type="text" name="slug" id="slugField" class="form-control font-monospace"
                   value="<?= e($pg['slug'] ?? '') ?>"
                   placeholder="about-us" required>
          </div>
          <div class="form-text">Only lowercase letters, numbers and hyphens.</div>
        </div>
      </div>

      <!-- Editor toolbar -->
      <div class="card border-0 shadow-sm rounded-xl p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <label class="form-label fw-600 mb-0">Content *</label>
          <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-secondary active" id="btnVisual" onclick="switchMode('visual')">
              <i class="bi bi-eye me-1"></i>Visual
            </button>
            <button type="button" class="btn btn-outline-secondary" id="btnHtml" onclick="switchMode('html')">
              <i class="bi bi-code-slash me-1"></i>HTML
            </button>
          </div>
        </div>

        <!-- Format toolbar (visual mode) -->
        <div id="toolbar" class="d-flex flex-wrap gap-1 mb-2 p-2 bg-body-secondary rounded-3">
          <?php foreach ([
            ['bold',      'bi-type-bold',      'Bold',        'bold'],
            ['italic',    'bi-type-italic',     'Italic',      'italic'],
            ['underline', 'bi-type-underline',  'Underline',   'underline'],
            ['|','','',''],
            ['h2',        'bi-type-h2',         'Heading 2',   ''],
            ['h3',        'bi-type-h3',         'Heading 3',   ''],
            ['|','','',''],
            ['insertUnorderedList','bi-list-ul','Bullet List',''],
            ['insertOrderedList',  'bi-list-ol','Number List',''],
            ['|','','',''],
            ['createLink',         'bi-link-45deg','Link',     ''],
          ] as [$cmd, $icon, $title, $val]): ?>
          <?php if ($cmd === '|'): ?>
          <div class="vr mx-1"></div>
          <?php else: ?>
          <button type="button" class="btn btn-sm btn-light" onclick="fmt('<?= $cmd ?>','<?= $val ?>')" title="<?= $title ?>">
            <i class="bi <?= $icon ?>"></i>
          </button>
          <?php endif; ?>
          <?php endforeach; ?>
        </div>

        <!-- Visual editor -->
        <div id="visualEditor" class="form-control"
             contenteditable="true" style="min-height:320px;font-size:.9rem;line-height:1.7"
             oninput="syncToHidden()"><?= $pg['content'] ?? '' ?></div>

        <!-- HTML editor -->
        <textarea id="htmlEditor" name="content" class="form-control font-monospace d-none"
                  rows="16" style="font-size:.8rem;line-height:1.6"
                  oninput="syncToVisual()"><?= e($pg['content'] ?? '') ?></textarea>
      </div>
    </div>

    <!-- Sidebar settings -->
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm rounded-xl p-4 mb-3">
        <h6 class="fw-bold mb-3">Page Settings</h6>
        <div class="mb-3">
          <label class="form-label small fw-600">Sort Order</label>
          <input type="number" name="sort_order" class="form-control" value="<?= e($pg['sort_order'] ?? 0) ?>" min="0">
        </div>
        <div class="d-flex flex-column gap-2">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_active" id="pgActive"
                   <?= ($pg['is_active'] ?? 1) ? 'checked' : '' ?>>
            <label class="form-check-label small fw-600" for="pgActive">Published</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="show_in_header" id="pgHeader"
                   <?= ($pg['show_in_header'] ?? 0) ? 'checked' : '' ?>>
            <label class="form-check-label small fw-600" for="pgHeader">Show in nav header</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="show_in_footer" id="pgFooter"
                   <?= ($pg['show_in_footer'] ?? 1) ? 'checked' : '' ?>>
            <label class="form-check-label small fw-600" for="pgFooter">Show in footer</label>
          </div>
        </div>
      </div>

      <!-- SEO -->
      <div class="card border-0 shadow-sm rounded-xl p-4 mb-3">
        <h6 class="fw-bold mb-3">SEO (optional)</h6>
        <div class="mb-3">
          <label class="form-label small fw-600">Meta Title</label>
          <input type="text" name="meta_title" class="form-control"
                 value="<?= e($pg['meta_title'] ?? '') ?>" maxlength="200">
        </div>
        <div>
          <label class="form-label small fw-600">Meta Description</label>
          <textarea name="meta_description" class="form-control" rows="3" maxlength="400"><?= e($pg['meta_description'] ?? '') ?></textarea>
        </div>
      </div>

      <!-- Save -->
      <div class="d-grid gap-2">
        <button type="submit" class="btn btn-primary fw-700 py-2">
          <i class="bi bi-floppy2-fill me-2"></i>
          <?= $isNew ? 'Create Page' : 'Save Changes' ?>
        </button>
        <a href="<?= url('admin/pages') ?>" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </div>

  </div>
</form>

<script>
let mode = 'visual';

function switchMode(m) {
    mode = m;
    const ve = document.getElementById('visualEditor');
    const he = document.getElementById('htmlEditor');
    const tb = document.getElementById('toolbar');
    if (m === 'visual') {
        ve.classList.remove('d-none');
        he.classList.add('d-none');
        tb.classList.remove('d-none');
        ve.innerHTML = he.value;
        document.getElementById('btnVisual').classList.add('active');
        document.getElementById('btnHtml').classList.remove('active');
    } else {
        ve.classList.add('d-none');
        he.classList.remove('d-none');
        tb.classList.add('d-none');
        he.value = ve.innerHTML;
        document.getElementById('btnHtml').classList.add('active');
        document.getElementById('btnVisual').classList.remove('active');
    }
}

function syncToHidden() {
    document.getElementById('htmlEditor').value = document.getElementById('visualEditor').innerHTML;
}
function syncToVisual() {
    document.getElementById('visualEditor').innerHTML = document.getElementById('htmlEditor').value;
}

function fmt(cmd, val) {
    document.getElementById('visualEditor').focus();
    if (cmd === 'h2' || cmd === 'h3') {
        document.execCommand('formatBlock', false, '<' + cmd + '>');
    } else if (cmd === 'createLink') {
        const url = prompt('Enter URL:');
        if (url) document.execCommand('createLink', false, url);
    } else {
        document.execCommand(cmd, false, val || null);
    }
    syncToHidden();
}

// Auto slug from title
let slugEdited = <?= $pid > 0 ? 'true' : 'false' ?>;
document.getElementById('slugField').addEventListener('input', () => { slugEdited = true; });

function autoSlug(v) {
    if (slugEdited) return;
    document.getElementById('slugField').value = v
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .trim()
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');
}

// Ensure HTML textarea is submitted (always the source of truth)
document.getElementById('pageForm').addEventListener('submit', () => {
    if (mode === 'visual') {
        document.getElementById('htmlEditor').value = document.getElementById('visualEditor').innerHTML;
    }
    document.getElementById('htmlEditor').classList.remove('d-none');
    document.getElementById('htmlEditor').name = 'content';
});
</script>
<?php admin_page_end(); ?>
